<?php
// forgot_password.php
require_once 'config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_reset_token = ?, password_reset_expires = ? 
                WHERE id = ?
            ");
            $stmt->execute([$token, $expires, $user['id']]);
            
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
            
            // In production, send email here
            $message = "Password reset link has been sent to your email. (Demo: $resetLink)";
        } else {
            $error = 'Email address not found';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Enginove</title>
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
        .message { background:#d4edda; color:#0f5a2e; padding:12px; border-radius:8px; margin-bottom:20px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px; border-radius:8px; margin-bottom:20px; }
        .back { text-align:center; margin-top:15px; }
        .back a { color:#1f8b4c; text-decoration:none; font-size:14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Forgot Password</h2>
        <p>Enter your email address and we'll send you a reset link.</p>
        
        <?php if ($message): ?>
            <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <input type="email" name="email" placeholder="Enter your email address" required>
            </div>
            <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
        </form>
        
        <div class="back">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>