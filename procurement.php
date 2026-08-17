<?php
// procurement.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Procurement Dashboard";
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
        
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; margin-bottom:30px; }
        .card { background:white; padding:25px; border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,0.05); border-left:4px solid var(--green); }
        .card h4 { font-size:14px; color:#64748b; margin-bottom:8px; }
        .card h2 { font-size:32px; color:var(--green); }
        
        .grid { display:grid; grid-template-columns:2fr 1fr; gap:20px; }
        .table-card { background:white; padding:25px; border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        .table-card h3 { margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; }
        th { text-align:left; padding:12px; background:var(--light-green); font-weight:600; }
        td { padding:12px; border-bottom:1px solid #eee; }
        .status { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-approved { background:var(--light-green); color:var(--green); }
        .status-pending { background:#fef3c7; color:#b45309; }
        
        @media(max-width:991px) { .main { margin-left:0; } .grid { grid-template-columns:1fr; } }
        @media(max-width:768px) { .content { padding:15px; } .page-title { font-size:22px; } }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-boxes"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Track procurement activities, comparative quotations, and purchase requests.</p>
            
            <?php
            // Get stats
            $stats = $pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM purchase_orders WHERE status = 'Pending') as pending_orders,
                    (SELECT COUNT(*) FROM purchase_orders WHERE status = 'Approved') as approved_orders,
                    (SELECT COUNT(*) FROM suppliers) as total_suppliers,
                    (SELECT COUNT(*) FROM tenders WHERE status = 'Open') as open_tenders
            ")->fetch();
            ?>
            
            <div class="cards">
                <div class="card"><h4>Pending Orders</h4><h2><?= $stats['pending_orders'] ?></h2></div>
                <div class="card"><h4>Approved Orders</h4><h2><?= $stats['approved_orders'] ?></h2></div>
                <div class="card"><h4>Active Suppliers</h4><h2><?= $stats['total_suppliers'] ?></h2></div>
                <div class="card"><h4>Open Tenders</h4><h2><?= $stats['open_tenders'] ?></h2></div>
            </div>
            
            <div class="grid">
                <div class="table-card">
                    <h3>Recent Purchase Orders</h3>
                    <table>
                        <thead>
                            <tr><th>PO Number</th><th>Supplier</th><th>Amount</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent = $pdo->query("
                                SELECT po.*, s.supplier_name 
                                FROM purchase_orders po 
                                JOIN suppliers s ON s.id = po.supplier_id 
                                ORDER BY po.created_at DESC LIMIT 5
                            ")->fetchAll();
                            foreach ($recent as $r): ?>
                                <tr>
                                    <td><?= $r['po_number'] ?></td>
                                    <td><?= $r['supplier_name'] ?></td>
                                    <td>$<?= number_format($r['total_amount'], 2) ?></td>
                                    <td><span class="status status-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-card">
                    <h3>Procurement Timeline</h3>
                    <div style="padding:10px 0;">
                        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;">
                            <span>📋 Tender Issued</span>
                            <span style="color:var(--green);">Today</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;">
                            <span>📊 Quotations Received</span>
                            <span style="color:var(--green);">5 of 8</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;">
                            <span>📦 PO Created</span>
                            <span style="color:#b45309;">3 pending</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:10px 0;">
                            <span>✅ Order Delivered</span>
                            <span style="color:var(--green);">12 this month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>