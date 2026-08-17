<?php
// quantity_survey.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Quantity Survey";
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
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:600px; }
        th { text-align:left; padding:14px; background:var(--light-green); font-weight:600; }
        td { padding:14px; border-bottom:1px solid #eee; }
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; }
        .btn-green { background:var(--green); color:white; }
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
            <h1 class="page-title"><i class="fas fa-ruler-combined"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Manage bills of quantities, cost estimates, and measurements.</p>
            
            <div class="cards">
                <div class="card"><h4>Active BOQs</h4><h2>24</h2></div>
                <div class="card"><h4>Total Cost</h4><h2>$4.2M</h2></div>
                <div class="card"><h4>Projects</h4><h2>12</h2></div>
                <div class="card"><h4>Pending Review</h4><h2>8</h2></div>
            </div>
            
            <div class="table-container">
                <h3 style="margin-bottom:15px;">Bills of Quantities</h3>
                <table>
                    <thead>
                        <tr><th>BOQ Reference</th><th>Project</th><th>Category</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>BOQ-2026-001</td><td>Harare Office</td><td>Construction</td><td>$450,000</td><td><span class="status status-approved">Approved</span></td></tr>
                        <tr><td>BOQ-2026-002</td><td>Highlands Renovation</td><td>Renovation</td><td>$85,000</td><td><span class="status status-pending">Pending</span></td></tr>
                        <tr><td>BOQ-2026-003</td><td>Bulawayo Bridge</td><td>Civil</td><td>$1,200,000</td><td><span class="status status-approved">Approved</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>