<?php
// tender_submit.php - Submit a tender checklist for review
require_once 'config/database.php';
requireLogin();

$tender_id = $_GET['tender_id'] ?? 0;
$checklist_id = $_GET['checklist_id'] ?? 0;

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

// Get checklist details
$stmt = $pdo->prepare("SELECT * FROM tender_checklists WHERE id = ? AND tender_id = ?");
$stmt->execute([$checklist_id, $tender_id]);
$checklist = $stmt->fetch();

if (!$checklist) {
    header('Location: tender_checklist.php?tender_id=' . $tender_id);
    exit();
}

// Get checklist items
$stmt = $pdo->prepare("
    SELECT * FROM tender_checklist_items 
    WHERE checklist_id = ? 
    ORDER BY order_number, item_number
");
$stmt->execute([$checklist_id]);
$items = $stmt->fetchAll();

// Get all users for reviewer dropdown
$users = $pdo->query("SELECT id, first_name, last_name, username, department_id FROM users WHERE status = 'ACTIVE' ORDER BY first_name")->fetchAll();

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_tender'])) {
    try {
        // Check if all required items are attached
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as missing
            FROM tender_checklist_items
            WHERE checklist_id = ? AND is_required = 1 AND is_attached = 0
        ");
        $stmt->execute([$checklist_id]);
        $missing = $stmt->fetch()['missing'];
        
        if ($missing > 0) {
            $error = "Cannot submit: $missing required items are not attached.";
        } else {
            $reviewer_id = $_POST['reviewer_id'] ?? null;
            
            if (!$reviewer_id) {
                $error = "Please select a reviewer for this checklist.";
            } else {
                $pdo->beginTransaction();
                
                // Get current submission count
                $current_count = $checklist['submission_count'] ?? 0;
                $new_count = $current_count + 1;
                
                // Update checklist status
                $stmt = $pdo->prepare("
                    UPDATE tender_checklists 
                    SET status = 'Ready for Review', 
                        submission_date = CURDATE(), 
                        is_submitted = 1,
                        reviewer_id = ?,
                        review_requested_at = NOW(),
                        review_response = 'Pending',
                        submission_count = ?
                    WHERE id = ?
                ");
                $stmt->execute([$reviewer_id, $new_count, $checklist_id]);
                
                // Update tender status
                $stmt = $pdo->prepare("
                    UPDATE tenders 
                    SET status = 'Submitted', 
                        submission_date = CURDATE() 
                    WHERE id = ?
                ");
                $stmt->execute([$tender_id]);
                
                // Update tender checklist counts
                updateTenderChecklistStatus($pdo, $tender_id);
                
                // Log review history
                $stmt = $pdo->prepare("
                    INSERT INTO tender_review_history (
                        checklist_id, tender_id, reviewer_id, submitted_by,
                        submission_date, review_response, resubmission_count
                    ) VALUES (?, ?, ?, ?, NOW(), 'Pending', ?)
                ");
                $stmt->execute([
                    $checklist_id,
                    $tender_id,
                    $reviewer_id,
                    $_SESSION['user_id'],
                    $new_count
                ]);
                
                // Create notification for reviewer - using getUserName from database.php
                $reviewer_name = getUserName($reviewer_id);
                $submitter_name = getUserName($_SESSION['user_id']);
                $notification_message = "A tender checklist has been submitted for your review.\n\n" .
                                        "Tender: " . $tender['tender_number'] . " - " . $tender['tender_name'] . "\n" .
                                        "Submitted by: " . $submitter_name . "\n" .
                                        "Checklist: " . $checklist['checklist_number'] . "\n" .
                                        "Submission #: " . $new_count;
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id, type, title, message, link, created_by
                    ) VALUES (?, 'checklist_review', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $reviewer_id,
                    "Checklist Ready for Review",
                    $notification_message,
                    "tender_checklist.php?tender_id=$tender_id&id=$checklist_id",
                    $_SESSION['user_id']
                ]);
                
                // Also notify the submitter that it was sent
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id, type, title, message, link, created_by
                    ) VALUES (?, 'checklist_submitted', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    "Checklist Submitted for Review",
                    "Your checklist for tender " . $tender['tender_number'] . " has been submitted for review to " . $reviewer_name . ".",
                    "tender_checklist.php?tender_id=$tender_id&id=$checklist_id",
                    $_SESSION['user_id']
                ]);
                
                // Log activity
                logActivity($_SESSION['user_id'], "Submitted checklist for tender " . $tender['tender_number'] . " for review to " . $reviewer_name);
                
                $pdo->commit();
                
                $message = "Checklist submitted for review successfully! The reviewer has been notified.";
                
                // Redirect back to checklist view
                header("Location: tender_checklist.php?tender_id=$tender_id&success=1");
                exit();
            }
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error submitting checklist: " . $e->getMessage();
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

// ===== REMOVED: Duplicate getUserName() function =====
// The function is already defined in config/database.php
// Use: getUserName($user_id) instead

$pageTitle = "Submit Tender: " . $tender['tender_number'];
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 8px;
        }
        .page-header .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .page-header .subtitle {
            margin: 0;
            font-size: 14px;
            color: #64748b;
        }
        
        .btn { 
            padding: 10px 24px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: .25s; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 14px; 
            text-decoration: none;
        }
        .btn-green { background: var(--green); color: white; }
        .btn-green:hover { background: #0f6a36; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(31,139,76,0.25); }
        .btn-outline { background: transparent; border: 2px solid var(--green); color: var(--green); }
        .btn-outline:hover { background: var(--green); color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-sm { padding: 4px 12px; font-size: 12px; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 20px;
        }
        .card h3 {
            font-size: 18px;
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h3 i {
            color: var(--green);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-submitted { background: #fef3c7; color: #b45309; }
        .status-open { background: var(--light-green); color: var(--green); }
        .status-draft { background: #e5e7eb; color: #6b7280; }
        
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .checklist-item:last-child { border-bottom: none; }
        .checklist-item .icon { width: 24px; text-align: center; font-size: 16px; }
        .checklist-item .icon.attached { color: var(--green); }
        .checklist-item .icon.missing { color: #dc2626; }
        .checklist-item .desc { flex: 1; font-size: 13px; }
        .checklist-item .desc.required { font-weight: 500; }
        .checklist-item .desc .required-badge { color: #dc2626; font-size: 10px; margin-left: 4px; }
        
        .summary-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
        }
        .summary-box .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-box .row:last-child { border-bottom: none; }
        .summary-box .label { color: #64748b; font-size: 13px; }
        .summary-box .value { font-weight: 600; color: var(--dark); font-size: 13px; }
        
        .message { 
            background: #d4edda; 
            color: #0f5a2e; 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .error { 
            background: #fee2e2; 
            color: #b91c1c; 
            padding: 12px 16px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f1f5f9;
        }
        
        .submission-count {
            display: inline-block;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .reviewer-select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        .reviewer-select:focus {
            border-color: var(--green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(31,139,76,0.1);
        }
        
        @media(max-width: 991px) { 
            .main { margin-left: 0; }
        }
        @media(max-width: 768px) { 
            .content { padding: 15px; } 
            .page-header .page-title { font-size: 20px; }
            .card { padding: 20px; }
            .actions { flex-direction: column; }
            .actions .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="fas fa-paper-plane" style="color:var(--green);"></i> <?= $pageTitle ?></h1>
                    <p class="subtitle">Review and submit the tender checklist for approval</p>
                </div>
                <a href="tender_checklist.php?tender_id=<?= $tender_id ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Checklist</a>
            </div>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <!-- Tender Info -->
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> Tender Information</h3>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div>
                        <span style="color:#64748b;font-size:13px;">Tender Number</span>
                        <div style="font-weight:600;font-size:16px;"><?= htmlspecialchars($tender['tender_number']) ?></div>
                    </div>
                    <div>
                        <span style="color:#64748b;font-size:13px;">Status</span>
                        <div>
                            <span class="status-badge status-<?= strtolower($tender['status']) ?>">
                                <?= $tender['status'] ?>
                            </span>
                        </div>
                    </div>
                    <div style="grid-column:1/-1;">
                        <span style="color:#64748b;font-size:13px;">Tender Name</span>
                        <div style="font-weight:500;"><?= htmlspecialchars($tender['tender_name']) ?></div>
                    </div>
                    <div>
                        <span style="color:#64748b;font-size:13px;">Due Date</span>
                        <div><?= date('d M Y', strtotime($tender['due_date'])) ?></div>
                    </div>
                    <div>
                        <span style="color:#64748b;font-size:13px;">Checklist</span>
                        <div><?= htmlspecialchars($checklist['checklist_number']) ?></div>
                    </div>
                    <div>
                        <span style="color:#64748b;font-size:13px;">Submissions</span>
                        <div>
                            <span class="submission-count">
                                <?= ($checklist['submission_count'] ?? 0) + 1 ?> (including this)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Checklist Items Review -->
            <div class="card">
                <h3><i class="fas fa-clipboard-check"></i> Checklist Items Review</h3>
                
                <?php 
                $total_items = count($items);
                $attached_items = array_filter($items, function($item) { return $item['is_attached'] == 1; });
                $attached_count = count($attached_items);
                $missing_items = array_filter($items, function($item) { return $item['is_required'] == 1 && $item['is_attached'] == 0; });
                $missing_count = count($missing_items);
                ?>
                
                <div class="summary-box">
                    <div class="row">
                        <span class="label">Total Items</span>
                        <span class="value"><?= $total_items ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Attached</span>
                        <span class="value" style="color:var(--green);"><?= $attached_count ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Missing Required</span>
                        <span class="value" style="color:<?= $missing_count > 0 ? '#dc2626' : 'var(--green)' ?>;">
                            <?= $missing_count ?>
                            <?php if ($missing_count > 0): ?>
                                <span style="font-size:12px;color:#dc2626;">⚠️ Cannot submit</span>
                            <?php else: ?>
                                <span style="font-size:12px;color:var(--green);">✅ All required attached</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div style="max-height:300px; overflow-y:auto; margin:0 -5px; padding:0 5px;">
                    <?php foreach ($items as $item): ?>
                        <div class="checklist-item">
                            <span class="icon <?= $item['is_attached'] ? 'attached' : 'missing' ?>">
                                <i class="fas fa-<?= $item['is_attached'] ? 'check-circle' : 'circle' ?>"></i>
                            </span>
                            <span class="desc <?= $item['is_required'] ? 'required' : '' ?>">
                                <?= htmlspecialchars($item['item_description']) ?>
                                <?php if ($item['is_required']): ?>
                                    <span class="required-badge">*</span>
                                <?php endif; ?>
                            </span>
                            <?php if ($item['attachment_file']): ?>
                                <a href="<?= htmlspecialchars($item['attachment_file']) ?>" target="_blank" style="font-size:12px;color:var(--green);">
                                    <i class="fas fa-paperclip"></i>
                                </a>
                            <?php endif; ?>
                            <span style="font-size:11px;color:#64748b;">
                                <?= $item['is_attached'] ? 'Attached' : 'Missing' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Submit Confirmation with Reviewer Selection -->
            <div class="card" style="border:2px solid <?= $missing_count > 0 ? '#fee2e2' : '#d4edda' ?>;">
                <h3><i class="fas fa-paper-plane"></i> Submit for Review</h3>
                
                <?php if ($missing_count > 0): ?>
                    <div style="padding:15px; background:#fee2e2; border-radius:8px; color:#b91c1c; margin-bottom:15px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Cannot submit:</strong> <?= $missing_count ?> required item(s) are not attached.
                        Please attach all required documents before submitting.
                    </div>
                <?php else: ?>
                    <div style="padding:15px; background:#d4edda; border-radius:8px; color:#0f5a2e; margin-bottom:15px;">
                        <i class="fas fa-check-circle"></i>
                        <strong>All required items are attached!</strong> 
                        You can now submit this tender for review.
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <!-- Reviewer Selection -->
                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-weight:600; margin-bottom:6px; font-size:14px; color:var(--dark);">
                            Select Reviewer <span style="color:#dc2626;">*</span>
                        </label>
                        <select name="reviewer_id" class="reviewer-select" required <?= $missing_count > 0 ? 'disabled' : '' ?>>
                            <option value="">-- Select Reviewer --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> 
                                    (<?= htmlspecialchars($user['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size:12px; color:#64748b; margin-top:4px;">
                            <i class="fas fa-info-circle"></i> 
                            Select the person who will review this checklist. They will be notified.
                        </div>
                    </div>
                    
                    <div style="margin-bottom:15px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="confirm" value="1" required <?= $missing_count > 0 ? 'disabled' : '' ?>>
                            <span style="font-size:14px;">I confirm that all required documents are attached and the checklist is complete.</span>
                        </label>
                    </div>
                    
                    <div class="actions">
                        <?php if ($missing_count == 0): ?>
                            <button type="submit" name="submit_tender" class="btn btn-warning">
                                <i class="fas fa-paper-plane"></i> Submit for Review
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-danger" disabled>
                                <i class="fas fa-times-circle"></i> Cannot Submit - Missing Items
                            </button>
                        <?php endif; ?>
                        
                        <a href="tender_checklist.php?tender_id=<?= $tender_id ?>" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Go Back to Checklist
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-hide messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.message, .error');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });
});
</script>
</body>
</html>