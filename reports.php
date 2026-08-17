<?php
// reports.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Reports";
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
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; }
        .report-card { background:white; border-radius:15px; padding:25px; box-shadow:0 8px 25px rgba(0,0,0,0.05); text-align:center; transition:.25s; }
        .report-card:hover { transform:translateY(-5px); box-shadow:0 12px 35px rgba(0,0,0,0.08); }
        .report-card .icon { font-size:48px; color:var(--green); margin-bottom:15px; }
        .report-card h3 { font-size:18px; color:var(--dark); margin-bottom:8px; }
        .report-card p { font-size:14px; color:#64748b; margin-bottom:15px; }
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
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
            <h1 class="page-title"><i class="fas fa-chart-line"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Generate and export reports in Excel and PDF formats.</p>
            
            <div class="grid">
                <div class="report-card">
                    <div class="icon"><i class="fas fa-file-signature"></i></div>
                    <h3>Tender Reports</h3>
                    <p>Export all tenders with status, assignments, and deadlines.</p>
                    <button class="btn btn-green"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-outline"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
                
                <div class="report-card">
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                    <h3>Purchase Orders</h3>
                    <p>Export PO data including suppliers, amounts, and delivery status.</p>
                    <button class="btn btn-green"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-outline"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
                
                <div class="report-card">
                    <div class="icon"><i class="fas fa-truck"></i></div>
                    <h3>Supplier Reports</h3>
                    <p>Export supplier database with contacts and product references.</p>
                    <button class="btn btn-green"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-outline"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
                
                <div class="report-card">
                    <div class="icon"><i class="fas fa-boxes"></i></div>
                    <h3>Procurement Summary</h3>
                    <p>Export procurement activities, quotes, and timelines.</p>
                    <button class="btn btn-green"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-outline"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
                
                <div class="report-card">
                    <div class="icon"><i class="fas fa-screwdriver-wrench"></i></div>
                    <h3>Artisan Reports</h3>
                    <p>Export artisan database with trades and contact details.</p>
                    <button class="btn btn-green"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-outline"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
                
                <div class="report-card">
                    <div class="icon"><i class="fas fa-list-check"></i></div>
                    <h3>Todo Reports</h3>
                    <p>Export task lists with assignments and completion status.</p>
                    <button class="btn btn-green"><i class="fas fa-file-excel"></i> Excel</button>
                    <button class="btn btn-outline"><i class="fas fa-file-pdf"></i> PDF</button>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>