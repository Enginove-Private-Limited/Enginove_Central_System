<?php
// tender_view.php - View detailed tender information
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
           u.first_name, u.last_name,
           d.department_name,
           assigned.first_name as assigned_first, assigned.last_name as assigned_last,
           creator.first_name as creator_first, creator.last_name as creator_last,
           (SELECT COUNT(*) FROM tender_checklists WHERE tender_id = t.id) as total_checklist_items,
           (SELECT COUNT(*) FROM tender_checklist_items tci 
            JOIN tender_checklists tc ON tc.id = tci.checklist_id 
            WHERE tc.tender_id = t.id AND tci.is_attached = 1) as attached_checklist_items
    FROM tenders t
    LEFT JOIN users u ON u.id = t.created_by
    LEFT JOIN departments d ON d.id = t.department_id
    LEFT JOIN users assigned ON assigned.id = t.assigned_to
    LEFT JOIN users creator ON creator.id = t.created_by
    WHERE t.id = ?
");
$stmt->execute([$id]);
$tender = $stmt->fetch();

if (!$tender) {
    header('Location: tender_ongoing.php');
    exit();
}

// Get tracking history
$tracking = $pdo->prepare("
    SELECT * FROM tender_tracking 
    WHERE tender_id = ? 
    ORDER BY updated_at DESC
");
$tracking->execute([$id]);
$tracking = $tracking->fetchAll();

// Get UZ submission if exists
$uz = $pdo->prepare("
    SELECT * FROM uz_submissions 
    WHERE tender_id = ? OR project_name LIKE CONCAT('%', ?, '%')
");
$uz->execute([$id, $tender['tender_name']]);
$uz = $uz->fetch();

// Get site visits related to this tender
$visits = $pdo->prepare("
    SELECT * FROM site_visits 
    WHERE project_name LIKE CONCAT('%', ?, '%')
    ORDER BY visit_date DESC
");
$visits->execute([$tender['tender_name']]);
$visits = $visits->fetchAll();

// Get checklist if exists
$checklist = $pdo->prepare("
    SELECT tc.*, 
           CONCAT(u.first_name, ' ', u.last_name) as prepared_by_name,
           CONCAT(r.first_name, ' ', r.last_name) as reviewed_by_name,
           CONCAT(a.first_name, ' ', a.last_name) as approved_by_name
    FROM tender_checklists tc
    LEFT JOIN users u ON u.id = tc.prepared_by
    LEFT JOIN users r ON r.id = tc.reviewed_by
    LEFT JOIN users a ON a.id = tc.approved_by
    WHERE tc.tender_id = ?
    ORDER BY tc.created_at DESC LIMIT 1
");
$checklist->execute([$id]);
$checklist = $checklist->fetch();

// Get checklist items if checklist exists
$checklist_items = [];
if ($checklist) {
    $stmt = $pdo->prepare("
        SELECT * FROM tender_checklist_items 
        WHERE checklist_id = ? 
        ORDER BY order_number, item_number
    ");
    $stmt->execute([$checklist['id']]);
    $checklist_items = $stmt->fetchAll();
}

$pageTitle = "Tender: " . $tender['tender_number'];
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
            margin-bottom: 25px;
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
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-warning { background:#f59e0b; color:white; }
        .btn-warning:hover { background:#d97706; }
        .btn-sm { padding:4px 12px; font-size:12px; }
        
        .status { padding:4px 16px; border-radius:20px; font-size:13px; font-weight:600; display:inline-block; }
        .status-draft { background:#e5e7eb; color:#6b7280; }
        .status-open { background:var(--light-green); color:var(--green); }
        .status-submitted { background:#fef3c7; color:#b45309; }
        .status-awarded { background:#dbeafe; color:#1d4ed8; }
        .status-lost { background:#fee2e2; color:#b91c1c; }
        .status-cancelled { background:#f3f4f6; color:#6b7280; }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }
        .detail-card h3 {
            font-size: 16px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-card .row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .detail-card .row:last-child { border-bottom: none; }
        .detail-card .label { color: #64748b; font-size: 14px; }
        .detail-card .value { font-weight: 500; color: var(--dark); font-size: 14px; }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .tracking-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .tracking-item {
            padding: 12px 15px;
            border-left: 3px solid var(--green);
            margin-bottom: 10px;
            background: #f8fafc;
            border-radius: 0 8px 8px 0;
        }
        .tracking-item .date { font-size: 12px; color: #64748b; }
        .tracking-item .status { font-size: 12px; }
        .tracking-item .remarks { font-size: 13px; color: var(--dark); margin-top: 4px; }
        
        .actions-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        
        /* Checklist Progress */
        .checklist-progress-container {
            margin-top: 10px;
        }
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
        
        .checklist-item-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .checklist-item-row:last-child { border-bottom: none; }
        .checklist-item-row .check-icon {
            width: 20px;
            text-align: center;
        }
        .checklist-item-row .check-icon.attached { color: var(--green); }
        .checklist-item-row .check-icon.missing { color: #dc2626; }
        .checklist-item-row .item-desc { flex: 1; font-size: 13px; }
        .checklist-item-row .item-desc.required { font-weight: 500; }
        .checklist-item-row .item-desc .required-badge { 
            color: #dc2626; 
            font-size: 10px; 
            margin-left: 4px;
        }
        
        .checklist-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .checklist-status-badge.not-started { background:#fee2e2; color:#b91c1c; }
        .checklist-status-badge.in-progress { background:#fef3c7; color:#b45309; }
        .checklist-status-badge.ready-for-review { background:#dbeafe; color:#1d4ed8; }
        .checklist-status-badge.complete { background:var(--light-green); color:var(--green); }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .detail-grid { grid-template-columns: 1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-header .page-title { font-size:20px; }
            .detail-card { padding:18px; }
        }
        @media(max-width:480px) {
            .page-header { flex-direction:column; align-items:flex-start; gap:10px; }
            .actions-bar { flex-direction:column; }
            .actions-bar .btn { justify-content:center; }
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
                <div>
                    <h1 class="page-title"><i class="fas fa-file-signature"></i> <?= htmlspecialchars($tender['tender_number']) ?></h1>
                    <p class="subtitle"><?= htmlspecialchars($tender['tender_name']) ?></p>
                </div>
                <div>
                    <span class="status status-<?= strtolower($tender['status']) ?>"><?= $tender['status'] ?></span>
                </div>
            </div>
            
            <div class="actions-bar">
                <a href="tender_edit.php?id=<?= $id ?>" class="btn btn-green"><i class="fas fa-edit"></i> Edit Tender</a>
                <a href="tender_checklist.php?tender_id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-clipboard-check"></i> Checklist</a>
                <a href="tender_ongoing.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Tenders</a>
                <button onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print</button>
            </div>
            
            <div class="detail-grid">
                <!-- Basic Information -->
                <div class="detail-card">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    <div class="row"><span class="label">Tender Number</span><span class="value"><?= htmlspecialchars($tender['tender_number']) ?></span></div>
                    <div class="row"><span class="label">Tender Name</span><span class="value"><?= htmlspecialchars($tender['tender_name']) ?></span></div>
                    <div class="row"><span class="label">Department</span><span class="value"><?= htmlspecialchars($tender['department_name'] ?? 'N/A') ?></span></div>
                    <div class="row"><span class="label">Status</span><span class="value"><span class="status status-<?= strtolower($tender['status']) ?>"><?= $tender['status'] ?></span></span></div>
                    <div class="row"><span class="label">Created By</span><span class="value"><?= htmlspecialchars(($tender['creator_first'] ?? '') . ' ' . ($tender['creator_last'] ?? '')) ?: 'Unknown' ?></span></div>
                    <div class="row"><span class="label">Created At</span><span class="value"><?= date('d M Y H:i', strtotime($tender['created_at'])) ?></span></div>
                </div>
                
                <!-- Client Information -->
                <div class="detail-card">
                    <h3><i class="fas fa-building"></i> Client Information</h3>
                    <div class="row"><span class="label">Client Name</span><span class="value"><?= htmlspecialchars($tender['client_name'] ?? 'N/A') ?></span></div>
                    <div class="row"><span class="label">Client Contact</span><span class="value"><?= htmlspecialchars($tender['client_contact'] ?? 'N/A') ?></span></div>
                    <div class="row"><span class="label">Assigned To</span><span class="value"><?= htmlspecialchars(($tender['assigned_first'] ?? '') . ' ' . ($tender['assigned_last'] ?? '')) ?: 'Unassigned' ?></span></div>
                    <div class="row"><span class="label">Budget Amount</span><span class="value">$<?= number_format($tender['budget_amount'] ?? 0, 2) ?></span></div>
                    <div class="row"><span class="label">Validity Period</span><span class="value"><?= $tender['validity_period'] ?> days</span></div>
                </div>
                
                <!-- Dates -->
                <div class="detail-card">
                    <h3><i class="fas fa-calendar-alt"></i> Key Dates</h3>
                    <div class="row"><span class="label">Issue Date</span><span class="value"><?= date('d M Y', strtotime($tender['issue_date'])) ?></span></div>
                    <div class="row"><span class="label">Due Date</span><span class="value"><?= date('d M Y', strtotime($tender['due_date'])) ?></span></div>
                    <div class="row"><span class="label">Days Remaining</span>
                        <span class="value">
                            <?php
                            $days_left = (strtotime($tender['due_date']) - time()) / 86400;
                            if ($days_left > 0) {
                                echo round($days_left) . ' days';
                            } elseif ($days_left == 0) {
                                echo 'Today!';
                            } else {
                                echo '<span style="color:#dc2626;">Overdue by ' . abs(round($days_left)) . ' days</span>';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="row"><span class="label">Submission Date</span><span class="value"><?= $tender['submission_date'] ? date('d M Y', strtotime($tender['submission_date'])) : 'Not submitted' ?></span></div>
                </div>
                
                <!-- Checklist Status -->
                <div class="detail-card">
                    <h3><i class="fas fa-clipboard-check"></i> Checklist Status</h3>
                    <?php
                    $total_items = $tender['total_checklist_items'] ?? 0;
                    $attached_items = $tender['attached_checklist_items'] ?? 0;
                    $progress = $total_items > 0 ? round(($attached_items / $total_items) * 100) : 0;
                    $status_class = strtolower(str_replace(' ', '-', $tender['checklist_status'] ?? 'Not Started'));
                    ?>
                    <div class="row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="checklist-status-badge <?= $status_class ?>">
                                <i class="fas fa-<?= $tender['checklist_status'] == 'Complete' ? 'check-circle' : ($tender['checklist_status'] == 'Not Started' ? 'circle' : ($tender['checklist_status'] == 'Ready for Review' ? 'flag' : 'spinner')) ?>"></i>
                                <?= $tender['checklist_status'] ?? 'Not Started' ?>
                            </span>
                        </span>
                    </div>
                    <div class="row">
                        <span class="label">Progress</span>
                        <span class="value">
                            <?php if ($total_items > 0): ?>
                                <?= $attached_items ?>  documents attached
                            <?php else: ?>
                                No checklist created
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($total_items > 0): ?>
                    <div class="checklist-progress-container">
                        <div class="checklist-progress-bar">
                            <div class="fill <?= $progress == 100 ? 'complete' : ($progress > 0 ? 'in-progress' : 'not-started') ?>" style="width: <?= $progress ?>%;"></div>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:12px; color:#64748b;">
                            <span><?= $progress ?>% Complete</span>
                            <span></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top:15px;">
                        <a href="tender_checklist.php?tender_id=<?= $id ?>" class="btn btn-warning" style="width:100%;justify-content:center;">
                            <i class="fas fa-clipboard-check"></i> 
                            <?= $checklist ? 'Manage Checklist' : 'Create Checklist' ?>
                        </a>
                    </div>
                </div>
                
                <!-- Checklist Items Preview -->
                <?php if ($checklist && !empty($checklist_items)): ?>
                <div class="detail-card full-width">
                    <h3><i class="fas fa-list"></i> Checklist Items</h3>
                    <div style="max-height:300px; overflow-y:auto;">
                        <?php foreach ($checklist_items as $item): ?>
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
                                <?php if ($item['attachment_file']): ?>
                                    <a href="<?= htmlspecialchars($item['attachment_file']) ?>" target="_blank" style="font-size:12px;color:var(--green);">
                                        <i class="fas fa-paperclip"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($item['comment']): ?>
                                    <span style="font-size:11px;color:#64748b;" title="<?= htmlspecialchars($item['comment']) ?>">
                                        <i class="fas fa-comment"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:10px; font-size:12px; color:#64748b;">
                        <span style="color:var(--green);"><i class="fas fa-check-circle"></i> Attached</span>
                        <span style="color:#dc2626; margin-left:15px;"><i class="fas fa-circle"></i> Missing</span>
                        <span style="color:#dc2626; margin-left:15px;">* Required</span>
                        <a href="tender_checklist.php?tender_id=<?= $id ?>" style="float:right;color:var(--green);">View All →</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- PDF & Documents -->
                <div class="detail-card">
                    <h3><i class="fas fa-file-pdf"></i> Documents</h3>
                    <div class="row">
                        <span class="label">Quotation PDF</span>
                        <span class="value">
                            <?php if ($tender['quotation_pdf']): ?>
                                <a href="<?= htmlspecialchars($tender['quotation_pdf']) ?>" target="_blank" class="btn btn-sm btn-green"><i class="fas fa-download"></i> Download</a>
                            <?php else: ?>
                                <span style="color:#64748b;">No PDF uploaded</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="row">
                        <span class="label">Submission Proof</span>
                        <span class="value">
                            <?php if ($tender['submission_proof']): ?>
                                <a href="<?= htmlspecialchars($tender['submission_proof']) ?>" target="_blank" class="btn btn-sm btn-green"><i class="fas fa-download"></i> Download</a>
                            <?php else: ?>
                                <span style="color:#64748b;">No proof uploaded</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($tender['tracking_platform']): ?>
                    <div class="row"><span class="label">Tracking Platform</span><span class="value"><?= htmlspecialchars($tender['tracking_platform']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($tender['tracking_reference']): ?>
                    <div class="row"><span class="label">Tracking Reference</span><span class="value"><?= htmlspecialchars($tender['tracking_reference']) ?></span></div>
                    <?php endif; ?>
                </div>
                
                <!-- Description - Full Width -->
                <div class="detail-card full-width">
                    <h3><i class="fas fa-align-left"></i> Description</h3>
                    <p style="line-height:1.7;color:var(--dark);"><?= nl2br(htmlspecialchars($tender['description'] ?? 'No description provided')) ?></p>
                </div>
                
                <!-- UZ Submission Details -->
                <?php if ($uz): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-university"></i> UZ Submission Details</h3>
                    <div class="row"><span class="label">Project</span><span class="value"><?= htmlspecialchars($uz['project_name']) ?></span></div>
                    <div class="row"><span class="label">Allocated To</span><span class="value"><?= htmlspecialchars($uz['allocated_to'] ?? 'N/A') ?></span></div>
                    <div class="row"><span class="label">Submitted By</span><span class="value"><?= htmlspecialchars($uz['submitted_by'] ?? 'N/A') ?></span></div>
                    <div class="row"><span class="label">PMU Personnel</span><span class="value"><?= htmlspecialchars($uz['pmu_personnel'] ?? 'N/A') ?></span></div>
                    <div class="row"><span class="label">Submitted On</span><span class="value"><?= $uz['submitted_on'] ? date('d M Y', strtotime($uz['submitted_on'])) : 'N/A' ?></span></div>
                    <div class="row"><span class="label">Follow Up Date</span><span class="value"><?= $uz['follow_up_date'] ? date('d M Y', strtotime($uz['follow_up_date'])) : 'N/A' ?></span></div>
                    <div class="row"><span class="label">Status</span><span class="value"><span class="status status-<?= strtolower(str_replace(' ', '-', $uz['status'])) ?>"><?= $uz['status'] ?></span></span></div>
                    <?php if ($uz['remarks']): ?>
                    <div class="row"><span class="label">Remarks</span><span class="value"><?= htmlspecialchars($uz['remarks']) ?></span></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Site Visits -->
                <?php if (!empty($visits)): ?>
                <div class="detail-card">
                    <h3><i class="fas fa-map-marker-alt"></i> Site Visits</h3>
                    <?php foreach ($visits as $visit): ?>
                        <div style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
                            <div style="font-weight:600;"><?= htmlspecialchars($visit['project_name']) ?></div>
                            <div style="font-size:13px;color:#64748b;">
                                <?= $visit['visit_date'] ? date('d M Y', strtotime($visit['visit_date'])) : 'N/A' ?>
                                <?= $visit['visit_time'] ? ' at ' . htmlspecialchars($visit['visit_time']) : '' ?>
                                <span class="status status-<?= strtolower($visit['status']) ?>" style="font-size:10px;padding:2px 10px;margin-left:8px;"><?= $visit['status'] ?></span>
                            </div>
                            <div style="font-size:13px;color:#64748b;">Assigned to: <?= htmlspecialchars($visit['assigned_to'] ?? 'N/A') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Tracking History -->
                <div class="detail-card full-width">
                    <h3><i class="fas fa-history"></i> Tracking History</h3>
                    <?php if (!empty($tracking)): ?>
                        <div class="tracking-list">
                            <?php foreach ($tracking as $track): ?>
                                <div class="tracking-item">
                                    <div style="display:flex;justify-content:space-between;align-items:center;">
                                        <span class="status status-<?= strtolower($track['status']) ?>"><?= $track['status'] ?></span>
                                        <span class="date"><?= date('d M Y H:i', strtotime($track['updated_at'])) ?></span>
                                    </div>
                                    <?php if ($track['remarks']): ?>
                                        <div class="remarks"><?= htmlspecialchars($track['remarks']) ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:12px;color:#64748b;margin-top:4px;">Updated by: <?= htmlspecialchars($track['updated_by'] ?? 'System') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:#64748b;padding:20px 0;text-align:center;">No tracking history available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>