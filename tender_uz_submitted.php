<?php
// tender_uz_submitted.php - UZ Submitted Tenders
require_once 'config/database.php';
requireLogin();

$pageTitle = "UZ Submitted Tenders";
$message = '';
$error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
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
}

// Get all UZ submissions with tender info
$uz_submissions = $pdo->query("
    SELECT uz.*, t.tender_number, t.tender_name
    FROM uz_submissions uz
    LEFT JOIN tenders t ON t.id = uz.tender_id
    ORDER BY uz.created_at DESC
")->fetchAll();

// Get stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'Awarded' THEN 1 ELSE 0 END) as awarded,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress
    FROM uz_submissions
")->fetch();

// Get tender list for dropdown
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
        .stat-card .value.purple { color: #7c3aed; }
        .stat-card .value.green { color: var(--green); }
        
        .actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .search-filter { display:flex; gap:15px; flex-wrap:wrap; }
        .search-filter input, .search-filter select { padding:10px 16px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; background:white; }
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:700px; font-size:13px; }
        th { text-align:left; padding:12px 14px; background:var(--light-green); color:var(--dark); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        td { padding:12px 14px; border-bottom:1px solid #f3f4f6; }
        tr:hover td { background:#fafdfb; }
        
        .status { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
        .status-pending { background:#fef3c7; color:#b45309; }
        .status-submitted { background:#dbeafe; color:#1d4ed8; }
        .status-in-progress { background:#fef3c7; color:#b45309; }
        .status-awarded { background:var(--light-green); color:var(--green); }
        .status-completed { background:var(--light-green); color:var(--green); }
        
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
            <h1 class="page-title"><i class="fas fa-university"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Track UZ submissions and their status.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="label">Total</div><div class="value"><?= $stats['total'] ?? 0 ?></div></div>
                <div class="stat-card"><div class="label">Pending</div><div class="value orange"><?= $stats['pending'] ?? 0 ?></div></div>
                <div class="stat-card"><div class="label">In Progress</div><div class="value blue"><?= $stats['in_progress'] ?? 0 ?></div></div>
                <div class="stat-card"><div class="label">Submitted</div><div class="value purple"><?= $stats['submitted'] ?? 0 ?></div></div>
                <div class="stat-card"><div class="label">Awarded</div><div class="value green"><?= $stats['awarded'] ?? 0 ?></div></div>
            </div>
            
            <div class="actions">
                <button class="btn btn-green" onclick="openModal('uzModal')"><i class="fas fa-plus"></i> Add UZ Submission</button>
                <div class="search-filter">
                    <input type="text" id="uzSearch" placeholder="Search..." onkeyup="filterTable()">
                    <select id="statusFilter" onchange="filterTable()">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Submitted">Submitted</option>
                        <option value="Awarded">Awarded</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <table id="uzTable">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Tender</th>
                            <th>Allocated To</th>
                            <th>Submitted By</th>
                            <th>PMU</th>
                            <th>Submitted On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uz_submissions as $uz): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($uz['project_name']) ?></strong></td>
                                <td><?= htmlspecialchars($uz['tender_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($uz['allocated_to'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($uz['submitted_by'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($uz['pmu_personnel'] ?? 'N/A') ?></td>
                                <td><?= $uz['submitted_on'] ? date('d/m/Y', strtotime($uz['submitted_on'])) : 'N/A' ?></td>
                                <td><span class="status status-<?= strtolower($uz['status']) ?>"><?= $uz['status'] ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-edit" onclick="editUZ(<?= $uz['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <button onclick="deleteItem('delete_uz', <?= $uz['id'] ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($uz_submissions)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#64748b;padding:40px;">
                                    <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;color:#d1d5db;"></i>
                                    No UZ submissions found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- UZ Modal -->
<div class="modal" id="uzModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('uzModal')">&times;</span>
        <h2><i class="fas fa-university"></i> <span id="uzModalTitle">Add UZ Submission</span></h2>
        <form method="POST">
            <input type="hidden" name="action" id="uzAction" value="create_uz">
            <input type="hidden" name="id" id="uzId" value="">
            <div class="form-group">
                <label>Project Name *</label>
                <input type="text" name="project_name" id="uz_project_name" required>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Allocated To</label><input type="text" name="allocated_to" id="uz_allocated_to"></div>
                <div class="form-group"><label>Submitted By</label><input type="text" name="submitted_by" id="uz_submitted_by"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>PMU Personnel</label><input type="text" name="pmu_personnel" id="uz_pmu_personnel"></div>
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
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="uz_status">
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Submitted">Submitted</option>
                    <option value="Awarded">Awarded</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" id="uz_remarks" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> <span id="uzSubmitText">Save</span></button>
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

function editUZ(id) {
    document.getElementById('uzModalTitle').textContent = 'Edit UZ Submission';
    document.getElementById('uzAction').value = 'update_uz';
    document.getElementById('uzId').value = id;
    document.getElementById('uzSubmitText').textContent = 'Update';
    openModal('uzModal');
}

function filterTable() {
    var search = document.getElementById('uzSearch').value.toLowerCase();
    var status = document.getElementById('statusFilter').value;
    var rows = document.querySelectorAll('#uzTable tbody tr');
    
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