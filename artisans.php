<?php
// artisans.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Artisans";
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'create') {
        $stmt = $pdo->prepare("
            INSERT INTO artisans (artisan_name, trade, phone, email, address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['artisan_name'],
            $_POST['trade'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['address']
        ]);
        $message = 'Artisan added successfully!';
    }
}

$artisans = $pdo->query("SELECT * FROM artisans ORDER BY artisan_name")->fetchAll();
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
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-sm { padding:6px 14px; font-size:13px; }
        .actions { display:flex; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:700px; }
        th { text-align:left; padding:14px; background:var(--light-green); color:var(--dark); font-weight:600; }
        td { padding:14px; border-bottom:1px solid #eee; }
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:500px; }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; }
        .form-group input, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .status { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-active { background:var(--light-green); color:var(--green); }
        .status-inactive { background:#f3f4f6; color:#6b7280; }
        @media(max-width:991px) { .main { margin-left:0; } }
        @media(max-width:768px) { .content { padding:15px; } .page-title { font-size:22px; } .actions { flex-direction:column; } }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-screwdriver-wrench"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Manage artisans and skilled workers database.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            
            <div class="actions">
                <button class="btn btn-green" onclick="openModal()"><i class="fas fa-plus"></i> Add Artisan</button>
                <input type="text" id="searchInput" placeholder="Search artisans..." onkeyup="filterTable()" style="padding:10px 16px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px;width:250px;">
            </div>
            
            <div class="table-container">
                <table id="artisanTable">
                    <thead>
                        <tr>
                            <th>Artisan Name</th>
                            <th>Trade</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artisans as $a): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($a['artisan_name']) ?></strong></td>
                                <td><?= htmlspecialchars($a['trade'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($a['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($a['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($a['address'] ?? 'N/A') ?></td>
                                <td><span class="status status-active">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-green"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm" style="background:#e5e7eb;"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Artisan Modal -->
<div class="modal" id="artisanModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> Add Artisan</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Artisan Name *</label>
                <input type="text" name="artisan_name" required>
            </div>
            <div class="form-group">
                <label>Trade/Specialty</label>
                <input type="text" name="trade" placeholder="e.g. Welder, Electrician, Plumber">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email">
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Save Artisan</button>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('artisanModal').classList.add('show'); }
function closeModal() { document.getElementById('artisanModal').classList.remove('show'); }
function filterTable() {
    var input = document.getElementById('searchInput').value.toLowerCase();
    var rows = document.querySelectorAll('#artisanTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
    });
}
window.onclick = function(e) { if (e.target === document.getElementById('artisanModal')) closeModal(); }
</script>
</body>
</html>