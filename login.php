<?php
// login.php
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name 
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE u.username = ? AND u.status = 'ACTIVE'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['department_id'] = $user['department_id'];
            
            // Update last login
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                ->execute([$user['id']]);
            
            // Log activity
            $pdo->prepare("
                INSERT INTO activity_logs (user_id, activity) 
                VALUES (?, 'User logged in')
            ")->execute([$user['id']]);
            
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Enginove Central System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        body { background: #f4f9f6; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .login-container { background:white; padding:50px 40px; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,0.08); width:100%; max-width:420px; }
        .logo { text-align:center; margin-bottom:30px; }
        .logo img { 
            height: 60px; 
            width: auto; 
            display: block; 
            margin: 0 auto 12px;
        }
        .logo h1 { color:#1f8b4c; font-size:24px; font-weight:700; }
        .logo p { color:#64748b; font-size:14px; margin-top:4px; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; color:#1e2a2f; font-size:14px; }
        .form-group input { width:100%; padding:12px 16px; border:2px solid #e5e7eb; border-radius:10px; font-size:14px; transition:0.3s; }
        .form-group input:focus { outline:none; border-color:#1f8b4c; box-shadow:0 0 0 4px rgba(31,139,76,0.1); }
        .btn { width:100%; padding:14px; background:#1f8b4c; color:white; border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; transition:0.3s; }
        .btn:hover { background:#0f6a36; transform:translateY(-2px); }
        .error { background:#fee2e2; color:#b91c1c; padding:12px; border-radius:8px; margin-bottom:20px; font-size:14px; }
        .links { text-align:center; margin-top:20px; }
        .links a { color:#1f8b4c; text-decoration:none; font-size:14px; }
        .links a:hover { text-decoration:underline; }
        
        @media (max-width: 480px) {
            .login-container { padding:30px 20px; }
            .logo img { height: 50px; }
            .logo h1 { font-size:20px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="logo.png" alt="Enginove">
        </div>
        
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
        
        <div class="links">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </div>
</body>
</html>