<?php
// users.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "User Management";
$message = '';
$error = '';

// Only admin can manage users
if ($_SESSION['role_name'] !== 'Administrator') {
    header('Location: index.php');
    exit();
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $department_id = $_POST['department_id'];
    $role_id = $_POST['role_id'];
    $password = password_hash('password123', PASSWORD_DEFAULT);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, first_name, last_name, phone, department_id, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $first_name, $last_name, $phone, $department_id, $password]);
        $user_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $role_id]);
        
        $pdo->commit();
        $message = "User created successfully! Default password: password123";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating user: " . $e->getMessage();
    }
}

$users = $pdo->query("
    SELECT u.*, d.department_name, r.role_name 
    FROM users u
    JOIN departments d ON d.id = u.department_id
    JOIN user_roles ur ON ur.user_id = u.id
    JOIN roles r ON r.id = ur.role_id
    ORDER BY u.created_at DESC
")->fetchAll();

$departments = $pdo->query("SELECT * FROM departments WHERE status = 'ACTIVE'")->fetchAll();
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();
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
        .btn-danger { background:#dc2626; color:white; }
        .actions { display:flex; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:700px; }
        th { text-align:left; padding:14px; background:var(--light-green); color:var(--dark); font-weight:600; }
        td { padding:14px; border-bottom:1px solid #eee; }
        .status { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .status-active { background:var(--light-green); color:var(--green); }
        .status-disabled { background:#f3f4f6; color:#6b7280; }
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; }
        .form-group input, .form-group select { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
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
            <h1 class="page-title"><i class="fas fa-users"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Manage system users, roles, and departments.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <div class="actions">
                <button class="btn btn-green" onclick="openModal()"><i class="fas fa-user-plus"></i> Add User</button>
                <span style="color:#64748b;">Total: <?= count($users) ?> users</span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['department_name']) ?></td>
                                <td><?= htmlspecialchars($u['role_name']) ?></td>
                                <td><span class="status status-<?= strtolower($u['status']) ?>"><?= $u['status'] ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-green"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal" id="userModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone">
            </div>
            <div class="form-group">
                <label>Department *</label>
                <select name="department_id" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= $d['department_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Role *</label>
                <select name="role_id" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= $r['role_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Create User</button>
            <p style="font-size:12px;color:#64748b;margin-top:10px;text-align:center;">Default password: <strong>password123</strong></p>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('userModal').classList.add('show'); }
function closeModal() { document.getElementById('userModal').classList.remove('show'); }
window.onclick = function(e) { if (e.target === document.getElementById('userModal')) closeModal(); }
</script>
</body>
</html>