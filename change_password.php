<?php
// change_password.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Change Password";
$message = '';
$error = '';

$user_id = $_SESSION['user_id'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        try {
            // Get current user's password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                $message = "Password changed successfully!";
                
                // Log activity
                logActivity($_SESSION['user_id'], "Changed password");
                
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (Exception $e) {
            $error = "Error changing password: " . $e->getMessage();
        }
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
        
        .card { 
            background:white; 
            padding:30px; 
            border-radius:15px; 
            box-shadow:0 8px 25px rgba(0,0,0,0.05); 
            max-width:600px; 
            margin:0 auto;
        }
        .card h3 { margin-bottom:20px; color:var(--dark); font-size:20px; text-align:center; }
        
        .form-group { margin-bottom:20px; }
        .form-group label { 
            display:block; 
            font-weight:500; 
            margin-bottom:6px; 
            font-size:14px; 
            color:#374151; 
        }
        .form-group input { 
            width:100%; 
            padding:12px 16px; 
            border:2px solid #e5e7eb; 
            border-radius:8px; 
            font-size:14px; 
            transition:.2s; 
        }
        .form-group input:focus {
            border-color:var(--green); 
            outline:none; 
            box-shadow:0 0 0 3px rgba(31,139,76,0.1);
        }
        .form-group .password-hint {
            font-size:12px;
            color:#64748b;
            margin-top:4px;
        }
        
        .btn { 
            padding:12px 24px; 
            border:none; 
            border-radius:8px; 
            font-weight:600; 
            cursor:pointer; 
            transition:.25s; 
            display:inline-flex; 
            align-items:center; 
            gap:8px; 
            font-size:14px; 
            width:100%;
            justify-content:center;
        }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-1px); box-shadow:0 4px 12px rgba(31,139,76,0.3); }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        
        .message { 
            background:#d4edda; 
            color:#0f5a2e; 
            padding:12px 16px; 
            border-radius:8px; 
            margin-bottom:20px; 
            display:flex; 
            align-items:center; 
            gap:10px; 
        }
        .error { 
            background:#fee2e2; 
            color:#b91c1c; 
            padding:12px 16px; 
            border-radius:8px; 
            margin-bottom:20px; 
            display:flex; 
            align-items:center; 
            gap:10px; 
        }
        
        .password-requirements {
            background:#f8fafc;
            padding:15px;
            border-radius:8px;
            margin-top:10px;
        }
        .password-requirements li {
            list-style:none;
            font-size:13px;
            color:#64748b;
            padding:4px 0;
        }
        .password-requirements li i {
            margin-right:8px;
            color:var(--green);
        }
        
        .actions { display:flex; gap:10px; margin-top:10px; }
        .actions .btn { width:auto; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; } 
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-title { font-size:22px; }
            .card { padding:20px; }
            .actions { flex-direction:column; }
            .actions .btn { width:100%; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-key"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Update your account password.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" required placeholder="Enter your current password">
                    </div>
                    
                    <div class="form-group">
                        <label>New Password *</label>
                        <input type="password" name="new_password" required placeholder="Enter new password">
                        <div class="password-hint">Password must be at least 8 characters long</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" required placeholder="Confirm new password">
                    </div>
                    
                    <div class="password-requirements">
                        <h4 style="font-size:13px; color:#374151; margin-bottom:6px;">Password Requirements:</h4>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> Minimum 8 characters</li>
                            <li><i class="fas fa-check-circle"></i> At least one uppercase letter</li>
                            <li><i class="fas fa-check-circle"></i> At least one lowercase letter</li>
                            <li><i class="fas fa-check-circle"></i> At least one number</li>
                        </ul>
                    </div>
                    
                    <div class="actions" style="margin-top:20px;">
                        <button type="submit" name="change_password" class="btn btn-green">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                        <a href="profile.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
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