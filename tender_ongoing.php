<?php
// tender_ongoing.php - Standalone Ongoing Tenders page
require_once 'config/database.php';
requireLogin();

$pageTitle = "Ongoing Tenders";
$message = '';
$error = '';

// Get all ongoing tenders (Open or Draft status) with direct checklist counts
$tenders = $pdo->query("
    SELECT t.*, 
           u.first_name, u.last_name,
           d.department_name,
           assigned.first_name as assigned_first, assigned.last_name as assigned_last,
           -- Direct count of checklist items (not from tenders table)
           (SELECT COUNT(*) FROM tender_checklist_items tci 
            JOIN tender_checklists tc ON tc.id = tci.checklist_id 
            WHERE tc.tender_id = t.id) as total_checklist_items,
           (SELECT COUNT(*) FROM tender_checklist_items tci 
            JOIN tender_checklists tc ON tc.id = tci.checklist_id 
            WHERE tc.tender_id = t.id AND tci.is_attached = 1) as attached_checklist_items
    FROM tenders t
    LEFT JOIN users u ON u.id = t.created_by
    LEFT JOIN departments d ON d.id = t.department_id
    LEFT JOIN users assigned ON assigned.id = t.assigned_to
    WHERE t.status IN ('Open', 'Draft')
    ORDER BY t.due_date ASC
")->fetchAll();

// Get stats including checklist status breakdown
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_ongoing,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
        SUM(CASE WHEN DATEDIFF(due_date, CURDATE()) <= 5 AND due_date >= CURDATE() THEN 1 ELSE 0 END) as expiring_soon_count,
        -- Checklist status breakdown
        SUM(CASE WHEN t.checklist_status = 'Not Started' THEN 1 ELSE 0 END) as checklist_not_started,
        SUM(CASE WHEN t.checklist_status = 'In Progress' THEN 1 ELSE 0 END) as checklist_in_progress,
        SUM(CASE WHEN t.checklist_status = 'Complete' THEN 1 ELSE 0 END) as checklist_complete,
        SUM(CASE WHEN t.checklist_status = 'Ready for Review' THEN 1 ELSE 0 END) as checklist_ready_review,
        SUM(CASE WHEN t.checklist_status = 'N/A' OR t.checklist_status IS NULL THEN 1 ELSE 0 END) as checklist_no_checklist
    FROM tenders t
    WHERE t.status IN ('Open', 'Draft')
")->fetch();

// Function to get checklist items for a tender
function getChecklistItems($pdo, $tender_id) {
    $stmt = $pdo->prepare("
        SELECT tci.*, tc.checklist_number, tc.status as checklist_status
        FROM tender_checklist_items tci
        JOIN tender_checklists tc ON tc.id = tci.checklist_id
        WHERE tc.tender_id = ?
        ORDER BY tci.order_number, tci.item_number
    ");
    $stmt->execute([$tender_id]);
    return $stmt->fetchAll();
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
        .page-title { font-size:28px; font-weight:700; color:var(--dark); margin-bottom:8px; }
        .subtitle { color:#64748b; margin-bottom:30px; }
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-sm { padding:6px 14px; font-size:13px; }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-edit { background:#e5e7eb; color:#1e2a2f; }
        .btn-edit:hover { background:#d1d5db; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
        .stat-card .label { 
            font-size: 11px; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            font-weight: 600;
        }
        .stat-card .value { 
            font-size: 22px; 
            font-weight: 700; 
            color: var(--dark); 
            margin-top: 2px; 
        }
        .stat-card .value.green { color: var(--green); }
        .stat-card .value.orange { color: #b45309; }
        .stat-card .value.red { color: #dc2626; }
        .stat-card .value.blue { color: #2563eb; }
        .stat-card .value.purple { color: #7c3aed; }
        .stat-card .value.gray { color: #64748b; }
        
        .stat-card.checklist-not-started { border-left-color: #dc2626; }
        .stat-card.checklist-in-progress { border-left-color: #f59e0b; }
        .stat-card.checklist-complete { border-left-color: var(--green); }
        .stat-card.checklist-ready { border-left-color: #2563eb; }
        .stat-card.checklist-none { border-left-color: #94a3b8; }
        
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
        .status-open { background:var(--light-green); color:var(--green); }
        .status-draft { background:#e5e7eb; color:#6b7280; }
        
        /* Checklist Status Indicators */
        .checklist-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }
        .checklist-status.not-started { background:#fee2e2; color:#b91c1c; }
        .checklist-status.in-progress { background:#fef3c7; color:#b45309; }
        .checklist-status.ready-for-review { background:#dbeafe; color:#1d4ed8; }
        .checklist-status.complete { background:var(--light-green); color:var(--green); }
        .checklist-status.na { background:#e5e7eb; color:#6b7280; }
        
        .checklist-progress {
            width: 80px;
            height: 6px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            display: inline-block;
        }
        .checklist-progress .fill {
            height: 100%;
            background: var(--green);
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .expiring-soon { color: #dc2626; font-weight:600; }
        .expiring-warning { color: #b45309; font-weight:600; }
        
        /* Checklist Items Popup */
        .checklist-popup {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        .checklist-popup .popup-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 280px;
            max-width: 350px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 15px;
            z-index: 100;
            left: 0;
            top: 100%;
            margin-top: 8px;
            border: 1px solid #e5e7eb;
            max-height: 300px;
            overflow-y: auto;
        }
        .checklist-popup:hover .popup-content {
            display: block;
        }
        .checklist-popup .popup-content .item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 12px;
        }
        .checklist-popup .popup-content .item:last-child {
            border-bottom: none;
        }
        .checklist-popup .popup-content .item .status-icon {
            font-size: 14px;
        }
        .checklist-popup .popup-content .item .status-icon.attached { color: var(--green); }
        .checklist-popup .popup-content .item .status-icon.missing { color: #dc2626; }
        .checklist-popup .popup-content .item .item-text {
            flex: 1;
        }
        .checklist-popup .popup-content .item .required-badge {
            color: #dc2626;
            font-size: 10px;
            margin-left: 4px;
        }
        .checklist-popup .popup-content .popup-header {
            font-weight: 600;
            font-size: 13px;
            color: var(--dark);
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        .checklist-popup .popup-content .popup-header .count {
            font-weight: 400;
            color: #64748b;
            font-size: 12px;
        }
        .checklist-popup .popup-content .no-items {
            color: #94a3b8;
            font-size: 13px;
            text-align: center;
            padding: 15px 0;
        }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media(max-width:768px) { 
            .content { padding:15px; }
            .page-title { font-size:22px; }
            .actions { flex-direction:column; align-items:stretch; }
            .search-filter { flex-direction:column; }
            .search-filter input, .search-filter select { width:100%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width:480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .checklist-popup .popup-content {
                min-width: 200px;
                max-width: 280px;
                left: -50px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-clock"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Track all ongoing tenders and their checklist status.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">Total Ongoing</div>
                    <div class="value"><?= $stats['total_ongoing'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Open</div>
                    <div class="value green"><?= $stats['open_count'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Draft</div>
                    <div class="value blue"><?= $stats['draft_count'] ?? 0 ?></div>
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
            
            <!-- Checklist Status Stats -->
            <div class="stats-grid" style="margin-bottom:25px;">
                <div class="stat-card checklist-not-started">
                    <div class="label"><i class="fas fa-circle" style="color:#dc2626;"></i> Not Started</div>
                    <div class="value red"><?= $stats['checklist_not_started'] ?? 0 ?></div>
                </div>
                <div class="stat-card checklist-in-progress">
                    <div class="label"><i class="fas fa-circle" style="color:#f59e0b;"></i> In Progress</div>
                    <div class="value orange"><?= $stats['checklist_in_progress'] ?? 0 ?></div>
                </div>
                <div class="stat-card checklist-ready">
                    <div class="label"><i class="fas fa-circle" style="color:#2563eb;"></i> Ready for Review</div>
                    <div class="value blue"><?= $stats['checklist_ready_review'] ?? 0 ?></div>
                </div>
                <div class="stat-card checklist-complete">
                    <div class="label"><i class="fas fa-circle" style="color:var(--green);"></i> Complete</div>
                    <div class="value green"><?= $stats['checklist_complete'] ?? 0 ?></div>
                </div>
                <div class="stat-card checklist-none">
                    <div class="label"><i class="fas fa-circle" style="color:#94a3b8;"></i> No Checklist</div>
                    <div class="value gray"><?= $stats['checklist_no_checklist'] ?? 0 ?></div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="actions">
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="tender_submitted.php" class="btn btn-outline btn-sm">Submitted</a>
                    <a href="tender_uz_submitted.php" class="btn btn-outline btn-sm">UZ Submitted</a>
                    <a href="tender_supplier_reg.php" class="btn btn-outline btn-sm">Supplier Reg</a>
                    <a href="tender_site_visits.php" class="btn btn-outline btn-sm">Site Visits</a>
                    <a href="#" onclick="openModal('tenderModal'); return false;" class="btn btn-green btn-sm"><i class="fas fa-plus"></i> New Tender</a>
                </div>
                <div class="search-filter">
                    <input type="text" id="searchInput" placeholder="Search tenders..." onkeyup="filterTable()">
                    <select id="statusFilter" onchange="filterTable()">
                        <option value="">All Status</option>
                        <option value="Open">Open</option>
                        <option value="Draft">Draft</option>
                    </select>
                    <select id="checklistFilter" onchange="filterTable()">
                        <option value="">All Checklist Status</option>
                        <option value="Not Started">Not Started</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Ready for Review">Ready for Review</option>
                        <option value="Complete">Complete</option>
                    </select>
                </div>
            </div>
            
            <!-- Table -->
            <div class="table-container">
                <table id="ongoingTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Closing Date</th>
                            <th>Status</th>
                            <th>Checklist</th>
                            <th>Procurement Manager</th>
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
                            
                            // Calculate checklist progress
                            $total_items = $tender['total_checklist_items'] ?? 0;
                            $attached_items = $tender['attached_checklist_items'] ?? 0;
                            $progress = $total_items > 0 ? round(($attached_items / $total_items) * 100) : 0;
                            
                            // Determine checklist status
                            $checklist_status = $tender['checklist_status'] ?? 'Not Started';
                            $status_class = strtolower(str_replace(' ', '-', $checklist_status));
                            
                            // Get checklist items for popup
                            $checklist_items = getChecklistItems($pdo, $tender['id']);
                        ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($tender['issue_date'] ?? $tender['created_at'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($tender['tender_name']) ?></strong>
                                    <?php if ($days_left < 0): ?>
                                        <span style="color:#dc2626;font-size:10px;display:block;">Overdue by <?= abs(round($days_left)) ?> days</span>
                                    <?php elseif ($days_left <= 5 && $days_left >= 0): ?>
                                        <span style="color:#b45309;font-size:10px;display:block;"><?= round($days_left) ?> days left</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($tender['client_name'] ?? 'N/A') ?></td>
                                <td class="<?= $due_class ?>"><?= date('d/m/Y', strtotime($tender['due_date'])) ?></td>
                                <td><span class="status status-<?= strtolower($tender['status']) ?>"><?= $tender['status'] ?></span></td>
                                <td>
                                    <?php if ($total_items > 0): ?>
                                        <div style="display:flex; flex-direction:column; gap:4px; min-width:120px;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span class="checklist-status <?= $status_class ?>">
                                                    <i class="fas fa-<?= $checklist_status == 'Complete' ? 'check-circle' : ($checklist_status == 'Not Started' ? 'circle' : ($checklist_status == 'Ready for Review' ? 'flag' : 'spinner')) ?>"></i>
                                                    <?= $checklist_status ?>
                                                </span>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#64748b;">
                                                <span><?= $attached_items ?> / <?= $total_items ?> attached</span>
                                                <div class="checklist-progress">
                                                    <div class="fill" style="width: <?= $progress ?>%;"></div>
                                                </div>
                                                <span><?= $progress ?>%</span>
                                            </div>
                                            <!-- Checklist Items Popup -->
                                            <?php if (!empty($checklist_items)): ?>
                                            <div class="checklist-popup">
                                                <span style="font-size:11px;color:#2563eb;cursor:pointer;text-decoration:underline dotted;">
                                                    <i class="fas fa-list"></i> View items (<?= $total_items ?>)
                                                </span>
                                                <div class="popup-content">
                                                    <div class="popup-header">
                                                        <span>Checklist Items</span>
                                                        <span class="count"><?= $attached_items ?>/<?= $total_items ?></span>
                                                    </div>
                                                    <?php foreach ($checklist_items as $item): ?>
                                                        <div class="item">
                                                            <span class="status-icon <?= $item['is_attached'] ? 'attached' : 'missing' ?>">
                                                                <i class="fas fa-<?= $item['is_attached'] ? 'check-circle' : 'circle' ?>"></i>
                                                            </span>
                                                            <span class="item-text">
                                                                <?= htmlspecialchars($item['item_description']) ?>
                                                                <?php if ($item['is_required']): ?>
                                                                    <span class="required-badge">*</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:12px;">No checklist</span>
                                        <a href="tender_checklist.php?tender_id=<?= $tender['id'] ?>" class="btn btn-sm" style="background:#f59e0b;color:white;padding:2px 10px;font-size:10px;margin-top:4px;display:inline-block;">
                                            <i class="fas fa-plus"></i> Add
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(($tender['assigned_first'] ?? '') . ' ' . ($tender['assigned_last'] ?? '')) ?: 'Unassigned' ?></td>
                                <td>
                                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                                        <a href="tender_view.php?id=<?= $tender['id'] ?>" class="btn btn-sm btn-green"><i class="fas fa-eye"></i></a>
                                        <a href="tender_checklist.php?tender_id=<?= $tender['id'] ?>" class="btn btn-sm" style="background:#f59e0b;color:white;" title="Checklist">
                                            <i class="fas fa-clipboard-check"></i>
                                        </a>
                                        <a href="tender_edit.php?id=<?= $tender['id'] ?>" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tenders)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#64748b;padding:40px;">
                                    <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;color:#d1d5db;"></i>
                                    No ongoing tenders found.
                                    <br><br>
                                    <a href="#" onclick="openModal('tenderModal'); return false;" class="btn btn-green btn-sm">Create New Tender</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tender Modal -->

<script>
function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

function filterTable() {
    var search = document.getElementById('searchInput').value.toLowerCase();
    var status = document.getElementById('statusFilter').value;
    var checklist = document.getElementById('checklistFilter').value;
    var rows = document.querySelectorAll('#ongoingTable tbody tr');
    
    rows.forEach(row => {
        var text = row.textContent.toLowerCase();
        var rowStatus = row.querySelector('.status')?.textContent || '';
        var rowChecklist = row.querySelector('.checklist-status')?.textContent || '';
        
        var match = text.includes(search) && 
                    (status === '' || rowStatus === status) &&
                    (checklist === '' || rowChecklist.includes(checklist));
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