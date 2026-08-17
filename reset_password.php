<?php
// reset_password.php
require_once 'config/database.php';

$token = $_GET['token'] ?? '';
$error = '';
$message = '';
$valid = false;

if ($token) {
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE password_reset_token = ? 
        AND password_reset_expires > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $valid = true;
        $user_id = $user['id'];
    } else {
        $error = 'Invalid or expired reset token';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    
    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, password_reset_token = NULL, password_reset_expires = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$hashed, $user_id]);
        $message = 'Password reset successful! <a href="login.php">Login here</a>';
        $valid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Enginove</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { background:#f4f9f6; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .container { background:white; padding:50px 40px; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,0.08); width:100%; max-width:420px; }
        h2 { color:#1e2a2f; margin-bottom:10px; }
        p { color:#64748b; margin-bottom:25px; font-size:14px; }
        .form-group { margin-bottom:20px; }
        .form-group input { width:100%; padding:12px 16px; border:2px solid #e5e7eb; border-radius:10px; font-size:14px; }
        .form-group input:focus { outline:none; border-color:#1f8b4c; }
        .btn { width:100%; padding:14px; background:#1f8b4c; color:white; border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; }
        .btn:hover { background:#0f6a36; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px; border-radius:8px; margin-bottom:20px; }
        .message { background:#d4edda; color:#0f5a2e; padding:12px; border-radius:8px; margin-bottom:20px; }
        .message a { color:#1f8b4c; font-weight:600; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($valid): ?>
            <form method="POST">
                <div class="form-group">
                    <input type="password" name="password" placeholder="New password (min 6 characters)" required>
                </div>
                <div class="form-group">
                    <input type="password" name="confirm" placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="btn"><i class="fas fa-key"></i> Reset Password</button>
            </form>
        <?php elseif (!$message): ?>
            <p>Invalid or expired reset link. Please request a new one.</p>
            <a href="forgot_password.php" class="btn" style="display:block;text-align:center;text-decoration:none;">Request New Link</a>
        <?php endif; ?>
    </div>
</body>
</html>