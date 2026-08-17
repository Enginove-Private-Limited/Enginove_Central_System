<?php
// profile.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "My Profile";
$message = '';
$error = '';

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("
    SELECT u.*, d.department_name, r.role_name 
    FROM users u
    LEFT JOIN departments d ON d.id = u.department_id
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users SET 
                first_name = ?,
                last_name = ?,
                email = ?,
                phone = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $user_id
        ]);
        
        $message = "Profile updated successfully!";
        
        // Update session
        $_SESSION['first_name'] = $_POST['first_name'];
        $_SESSION['last_name'] = $_POST['last_name'];
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}
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
        .card h3 { margin-bottom:15px; color:var(--dark); font-size:18px; }
        
        .profile-grid { display:grid; grid-template-columns:1fr 2fr; gap:30px; }
        .profile-avatar { text-align:center; }
        .profile-avatar .avatar { 
            width:150px; height:150px; border-radius:50%; 
            background:var(--green); color:white; 
            display:flex; align-items:center; justify-content:center;
            font-size:60px; font-weight:700; margin:0 auto 15px;
        }
        .profile-avatar h3 { font-size:18px; color:var(--dark); }
        .profile-avatar p { color:#64748b; font-size:14px; }
        .role-badge { 
            display:inline-block; padding:4px 16px; border-radius:20px; 
            font-size:12px; font-weight:600; background:var(--light-green); color:var(--green);
        }
        
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:13px; color:#374151; }
        .form-group input, .form-group select { 
            width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; transition:.2s; 
        }
        .form-group input:focus, .form-group select:focus {
            border-color:var(--green); outline:none; box-shadow:0 0 0 3px rgba(31,139,76,0.1);
        }
        .form-group input:disabled { background:#f8fafc; cursor:not-allowed; }
        
        .btn { 
            padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; 
            transition:.25s; display:inline-flex; align-items:center; gap:8px; font-size:14px; 
        }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-1px); box-shadow:0 4px 12px rgba(31,139,76,0.3); }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        
        .info-row { display:flex; padding:10px 0; border-bottom:1px solid #f1f5f9; }
        .info-row .label { font-weight:500; color:#64748b; width:120px; flex-shrink:0; }
        .info-row .value { color:var(--dark); }
        
        @media(max-width:991px) { 
            .main { margin-left:0; } 
            .profile-grid { grid-template-columns:1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-title { font-size:22px; }
            .info-row { flex-direction:column; gap:4px; }
            .info-row .label { width:100%; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-user-circle"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">View and manage your personal information.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <div class="profile-grid">
                <!-- Left Column - Avatar & Info -->
                <div class="card profile-avatar">
                    <div class="avatar">
                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                    </div>
                    <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                    <p style="margin-bottom:10px;">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="role-badge"><?= htmlspecialchars($user['role_name'] ?? 'No Role') ?></span>
                    
                    <div style="margin-top:20px; text-align:left; padding-top:20px; border-top:1px solid #e5e7eb;">
                        <div class="info-row">
                            <span class="label">Department</span>
                            <span class="value"><?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Username</span>
                            <span class="value"><?= htmlspecialchars($user['username']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Status</span>
                            <span class="value">
                                <span style="display:inline-block; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600; background:<?= $user['status'] == 'ACTIVE' ? '#d4edda' : '#fee2e2' ?>; color:<?= $user['status'] == 'ACTIVE' ? '#0f5a2e' : '#b91c1c' ?>;">
                                    <?= $user['status'] ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label">Member Since</span>
                            <span class="value"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Last Login</span>
                            <span class="value"><?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'Never' ?></span>
                        </div>
                    </div>
                    
                    <div style="margin-top:20px; padding-top:20px; border-top:1px solid #e5e7eb;">
                        <a href="change_password.php" class="btn btn-outline" style="width:100%; justify-content:center;">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </div>
                
                <!-- Right Column - Edit Profile -->
                <div class="card">
                    <h3><i class="fas fa-edit"></i> Edit Profile</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <small style="color:#64748b; font-size:12px;">Username cannot be changed</small>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-green">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-hide messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.message, .error');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });
});
</script>
</body>
</html>