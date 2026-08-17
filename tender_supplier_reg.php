<?php
// tender_supplier_reg.php - Supplier Registrations
require_once 'config/database.php';
requireLogin();

$pageTitle = "Supplier Registrations";
$message = '';
$error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
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
}

$registrations = $pdo->query("SELECT * FROM supplier_registrations ORDER BY created_at DESC")->fetchAll();

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM supplier_registrations
")->fetch();
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
        .page-title { font-size:28px; font-weight:700; color:var(--dark); margin-bottom:8px; }
        .subtitle { color:#64748b; margin-bottom:30px; }
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-sm { padding:6px 14px; font-size:13px; }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-edit { background:#e5e7eb; color:#1e2a2f; }
        .btn-edit:hover { background:#d1d5db; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border-left: 4px solid var(--green);
        }
        .stat-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .value { font-size: 24px; font-weight: 700; color: var(--dark); margin-top: 2px; }
        .stat-card .value.orange { color: #b45309; }
        .stat-card .value.blue { color: #2563eb; }
        .stat-card .value.green { color: var(--green); }
        .stat-card .value.red { color: #dc2626; }
        
        .actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .search-filter { display:flex; gap:15px; flex-wrap:wrap; }
        .search-filter input, .search-filter select { padding:10px 16px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; background:white; }
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:700px; font-size:13px; }
        th { text-align:left; padding:12px 14px; background:var(--light-green); color:var(--dark); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        td { padding:12px 14px; border-bottom:1px solid #f3f4f6; }
        tr:hover td { background:#fafdfb; }
        
        .status { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
        .status-in-progress { background:#fef3c7; color:#b45309; }
        .status-submitted { background:#dbeafe; color:#1d4ed8; }
        .status-approved { background:var(--light-green); color:var(--green); }
        .status-rejected { background:#fee2e2; color:#b91c1c; }
        .status-pending { background:#fef3c7; color:#b45309; }
        
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; color:#64748b; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; color:var(--dark); }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-row { grid-template-columns:1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; }
            .page-title { font-size:22px; }
            .actions { flex-direction:column; align-items:stretch; }
            .search-filter { flex-direction:column; }
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
            <h1 class="page-title"><i class="fas fa-user-plus"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Track supplier registrations and their status.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="label">Total</div><div class="value"><?= $stats['total'] ?? 0 ?></div></div>
                <div class="stat-card"><div class="label">In Progress</div><div class="value orange"><?= $stats['in_progress'] ?? 0 ?></div></div>
                <div class="stat-card"><div class="label">Submitted</div><div class="value blue"><?= $stats['submitted'] ?? 0 ?></div></div>
                <div class="stat-card"><div class="label">Approved</div><div class="value green"><?= $stats['approved'] ?? 0 ?></div></div>
                <div class="stat-card"><div class="label">Rejected</div><div class="value red"><?= $stats['rejected'] ?? 0 ?></div></div>
            </div>
            
            <div class="actions">
                <button class="btn btn-green" onclick="openModal('regModal')"><i class="fas fa-plus"></i> Add Registration</button>
                <div class="search-filter">
                    <input type="text" id="regSearch" placeholder="Search..." onkeyup="filterTable()">
                    <select id="statusFilter" onchange="filterTable()">
                        <option value="">All Status</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Submitted">Submitted</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
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
                        <?php foreach ($registrations as $reg): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($reg['organization_name']) ?></strong></td>
                                <td><?= htmlspecialchars($reg['contact_person'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($reg['assigned_to'] ?? 'N/A') ?></td>
                                <td><?= $reg['due_date'] ? date('d/m/Y', strtotime($reg['due_date'])) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($reg['source'] ?? 'N/A') ?></td>
                                <td><span class="status status-<?= strtolower($reg['status']) ?>"><?= $reg['status'] ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-edit" onclick="editReg(<?= $reg['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <button onclick="deleteItem('delete_reg', <?= $reg['id'] ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:#64748b;padding:40px;">
                                    <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;color:#d1d5db;"></i>
                                    No supplier registrations found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Registration Modal -->
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
                <div class="form-group"><label>Source</label><input type="text" name="source" id="reg_source"></div>
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
                <textarea name="notes" id="reg_notes" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> <span id="regSubmitText">Save</span></button>
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

function editReg(id) {
    document.getElementById('regModalTitle').textContent = 'Edit Supplier Registration';
    document.getElementById('regAction').value = 'update_reg';
    document.getElementById('regId').value = id;
    document.getElementById('regSubmitText').textContent = 'Update';
    openModal('regModal');
}

function filterTable() {
    var search = document.getElementById('regSearch').value.toLowerCase();
    var status = document.getElementById('statusFilter').value;
    var rows = document.querySelectorAll('#regTable tbody tr');
    
    rows.forEach(row => {
        var text = row.textContent.toLowerCase();
        var rowStatus = row.querySelector('.status')?.textContent || '';
        var match = text.includes(search) && (status === '' || rowStatus === status);
        row.style.display = match ? '' : 'none';
    });
}

window.onclick = function(e) {
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        if (e.target === modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
}
</script>
</body>
</html>