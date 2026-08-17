<?php
// tender_edit.php - Edit tender information
require_once 'config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;

if (!$id) {
    header('Location: tender_ongoing.php');
    exit();
}

// Get tender details
$stmt = $pdo->prepare("
    SELECT t.*, 
           assigned.first_name as assigned_first, assigned.last_name as assigned_last
    FROM tenders t
    LEFT JOIN users assigned ON assigned.id = t.assigned_to
    WHERE t.id = ?
");
$stmt->execute([$id]);
$tender = $stmt->fetch();

if (!$tender) {
    header('Location: tender_ongoing.php');
    exit();
}

// Get departments and users for dropdowns
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'ACTIVE' ORDER BY department_name")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE status = 'ACTIVE' ORDER BY first_name")->fetchAll();

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tender'])) {
    try {
        $tender_number = $_POST['tender_number'];
        $tender_name = $_POST['tender_name'];
        $description = $_POST['description'];
        $department_id = $_POST['department_id'] ?: null;
        $assigned_to = $_POST['assigned_to'] ?: null;
        $issue_date = $_POST['issue_date'];
        $due_date = $_POST['due_date'];
        $validity_period = $_POST['validity_period'];
        $client_name = $_POST['client_name'] ?? '';
        $client_contact = $_POST['client_contact'] ?? '';
        $budget_amount = $_POST['budget_amount'] ?? 0;
        $status = $_POST['status'] ?? 'Draft';
        
        // Handle file upload
        $quotation_pdf = $tender['quotation_pdf'];
        if (isset($_FILES['quotation_pdf']) && $_FILES['quotation_pdf']['error'] === 0) {
            $upload_dir = 'uploads/tenders/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $quotation_pdf = $upload_dir . time() . '_' . $_FILES['quotation_pdf']['name'];
            move_uploaded_file($_FILES['quotation_pdf']['tmp_name'], $quotation_pdf);
        }
        
        $stmt = $pdo->prepare("
            UPDATE tenders SET 
                tender_number = ?,
                tender_name = ?,
                description = ?,
                department_id = ?,
                assigned_to = ?,
                issue_date = ?,
                due_date = ?,
                validity_period = ?,
                quotation_pdf = ?,
                client_name = ?,
                client_contact = ?,
                budget_amount = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $tender_number,
            $tender_name,
            $description,
            $department_id,
            $assigned_to,
            $issue_date,
            $due_date,
            $validity_period,
            $quotation_pdf,
            $client_name,
            $client_contact,
            $budget_amount,
            $status,
            $id
        ]);
        
        // Log activity
        logActivity($_SESSION['user_id'], "Updated tender: $tender_number");
        
        $message = "Tender updated successfully!";
        
        // Refresh tender data
        $stmt = $pdo->prepare("SELECT * FROM tenders WHERE id = ?");
        $stmt->execute([$id]);
        $tender = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = "Error updating tender: " . $e->getMessage();
    }
}

$pageTitle = "Edit Tender: " . $tender['tender_number'];
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
            padding: 8px 18px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: .25s; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            font-size: 13px; 
            text-decoration: none;
        }
        .btn-green { background: var(--green); color: white; }
        .btn-green:hover { background: #0f6a36; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(31,139,76,0.25); }
        .btn-outline { background: transparent; border: 2px solid var(--green); color: var(--green); }
        .btn-outline:hover { background: var(--green); color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 20px;
        }
        .card h3 {
            font-size: 16px;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h3 i {
            color: var(--green);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
            color: #374151;
        }
        .form-group label .required {
            color: #dc2626;
            margin-left: 2px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.2s;
            background: white;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(31,139,76,0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-group .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .form-group .file-upload {
            border: 2px dashed #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
            background: #fafdfb;
        }
        .form-group .file-upload:hover {
            border-color: var(--green);
            background: #f0fdf4;
        }
        .form-group .file-upload i {
            font-size: 24px;
            color: var(--green);
            display: block;
            margin-bottom: 6px;
        }
        .form-group .file-upload .file-name {
            font-size: 13px;
            color: var(--dark);
            font-weight: 500;
        }
        .form-group .file-upload .file-size {
            font-size: 12px;
            color: #64748b;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
            padding-top: 20px;
            border-top: 2px solid #f1f5f9;
        }
        .form-actions .btn {
            padding: 10px 24px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-draft { background: #e5e7eb; color: #6b7280; }
        .status-open { background: var(--light-green); color: var(--green); }
        .status-submitted { background: #fef3c7; color: #b45309; }
        .status-awarded { background: #dbeafe; color: #1d4ed8; }
        .status-lost { background: #fee2e2; color: #b91c1c; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }
        
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
        
        .current-file {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 13px;
        }
        .current-file a {
            color: var(--green);
            text-decoration: none;
        }
        .current-file a:hover {
            text-decoration: underline;
        }
        
        @media(max-width: 991px) { 
            .main { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
        }
        @media(max-width: 768px) { 
            .content { padding: 15px; } 
            .page-header .page-title { font-size: 20px; }
            .card { padding: 20px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
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
                    <h1 class="page-title"><i class="fas fa-edit" style="color:var(--green);"></i> <?= $pageTitle ?></h1>
                    <p class="subtitle">Update tender details and information</p>
                </div>
                <div>
                    <span class="status-badge status-<?= strtolower($tender['status']) ?>">
                        <?= $tender['status'] ?>
                    </span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <!-- Edit Form -->
            <div class="card">
                <h3><i class="fas fa-file-signature"></i> Tender Information</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <!-- Tender Number -->
                        <div class="form-group">
                            <label>Tender Number <span class="required">*</span></label>
                            <input type="text" name="tender_number" value="<?= htmlspecialchars($tender['tender_number']) ?>" required>
                        </div>
                        
                        <!-- Status -->
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="Draft" <?= $tender['status'] == 'Draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="Open" <?= $tender['status'] == 'Open' ? 'selected' : '' ?>>Open</option>
                                <option value="Submitted" <?= $tender['status'] == 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                <option value="Awarded" <?= $tender['status'] == 'Awarded' ? 'selected' : '' ?>>Awarded</option>
                                <option value="Lost" <?= $tender['status'] == 'Lost' ? 'selected' : '' ?>>Lost</option>
                                <option value="Cancelled" <?= $tender['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <!-- Tender Name -->
                        <div class="form-group full-width">
                            <label>Tender Name <span class="required">*</span></label>
                            <input type="text" name="tender_name" value="<?= htmlspecialchars($tender['tender_name']) ?>" required>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group full-width">
                            <label>Description</label>
                            <textarea name="description" rows="3"><?= htmlspecialchars($tender['description'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Client Name -->
                        <div class="form-group">
                            <label>Client Name</label>
                            <input type="text" name="client_name" value="<?= htmlspecialchars($tender['client_name'] ?? '') ?>">
                        </div>
                        
                        <!-- Client Contact -->
                        <div class="form-group">
                            <label>Client Contact</label>
                            <input type="text" name="client_contact" value="<?= htmlspecialchars($tender['client_contact'] ?? '') ?>">
                        </div>
                        
                        <!-- Department -->
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= $tender['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Assigned To -->
                        <div class="form-group">
                            <label>Assigned To</label>
                            <select name="assigned_to">
                                <option value="">Select User</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $tender['assigned_to'] == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Issue Date -->
                        <div class="form-group">
                            <label>Issue Date <span class="required">*</span></label>
                            <input type="date" name="issue_date" value="<?= $tender['issue_date'] ?>" required>
                        </div>
                        
                        <!-- Due Date -->
                        <div class="form-group">
                            <label>Due Date <span class="required">*</span></label>
                            <input type="date" name="due_date" value="<?= $tender['due_date'] ?>" required>
                        </div>
                        
                        <!-- Validity Period -->
                        <div class="form-group">
                            <label>Validity Period (days) <span class="required">*</span></label>
                            <input type="number" name="validity_period" value="<?= $tender['validity_period'] ?>" required>
                        </div>
                        
                        <!-- Budget Amount -->
                        <div class="form-group">
                            <label>Budget Amount ($)</label>
                            <input type="number" step="0.01" name="budget_amount" value="<?= $tender['budget_amount'] ?? 0 ?>">
                        </div>
                        
                        <!-- Quotation PDF -->
                        <div class="form-group full-width">
                            <label>Quotation PDF</label>
                            <div class="file-upload" onclick="document.getElementById('pdfInput').click()">
                                <i class="fas fa-upload"></i>
                                <div class="file-name">Click to upload a new PDF</div>
                                <div class="file-size">or drag and drop (PDF only)</div>
                                <input type="file" id="pdfInput" name="quotation_pdf" accept=".pdf" style="display:none;" onchange="updateFileName(this)">
                            </div>
                            
                            <?php if ($tender['quotation_pdf']): ?>
                                <div class="current-file">
                                    <i class="fas fa-file-pdf" style="color:#dc2626;"></i>
                                    <span>Current file: <a href="<?= htmlspecialchars($tender['quotation_pdf']) ?>" target="_blank"><?= basename($tender['quotation_pdf']) ?></a></span>
                                    <span style="font-size:12px;color:#64748b;margin-left:auto;">Leave empty to keep current</span>
                                </div>
                            <?php endif; ?>
                            <div class="help-text">Upload a PDF document for this tender (Max 10MB)</div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="update_tender" class="btn btn-green">
                            <i class="fas fa-save"></i> Update Tender
                        </button>
                        <a href="tender_view.php?id=<?= $id ?>" class="btn btn-outline">
                            <i class="fas fa-eye"></i> View Tender
                        </a>
                        <a href="tender_ongoing.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Tenders
                        </a>
                        <a href="tender_checklist.php?tender_id=<?= $id ?>" class="btn btn-outline">
                            <i class="fas fa-clipboard-check"></i> Checklist
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Additional Info -->
            <div class="card" style="background:#f8fafc;border:2px solid #e5e7eb;">
                <h3><i class="fas fa-info-circle"></i> Tender Information</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px; font-size:13px;">
                    <div>
                        <span style="color:#64748b;">Created By</span><br>
                        <strong><?= htmlspecialchars($tender['creator_first'] ?? '') . ' ' . htmlspecialchars($tender['creator_last'] ?? '') ?: 'Unknown' ?></strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Created At</span><br>
                        <strong><?= date('d M Y H:i', strtotime($tender['created_at'])) ?></strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Last Updated</span><br>
                        <strong><?= date('d M Y H:i', strtotime($tender['updated_at'] ?? $tender['created_at'])) ?></strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Checklist Status</span><br>
                        <strong><?= $tender['checklist_status'] ?? 'Not Started' ?></strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Checklist Progress</span><br>
                        <strong><?= ($tender['checklist_completed'] ?? 0) . ' / ' . ($tender['checklist_count'] ?? 0) ?></strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Submission Date</span><br>
                        <strong><?= $tender['submission_date'] ? date('d M Y', strtotime($tender['submission_date'])) : 'Not submitted' ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateFileName(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const parent = input.closest('.file-upload');
        parent.querySelector('.file-name').textContent = file.name;
        parent.querySelector('.file-size').textContent = (file.size / 1024).toFixed(1) + ' KB';
    }
}

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