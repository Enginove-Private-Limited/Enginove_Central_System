<?php
// tender_submitted.php - Submitted Tenders
require_once 'config/database.php';
requireLogin();

$pageTitle = "Submitted Tenders";
$message = '';
$error = '';

// Get all submitted tenders
$tenders = $pdo->query("
    SELECT t.*, 
           u.first_name, u.last_name,
           d.department_name,
           assigned.first_name as assigned_first, assigned.last_name as assigned_last,
           (SELECT COUNT(*) FROM tender_checklists WHERE tender_id = t.id) as total_checklist_items,
           (SELECT COUNT(*) FROM tender_checklist_items tci 
            JOIN tender_checklists tc ON tc.id = tci.checklist_id 
            WHERE tc.tender_id = t.id AND tci.is_attached = 1) as attached_checklist_items
    FROM tenders t
    LEFT JOIN users u ON u.id = t.created_by
    LEFT JOIN departments d ON d.id = t.department_id
    LEFT JOIN users assigned ON assigned.id = t.assigned_to
    WHERE t.status = 'Submitted'
    ORDER BY t.due_date ASC
")->fetchAll();

// Get stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_submitted,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted_count,
        SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
        SUM(CASE WHEN DATEDIFF(due_date, CURDATE()) <= 5 AND due_date >= CURDATE() THEN 1 ELSE 0 END) as expiring_soon_count
    FROM tenders
    WHERE status = 'Submitted'
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
        .btn-warning { background:#f59e0b; color:white; }
        .btn-warning:hover { background:#d97706; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        .stat-card .value.red { color: #dc2626; }
        .stat-card .value.blue { color: #2563eb; }
        
        .actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .search-filter { display:flex; gap:15px; flex-wrap:wrap; }
        .search-filter input, .search-filter select { padding:10px 16px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; background:white; }
        .search-filter input:focus, .search-filter select:focus { outline:none; border-color:var(--green); }
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:800px; font-size:13px; }
        th { text-align:left; padding:12px 14px; background:var(--light-green); color:var(--dark); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        td { padding:12px 14px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
        tr:hover td { background:#fafdfb; }
        
        .status { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
        .status-submitted { background:#fef3c7; color:#b45309; }
        .status-awarded { background:#dbeafe; color:#1d4ed8; }
        .status-lost { background:#fee2e2; color:#b91c1c; }
        
        .checklist-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .checklist-status.complete { background:var(--light-green); color:var(--green); }
        .checklist-status.in-progress { background:#fef3c7; color:#b45309; }
        .checklist-status.not-started { background:#fee2e2; color:#b91c1c; }
        
        .expiring-soon { color: #dc2626; font-weight:600; }
        .expiring-warning { color: #b45309; font-weight:600; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width:768px) { 
            .content { padding:15px; }
            .page-title { font-size:22px; }
            .actions { flex-direction:column; align-items:stretch; }
            .search-filter { flex-direction:column; }
            .search-filter input, .search-filter select { width:100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
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
            <h1 class="page-title"><i class="fas fa-paper-plane"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Track all submitted tenders and their status.</p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">Total Submitted</div>
                    <div class="value"><?= $stats['total_submitted'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Overdue</div>
                    <div class="value red"><?= $stats['overdue_count'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Expiring Soon</div>
                    <div class="value orange"><?= $stats['expiring_soon_count'] ?? 0 ?></div>
                </div>
            </div>
            
            <div class="actions">
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="tender_ongoing.php" class="btn btn-outline btn-sm">Ongoing</a>
                    <a href="tender_uz_submitted.php" class="btn btn-outline btn-sm">UZ Submitted</a>
                    <a href="tender_supplier_reg.php" class="btn btn-outline btn-sm">Supplier Reg</a>
                    <a href="tender_site_visits.php" class="btn btn-outline btn-sm">Site Visits</a>
                </div>
                <div class="search-filter">
                    <input type="text" id="searchInput" placeholder="Search..." onkeyup="filterTable()">
                    <select id="statusFilter" onchange="filterTable()">
                        <option value="">All Status</option>
                        <option value="Submitted">Submitted</option>
                        <option value="Awarded">Awarded</option>
                        <option value="Lost">Lost</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <table id="submittedTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tender</th>
                            <th>Client</th>
                            <th>Submitted Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Checklist</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenders as $tender): 
                            $days_left = (strtotime($tender['due_date']) - time()) / 86400;
                            $due_class = '';
                            if ($days_left < 0) {
                                $due_class = 'expiring-soon';
                            } elseif ($days_left <= 5) {
                                $due_class = 'expiring-warning';
                            }
                            
                            $total_items = $tender['total_checklist_items'] ?? 0;
                            $attached_items = $tender['attached_checklist_items'] ?? 0;
                            $checklist_status = $total_items > 0 ? ($attached_items == $total_items ? 'Complete' : 'In Progress') : 'Not Started';
                            $status_class = strtolower($checklist_status);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tender['tender_number']) ?></strong></td>
                                <td><?= htmlspecialchars($tender['tender_name']) ?></td>
                                <td><?= htmlspecialchars($tender['client_name'] ?? 'N/A') ?></td>
                                <td><?= $tender['submission_date'] ? date('d/m/Y', strtotime($tender['submission_date'])) : 'N/A' ?></td>
                                <td class="<?= $due_class ?>"><?= date('d/m/Y', strtotime($tender['due_date'])) ?></td>
                                <td><span class="status status-<?= strtolower($tender['status']) ?>"><?= $tender['status'] ?></span></td>
                                <td>
                                    <?php if ($total_items > 0): ?>
                                        <span class="checklist-status <?= $status_class ?>">
                                            <i class="fas fa-<?= $checklist_status == 'Complete' ? 'check-circle' : 'circle' ?>"></i>
                                            <?= $checklist_status ?> (<?= $attached_items ?>/<?= $total_items ?>)
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;font-size:12px;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                                        <a href="tender_view.php?id=<?= $tender['id'] ?>" class="btn btn-sm btn-green"><i class="fas fa-eye"></i></a>
                                        <a href="tender_checklist.php?tender_id=<?= $tender['id'] ?>" class="btn btn-sm btn-warning" title="Checklist"><i class="fas fa-clipboard-check"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tenders)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#64748b;padding:40px;">
                                    <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;color:#d1d5db;"></i>
                                    No submitted tenders found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function filterTable() {
    var search = document.getElementById('searchInput').value.toLowerCase();
    var status = document.getElementById('statusFilter').value;
    var rows = document.querySelectorAll('#submittedTable tbody tr');
    
    rows.forEach(row => {
        var text = row.textContent.toLowerCase();
        var rowStatus = row.querySelector('.status')?.textContent || '';
        
        var match = text.includes(search) && (status === '' || rowStatus === status);
        row.style.display = match ? '' : 'none';
    });
}
</script>
</body>
</html>