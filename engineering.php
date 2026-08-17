<?php
// engineering.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Engineering";
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
        .status-in-progress { background:#dbeafe; color:#1d4ed8; }
        .status-completed { background:var(--light-green); color:var(--green); }
        .status-on-hold { background:#fef3c7; color:#b45309; }
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
            <h1 class="page-title"><i class="fas fa-gears"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Manage engineering projects, drawings, and site progress.</p>
            
            <div class="cards">
                <div class="card"><h4>Active Projects</h4><h2>8</h2></div>
                <div class="card"><h4>Engineers</h4><h2>14</h2></div>
                <div class="card"><h4>Drawings</h4><h2>36</h2></div>
                <div class="card"><h4>Inspections</h4><h2>22</h2></div>
            </div>
            
            <div class="grid">
                <div class="table-card">
                    <h3>Engineering Projects</h3>
                    <table>
                        <thead><tr><th>Project</th><th>Engineer</th><th>Status</th><th>Progress</th></tr></thead>
                        <tbody>
                            <tr><td>Highlands Bridge</td><td>John Moyo</td><td><span class="status status-in-progress">In Progress</span></td><td>65%</td></tr>
                            <tr><td>Solar Installation</td><td>Sarah Ndlovu</td><td><span class="status status-completed">Completed</span></td><td>100%</td></tr>
                            <tr><td>Road Construction</td><td>David Chen</td><td><span class="status status-in-progress">In Progress</span></td><td>40%</td></tr>
                            <tr><td>Drainage System</td><td>Mike Chiweshe</td><td><span class="status status-on-hold">On Hold</span></td><td>20%</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-card">
                    <h3>Recent Inspections</h3>
                    <div style="padding:10px 0;">
                        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;">
                            <span>🏗️ Site Visit - Highlands</span>
                            <span style="color:var(--green);">Today</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;">
                            <span>📐 Quality Check - Bulawayo</span>
                            <span style="color:#b45309;">Pending</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:10px 0;">
                            <span>📊 Structural Review</span>
                            <span style="color:var(--green);">Passed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>