<?php
// checklists.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Checklists";
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
        
        .card { background:white; padding:25px; border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,0.05); margin-bottom:20px; }
        .card h3 { margin-bottom:15px; color:var(--dark); }
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:700px; }
        th { text-align:left; padding:12px; background:var(--light-green); font-weight:600; font-size:13px; }
        td { padding:12px; border-bottom:1px solid #f1f5f9; font-size:14px; }
        tr:hover td { background:#fafdfb; }
        
        .status-badge { 
            display:inline-block; padding:3px 12px; border-radius:20px; font-size:11px; font-weight:600; 
        }
        .status-badge.pending { background:#fef3c7; color:#92400e; }
        .status-badge.in-progress { background:#dbeafe; color:#1e40af; }
        .status-badge.completed { background:#d4edda; color:#0f5a2e; }
        .status-badge.cancelled { background:#fee2e2; color:#b91c1c; }
        
        .progress-bar {
            width: 100px;
            height: 6px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            display: inline-block;
        }
        .progress-bar .fill {
            height: 100%;
            background: var(--green);
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        @media(max-width:991px) { .main { margin-left:0; } }
        @media(max-width:768px) { .content { padding:15px; } .page-title { font-size:22px; } }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-clipboard-check"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Task checklists and verification.</p>
            
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                    <h3><i class="fas fa-plus-circle"></i> Quick Actions</h3>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="checklist_add.php" class="btn btn-green"><i class="fas fa-plus"></i> New Checklist</a>
                        <a href="checklist_completed.php" class="btn btn-outline"><i class="fas fa-check-double"></i> Completed</a>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <h3 style="margin-bottom:15px;"><i class="fas fa-list"></i> Active Checklists</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Checklist #</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $checklists = $pdo->query("
                            SELECT * FROM checklists 
                            WHERE status != 'Completed' AND status != 'Cancelled'
                            ORDER BY due_date ASC LIMIT 20
                        ")->fetchAll();
                        
                        if (count($checklists) > 0):
                            foreach ($checklists as $checklist):
                                $statusClass = str_replace(' ', '-', strtolower($checklist['status']));
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($checklist['checklist_number']) ?></strong></td>
                                <td><?= htmlspecialchars(substr($checklist['title'], 0, 40)) ?></td>
                                <td><?= htmlspecialchars($checklist['checklist_type']) ?></td>
                                <td><?= date('d M Y', strtotime($checklist['due_date'])) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $checklist['status'] ?></span></td>
                                <td>
                                    <a href="checklist_view.php?id=<?= $checklist['id'] ?>" class="btn btn-outline" style="padding:4px 12px;font-size:12px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="checklist_edit.php?id=<?= $checklist['id'] ?>" class="btn btn-outline" style="padding:4px 12px;font-size:12px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:30px;color:#64748b;">
                                    <i class="fas fa-clipboard" style="font-size:24px;display:block;margin-bottom:10px;"></i>
                                    No active checklists found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-container" style="margin-top:20px;">
                <h3 style="margin-bottom:15px;"><i class="fas fa-check-circle"></i> Recently Completed</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Checklist #</th>
                            <th>Title</th>
                            <th>Completed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $completed = $pdo->query("
                            SELECT * FROM checklists 
                            WHERE status = 'Completed'
                            ORDER BY completed_at DESC LIMIT 5
                        ")->fetchAll();
                        
                        if (count($completed) > 0):
                            foreach ($completed as $item):
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['checklist_number']) ?></strong></td>
                                <td><?= htmlspecialchars(substr($item['title'], 0, 40)) ?></td>
                                <td><?= date('d M Y', strtotime($item['completed_at'])) ?></td>
                                <td>
                                    <a href="checklist_view.php?id=<?= $item['id'] ?>" class="btn btn-outline" style="padding:4px 12px;font-size:12px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center;padding:20px;color:#64748b;">
                                    No completed checklists yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>