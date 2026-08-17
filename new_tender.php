<?php
// new_tender.php - Create a new tender
require_once 'config/database.php';
requireLogin();

$pageTitle = "New Tender";
$message = '';
$error = '';

// Get departments and users for dropdowns
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'ACTIVE' ORDER BY department_name")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE status = 'ACTIVE' ORDER BY first_name")->fetchAll();

// Generate next tender number
function generateTenderNumber($pdo) {
    $year = date('Y');
    // Get the highest tender number for this year
    $stmt = $pdo->prepare("
        SELECT tender_number FROM tenders 
        WHERE tender_number LIKE ? 
        ORDER BY tender_number DESC LIMIT 1
    ");
    $stmt->execute(["TN-$year-%"]);
    $last = $stmt->fetchColumn();
    
    if ($last) {
        // Extract the number part and increment
        $parts = explode('-', $last);
        $num = intval(end($parts)) + 1;
    } else {
        $num = 1;
    }
    
    // Format: TN-2026-001
    return "TN-$year-" . str_pad($num, 3, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tender'])) {
    try {
        // Auto-generate tender number (not from form)
        $tender_number = generateTenderNumber($pdo);
        $tender_name = $_POST['tender_name'];
        $description = $_POST['description'] ?? '';
        $assigned_to = $_POST['assigned_to'] ?: null;
        
        // Get department from selected user
        $department_id = null;
        if ($assigned_to) {
            $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
            $stmt->execute([$assigned_to]);
            $department_id = $stmt->fetchColumn();
        }
        
        $issue_date = $_POST['issue_date'];
        $due_date = $_POST['due_date'];
        $validity_period = $_POST['validity_period'];
        $client_name = $_POST['client_name'] ?? '';
        $client_contact = $_POST['client_contact'] ?? '';
        $budget_amount = $_POST['budget_amount'] ?? 0;
        
        $quotation_pdf = '';
        if (isset($_FILES['quotation_pdf']) && $_FILES['quotation_pdf']['error'] === 0) {
            $upload_dir = 'uploads/tenders/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $quotation_pdf = $upload_dir . time() . '_' . $_FILES['quotation_pdf']['name'];
            move_uploaded_file($_FILES['quotation_pdf']['tmp_name'], $quotation_pdf);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO tenders (
                tender_number, tender_name, description, department_id, 
                assigned_to, issue_date, due_date, validity_period, 
                quotation_pdf, created_by, client_name, client_contact, budget_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tender_number, $tender_name, $description, $department_id,
            $assigned_to, $issue_date, $due_date, $validity_period,
            $quotation_pdf, $_SESSION['user_id'], $client_name, $client_contact, $budget_amount
        ]);
        
        // Log activity
        $pdo->prepare("
            INSERT INTO activity_logs (user_id, activity) 
            VALUES (?, 'Created tender: $tender_number')
        ")->execute([$_SESSION['user_id']]);
        
        $message = "Tender created successfully!";
        
        // Redirect to view the new tender
        $tender_id = $pdo->lastInsertId();
        header("Location: tender_view.php?id=$tender_id");
        exit();
        
    } catch (Exception $e) {
        $error = "Error creating tender: " . $e->getMessage();
    }
}
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
        .btn-sm { padding: 6px 14px; font-size: 12px; }
        
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
        .form-group input[readonly] {
            background: #f8fafc;
            cursor: not-allowed;
        }
        
        .file-upload {
            border: 2px dashed #e5e7eb;
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
            background: #fafdfb;
        }
        .file-upload:hover {
            border-color: var(--green);
            background: #f0fdf4;
        }
        .file-upload i {
            font-size: 32px;
            color: var(--green);
            display: block;
            margin-bottom: 8px;
        }
        .file-upload .file-name {
            font-size: 14px;
            color: var(--dark);
            font-weight: 500;
        }
        .file-upload .file-size {
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
        
        .generated-number {
            background: #f0fdf4;
            border: 2px solid var(--green);
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--green);
            font-weight: 500;
            font-size: 14px;
        }
        .generated-number i {
            font-size: 18px;
        }
        .generated-number span {
            font-weight: 700;
            font-size: 16px;
        }
        
        .department-auto {
            font-size: 12px;
            color: #64748b;
            padding: 4px 0;
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
            .generated-number { flex-wrap: wrap; }
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
                    <h1 class="page-title"><i class="fas fa-plus-circle" style="color:var(--green);"></i> <?= $pageTitle ?></h1>
                    <p class="subtitle">Create a new tender in the system</p>
                </div>
                <a href="tender_ongoing.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Tenders</a>
            </div>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <!-- New Tender Form -->
            <div class="card">
                <h3><i class="fas fa-file-signature"></i> Tender Information</h3>
                <form method="POST" enctype="multipart/form-data" id="tenderForm">
                    <div class="form-grid">
                        <!-- Tender Number - Auto Generated (Hidden from UI) -->
                        <div class="form-group full-width">
                            <div class="generated-number">
                                <i class="fas fa-check-circle"></i>
                                Tender Number: <span id="tenderNumberDisplay"><?= generateTenderNumber($pdo) ?></span>
                                <span style="font-size:12px;color:#64748b;font-weight:400;margin-left:auto;">
                                    <i class="fas fa-info-circle"></i> Auto-generated
                                </span>
                            </div>
                            <input type="hidden" name="tender_number" id="tenderNumber" value="<?= generateTenderNumber($pdo) ?>">
                        </div>
                        
                        <!-- Tender Name -->
                        <div class="form-group full-width">
                            <label>Tender Name <span class="required">*</span></label>
                            <input type="text" name="tender_name" placeholder="e.g. Office Renovation" required>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group full-width">
                            <label>Description</label>
                            <textarea name="description" rows="3" placeholder="Brief description of the tender"></textarea>
                        </div>
                        
                        <!-- Client Name -->
                        <div class="form-group">
                            <label>Client Name</label>
                            <input type="text" name="client_name" placeholder="Client/Organization">
                        </div>
                        
                        <!-- Client Contact -->
                        <div class="form-group">
                            <label>Client Contact</label>
                            <input type="text" name="client_contact" placeholder="Contact person/phone">
                        </div>
                        
                        <!-- Assigned To (User) -->
                        <div class="form-group">
                            <label>Assigned To <span class="required">*</span></label>
                            <select name="assigned_to" id="assigned_to" required onchange="updateDepartment()">
                                <option value="">Select User</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" data-dept="<?= $user['department_id'] ?>">
                                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Select the person responsible for this tender</div>
                        </div>
                        
                        <!-- Department (Auto-filled from user) -->
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" id="department_display" readonly placeholder="Will be auto-filled from assigned user">
                            <input type="hidden" name="department_id" id="department_id" value="">
                            <div class="department-auto" id="departmentHint">
                                <i class="fas fa-info-circle"></i> Department will be auto-filled when you select a user
                            </div>
                        </div>
                        
                        <!-- Issue Date -->
                        <div class="form-group">
                            <label>Issue Date <span class="required">*</span></label>
                            <input type="date" name="issue_date" required>
                        </div>
                        
                        <!-- Due Date -->
                        <div class="form-group">
                            <label>Due Date <span class="required">*</span></label>
                            <input type="date" name="due_date" required>
                        </div>
                        
                        <!-- Validity Period -->
                        <div class="form-group">
                            <label>Validity (days) <span class="required">*</span></label>
                            <input type="number" name="validity_period" placeholder="30" required value="30">
                            <div class="help-text">Number of days the tender is valid</div>
                        </div>
                        
                        <!-- Budget Amount -->
                        <div class="form-group">
                            <label>Budget ($)</label>
                            <input type="number" step="0.01" name="budget_amount" placeholder="0.00" value="0.00">
                        </div>
                        
                        <!-- Quotation PDF -->
                        <div class="form-group full-width">
                            <label>Quotation PDF</label>
                            <div class="file-upload" onclick="document.getElementById('pdfInput').click()">
                                <i class="fas fa-upload"></i>
                                <div class="file-name">Click to upload PDF</div>
                                <div class="file-size">or drag and drop (PDF only, max 10MB)</div>
                                <input type="file" id="pdfInput" name="quotation_pdf" accept=".pdf" style="display:none;" onchange="updateFileName(this)">
                            </div>
                            <div class="help-text">Upload the tender quotation document (PDF format)</div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="create_tender" class="btn btn-green">
                            <i class="fas fa-save"></i> Create Tender
                        </button>
                        <a href="tender_ongoing.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Update department based on selected user
function updateDepartment() {
    const select = document.getElementById('assigned_to');
    const deptDisplay = document.getElementById('department_display');
    const deptHidden = document.getElementById('department_id');
    const deptHint = document.getElementById('departmentHint');
    
    const selectedOption = select.options[select.selectedIndex];
    const deptId = selectedOption ? selectedOption.getAttribute('data-dept') : null;
    
    // Map department IDs to names (from PHP)
    const departments = <?php 
        $deptMap = [];
        foreach ($departments as $dept) {
            $deptMap[$dept['id']] = $dept['department_name'];
        }
        echo json_encode($deptMap);
    ?>;
    
    if (deptId && departments[deptId]) {
        deptDisplay.value = departments[deptId];
        deptHidden.value = deptId;
        deptHint.innerHTML = '<i class="fas fa-check-circle" style="color:var(--green);"></i> Department: <strong>' + departments[deptId] + '</strong> (auto-filled)';
        deptHint.style.color = 'var(--green)';
    } else {
        deptDisplay.value = '';
        deptHidden.value = '';
        deptHint.innerHTML = '<i class="fas fa-info-circle"></i> Department will be auto-filled when you select a user';
        deptHint.style.color = '#64748b';
    }
}

// Update file upload display
function updateFileName(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const parent = input.closest('.file-upload');
        if (parent) {
            parent.querySelector('.file-name').textContent = file.name;
            parent.querySelector('.file-size').textContent = (file.size / 1024).toFixed(1) + ' KB';
            parent.style.borderColor = 'var(--green)';
            parent.style.background = '#f0fdf4';
        }
    }
}

// Set default dates
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const issueDate = document.querySelector('input[name="issue_date"]');
    const dueDate = document.querySelector('input[name="due_date"]');
    
    if (issueDate) {
        issueDate.value = today.toISOString().split('T')[0];
    }
    if (dueDate) {
        const futureDate = new Date();
        futureDate.setDate(futureDate.getDate() + 30);
        dueDate.value = futureDate.toISOString().split('T')[0];
    }
});

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

// Form validation - ensure user is selected
document.getElementById('tenderForm').addEventListener('submit', function(e) {
    const assignedTo = document.getElementById('assigned_to').value;
    if (!assignedTo) {
        e.preventDefault();
        alert('Please select a user to assign this tender to.');
        document.getElementById('assigned_to').focus();
        return false;
    }
    return true;
});
</script>
</body>
</html>