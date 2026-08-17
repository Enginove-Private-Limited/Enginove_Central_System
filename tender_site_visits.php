<?php
// tender_site_visits.php - Site Visits
require_once 'config/database.php';
requireLogin();

$pageTitle = "Site Visits";
$message = '';
$error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
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
        logActivity($_SESSION['user_id'], "Added site visit: " . $_POST['project_name']);
    }
    
    if ($action === 'delete_visit') {
        $stmt = $pdo->prepare("DELETE FROM site_visits WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Site Visit deleted!';
        logActivity($_SESSION['user_id'], "Deleted site visit ID: " . $_POST['id']);
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
        logActivity($_SESSION['user_id'], "Updated site visit ID: " . $_POST['id']);
    }
    
    // Mark as completed
    if ($action === 'complete_visit') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("
            UPDATE site_visits SET 
                status = 'Completed',
                notes = CONCAT(IFNULL(notes, ''), '\nCompleted on: ', NOW())
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $message = 'Site visit marked as completed!';
        logActivity($_SESSION['user_id'], "Marked site visit ID: $id as completed");
    }
}

$visits = $pdo->query("
    SELECT * FROM site_visits 
    ORDER BY visit_date ASC
")->fetchAll();

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN visit_date < CURDATE() AND status != 'Completed' THEN 1 ELSE 0 END) as overdue
    FROM site_visits
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
        .btn-success { background:#22c55e; color:white; }
        .btn-success:hover { background:#16a34a; }
        .btn-warning { background:#f59e0b; color:white; }
        .btn-warning:hover { background:#d97706; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 15px 18px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border-left: 4px solid var(--green);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .stat-card .value { font-size: 22px; font-weight: 700; color: var(--dark); margin-top: 2px; }
        .stat-card .value.orange { color: #b45309; }
        .stat-card .value.blue { color: #2563eb; }
        .stat-card .value.green { color: var(--green); }
        .stat-card .value.red { color: #dc2626; }
        .stat-card .value.gray { color: #64748b; }
        
        .actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .search-filter { display:flex; gap:15px; flex-wrap:wrap; }
        .search-filter input, .search-filter select { padding:10px 16px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; background:white; }
        .search-filter input:focus, .search-filter select:focus { outline:none; border-color:var(--green); }
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:700px; font-size:13px; }
        th { text-align:left; padding:12px 14px; background:var(--light-green); color:var(--dark); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        td { padding:12px 14px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
        tr:hover td { background:#fafdfb; }
        
        .status { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
        .status-scheduled { background:#dbeafe; color:#1d4ed8; }
        .status-completed { background:var(--light-green); color:var(--green); }
        .status-cancelled { background:#fee2e2; color:#b91c1c; }
        
        .overdue-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            background: #fee2e2;
            color: #dc2626;
            margin-left: 6px;
        }
        
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; color:#64748b; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; color:var(--dark); }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .form-row { grid-template-columns:1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; }
            .page-title { font-size:22px; }
            .actions { flex-direction:column; align-items:stretch; }
            .search-filter { flex-direction:column; }
            .search-filter input, .search-filter select { width:100%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .action-buttons { flex-wrap:wrap; }
        }
        @media(max-width:480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-map-marker-alt"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Track all site visits and their status.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">Total</div>
                    <div class="value"><?= $stats['total'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Scheduled</div>
                    <div class="value blue"><?= $stats['scheduled'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Completed</div>
                    <div class="value green"><?= $stats['completed'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Cancelled</div>
                    <div class="value orange"><?= $stats['cancelled'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Overdue</div>
                    <div class="value red"><?= $stats['overdue'] ?? 0 ?></div>
                </div>
            </div>
            
            <div class="actions">
                <button class="btn btn-green" onclick="openModal('visitModal')"><i class="fas fa-plus"></i> Add Site Visit</button>
                <div class="search-filter">
                    <input type="text" id="visitSearch" placeholder="Search..." onkeyup="filterTable()">
                    <select id="statusFilter" onchange="filterTable()">
                        <option value="">All Status</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <table id="visitTable">
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
                        <?php foreach ($visits as $visit): 
                            $is_overdue = strtotime($visit['visit_date']) < time() && $visit['status'] != 'Completed' && $visit['status'] != 'Cancelled';
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($visit['project_name']) ?></strong>
                                    <?php if ($is_overdue): ?>
                                        <span class="overdue-badge"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($visit['client_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['assigned_to'] ?? 'N/A') ?></td>
                                <td><?= date('d/m/Y', strtotime($visit['visit_date'])) ?></td>
                                <td><?= htmlspecialchars($visit['visit_time'] ?? '-') ?></td>
                                <td>
                                    <span class="status status-<?= strtolower($visit['status']) ?>">
                                        <?php if ($visit['status'] == 'Completed'): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php elseif ($visit['status'] == 'Scheduled'): ?>
                                            <i class="fas fa-clock"></i>
                                        <?php elseif ($visit['status'] == 'Cancelled'): ?>
                                            <i class="fas fa-times-circle"></i>
                                        <?php endif; ?>
                                        <?= $visit['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Mark as Completed -->
                                        <?php if ($visit['status'] != 'Completed' && $visit['status'] != 'Cancelled'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="complete_visit">
                                                <input type="hidden" name="id" value="<?= $visit['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Mark as Completed" onclick="return confirm('Mark this site visit as completed?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Edit -->
                                        <button class="btn btn-sm btn-edit" onclick="editVisit(<?= $visit['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        
                                        <!-- Delete -->
                                        <button onclick="deleteItem('delete_visit', <?= $visit['id'] ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($visits)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:#64748b;padding:40px;">
                                    <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;color:#d1d5db;"></i>
                                    No site visits found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Visit Modal -->
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

function editVisit(id) {
    // Fetch data via AJAX or preload
    // For now, we'll just show the modal with edit mode
    document.getElementById('visitModalTitle').textContent = 'Edit Site Visit';
    document.getElementById('visitAction').value = 'update_visit';
    document.getElementById('visitId').value = id;
    document.getElementById('visitSubmitText').textContent = 'Update';
    openModal('visitModal');
}

function filterTable() {
    var search = document.getElementById('visitSearch').value.toLowerCase();
    var status = document.getElementById('statusFilter').value;
    var rows = document.querySelectorAll('#visitTable tbody tr');
    
    rows.forEach(row => {
        var text = row.textContent.toLowerCase();
        var rowStatus = row.querySelector('.status')?.textContent || '';
        
        var match = text.includes(search) && (status === '' || rowStatus.includes(status));
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