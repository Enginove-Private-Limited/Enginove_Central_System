<?php
// memos.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Memos";
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
        
        .priority-high { color:#dc2626; }
        .priority-medium { color:#f59e0b; }
        .priority-low { color:#64748b; }
        .priority-urgent { color:#dc2626; font-weight:600; }
        
        .status-badge { 
            display:inline-block; padding:3px 12px; border-radius:20px; font-size:11px; font-weight:600; 
        }
        .status-badge.draft { background:#e5e7eb; color:#374151; }
        .status-badge.sent { background:#dbeafe; color:#1e40af; }
        .status-badge.read { background:#d4edda; color:#0f5a2e; }
        .status-badge.archived { background:#e5e7eb; color:#64748b; }
        
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
            <h1 class="page-title"><i class="fas fa-envelope"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Internal memos and communications.</p>
            
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                    <h3><i class="fas fa-plus-circle"></i> Quick Actions</h3>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="memo_add.php" class="btn btn-green"><i class="fas fa-plus"></i> New Memo</a>
                        <a href="memo_inbox.php" class="btn btn-outline"><i class="fas fa-inbox"></i> Inbox</a>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <h3 style="margin-bottom:15px;"><i class="fas fa-list"></i> Recent Memos</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Memo #</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $memos = $pdo->query("
                            SELECT * FROM memos 
                            ORDER BY created_at DESC LIMIT 20
                        ")->fetchAll();
                        
                        if (count($memos) > 0):
                            foreach ($memos as $memo):
                                $priorityClass = 'priority-' . strtolower($memo['priority']);
                                $statusClass = strtolower($memo['status']);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($memo['memo_number']) ?></strong></td>
                                <td><?= htmlspecialchars(substr($memo['subject'], 0, 40)) ?></td>
                                <td><?= date('d M Y', strtotime($memo['memo_date'])) ?></td>
                                <td class="<?= $priorityClass ?>">
                                    <i class="fas fa-flag"></i> <?= $memo['priority'] ?>
                                </td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $memo['status'] ?></span></td>
                                <td>
                                    <a href="memo_view.php?id=<?= $memo['id'] ?>" class="btn btn-outline" style="padding:4px 12px;font-size:12px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:30px;color:#64748b;">
                                    <i class="fas fa-envelope-open" style="font-size:24px;display:block;margin-bottom:10px;"></i>
                                    No memos found.
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