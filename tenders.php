<?php
// tenders.php - Enhanced with multiple views and full CRUD
require_once 'config/database.php';
requireLogin();

$pageTitle = "Tender Management";
$message = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'dashboard';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ===== TENDER CRUD =====
    if ($action === 'create_tender') {
        $tender_number = $_POST['tender_number'];
        $tender_name = $_POST['tender_name'];
        $description = $_POST['description'];
        $department_id = $_POST['department_id'];
        $assigned_to = $_POST['assigned_to'];
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
        
        $pdo->prepare("
            INSERT INTO activity_logs (user_id, activity) 
            VALUES (?, 'Created tender: $tender_number')
        ")->execute([$_SESSION['user_id']]);
        
        $message = 'Tender created successfully!';
    }
    
    if ($action === 'delete_tender') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM tenders WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Tender deleted successfully!';
    }
    
    if ($action === 'update_tender_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE tenders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $message = 'Tender status updated successfully!';
    }
    
    // ===== UZ SUBMISSIONS CRUD =====
    if ($action === 'create_uz') {
        $stmt = $pdo->prepare("
            INSERT INTO uz_submissions (
                tender_id, project_name, allocated_to, submitted_by, 
                pmu_personnel, submitted_on, follow_up_date, status, remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['tender_id'] ?: NULL,
            $_POST['project_name'],
            $_POST['allocated_to'],
            $_POST['submitted_by'],
            $_POST['pmu_personnel'],
            $_POST['submitted_on'],
            $_POST['follow_up_date'],
            $_POST['status'],
            $_POST['remarks']
        ]);
        $message = 'UZ Submission added successfully!';
    }
    
    if ($action === 'delete_uz') {
        $stmt = $pdo->prepare("DELETE FROM uz_submissions WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'UZ Submission deleted!';
    }
    
    if ($action === 'update_uz') {
        $stmt = $pdo->prepare("
            UPDATE uz_submissions SET 
                project_name = ?, allocated_to = ?, submitted_by = ?,
                pmu_personnel = ?, submitted_on = ?, follow_up_date = ?,
                status = ?, remarks = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['project_name'],
            $_POST['allocated_to'],
            $_POST['submitted_by'],
            $_POST['pmu_personnel'],
            $_POST['submitted_on'],
            $_POST['follow_up_date'],
            $_POST['status'],
            $_POST['remarks'],
            $_POST['id']
        ]);
        $message = 'UZ Submission updated successfully!';
    }
    
    // ===== SUPPLIER REGISTRATIONS CRUD =====
    if ($action === 'create_reg') {
        $stmt = $pdo->prepare("
            INSERT INTO supplier_registrations (
                organization_name, contact_person, email, phone,
                registration_type, assigned_to, submitted_by,
                due_date, source, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['organization_name'],
            $_POST['contact_person'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['registration_type'],
            $_POST['assigned_to'],
            $_POST['submitted_by'],
            $_POST['due_date'],
            $_POST['source'],
            $_POST['status'],
            $_POST['notes']
        ]);
        $message = 'Supplier Registration added successfully!';
    }
    
    if ($action === 'delete_reg') {
        $stmt = $pdo->prepare("DELETE FROM supplier_registrations WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Supplier Registration deleted!';
    }
    
    if ($action === 'update_reg') {
        $stmt = $pdo->prepare("
            UPDATE supplier_registrations SET 
                organization_name = ?, contact_person = ?, email = ?,
                phone = ?, registration_type = ?, assigned_to = ?,
                submitted_by = ?, due_date = ?, source = ?,
                status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['organization_name'],
            $_POST['contact_person'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['registration_type'],
            $_POST['assigned_to'],
            $_POST['submitted_by'],
            $_POST['due_date'],
            $_POST['source'],
            $_POST['status'],
            $_POST['notes'],
            $_POST['id']
        ]);
        $message = 'Supplier Registration updated successfully!';
    }
    
    // ===== SITE VISITS CRUD =====
    if ($action === 'create_visit') {
        $stmt = $pdo->prepare("
            INSERT INTO site_visits (
                project_name, client_name, assigned_to,
                visit_date, visit_time, location, notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['project_name'],
            $_POST['client_name'],
            $_POST['assigned_to'],
            $_POST['visit_date'],
            $_POST['visit_time'],
            $_POST['location'],
            $_POST['notes'],
            $_POST['status']
        ]);
        $message = 'Site Visit added successfully!';
    }
    
    if ($action === 'delete_visit') {
        $stmt = $pdo->prepare("DELETE FROM site_visits WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Site Visit deleted!';
    }
    
    if ($action === 'update_visit') {
        $stmt = $pdo->prepare("
            UPDATE site_visits SET 
                project_name = ?, client_name = ?, assigned_to = ?,
                visit_date = ?, visit_time = ?, location = ?,
                notes = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['project_name'],
            $_POST['client_name'],
            $_POST['assigned_to'],
            $_POST['visit_date'],
            $_POST['visit_time'],
            $_POST['location'],
            $_POST['notes'],
            $_POST['status'],
            $_POST['id']
        ]);
        $message = 'Site Visit updated successfully!';
    }
}

// ===== FETCH DATA =====
// Tenders
$tenders = $pdo->query("
    SELECT t.*, 
           u.first_name, u.last_name,
           d.department_name,
           assigned.first_name as assigned_first, assigned.last_name as assigned_last
    FROM tenders t
    LEFT JOIN users u ON u.id = t.created_by
    LEFT JOIN departments d ON d.id = t.department_id
    LEFT JOIN users assigned ON assigned.id = t.assigned_to
    ORDER BY t.created_at DESC
")->fetchAll();

// UZ Submissions
$uz_submissions = $pdo->query("
    SELECT * FROM uz_submissions ORDER BY created_at DESC
")->fetchAll();

// Supplier Registrations
$supplier_registrations = $pdo->query("
    SELECT * FROM supplier_registrations ORDER BY created_at DESC
")->fetchAll();

// Site Visits
$site_visits = $pdo->query("
    SELECT * FROM site_visits ORDER BY visit_date ASC
")->fetchAll();

// Departments and Users for dropdowns
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'ACTIVE'")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE status = 'ACTIVE'")->fetchAll();

// Stats
$stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM tenders) as total_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Open') as open_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Submitted') as submitted_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Awarded') as awarded_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Lost') as lost_tenders,
        (SELECT COUNT(*) FROM tenders WHERE due_date < CURDATE() AND status != 'Closed') as overdue_tenders,
        (SELECT COUNT(*) FROM tenders WHERE DATEDIFF(due_date, CURDATE()) BETWEEN 0 AND 30) as expiring_30_days,
        (SELECT COUNT(*) FROM tenders WHERE DATEDIFF(due_date, CURDATE()) BETWEEN 31 AND 60) as expiring_60_days,
        (SELECT COUNT(*) FROM uz_submissions) as total_uz,
        (SELECT COUNT(*) FROM supplier_registrations) as total_reg,
        (SELECT COUNT(*) FROM site_visits WHERE status = 'Scheduled') as total_visits
")->fetch();

// Get tender list for dropdowns
$tender_list = $pdo->query("SELECT id, tender_number, tender_name FROM tenders ORDER BY tender_number")->fetchAll();
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
            margin-bottom: 30px;
        }
        .page-header .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .page-header .subtitle {
            margin: 0;
            font-size: 15px;
            color: #64748b;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 15px 18px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border-left: 4px solid var(--green);
        }
        .stat-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .value { font-size: 22px; font-weight: 700; color: var(--dark); margin-top: 2px; }
        .stat-card .value.green { color: var(--green); }
        .stat-card .value.orange { color: #b45309; }
        .stat-card .value.red { color: #dc2626; }
        .stat-card .value.blue { color: #2563eb; }
        .stat-card .value.purple { color: #7c3aed; }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 25px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            overflow-x: auto;
            flex-wrap: nowrap;
        }
        .tabs a {
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            font-size: 14px;
            transition: .25s;
            white-space: nowrap;
        }
        .tabs a:hover { background: #f1f5f9; }
        .tabs a.active {
            background: var(--green);
            color: white;
        }
        .tabs a i { margin-right: 8px; }
        
        .actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-sm { padding:6px 14px; font-size:13px; }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-edit { background:#e5e7eb; color:#1e2a2f; }
        .btn-edit:hover { background:#d1d5db; }
        
        .search-filter { display:flex; gap:15px; flex-wrap:wrap; }
        .search-filter input, .search-filter select { padding:10px 16px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; background:white; }
        .search-filter input:focus, .search-filter select:focus { outline:none; border-color:var(--green); }
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:700px; font-size:13px; }
        th { text-align:left; padding:12px 14px; background:var(--light-green); color:var(--dark); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        td { padding:12px 14px; border-bottom:1px solid #f3f4f6; }
        .status { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
        .status-draft { background:#e5e7eb; color:#6b7280; }
        .status-open { background:var(--light-green); color:var(--green); }
        .status-submitted { background:#fef3c7; color:#b45309; }
        .status-awarded { background:#dbeafe; color:#1d4ed8; }
        .status-lost { background:#fee2e2; color:#b91c1c; }
        .status-cancelled { background:#f3f4f6; color:#6b7280; }
        .status-in-progress { background:#dbeafe; color:#1d4ed8; }
        .status-pending { background:#fef3c7; color:#b45309; }
        .status-approved { background:var(--light-green); color:var(--green); }
        .status-rejected { background:#fee2e2; color:#b91c1c; }
        .status-scheduled { background:#dbeafe; color:#1d4ed8; }
        .status-completed { background:var(--light-green); color:var(--green); }
        
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:650px; max-height:90vh; overflow-y:auto; }
        .modal-content h2 { margin-bottom:20px; color:var(--dark); }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; color:#64748b; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; color:var(--dark); }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--green); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .badge-count { background:#ef4444; color:white; padding:1px 8px; border-radius:12px; font-size:11px; margin-left:6px; }
        
        .btn-group { display:flex; gap:5px; flex-wrap:wrap; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .page-header .page-title { font-size:24px; }
            .page-header .subtitle { font-size:14px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-header .page-title { font-size:20px; }
            .page-header .subtitle { font-size:13px; }
            .actions { flex-direction:column; align-items:stretch; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-row { grid-template-columns:1fr; }
            .tabs { flex-wrap:nowrap; overflow-x:auto; }
            .tabs a { padding:8px 16px; font-size:13px; }
        }
        @media(max-width:480px) { 
            .page-header { flex-direction:column; align-items:flex-start; gap:5px; }
            .page-header .page-title { font-size:18px; }
            .page-header .subtitle { font-size:12px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap:8px; }
            .stat-card { padding:10px 12px; }
            .stat-card .value { font-size:18px; }
            .search-filter { flex-direction:column; width:100%; }
            .search-filter input, .search-filter select { width:100%; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <div class="page-header">
                <p class="subtitle">Manage all tenders, track submissions, and monitor progress.</p>
            </div>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card"><div class="label">Total Tenders</div><div class="value"><?= $stats['total_tenders'] ?></div></div>
                <div class="stat-card"><div class="label">Open</div><div class="value orange"><?= $stats['open_tenders'] ?></div></div>
                <div class="stat-card"><div class="label">Submitted</div><div class="value blue"><?= $stats['submitted_tenders'] ?></div></div>
                <div class="stat-card"><div class="label">Awarded</div><div class="value green"><?= $stats['awarded_tenders'] ?></div></div>
                <div class="stat-card"><div class="label">Lost</div><div class="value red"><?= $stats['lost_tenders'] ?></div></div>
                <div class="stat-card"><div class="label">Overdue</div><div class="value red"><?= $stats['overdue_tenders'] ?></div></div>
                <div class="stat-card"><div class="label">UZ Submissions</div><div class="value purple"><?= $stats['total_uz'] ?></div></div>
                <div class="stat-card"><div class="label">Registrations</div><div class="value blue"><?= $stats['total_reg'] ?></div></div>
                <div class="stat-card"><div class="label">Site Visits</div><div class="value green"><?= $stats['total_visits'] ?></div></div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <!-- <a href="?tab=dashboard" class="<?= $active_tab == 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a> -->
                <a href="?tab=ongoing" class="<?= $active_tab == 'ongoing' ? 'active' : '' ?>"><i class="fas fa-clock"></i> Ongoing</a>
                <a href="?tab=submitted" class="<?= $active_tab == 'submitted' ? 'active' : '' ?>"><i class="fas fa-paper-plane"></i> Submitted</a>
                <a href="?tab=uz_submitted" class="<?= $active_tab == 'uz_submitted' ? 'active' : '' ?>"><i class="fas fa-university"></i> UZ Submitted</a>
                <a href="?tab=supplier_reg" class="<?= $active_tab == 'supplier_reg' ? 'active' : '' ?>"><i class="fas fa-user-plus"></i> Supplier Reg</a>
                <a href="?tab=site_visits" class="<?= $active_tab == 'site_visits' ? 'active' : '' ?>"><i class="fas fa-map-marker-alt"></i> Site Visits</a>
                    <a href="#" onclick="openModal('tenderModal'); return false;" ><i class="fas fa-plus"></i> New Tender</a>
            </div>
            
            <!-- ===== DASHBOARD TAB ===== -->
            <div class="tab-content <?= $active_tab == 'dashboard' ? 'active' : '' ?>">
                <div class="actions">
                    <!-- <button class="btn btn-green" onclick="openModal('tenderModal')"><i class="fas fa-plus"></i> New Tender</button> -->
                    <div class="search-filter">
                        <input type="text" id="searchInput" placeholder="Search tenders..." onkeyup="filterTenders()">
                        <select id="statusFilter" onchange="filterTenders()">
                            <option value="">All Status</option>
                            <option value="Draft">Draft</option>
                            <option value="Open">Open</option>
                            <option value="Submitted">Submitted</option>
                            <option value="Awarded">Awarded</option>
                            <option value="Lost">Lost</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="tenderTable">
                        <thead>
                            <tr>
                                <th>Tender No.</th>
                                <th>Name</th>
                                <th>Client</th>
                                <th>Department</th>
                                <th>Assigned To</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tenders as $tender): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($tender['tender_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($tender['tender_name']) ?></td>
                                    <td><?= htmlspecialchars($tender['client_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($tender['department_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars(($tender['assigned_first'] ?? '') . ' ' . ($tender['assigned_last'] ?? '')) ?: 'Unassigned' ?></td>
                                    <td><?= date('d M Y', strtotime($tender['due_date'])) ?></td>
                                    <td><span class="status status-<?= strtolower($tender['status']) ?>"><?= $tender['status'] ?></span></td>
                                    <td>
                                        <a href="tender_view.php?id=<?= $tender['id'] ?>" class="btn btn-sm btn-green"><i class="fas fa-eye"></i></a>
                                        <a href="tender_edit.php?id=<?= $tender['id'] ?>" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                                        <button onclick="deleteItem('delete_tender', <?= $tender['id'] ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ===== ONGOING TAB ===== -->
            <div class="tab-content <?= $active_tab == 'ongoing' ? 'active' : '' ?>">
                <div class="actions">
                    <span style="font-weight:600;color:var(--dark);">Tenders in Progress</span>
                    <div class="search-filter">
                        <input type="text" id="ongoingSearch" placeholder="Search..." onkeyup="filterTable('ongoingTable', 'ongoingSearch')">
                    </div>
                </div>
                <div class="table-container">
                    <table id="ongoingTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Project</th>
                                <th>Site Visit/RFQ</th>
                                <th>Closing Date</th>
                                <th>Bid Bond</th>
                                <th>Procurement Manager</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $ongoing = array_filter($tenders, function($t) {
                                return in_array($t['status'], ['Open', 'Draft']);
                            });
                            foreach ($ongoing as $t): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($t['issue_date'] ?? $t['created_at'])) ?></td>
                                    <td><strong><?= htmlspecialchars($t['tender_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($t['client_name'] ?? 'N/A') ?></td>
                                    <td><?= date('d/m/Y', strtotime($t['due_date'])) ?></td>
                                    <td>$<?= number_format($t['budget_amount'] ?? 0, 2) ?></td>
                                    <td><?= htmlspecialchars(($t['assigned_first'] ?? '') . ' ' . ($t['assigned_last'] ?? '')) ?: 'Unassigned' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($ongoing)): ?>
                                <tr><td colspan="6" style="text-align:center;color:#64748b;padding:30px;">No ongoing tenders</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ===== SUBMITTED TAB ===== -->
            <div class="tab-content <?= $active_tab == 'submitted' ? 'active' : '' ?>">
                <div class="actions">
                    <span style="font-weight:600;color:var(--dark);">Submitted Tenders</span>
                    <div class="search-filter">
                        <input type="text" id="submittedSearch" placeholder="Search..." onkeyup="filterTable('submittedTable', 'submittedSearch')">
                    </div>
                </div>
                <div class="table-container">
                    <table id="submittedTable">
                        <thead>
                            <tr>
                                <th>Tender</th>
                                <th>Allocated To</th>
                                <th>Submitted By</th>
                                <th>Closing Date</th>
                                <th>Bid Bond</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $submitted = array_filter($tenders, function($t) {
                                return $t['status'] == 'Submitted';
                            });
                            foreach ($submitted as $t): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($t['tender_name']) ?></strong></td>
                                    <td><?= htmlspecialchars(($t['assigned_first'] ?? '') . ' ' . ($t['assigned_last'] ?? '')) ?: 'N/A' ?></td>
                                    <td><?= htmlspecialchars($t['first_name'] ?? 'N/A') ?></td>
                                    <td><?= date('d/m/Y', strtotime($t['due_date'])) ?></td>
                                    <td>$<?= number_format($t['budget_amount'] ?? 0, 2) ?></td>
                                    <td><span class="status status-submitted">Submitted</span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($submitted)): ?>
                                <tr><td colspan="6" style="text-align:center;color:#64748b;padding:30px;">No submitted tenders</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ===== UZ SUBMITTED TAB ===== -->
            <div class="tab-content <?= $active_tab == 'uz_submitted' ? 'active' : '' ?>">
                <div class="actions">
                    <button class="btn btn-green" onclick="openModal('uzModal')"><i class="fas fa-plus"></i> Add UZ Submission</button>
                    <div class="search-filter">
                        <input type="text" id="uzSearch" placeholder="Search..." onkeyup="filterTable('uzTable', 'uzSearch')">
                    </div>
                </div>
                <div class="table-container">
                    <table id="uzTable">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Allocated To</th>
                                <th>Submitted By</th>
                                <th>PMU Personnel</th>
                                <th>Submitted On</th>
                                <th>Follow Up</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uz_submissions as $uz): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($uz['project_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($uz['allocated_to'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($uz['submitted_by'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($uz['pmu_personnel'] ?? 'N/A') ?></td>
                                    <td><?= $uz['submitted_on'] ? date('d/m/Y', strtotime($uz['submitted_on'])) : 'N/A' ?></td>
                                    <td><?= $uz['follow_up_date'] ? date('d/m/Y', strtotime($uz['follow_up_date'])) : 'N/A' ?></td>
                                    <td><span class="status status-<?= strtolower(str_replace(' ', '-', $uz['status'])) ?>"><?= $uz['status'] ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-edit" onclick="editUZ(<?= $uz['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteItem('delete_uz', <?= $uz['id'] ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($uz_submissions)): ?>
                                <tr><td colspan="8" style="text-align:center;color:#64748b;padding:30px;">No UZ submissions found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ===== SUPPLIER REGISTRATION TAB ===== -->
            <div class="tab-content <?= $active_tab == 'supplier_reg' ? 'active' : '' ?>">
                <div class="actions">
                    <button class="btn btn-green" onclick="openModal('regModal')"><i class="fas fa-plus"></i> Add Registration</button>
                    <div class="search-filter">
                        <input type="text" id="regSearch" placeholder="Search..." onkeyup="filterTable('regTable', 'regSearch')">
                    </div>
                </div>
                <div class="table-container">
                    <table id="regTable">
                        <thead>
                            <tr>
                                <th>Organization</th>
                                <th>Contact Person</th>
                                <th>Assigned To</th>
                                <th>Due Date</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supplier_registrations as $reg): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($reg['organization_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($reg['contact_person'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($reg['assigned_to'] ?? 'N/A') ?></td>
                                    <td><?= $reg['due_date'] ? date('d/m/Y', strtotime($reg['due_date'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($reg['source'] ?? 'N/A') ?></td>
                                    <td><span class="status status-<?= strtolower(str_replace(' ', '-', $reg['status'])) ?>"><?= $reg['status'] ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-edit" onclick="editReg(<?= $reg['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteItem('delete_reg', <?= $reg['id'] ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($supplier_registrations)): ?>
                                <tr><td colspan="7" style="text-align:center;color:#64748b;padding:30px;">No supplier registrations found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ===== SITE VISITS TAB ===== -->
            <div class="tab-content <?= $active_tab == 'site_visits' ? 'active' : '' ?>">
                <div class="actions">
                    <button class="btn btn-green" onclick="openModal('visitModal')"><i class="fas fa-plus"></i> Add Site Visit</button>
                    <div class="search-filter">
                        <input type="text" id="siteSearch" placeholder="Search..." onkeyup="filterTable('siteTable', 'siteSearch')">
                    </div>
                </div>
                <div class="table-container">
                    <table id="siteTable">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Assigned To</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($site_visits as $visit): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($visit['project_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($visit['client_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($visit['assigned_to'] ?? 'N/A') ?></td>
                                    <td><?= date('d/m/Y', strtotime($visit['visit_date'])) ?></td>
                                    <td><?= htmlspecialchars($visit['visit_time'] ?? '-') ?></td>
                                    <td><span class="status status-<?= strtolower($visit['status']) ?>"><?= $visit['status'] ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-edit" onclick="editVisit(<?= $visit['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteItem('delete_visit', <?= $visit['id'] ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($site_visits)): ?>
                                <tr><td colspan="7" style="text-align:center;color:#64748b;padding:30px;">No site visits scheduled</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODALS ===== -->

<!-- Tender Modal -->
<div class="modal" id="tenderModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('tenderModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> New Tender</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_tender">
            <div class="form-group">
                <label>Tender Number *</label>
                <input type="text" name="tender_number" placeholder="e.g. TN-2026-001" required>
            </div>
            <div class="form-group">
                <label>Tender Name *</label>
                <input type="text" name="tender_name" placeholder="e.g. Office Renovation" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Brief description"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Client Name</label><input type="text" name="client_name" placeholder="Client/Organization"></div>
                <div class="form-group"><label>Client Contact</label><input type="text" name="client_contact" placeholder="Contact person/phone"></div>
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department_id">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= $dept['department_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Assigned To</label>
                <select name="assigned_to">
                    <option value="">Select User</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= $user['first_name'] . ' ' . $user['last_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Issue Date *</label><input type="date" name="issue_date" required></div>
                <div class="form-group"><label>Due Date *</label><input type="date" name="due_date" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Validity (days) *</label><input type="number" name="validity_period" placeholder="30" required></div>
                <div class="form-group"><label>Budget ($)</label><input type="number" step="0.01" name="budget_amount" placeholder="0.00"></div>
            </div>
            <div class="form-group">
                <label>Quotation PDF</label>
                <div class="file-input" onclick="document.getElementById('pdfInput').click()">
                    <i class="fas fa-upload"></i> Click to upload PDF
                    <input type="file" id="pdfInput" name="quotation_pdf" accept=".pdf" style="display:none;" onchange="this.parentElement.innerHTML = this.files[0].name">
                </div>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Create Tender</button>
        </form>
    </div>
</div>

<!-- UZ Submission Modal -->
<div class="modal" id="uzModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('uzModal')">&times;</span>
        <h2><i class="fas fa-university"></i> <span id="uzModalTitle">Add UZ Submission</span></h2>
        <form method="POST">
            <input type="hidden" name="action" id="uzAction" value="create_uz">
            <input type="hidden" name="id" id="uzId" value="">
            <div class="form-group">
                <label>Project Name *</label>
                <input type="text" name="project_name" id="uz_project_name" placeholder="e.g. UZ - Supply and delivery of materials" required>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Allocated To</label><input type="text" name="allocated_to" id="uz_allocated_to" placeholder="Person allocated"></div>
                <div class="form-group"><label>Submitted By</label><input type="text" name="submitted_by" id="uz_submitted_by" placeholder="Who submitted"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>PMU Personnel</label><input type="text" name="pmu_personnel" id="uz_pmu_personnel" placeholder="PMU contact"></div>
                <div class="form-group"><label>Related Tender</label>
                    <select name="tender_id" id="uz_tender_id">
                        <option value="">Select Tender</option>
                        <?php foreach ($tender_list as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= $t['tender_number'] ?> - <?= $t['tender_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Submitted On</label><input type="date" name="submitted_on" id="uz_submitted_on"></div>
                <div class="form-group"><label>Follow Up Date</label><input type="date" name="follow_up_date" id="uz_follow_up_date"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="uz_status">
                        <option value="Pending">Pending</option>
                        <option value="Submitted">Submitted</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Awarded">Awarded</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" id="uz_remarks" rows="2" placeholder="Additional notes"></textarea>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> <span id="uzSubmitText">Save</span></button>
        </form>
    </div>
</div>

<!-- Supplier Registration Modal -->
<div class="modal" id="regModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('regModal')">&times;</span>
        <h2><i class="fas fa-user-plus"></i> <span id="regModalTitle">Add Supplier Registration</span></h2>
        <form method="POST">
            <input type="hidden" name="action" id="regAction" value="create_reg">
            <input type="hidden" name="id" id="regId" value="">
            <div class="form-group">
                <label>Organization Name *</label>
                <input type="text" name="organization_name" id="reg_organization_name" required>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" id="reg_contact_person"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="reg_email"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Phone</label><input type="text" name="phone" id="reg_phone"></div>
                <div class="form-group"><label>Registration Type</label>
                    <select name="registration_type" id="reg_type">
                        <option value="Supplier Registration">Supplier Registration</option>
                        <option value="Contractor Registration">Contractor Registration</option>
                        <option value="Service Provider">Service Provider</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Assigned To</label><input type="text" name="assigned_to" id="reg_assigned_to"></div>
                <div class="form-group"><label>Submitted By</label><input type="text" name="submitted_by" id="reg_submitted_by"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Due Date</label><input type="date" name="due_date" id="reg_due_date"></div>
                <div class="form-group"><label>Source</label><input type="text" name="source" id="reg_source" placeholder="e.g. tendertube, gazette"></div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="reg_status">
                    <option value="In Progress">In Progress</option>
                    <option value="Submitted">Submitted</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="reg_notes" rows="2" placeholder="Additional notes"></textarea>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> <span id="regSubmitText">Save</span></button>
        </form>
    </div>
</div>

<!-- Site Visit Modal -->
<div class="modal" id="visitModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('visitModal')">&times;</span>
        <h2><i class="fas fa-map-marker-alt"></i> <span id="visitModalTitle">Add Site Visit</span></h2>
        <form method="POST">
            <input type="hidden" name="action" id="visitAction" value="create_visit">
            <input type="hidden" name="id" id="visitId" value="">
            <div class="form-group">
                <label>Project Name *</label>
                <input type="text" name="project_name" id="visit_project_name" required>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Client Name</label><input type="text" name="client_name" id="visit_client_name"></div>
                <div class="form-group"><label>Assigned To</label><input type="text" name="assigned_to" id="visit_assigned_to"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Visit Date</label><input type="date" name="visit_date" id="visit_date"></div>
                <div class="form-group"><label>Visit Time</label><input type="text" name="visit_time" id="visit_time" placeholder="e.g. 10:00 AM"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Location</label><input type="text" name="location" id="visit_location"></div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="visit_status">
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="visit_notes" rows="2" placeholder="Additional notes"></textarea>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> <span id="visitSubmitText">Save</span></button>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

function deleteItem(action, id) {
    if(confirm('Delete this item?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="'+action+'"><input type="hidden" name="id" value="'+id+'">';
        document.body.appendChild(form);
        form.submit();
    }
}

function filterTenders() {
    var input = document.getElementById('searchInput').value.toLowerCase();
    var status = document.getElementById('statusFilter').value;
    var rows = document.querySelectorAll('#tenderTable tbody tr');
    rows.forEach(row => {
        var text = row.textContent.toLowerCase();
        var rowStatus = row.querySelector('.status')?.textContent || '';
        var match = text.includes(input) && (status === '' || rowStatus === status);
        row.style.display = match ? '' : 'none';
    });
}

function filterTable(tableId, searchId) {
    var input = document.getElementById(searchId).value.toLowerCase();
    var rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
    });
}

// ===== UZ Edit Functions =====
function editUZ(id) {
    // Fetch data via AJAX or preload
    // For now, redirect or use preloaded data
    // You can implement AJAX fetch here
    document.getElementById('uzModalTitle').textContent = 'Edit UZ Submission';
    document.getElementById('uzAction').value = 'update_uz';
    document.getElementById('uzId').value = id;
    document.getElementById('uzSubmitText').textContent = 'Update';
    
    // Pre-fill with current data (you'll need to fetch via AJAX)
    // For demo, we'll just show the modal
    openModal('uzModal');
}

// ===== Reg Edit Functions =====
function editReg(id) {
    document.getElementById('regModalTitle').textContent = 'Edit Supplier Registration';
    document.getElementById('regAction').value = 'update_reg';
    document.getElementById('regId').value = id;
    document.getElementById('regSubmitText').textContent = 'Update';
    openModal('regModal');
}

// ===== Visit Edit Functions =====
function editVisit(id) {
    document.getElementById('visitModalTitle').textContent = 'Edit Site Visit';
    document.getElementById('visitAction').value = 'update_visit';
    document.getElementById('visitId').value = id;
    document.getElementById('visitSubmitText').textContent = 'Update';
    openModal('visitModal');
}

// Close modals on outside click
window.onclick = function(e) {
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        if (e.target === modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
}

// Reset modals when closed
function resetModal(id) {
    var form = document.querySelector('#' + id + ' form');
    if (form) form.reset();
    document.getElementById(id + 'Title').textContent = '';
    document.getElementById(id + 'Action').value = 'create_' + id.replace('Modal', '');
    document.getElementById(id + 'Id').value = '';
    document.getElementById(id + 'SubmitText').textContent = 'Save';
}
</script>
</body>
</html>