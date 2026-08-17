<?php
// tender_checklist.php - Manage checklist for a specific tender
require_once 'config/database.php';
requireLogin();

$tender_id = $_GET['tender_id'] ?? 0;
$checklist_id = $_GET['id'] ?? 0;

if (!$tender_id) {
    header('Location: tender_ongoing.php');
    exit();
}

// Get tender details
$stmt = $pdo->prepare("SELECT * FROM tenders WHERE id = ?");
$stmt->execute([$tender_id]);
$tender = $stmt->fetch();

if (!$tender) {
    header('Location: tender_ongoing.php');
    exit();
}

// Pre-defined checklist items for manual selection
$predefined_items = [
    'Bid Submission Sheet' => 'required',
    'Bill of Quantities' => 'required',
    'Material Schedule' => 'required',
    'Scope of Work' => 'required',
    'Equipment Schedule' => 'required',
    'Method Statement' => 'required',
    'Company Profile' => 'required',
    'Reference/Relevant Experience' => 'required',
    'Site Visit Certificate' => 'required',
    'Valid NSSA Certificate' => 'required',
    'Valid Tax Clearance Certificate' => 'required',
    'Certificate Confirming Registration' => 'required',
    'Certificate of Incorporation' => 'required',
    'CR5' => 'required',
    'CR6' => 'required',
    'Declaration of Non-Conflict of Interest' => 'required',
    'Declaration of Eligibility' => 'required',
    'Bid Bond or Bid Security' => 'required',
    'SPOC Fee' => 'required'
];

// Get existing checklist for this tender
$stmt = $pdo->prepare("SELECT * FROM tender_checklists WHERE tender_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$tender_id]);
$checklist = $stmt->fetch();

$items = [];
if ($checklist) {
    $stmt = $pdo->prepare("SELECT * FROM tender_checklist_items WHERE checklist_id = ? ORDER BY order_number, item_number");
    $stmt->execute([$checklist['id']]);
    $items = $stmt->fetchAll();
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Create manual checklist
    if ($action === 'create_manual') {
        $title = $_POST['title'] ?? 'Tender Checklist';
        $selected_items = $_POST['selected_items'] ?? [];
        $custom_items_text = $_POST['custom_items'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            $checklist_number = 'CL-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $manual_items_str = implode("\n", $selected_items);
            if (!empty($custom_items_text)) {
                $manual_items_str .= "\n" . $custom_items_text;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO tender_checklists (
                    tender_id, checklist_number, title, status, type, manual_items, created_by
                ) VALUES (?, ?, ?, 'Draft', 'manual', ?, ?)
            ");
            $stmt->execute([$tender_id, $checklist_number, $title, $manual_items_str, $_SESSION['user_id']]);
            $new_checklist_id = $pdo->lastInsertId();
            
            $order = 1;
            foreach ($selected_items as $item_data) {
                $parts = explode('|', $item_data);
                $description = trim($parts[0]);
                $is_required = isset($parts[1]) && $parts[1] == 'required' ? 1 : 0;
                
                if (!empty($description)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO tender_checklist_items (
                            checklist_id, item_number, item_description, is_required, order_number
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$new_checklist_id, $order, $description, $is_required, $order]);
                    $order++;
                }
            }
            
            if (!empty($custom_items_text)) {
                $custom_lines = array_filter(array_map('trim', explode("\n", $custom_items_text)));
                foreach ($custom_lines as $custom_item) {
                    if (!empty($custom_item)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO tender_checklist_items (
                                checklist_id, item_number, item_description, is_required, order_number
                            ) VALUES (?, ?, ?, 0, ?)
                        ");
                        $stmt->execute([$new_checklist_id, $order, $custom_item, $order]);
                        $order++;
                    }
                }
            }
            
            updateTenderChecklistStatus($pdo, $tender_id);
            logActivity($_SESSION['user_id'], "Created manual checklist $checklist_number for tender " . $tender['tender_number']);
            
            $pdo->commit();
            $message = "Manual checklist created successfully!";
            header("Location: tender_checklist.php?tender_id=$tender_id");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating manual checklist: " . $e->getMessage();
        }
    }
    
    // Update checklist items
    if ($action === 'update_checklist') {
        try {
            $pdo->beginTransaction();
            
            foreach ($_POST['items'] as $item_id => $data) {
                $is_attached = isset($data['is_attached']) ? 1 : 0;
                $comment = $data['comment'] ?? '';
                
                $stmt = $pdo->prepare("
                    UPDATE tender_checklist_items 
                    SET is_attached = ?, comment = ?
                    WHERE id = ? AND checklist_id = ?
                ");
                $stmt->execute([$is_attached, $comment, $item_id, $checklist['id']]);
            }
            
            $status = $_POST['status'] ?? 'In Progress';
            $stmt = $pdo->prepare("UPDATE tender_checklists SET status = ? WHERE id = ?");
            $stmt->execute([$status, $checklist['id']]);
            
            updateTenderChecklistStatus($pdo, $tender_id);
            logActivity($_SESSION['user_id'], "Updated checklist for tender " . $tender['tender_number']);
            
            $pdo->commit();
            $message = "Checklist updated successfully!";
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM tender_checklists WHERE id = ?");
            $stmt->execute([$checklist['id']]);
            $checklist = $stmt->fetch();
            
            $stmt = $pdo->prepare("SELECT * FROM tender_checklist_items WHERE checklist_id = ? ORDER BY order_number, item_number");
            $stmt->execute([$checklist['id']]);
            $items = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating checklist: " . $e->getMessage();
        }
    }
    
    // Submit for review
    if ($action === 'submit_for_review') {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as missing
                FROM tender_checklist_items
                WHERE checklist_id = ? AND is_required = 1 AND is_attached = 0
            ");
            $stmt->execute([$checklist['id']]);
            $missing = $stmt->fetch()['missing'];
            
            if ($missing > 0) {
                $error = "Cannot submit: $missing required items are not attached.";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tender_checklists 
                    SET status = 'Ready for Review', submission_date = CURDATE(), is_submitted = 1
                    WHERE id = ?
                ");
                $stmt->execute([$checklist['id']]);
                
                $stmt = $pdo->prepare("UPDATE tenders SET status = 'Submitted', submission_date = CURDATE() WHERE id = ?");
                $stmt->execute([$tender_id]);
                
                updateTenderChecklistStatus($pdo, $tender_id);
                logActivity($_SESSION['user_id'], "Submitted checklist for tender " . $tender['tender_number']);
                
                $message = "Checklist submitted for review successfully! Tender status updated to 'Submitted'.";
            }
            
        } catch (Exception $e) {
            $error = "Error submitting checklist: " . $e->getMessage();
        }
    }
    
    // Review checklist (for reviewers)
    if ($action === 'review_checklist') {
        try {
            $review_checklist_id = $_POST['checklist_id'];
            $decision = $_POST['review_decision'];
            $comments = $_POST['review_comments'] ?? '';
            
            // Verify the user is the reviewer
            $stmt = $pdo->prepare("SELECT reviewer_id, tender_id FROM tender_checklists WHERE id = ?");
            $stmt->execute([$review_checklist_id]);
            $checklist_data = $stmt->fetch();
            
            if ($checklist_data['reviewer_id'] != $_SESSION['user_id']) {
                $error = "You are not authorized to review this checklist.";
            } else {
                $pdo->beginTransaction();
                
                // Update checklist review response
                $stmt = $pdo->prepare("
                    UPDATE tender_checklists 
                    SET review_response = ?,
                        review_comments = ?,
                        review_response_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$decision, $comments, $review_checklist_id]);
                
                // Update review history
                $stmt = $pdo->prepare("
                    UPDATE tender_review_history 
                    SET review_response = ?,
                        review_comments = ?,
                        review_date = NOW()
                    WHERE checklist_id = ? AND review_response = 'Pending'
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$decision, $comments, $review_checklist_id]);
                
                // Update checklist status based on decision
                $new_status = 'Under Review';
                if ($decision == 'approved') {
                    $new_status = 'Approved';
                } elseif ($decision == 'rejected' || $decision == 'needs_revision') {
                    $new_status = 'Rejected';
                }
                
                $stmt = $pdo->prepare("UPDATE tender_checklists SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $review_checklist_id]);
                
                // Notify the submitter
                $stmt = $pdo->prepare("SELECT created_by FROM tender_checklists WHERE id = ?");
                $stmt->execute([$review_checklist_id]);
                $submitter_id = $stmt->fetchColumn();
                
                $decision_label = ucwords(str_replace('_', ' ', $decision));
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id, type, title, message, link, created_by
                    ) VALUES (?, 'review_response', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $submitter_id,
                    "Checklist Review Complete - " . $decision_label,
                    "Your checklist has been reviewed and " . strtolower($decision_label) . ".\n\nComments: " . $comments,
                    "tender_checklist.php?tender_id=" . $checklist_data['tender_id'] . "&id=" . $review_checklist_id,
                    $_SESSION['user_id']
                ]);
                
                logActivity($_SESSION['user_id'], "Reviewed checklist ID: $review_checklist_id with decision: $decision");
                
                $pdo->commit();
                
                $message = "Review submitted successfully! Decision: " . $decision_label;
                
                // Refresh data
                $stmt = $pdo->prepare("SELECT * FROM tender_checklists WHERE id = ?");
                $stmt->execute([$review_checklist_id]);
                $checklist = $stmt->fetch();
                
                $stmt = $pdo->prepare("SELECT * FROM tender_checklist_items WHERE checklist_id = ? ORDER BY order_number, item_number");
                $stmt->execute([$review_checklist_id]);
                $items = $stmt->fetchAll();
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error submitting review: " . $e->getMessage();
        }
    }
    
    // Add signature
    if ($action === 'add_signature') {
        try {
            $role = $_POST['signature_role'];
            $signature_data = $_POST['signature_data'];
            
            $upload_dir = 'uploads/signatures/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $filename = 'sig_' . time() . '_' . $role . '.png';
            $filepath = $upload_dir . $filename;
            
            $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
            $signature_data = str_replace(' ', '+', $signature_data);
            $data = base64_decode($signature_data);
            file_put_contents($filepath, $data);
            
            $update_data = [];
            if ($role === 'prepared') {
                $update_data['prepared_by'] = $_SESSION['user_id'];
                $update_data['prepared_date'] = date('Y-m-d H:i:s');
                $update_data['prepared_signature'] = $filepath;
                $update_data['status'] = 'In Progress';
            } elseif ($role === 'reviewed') {
                $update_data['reviewed_by'] = $_SESSION['user_id'];
                $update_data['reviewed_date'] = date('Y-m-d H:i:s');
                $update_data['reviewed_signature'] = $filepath;
                $update_data['status'] = 'Under Review';
            } elseif ($role === 'approved') {
                $update_data['approved_by'] = $_SESSION['user_id'];
                $update_data['approved_date'] = date('Y-m-d H:i:s');
                $update_data['approved_signature'] = $filepath;
                $update_data['status'] = 'Approved';
            }
            
            $sql = "UPDATE tender_checklists SET ";
            $sets = [];
            foreach ($update_data as $key => $value) {
                $sets[] = "$key = ?";
            }
            $sql .= implode(', ', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $params = array_values($update_data);
            $params[] = $checklist['id'];
            $stmt->execute($params);
            
            updateTenderChecklistStatus($pdo, $tender_id);
            logActivity($_SESSION['user_id'], "Added $role signature for checklist " . $checklist['checklist_number']);
            $message = "Signature added successfully!";
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM tender_checklists WHERE id = ?");
            $stmt->execute([$checklist['id']]);
            $checklist = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = "Error adding signature: " . $e->getMessage();
        }
    }
    
    // Add attachment - also marks as attached
    if ($action === 'add_attachment' && isset($_FILES['attachment'])) {
        try {
            $item_id = $_POST['item_id'];
            
            $upload_dir = 'uploads/checklist/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file = $_FILES['attachment'];
            $filename = 'checklist_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $stmt = $pdo->prepare("
                    UPDATE tender_checklist_items 
                    SET attachment_file = ?, is_attached = 1
                    WHERE id = ?
                ");
                $stmt->execute([$filepath, $item_id]);
                
                updateTenderChecklistStatus($pdo, $tender_id);
                logActivity($_SESSION['user_id'], "Uploaded attachment for checklist item " . $item_id);
                
                $message = "Attachment uploaded successfully!";
                
                // Refresh items
                $stmt = $pdo->prepare("SELECT * FROM tender_checklist_items WHERE checklist_id = ? ORDER BY order_number, item_number");
                $stmt->execute([$checklist['id']]);
                $items = $stmt->fetchAll();
            } else {
                $error = "Failed to upload attachment.";
            }
            
        } catch (Exception $e) {
            $error = "Error uploading attachment: " . $e->getMessage();
        }
    }
}

// Function to update tender checklist status
function updateTenderChecklistStatus($pdo, $tender_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_items,
            SUM(CASE WHEN is_attached = 1 THEN 1 ELSE 0 END) as attached_items
        FROM tender_checklist_items tci
        JOIN tender_checklists tc ON tc.id = tci.checklist_id
        WHERE tc.tender_id = ?
    ");
    $stmt->execute([$tender_id]);
    $result = $stmt->fetch();
    
    $total = $result['total_items'] ?? 0;
    $attached = $result['attached_items'] ?? 0;
    
    $stmt = $pdo->prepare("
        UPDATE tenders 
        SET checklist_count = ?, checklist_completed = ?
        WHERE id = ?
    ");
    $stmt->execute([$total, $attached, $tender_id]);
    
    $status = 'Not Started';
    if ($total > 0) {
        if ($attached == $total) {
            $status = 'Complete';
        } elseif ($attached > 0) {
            $status = 'In Progress';
        }
    }
    
    $stmt = $pdo->prepare("UPDATE tenders SET checklist_status = ? WHERE id = ?");
    $stmt->execute([$status, $tender_id]);
    
    return ['total' => $total, 'attached' => $attached, 'status' => $status];
}

$pageTitle = "Checklist: " . $tender['tender_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Enginove</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        :root { --green:#1f8b4c; --light-green:#d4edda; --dark:#1e2a2f; --off-white:#f4f9f6; --white:#ffffff; }
        body { background:var(--off-white); }
        .wrapper { display:flex; min-height:100vh; }
        .main { flex:1; margin-left:270px; transition:.3s; }
        .content { padding:30px; }
        .page-title { font-size:28px; font-weight:700; color:var(--dark); margin-bottom:4px; }
        .subtitle { color:#64748b; margin-bottom:25px; }
        
        .btn { padding:8px 18px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:13px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-1px); box-shadow:0 4px 12px rgba(31,139,76,0.25); }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-sm { padding:4px 12px; font-size:12px; border-radius:6px; }
        .btn-warning { background:#f59e0b; color:white; }
        .btn-warning:hover { background:#d97706; }
        .btn-success { background:#22c55e; color:white; }
        .btn-success:hover { background:#16a34a; }
        
        .card { background:white; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.04); margin-bottom:20px; }
        .card h3 { font-size:16px; color:var(--dark); margin-bottom:15px; padding-bottom:10px; border-bottom:2px solid #f1f5f9; display:flex; align-items:center; gap:10px; }
        .card h3 i { color:var(--green); }
        
        .checklist-grid { display:grid; grid-template-columns:2fr 1fr; gap:20px; }
        .status-badge { display:inline-block; padding:4px 16px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-draft { background:#e5e7eb; color:#6b7280; }
        .status-in-progress { background:#fef3c7; color:#b45309; }
        .status-ready-for-review { background:#dbeafe; color:#1d4ed8; }
        .status-under-review { background:#fef3c7; color:#b45309; }
        .status-approved { background:var(--light-green); color:var(--green); }
        .status-rejected { background:#fee2e2; color:#b91c1c; }
        .status-completed { background:var(--light-green); color:var(--green); }
        
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:600; margin-bottom:6px; font-size:13px; color:var(--dark); }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--green); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        
        .checklist-item-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .checklist-item-row:last-child { border-bottom: none; }
        .checklist-item-row .check-icon { width: 24px; text-align: center; font-size:16px; }
        .checklist-item-row .check-icon.attached { color: var(--green); }
        .checklist-item-row .check-icon.missing { color: #dc2626; }
        .checklist-item-row .item-desc { flex: 1; font-size: 13px; }
        .checklist-item-row .item-desc.required { font-weight: 500; }
        .checklist-item-row .item-desc .required-badge { color: #dc2626; font-size: 10px; margin-left: 4px; }
        .checklist-item-row .item-status { font-size:11px; color:#64748b; }
        .checklist-item-row .item-actions { display:flex; gap:6px; align-items:center; }
        
        .checklist-progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        .checklist-progress-bar .fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .checklist-progress-bar .fill.complete { background: var(--green); }
        .checklist-progress-bar .fill.in-progress { background: #f59e0b; }
        .checklist-progress-bar .fill.not-started { background: #dc2626; }
        
        .signature-area { border:2px dashed #e5e7eb; border-radius:10px; padding:20px; text-align:center; min-height:100px; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .signature-area img { max-width:200px; max-height:70px; }
        .signature-area canvas { border:1px solid #e5e7eb; border-radius:8px; cursor:crosshair; }
        
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; color:#64748b; }
        .modal-content h2 { margin-bottom:15px; }
        
        .signature-pad-modal .modal-content { max-width:600px; }
        
        .actions-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .item-checkbox-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            max-height: 300px;
            overflow-y: auto;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fafdfb;
        }
        .item-checkbox-list label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: .2s;
            font-size: 13px;
        }
        .item-checkbox-list label:hover { background: #e6f5ea; }
        .item-checkbox-list label input[type="checkbox"] { width:16px; height:16px; accent-color:var(--green); flex-shrink:0; }
        .item-checkbox-list .required-badge { color:#dc2626; font-size:10px; margin-left:auto; }
        .select-all-btn { padding:4px 12px; font-size:12px; border:1px solid #e5e7eb; border-radius:6px; background:white; cursor:pointer; transition:.2s; }
        .select-all-btn:hover { background:var(--light-green); }
        
        /* Review section styles */
        .review-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .review-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 10px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            transition: .2s;
            flex: 1;
            min-width: 120px;
            justify-content: center;
        }
        .review-options label:hover { border-color: var(--green); }
        .review-options label.approve:hover { border-color: var(--green); background: #f0fdf4; }
        .review-options label.reject:hover { border-color: #dc2626; background: #fef2f2; }
        .review-options label.revise:hover { border-color: #f59e0b; background: #fffbeb; }
        .review-options input[type="radio"] { accent-color: var(--green); }
        .review-options .approved-text { color: var(--green); font-weight: 600; }
        .review-options .rejected-text { color: #dc2626; font-weight: 600; }
        .review-options .revise-text { color: #f59e0b; font-weight: 600; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .checklist-grid { grid-template-columns:1fr; }
            .item-checkbox-list { grid-template-columns:1fr; }
            .review-options label { min-width: 100px; padding: 8px 12px; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-title { font-size:22px; }
            .form-row { grid-template-columns:1fr; }
            .actions-bar { flex-direction:column; }
            .actions-bar .btn { justify-content:center; }
            .review-options { flex-direction:column; }
            .review-options label { width:100%; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:4px;">
                <h1 class="page-title" style="margin-bottom:0;"><i class="fas fa-clipboard-check" style="color:var(--green);"></i> <?= $pageTitle ?></h1>
                <a href="tender_view.php?id=<?= $tender_id ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Tender</a>
            </div>
            <p class="subtitle">Tender: <?= htmlspecialchars($tender['tender_number']) ?> - <?= htmlspecialchars($tender['tender_name']) ?></p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <?php if (!$checklist): ?>
            <!-- Create Manual Checklist -->
            <div class="card">
                <h3><i class="fas fa-plus-circle"></i> Create Checklist for this Tender</h3>
                <p style="color:#64748b;margin-bottom:15px;font-size:14px;">Select the items you need for this tender:</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_manual">
                    
                    <div class="form-group">
                        <label>Select Checklist Items</label>
                        <div style="margin-bottom:8px;">
                            <button type="button" class="select-all-btn" onclick="toggleAllItems(true)">Select All</button>
                            <button type="button" class="select-all-btn" onclick="toggleAllItems(false)">Deselect All</button>
                            <span style="font-size:12px;color:#64748b;margin-left:10px;">All items are selected by default</span>
                        </div>
                        <div class="item-checkbox-list" id="itemCheckboxList">
                            <?php foreach ($predefined_items as $item => $requirement): ?>
                                <label>
                                    <input type="checkbox" name="selected_items[]" value="<?= htmlspecialchars($item) . '|' . $requirement ?>" checked>
                                    <span><?= htmlspecialchars($item) ?></span>
                                    <?php if ($requirement == 'required'): ?>
                                        <span class="required-badge">*</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            Check all items that apply to this tender. Items marked with <span style="color:#dc2626;">*</span> are required.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Additional Custom Items (Optional)</label>
                        <textarea name="custom_items" rows="3" placeholder="Enter additional items one per line..."></textarea>
                        <div class="help-text">Add any items not in the list above</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Checklist Title</label>
                        <input type="text" name="title" value="Tender Submission Checklist - <?= htmlspecialchars($tender['tender_number']) ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-green">
                        <i class="fas fa-plus"></i> Create Manual Checklist
                    </button>
                    <a href="tender_view.php?id=<?= $tender_id ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Cancel</a>
                </form>
            </div>
            
            <script>
            function toggleAllItems(select) {
                var checkboxes = document.querySelectorAll('#itemCheckboxList input[type="checkbox"]');
                checkboxes.forEach(function(cb) {
                    cb.checked = select;
                });
            }
            </script>
            
            <?php else: ?>
            
            <!-- View Checklist -->
            <div class="checklist-grid">
                <div>
                    <div class="card">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:15px;">
                            <h3 style="margin-bottom:0;">
                                <i class="fas fa-list"></i> <?= htmlspecialchars($checklist['checklist_number']) ?>
                                <?php if ($checklist['reviewer_id'] == $_SESSION['user_id'] && $checklist['status'] == 'Ready for Review'): ?>
                                    <span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:2px 10px;border-radius:12px;margin-left:8px;">
                                        <i class="fas fa-user-check"></i> Assigned to You
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $checklist['status'])) ?>">
                                <?= $checklist['status'] ?>
                            </span>
                        </div>
                        
                        <?php 
                        $total_items = count($items);
                        $attached_items = array_filter($items, function($item) { return $item['is_attached'] == 1; });
                        $attached_count = count($attached_items);
                        $progress = $total_items > 0 ? round(($attached_count / $total_items) * 100) : 0;
                        ?>
                        
                        <div style="margin-bottom:15px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px; color:#64748b;">
                                <span><strong><?= $attached_count ?></strong> of <strong><?= $total_items ?></strong> items attached</span>
                                <span><?= $progress ?>%</span>
                            </div>
                            <div class="checklist-progress-bar">
                                <div class="fill <?= $progress == 100 ? 'complete' : ($progress > 0 ? 'in-progress' : 'not-started') ?>" style="width: <?= $progress ?>%;"></div>
                            </div>
                        </div>
                        
                        <?php if (empty($items)): ?>
                            <p style="color:#64748b;padding:20px 0;text-align:center;">No items in this checklist.</p>
                        <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_checklist">
                            
                            <div style="max-height:500px; overflow-y:auto; margin:0 -10px; padding:0 10px;">
                                <?php foreach ($items as $item): ?>
                                <div class="checklist-item-row">
                                    <span class="check-icon <?= $item['is_attached'] ? 'attached' : 'missing' ?>">
                                        <i class="fas fa-<?= $item['is_attached'] ? 'check-circle' : 'circle' ?>"></i>
                                    </span>
                                    <span class="item-desc <?= $item['is_required'] ? 'required' : '' ?>">
                                        <?= htmlspecialchars($item['item_description']) ?>
                                        <?php if ($item['is_required']): ?>
                                            <span class="required-badge">*</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="item-status">
                                        <input type="checkbox" name="items[<?= $item['id'] ?>][is_attached]" value="1" <?= $item['is_attached'] ? 'checked' : '' ?>>
                                        <span style="font-size:10px;">Attached</span>
                                    </span>
                                    <div class="item-actions">
                                        <?php if ($item['attachment_file']): ?>
                                            <a href="<?= htmlspecialchars($item['attachment_file']) ?>" target="_blank" class="btn btn-sm btn-outline" title="View Attachment">
                                                <i class="fas fa-paperclip"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="openAttachmentModal(<?= $item['id'] ?>)" title="Upload">
                                            <i class="fas fa-upload"></i>
                                        </button>
                                        <input type="text" name="items[<?= $item['id'] ?>][comment]" value="<?= htmlspecialchars($item['comment'] ?? '') ?>" placeholder="Comment" style="width:80px;padding:3px 6px;border:1px solid #e5e7eb;border-radius:4px;font-size:11px;">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;padding-top:15px;border-top:2px solid #f1f5f9;">
                                <button type="submit" class="btn btn-green"><i class="fas fa-save"></i> Save Checklist</button>
                                
                                <?php if ($checklist['status'] != 'Ready for Review' && $checklist['status'] != 'Approved' && $checklist['status'] != 'Completed' && $checklist['status'] != 'Under Review'): ?>
                                <a href="tender_submit.php?tender_id=<?= $tender_id ?>&checklist_id=<?= $checklist['id'] ?>" class="btn btn-warning">
                                    <i class="fas fa-paper-plane"></i> Submit for Review
                                </a>
                                <?php endif; ?>
                                
                                <a href="tender_view.php?id=<?= $tender_id ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
                            </div>
                            
                            <div style="margin-top:15px;padding:12px;background:#fef3c7;border-radius:8px;font-size:13px;color:#92400e;">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Note:</strong> All required items (<span style="color:#dc2626;">*</span>) must be attached before submitting for review.
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <div class="card">
                        <h3><i class="fas fa-info-circle"></i> Status</h3>
                        <div style="padding:5px 0;">
                            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f4f6;">
                                <span style="color:#64748b;">Status</span>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $checklist['status'])) ?>">
                                    <?= $checklist['status'] ?>
                                </span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f4f6;">
                                <span style="color:#64748b;">Submission Date</span>
                                <span><?= $checklist['submission_date'] ? date('d M Y', strtotime($checklist['submission_date'])) : 'Not submitted' ?></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:8px 0;">
                                <span style="color:#64748b;">Submitted</span>
                                <span><?= $checklist['is_submitted'] ? 'Yes' : 'No' ?></span>
                            </div>
                            <?php if ($checklist['review_response'] && $checklist['review_response'] != 'Pending'): ?>
                            <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid #f3f4f6;margin-top:8px;padding-top:12px;">
                                <span style="color:#64748b;">Review Decision</span>
                                <span class="status-badge <?= $checklist['review_response'] == 'approved' ? 'status-approved' : 'status-rejected' ?>" style="background:<?= $checklist['review_response'] == 'approved' ? 'var(--light-green)' : '#fee2e2' ?>;color:<?= $checklist['review_response'] == 'approved' ? 'var(--green)' : '#b91c1c' ?>;">
                                    <?= ucwords($checklist['review_response']) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($checklist['review_comments']): ?>
                            <div style="padding:8px 0;border-top:1px solid #f3f4f6;margin-top:4px;padding-top:12px;">
                                <span style="color:#64748b;font-size:12px;">Review Comments</span>
                                <div style="font-size:13px;color:var(--dark);margin-top:4px;background:#f8fafc;padding:8px 12px;border-radius:6px;">
                                    <?= nl2br(htmlspecialchars($checklist['review_comments'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3><i class="fas fa-pen-fancy"></i> Signatures</h3>
                        
                        <div style="margin-bottom:15px;">
                            <h4 style="font-size:13px;color:#64748b;margin-bottom:6px;">Prepared By (Quantity Surveyor)</h4>
                            <?php if ($checklist['prepared_signature']): ?>
                                <div class="signature-area">
                                    <img src="<?= htmlspecialchars($checklist['prepared_signature']) ?>" alt="Prepared Signature">
                                    <div style="margin-top:6px;font-size:12px;color:var(--dark);">
                                        <?php echo getUserName($checklist['prepared_by']); ?>
                                        <?= $checklist['prepared_date'] ? date('d M Y H:i', strtotime($checklist['prepared_date'])) : '' ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="signature-area">
                                    <p style="color:#94a3b8;font-size:13px;">No signature yet</p>
                                    <button class="btn btn-green btn-sm" onclick="openSignatureModal('prepared')">
                                        <i class="fas fa-pen"></i> Sign
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-bottom:15px;">
                            <h4 style="font-size:13px;color:#64748b;margin-bottom:6px;">Reviewed By (Reviewer)</h4>
                            <?php if ($checklist['reviewed_signature']): ?>
                                <div class="signature-area">
                                    <img src="<?= htmlspecialchars($checklist['reviewed_signature']) ?>" alt="Reviewed Signature">
                                    <div style="margin-top:6px;font-size:12px;color:var(--dark);">
                                        <?php echo getUserName($checklist['reviewed_by']); ?>
                                        <?= $checklist['reviewed_date'] ? date('d M Y H:i', strtotime($checklist['reviewed_date'])) : '' ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="signature-area">
                                    <p style="color:#94a3b8;font-size:13px;">No signature yet</p>
                                    <?php if ($checklist['status'] == 'Ready for Review' || $checklist['status'] == 'Under Review'): ?>
                                    <button class="btn btn-green btn-sm" onclick="openSignatureModal('reviewed')">
                                        <i class="fas fa-pen"></i> Sign
                                    </button>
                                    <?php else: ?>
                                    <span style="font-size:11px;color:#94a3b8;">Awaiting review status</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h4 style="font-size:13px;color:#64748b;margin-bottom:6px;">Approved By</h4>
                            <?php if ($checklist['approved_signature']): ?>
                                <div class="signature-area">
                                    <img src="<?= htmlspecialchars($checklist['approved_signature']) ?>" alt="Approved Signature">
                                    <div style="margin-top:6px;font-size:12px;color:var(--dark);">
                                        <?php echo getUserName($checklist['approved_by']); ?>
                                        <?= $checklist['approved_date'] ? date('d M Y H:i', strtotime($checklist['approved_date'])) : '' ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="signature-area">
                                    <p style="color:#94a3b8;font-size:13px;">No signature yet</p>
                                    <?php if ($checklist['status'] == 'Under Review'): ?>
                                    <button class="btn btn-green btn-sm" onclick="openSignatureModal('approved')">
                                        <i class="fas fa-pen"></i> Sign
                                    </button>
                                    <?php else: ?>
                                    <span style="font-size:11px;color:#94a3b8;">Awaiting approval status</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Review Section - Only shown to the assigned reviewer -->
            <?php if ($checklist['reviewer_id'] == $_SESSION['user_id'] && $checklist['status'] == 'Ready for Review'): ?>
            <div class="card" style="border:2px solid #dbeafe; margin-top:20px; background:#f8fafc;">
                <h3 style="color:#1d4ed8;"><i class="fas fa-check-double"></i> Review Checklist</h3>
                <p style="color:#64748b;margin-bottom:15px;font-size:14px;">
                    You have been assigned to review this checklist. Please review the attached documents and provide your decision.
                </p>
                
                <form method="POST" id="reviewForm">
                    <input type="hidden" name="action" value="review_checklist">
                    <input type="hidden" name="checklist_id" value="<?= $checklist['id'] ?>">
                    
                    <div class="form-group">
                        <label style="font-weight:600;display:block;margin-bottom:8px;">Review Decision *</label>
                        <div class="review-options">
                            <label class="approve">
                                <input type="radio" name="review_decision" value="approved" required>
                                <span class="approved-text"><i class="fas fa-check-circle"></i> Approve</span>
                            </label>
                            <label class="reject">
                                <input type="radio" name="review_decision" value="rejected">
                                <span class="rejected-text"><i class="fas fa-times-circle"></i> Reject</span>
                            </label>
                            <label class="revise">
                                <input type="radio" name="review_decision" value="needs_revision">
                                <span class="revise-text"><i class="fas fa-edit"></i> Needs Revision</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight:600;display:block;margin-bottom:6px;">Review Comments</label>
                        <textarea name="review_comments" rows="4" placeholder="Provide detailed feedback on the checklist items..." style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;"></textarea>
                    </div>
                    
                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
                        <button type="submit" class="btn btn-success" onclick="return confirm('Submit your review decision? This action cannot be undone.')">
                            <i class="fas fa-paper-plane"></i> Submit Review
                        </button>
                        <a href="tender_view.php?id=<?= $tender_id ?>" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Tender
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Signature Modal -->
<div class="modal signature-pad-modal" id="signatureModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('signatureModal')">&times;</span>
        <h2><i class="fas fa-pen-fancy"></i> Digital Signature</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_signature">
            <input type="hidden" name="signature_role" id="signatureRole">
            <input type="hidden" name="signature_data" id="signatureData">
            <div style="text-align:center;margin:20px 0;">
                <p style="color:#64748b;margin-bottom:15px;">Sign below using your mouse or touch</p>
                <canvas id="signaturePad" width="450" height="150" style="border:2px solid #e5e7eb;border-radius:8px;background:white;cursor:crosshair;"></canvas>
                <div style="margin-top:10px;display:flex;gap:10px;justify-content:center;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="clearSignature()"><i class="fas fa-undo"></i> Clear</button>
                </div>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Save Signature</button>
        </form>
    </div>
</div>

<!-- Attachment Modal -->
<div class="modal" id="attachmentModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('attachmentModal')">&times;</span>
        <h2><i class="fas fa-upload"></i> Upload Attachment</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_attachment">
            <input type="hidden" name="item_id" id="attachmentItemId">
            <div class="form-group">
                <label>Select File (PDF, JPG, PNG, DOC - Max 10MB)</label>
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-upload"></i> Upload</button>
        </form>
    </div>
</div>

<script>
// Signature Pad functionality
let isDrawing = false;
let signatureCanvas = null;
let signatureCtx = null;
let lastX = 0;
let lastY = 0;

function openSignatureModal(role) {
    document.getElementById('signatureRole').value = role;
    document.getElementById('signatureModal').classList.add('show');
    
    setTimeout(() => {
        signatureCanvas = document.getElementById('signaturePad');
        signatureCtx = signatureCanvas.getContext('2d');
        
        signatureCanvas.addEventListener('mousedown', startDrawing);
        signatureCanvas.addEventListener('mousemove', draw);
        signatureCanvas.addEventListener('mouseup', stopDrawing);
        signatureCanvas.addEventListener('mouseleave', stopDrawing);
        signatureCanvas.addEventListener('touchstart', handleTouchStart);
        signatureCanvas.addEventListener('touchmove', handleTouchMove);
        signatureCanvas.addEventListener('touchend', stopDrawing);
    }, 100);
}

function startDrawing(e) {
    isDrawing = true;
    const rect = signatureCanvas.getBoundingClientRect();
    lastX = (e.clientX - rect.left) * (signatureCanvas.width / rect.width);
    lastY = (e.clientY - rect.top) * (signatureCanvas.height / rect.height);
}

function draw(e) {
    if (!isDrawing) return;
    const rect = signatureCanvas.getBoundingClientRect();
    const x = (e.clientX - rect.left) * (signatureCanvas.width / rect.width);
    const y = (e.clientY - rect.top) * (signatureCanvas.height / rect.height);
    
    signatureCtx.beginPath();
    signatureCtx.moveTo(lastX, lastY);
    signatureCtx.lineTo(x, y);
    signatureCtx.strokeStyle = '#1e2a2f';
    signatureCtx.lineWidth = 2;
    signatureCtx.stroke();
    
    lastX = x;
    lastY = y;
}

function stopDrawing() {
    isDrawing = false;
}

function handleTouchStart(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const rect = signatureCanvas.getBoundingClientRect();
    isDrawing = true;
    lastX = (touch.clientX - rect.left) * (signatureCanvas.width / rect.width);
    lastY = (touch.clientY - rect.top) * (signatureCanvas.height / rect.height);
}

function handleTouchMove(e) {
    e.preventDefault();
    if (!isDrawing) return;
    const touch = e.touches[0];
    const rect = signatureCanvas.getBoundingClientRect();
    const x = (touch.clientX - rect.left) * (signatureCanvas.width / rect.width);
    const y = (touch.clientY - rect.top) * (signatureCanvas.height / rect.height);
    
    signatureCtx.beginPath();
    signatureCtx.moveTo(lastX, lastY);
    signatureCtx.lineTo(x, y);
    signatureCtx.strokeStyle = '#1e2a2f';
    signatureCtx.lineWidth = 2;
    signatureCtx.stroke();
    
    lastX = x;
    lastY = y;
}

function clearSignature() {
    if (signatureCtx) {
        signatureCtx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
    }
}

function openAttachmentModal(itemId) {
    document.getElementById('attachmentItemId').value = itemId;
    document.getElementById('attachmentModal').classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

document.querySelector('#signatureModal form').addEventListener('submit', function(e) {
    if (signatureCanvas) {
        const dataUrl = signatureCanvas.toDataURL('image/png');
        document.getElementById('signatureData').value = dataUrl;
    }
});

window.onclick = function(e) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        if (e.target === modal) {
            modal.classList.remove('show');
        }
    });
}
</script>
</body>
</html>