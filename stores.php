<?php
// stores.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Stores";
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
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .card { background:white; padding:25px; border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,0.05); border-left:4px solid var(--green); }
        .card h4 { font-size:14px; color:#64748b; margin-bottom:8px; }
        .card h2 { font-size:28px; color:var(--green); }
        .grid { display:grid; grid-template-columns:2fr 1fr; gap:20px; }
        .table-card { background:white; padding:25px; border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        .table-card h3 { margin-bottom:15px; }
        table { width:100%; border-collapse:collapse; min-width:600px; }
        th { text-align:left; padding:12px; background:var(--light-green); font-weight:600; }
        td { padding:12px; border-bottom:1px solid #eee; }
        .status { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-low { background:#fee2e2; color:#b91c1c; }
        .status-ok { background:var(--light-green); color:var(--green); }
        .status-high { background:#dbeafe; color:#1d4ed8; }
        .btn { padding:8px 16px; border:none; border-radius:6px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:6px; }
        .btn-green { background:var(--green); color:white; }
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
            <h1 class="page-title"><i class="fas fa-warehouse"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Manage inventory, stock levels, and material issuance.</p>
            
            <div class="cards">
                <div class="card"><h4>Total Items</h4><h2>156</h2></div>
                <div class="card"><h4>Low Stock</h4><h2 style="color:#b91c1c;">8</h2></div>
                <div class="card"><h4>Issued Today</h4><h2>14</h2></div>
                <div class="card"><h4>Received Today</h4><h2>23</h2></div>
            </div>
            
            <div class="grid">
                <div class="table-card">
                    <h3>Inventory <button class="btn btn-green" style="float:right;font-size:13px;"><i class="fas fa-plus"></i> Add</button></h3>
                    <table>
                        <thead><tr><th>Material</th><th>Category</th><th>Stock</th><th>Unit</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr><td>Cement</td><td>Building</td><td>450</td><td>Bags</td><td><span class="status status-ok">OK</span></td></tr>
                            <tr><td>Steel Rebar</td><td>Metal</td><td>28</td><td>Bundles</td><td><span class="status status-low">Low</span></td></tr>
                            <tr><td>Paint</td><td>Finishing</td><td>120</td><td>Liters</td><td><span class="status status-ok">OK</span></td></tr>
                            <tr><td>Bricks</td><td>Building</td><td>3,200</td><td>Units</td><td><span class="status status-high">High</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-card">
                    <h3>Recent Activity</h3>
                    <div style="padding:10px 0;">
                        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;font-size:14px;">
                            <span>📦 Issued: Cement</span>
                            <span style="color:var(--green);">50 bags</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;font-size:14px;">
                            <span>📦 Received: Steel</span>
                            <span style="color:var(--green);">15 bundles</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;font-size:14px;">
                            <span>📦 Issued: Paint</span>
                            <span style="color:var(--green);">20 liters</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:14px;">
                            <span>⚠️ Low Stock Alert</span>
                            <span style="color:#b91c1c;">8 items</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>