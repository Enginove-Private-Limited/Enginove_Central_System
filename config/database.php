<?php
// config/database.php
session_start();

$host = 'localhost';
$dbname = 'enginove_2026';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Check if user has any of the specified roles
function hasRole($role_names) {
    if (!isset($_SESSION['role_name'])) return false;
    if (is_array($role_names)) {
        return in_array($_SESSION['role_name'], $role_names);
    }
    return $_SESSION['role_name'] === $role_names;
}

// Check permission - FIXED: Added debugging and proper role_id handling
function hasPermission($module, $action = 'view') {
    // If no user is logged in, return false
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Get the user's role_id from the database if not in session
    if (!isset($_SESSION['role_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT r.id as role_id 
            FROM user_roles ur 
            JOIN roles r ON r.id = ur.role_id 
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $role = $stmt->fetch();
        if ($role) {
            $_SESSION['role_id'] = $role['role_id'];
        } else {
            return false;
        }
    }
    
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT rmp.can_view, rmp.can_create, rmp.can_edit, rmp.can_delete
        FROM role_module_permissions rmp
        JOIN modules m ON m.id = rmp.module_id
        WHERE rmp.role_id = ? AND m.module_name = ?
    ");
    $stmt->execute([$_SESSION['role_id'], $module]);
    $perm = $stmt->fetch();
    
    if (!$perm) return false;
    
    switch($action) {
        case 'view': return (bool)$perm['can_view'];
        case 'create': return (bool)$perm['can_create'];
        case 'edit': return (bool)$perm['can_edit'];
        case 'delete': return (bool)$perm['can_delete'];
        default: return false;
    }
}

// Special: Check if user can access settings (Admin/Dev only - HARDCODED)
function canAccessSettings() {
    return in_array($_SESSION['role_name'] ?? '', ['Administrator', 'Dev']);
}

// Special: Check if user can access role management (Admin/Dev only - HARDCODED)
function canAccessRoleManagement() {
    return in_array($_SESSION['role_name'] ?? '', ['Administrator', 'Dev']);
}

// Check if user can manage expense categories (Admin or Dev only)
function canManageCategories() {
    return in_array($_SESSION['role_name'] ?? '', ['Administrator', 'Dev']);
}

// Get user's full name
function getUserName($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Get user department
function getUserDepartment($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT d.department_name FROM users u
        JOIN departments d ON d.id = u.department_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Get user role
function getUserRole($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.role_name FROM users u
        JOIN user_roles ur ON ur.user_id = u.id
        JOIN roles r ON r.id = ur.role_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Check if user has specific module access
function canAccessModule($module_name, $action = 'view') {
    return hasPermission($module_name, $action);
}

// Get all permissions for current user (for sidebar)
function getUserPermissions() {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    // Get role_id if not set
    if (!isset($_SESSION['role_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT r.id as role_id 
            FROM user_roles ur 
            JOIN roles r ON r.id = ur.role_id 
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $role = $stmt->fetch();
        if ($role) {
            $_SESSION['role_id'] = $role['role_id'];
        } else {
            return [];
        }
    }
    
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.module_name, rmp.can_view, rmp.can_create, rmp.can_edit, rmp.can_delete
        FROM role_module_permissions rmp
        JOIN modules m ON m.id = rmp.module_id
        WHERE rmp.role_id = ?
    ");
    $stmt->execute([$_SESSION['role_id']]);
    $results = $stmt->fetchAll();
    
    $permissions = [];
    foreach ($results as $row) {
        $permissions[$row['module_name']] = [
            'view' => (bool)$row['can_view'],
            'create' => (bool)$row['can_create'],
            'edit' => (bool)$row['can_edit'],
            'delete' => (bool)$row['can_delete']
        ];
    }
    return $permissions;
}

// Log user activity
function logActivity($user_id, $activity, $ip_address = null) {
    global $pdo;
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $activity, $ip_address]);
}

// Get user's role name from session or database
function getCurrentRoleName() {
    if (isset($_SESSION['role_name'])) {
        return $_SESSION['role_name'];
    }
    
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT r.role_name 
            FROM user_roles ur 
            JOIN roles r ON r.id = ur.role_id 
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $role = $stmt->fetchColumn();
        if ($role) {
            $_SESSION['role_name'] = $role;
            return $role;
        }
    }
    return null;
}

// Check if user has permission for expense operations
function canManageExpenses() {
    return hasPermission('Expense Capture', 'create');
}

// Check if user can view expense reports
function canViewExpenseReports() {
    return hasPermission('Expense Capture', 'view');
}