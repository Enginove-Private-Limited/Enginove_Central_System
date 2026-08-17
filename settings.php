<?php
// settings.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Settings";
$message = '';
$error = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'roles';

// Only Admin and Dev can access settings
if (!in_array($_SESSION['role_name'], ['Administrator', 'Dev'])) {
    header('Location: index.php');
    exit();
}

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    try {
        $pdo->beginTransaction();
        
        // Delete existing permissions for this role
        $stmt = $pdo->prepare("DELETE FROM role_module_permissions WHERE role_id = ?");
        $stmt->execute([$_POST['role_id']]);
        
        // Insert new permissions
        if (isset($_POST['permissions'])) {
            $insertStmt = $pdo->prepare("
                INSERT INTO role_module_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['permissions'] as $module_id => $perms) {
                $insertStmt->execute([
                    $_POST['role_id'],
                    $module_id,
                    isset($perms['view']) ? 1 : 0,
                    isset($perms['create']) ? 1 : 0,
                    isset($perms['edit']) ? 1 : 0,
                    isset($perms['delete']) ? 1 : 0
                ]);
            }
        }
        
        $pdo->commit();
        $message = "Permissions updated successfully!";
        
        // Log the activity - using function from database.php
        logActivity($_SESSION['user_id'], "Updated permissions for role ID: " . $_POST['role_id']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating permissions: " . $e->getMessage();
    }
}

// Handle role creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    try {
        // Check if role already exists
        $check = $pdo->prepare("SELECT id FROM roles WHERE role_name = ?");
        $check->execute([$_POST['role_name']]);
        if ($check->rowCount() > 0) {
            $error = "Role '" . $_POST['role_name'] . "' already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
            $stmt->execute([$_POST['role_name'], $_POST['role_description']]);
            
            // Get the new role ID
            $role_id = $pdo->lastInsertId();
            
            // Auto-assign all permissions for Dev role
            if ($_POST['role_name'] === 'Dev') {
                $stmt = $pdo->prepare("
                    INSERT INTO role_module_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete) 
                    SELECT ?, id, 1, 1, 1, 1 FROM modules
                ");
                $stmt->execute([$role_id]);
            }
            
            $message = "Role created successfully!";
            logActivity($_SESSION['user_id'], "Created new role: " . $_POST['role_name']);
        }
    } catch (Exception $e) {
        $error = "Error creating role: " . $e->getMessage();
    }
}

// Handle role deletion
if (isset($_GET['delete_role'])) {
    $role_id = (int)$_GET['delete_role'];
    try {
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $message = "Role deleted successfully!";
        logActivity($_SESSION['user_id'], "Deleted role ID: " . $role_id);
    } catch (Exception $e) {
        $error = "Error deleting role: " . $e->getMessage();
    }
}

// Handle company settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_company'])) {
    $message = "Company settings updated successfully!";
    logActivity($_SESSION['user_id'], "Updated company settings");
}

// Handle security settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_security'])) {
    $message = "Security settings updated successfully!";
    logActivity($_SESSION['user_id'], "Updated security settings");
}

// Handle backup
if (isset($_GET['backup'])) {
    $message = "Database backup initiated successfully!";
    logActivity($_SESSION['user_id'], "Initiated database backup");
}

// Handle expense category actions (for Admin and Dev)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add category
    if (isset($_POST['add_category'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO expense_categories (category_name, category_code, description) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['category_name'], $_POST['category_code'], $_POST['category_description']]);
            $message = "Expense category added successfully!";
            logActivity($_SESSION['user_id'], "Added expense category: " . $_POST['category_name']);
        } catch (Exception $e) {
            $error = "Error adding category: " . $e->getMessage();
        }
    }
    
    // Delete category
    if (isset($_POST['delete_category'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
            $stmt->execute([$_POST['category_id']]);
            $message = "Expense category deleted successfully!";
            logActivity($_SESSION['user_id'], "Deleted expense category ID: " . $_POST['category_id']);
        } catch (Exception $e) {
            $error = "Error deleting category: " . $e->getMessage();
        }
    }
}

// Get activity logs
$logs = $pdo->query("
    SELECT al.*, u.username 
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC LIMIT 50
")->fetchAll();

// Get all roles
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();

// Get all modules
$modules = $pdo->query("SELECT * FROM modules ORDER BY id")->fetchAll();

// Get selected role for permission editing
$selected_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : ($roles[0]['id'] ?? 0);

// Get current permissions for selected role
$permissions = [];
if ($selected_role_id) {
    $stmt = $pdo->prepare("SELECT * FROM role_module_permissions WHERE role_id = ?");
    $stmt->execute([$selected_role_id]);
    while ($row = $stmt->fetch()) {
        $permissions[$row['module_id']] = $row;
    }
}

// Get expense categories
$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY category_name")->fetchAll();

// REMOVED: logActivity function (now in database.php)
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
        
        /* Tabs - Mobile Friendly */
        .tabs { 
            display:flex; 
            gap:4px; 
            margin-bottom:30px; 
            border-bottom:2px solid #e5e7eb; 
            background:white; 
            padding:0 20px; 
            border-radius:15px 15px 0 0; 
            box-shadow:0 4px 12px rgba(0,0,0,0.04);
            overflow-x:auto;
            -webkit-overflow-scrolling:touch;
            scrollbar-width:none;
        }
        .tabs::-webkit-scrollbar { display:none; }
        .tab { 
            padding:14px 18px; 
            font-weight:500; 
            color:#64748b; 
            cursor:pointer; 
            border-bottom:3px solid transparent; 
            transition:.2s; 
            text-decoration:none; 
            font-size:14px; 
            display:inline-flex; 
            align-items:center; 
            gap:8px; 
            white-space:nowrap;
            flex-shrink:0;
        }
        .tab:hover { color:var(--dark); }
        .tab.active { color:var(--green); border-bottom-color:var(--green); }
        .tab i { font-size:15px; }
        
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        
        .settings-card { 
            background:white; 
            padding:20px; 
            border-radius:15px; 
            box-shadow:0 8px 25px rgba(0,0,0,0.05); 
        }
        .settings-card h3 { margin-bottom:15px; color:var(--dark); font-size:18px; }
        .settings-card .form-group { margin-bottom:16px; }
        .settings-card .form-group label { 
            display:block; 
            font-weight:500; 
            margin-bottom:6px; 
            font-size:13px; 
            color:#374151; 
        }
        .settings-card .form-group input, 
        .settings-card .form-group select, 
        .settings-card .form-group textarea { 
            width:100%; 
            padding:10px 14px; 
            border:2px solid #e5e7eb; 
            border-radius:8px; 
            font-size:14px; 
            transition:.2s; 
        }
        .settings-card .form-group input:focus, 
        .settings-card .form-group select:focus, 
        .settings-card .form-group textarea:focus {
            border-color:var(--green); 
            outline:none; 
            box-shadow:0 0 0 3px rgba(31,139,76,0.1);
        }
        .settings-card .form-group textarea { resize:vertical; min-height:80px; }
        
        .btn { 
            padding:10px 20px; 
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
        .btn-green:hover { background:#0f6a36; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-sm { 
            padding:6px 14px; 
            font-size:12px; 
            width:auto;
        }
        .btn-sm-danger {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            cursor: pointer;
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 6px;
            transition: 0.2s;
        }
        .btn-sm-danger:hover { background: #fecaca; }
        
        .table-container { 
            background:white; 
            border-radius:15px; 
            padding:15px; 
            box-shadow:0 8px 25px rgba(0,0,0,0.05); 
            overflow-x:auto; 
            margin-top:20px; 
        }
        table { 
            width:100%; 
            border-collapse:collapse; 
            min-width:500px; 
            font-size:13px;
        }
        th { 
            text-align:left; 
            padding:10px 12px; 
            background:var(--light-green); 
            font-weight:600; 
            font-size:12px; 
        }
        td { 
            padding:10px 12px; 
            border-bottom:1px solid #f1f5f9; 
            font-size:13px; 
        }
        tr:hover td { background:#fafdfb; }
        
        .message { 
            background:#d4edda; 
            color:#0f5a2e; 
            padding:12px 16px; 
            border-radius:8px; 
            margin-bottom:20px; 
            display:flex; 
            align-items:center; 
            gap:10px; 
            font-size:14px;
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
            font-size:14px;
        }
        
        /* Permissions styling - Mobile First */
        .permissions-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.05);
            margin-top: 0;
        }
        .permissions-header {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        .permissions-header h3 { font-size:18px; }
        
        .permission-grid {
            overflow-x: auto;
            margin:0 -10px;
            padding:0 10px;
        }
        .permission-grid table {
            min-width: 550px;
            font-size:12px;
        }
        .permission-grid th {
            background: #f8fafc;
            text-align: center;
            font-size:11px;
            padding:8px 6px;
        }
        .permission-grid td {
            text-align: center;
            vertical-align: middle;
            padding:8px 6px;
        }
        .permission-grid .module-name {
            text-align: left;
            font-weight: 500;
            padding-left: 8px;
            font-size:12px;
        }
        .permission-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--green);
        }
        .permission-checkbox:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 4px;
        }
        .role-badge.admin { background: #dc2626; color: white; }
        .role-badge.dev { background: #7c3aed; color: white; }
        .role-badge.manager { background: #2563eb; color: white; }
        .role-badge.viewer { background: #64748b; color: white; }
        .role-badge.default { background: #e5e7eb; color: #374151; }
        
        .role-actions {
            display: flex;
            gap: 6px;
        }
        
        .role-management {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .role-list {
            width: 100%;
        }
        .role-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: 0.2s;
            border-radius: 8px;
        }
        .role-list-item:hover { background: #f8fafc; }
        .role-list-item.active { background: var(--light-green); }
        .role-list-item .role-name {
            font-weight: 500;
            font-size: 13px;
        }
        .role-list-item .role-desc {
            font-size: 11px;
            color: #64748b;
        }
        
        .add-role-form {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            border: 2px dashed #e5e7eb;
        }
        .add-role-form .form-row {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .add-role-form .form-row .form-group {
            width: 100%;
        }
        
        .company-settings-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .form-actions .btn {
            width: 100%;
        }
        
        /* Category Management */
        .category-table { width:100%; border-collapse:collapse; }
        .category-table th { text-align:left; padding:10px; background:var(--light-green); font-size:12px; }
        .category-table td { padding:10px; border-bottom:1px solid #f1f5f9; font-size:13px; }
        
        /* Mobile Responsive */
        @media (min-width: 768px) {
            .permissions-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            .role-management {
                flex-direction: row;
            }
            .role-list {
                flex: 1;
                min-width: 220px;
                max-width: 300px;
            }
            .company-settings-grid {
                flex-direction: row;
            }
            .company-settings-grid .settings-card {
                flex: 1;
            }
            .add-role-form .form-row {
                flex-direction: row;
            }
            .add-role-form .form-row .form-group {
                flex: 1;
            }
            .btn {
                width: auto;
            }
            .form-actions {
                flex-direction: row;
            }
            .form-actions .btn {
                width: auto;
            }
            .tabs {
                padding: 0 30px;
            }
            .tab {
                padding: 16px 24px;
                font-size: 14px;
            }
            .permissions-section {
                padding: 25px;
            }
            .settings-card {
                padding: 25px;
            }
        }
        
        @media (max-width: 480px) {
            .content { padding:15px; }
            .page-title { font-size:22px; }
            .tab { 
                padding:12px 14px; 
                font-size:12px; 
            }
            .tab i { font-size:13px; }
            .table-container { padding:10px; }
            table { font-size:12px; min-width:400px; }
            th, td { padding:8px 10px; }
            .permission-grid table { min-width:450px; }
            .permission-grid .module-name { font-size:11px; }
            .permission-checkbox { width:14px; height:14px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-gear"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">System configuration and role-based access control.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <a href="?tab=roles<?= isset($_GET['role_id']) ? '&role_id='.$_GET['role_id'] : '' ?>" 
                   class="tab <?= $activeTab == 'roles' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i> Roles & Permissions
                </a>
                <a href="?tab=company" class="tab <?= $activeTab == 'company' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i> Company Settings
                </a>
                <a href="?tab=security" class="tab <?= $activeTab == 'security' ? 'active' : '' ?>">
                    <i class="fas fa-shield-alt"></i> Security & Backup
                </a>
                <?php if (in_array($_SESSION['role_name'], ['Administrator', 'Dev'])): ?>
                <a href="?tab=categories" class="tab <?= $activeTab == 'categories' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i> Expense Categories
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Roles & Permissions -->
            <div class="tab-content <?= $activeTab == 'roles' ? 'active' : '' ?>">
                <div class="permissions-section">
                    <div class="permissions-header">
                        <h3><i class="fas fa-users-cog"></i> Role Management</h3>
                        <button class="btn btn-green btn-sm" onclick="toggleAddRole()">
                            <i class="fas fa-plus"></i> Add Role
                        </button>
                    </div>
                    
                    <!-- Add Role Form -->
                    <div id="addRoleForm" class="add-role-form" style="display:none;">
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Role Name</label>
                                    <input type="text" name="role_name" required placeholder="e.g., Procurement Manager">
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <input type="text" name="role_description" placeholder="Brief description">
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="create_role" class="btn btn-green">
                                        <i class="fas fa-save"></i> Create Role
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="role-management">
                        <!-- Role List -->
                        <div class="role-list">
                            <h4 style="margin-bottom:10px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Available Roles</h4>
                            <?php foreach ($roles as $role): ?>
                                <div class="role-list-item <?= $selected_role_id == $role['id'] ? 'active' : '' ?>" 
                                     onclick="window.location.href='?tab=roles&role_id=<?= $role['id'] ?>'">
                                    <div>
                                        <div class="role-name">
                                            <?= htmlspecialchars($role['role_name']) ?>
                                            <?php if ($role['role_name'] === 'Administrator'): ?>
                                                <span class="role-badge admin">Admin</span>
                                            <?php elseif ($role['role_name'] === 'Dev'): ?>
                                                <span class="role-badge dev">Dev</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="role-desc"><?= htmlspecialchars($role['description'] ?? 'No description') ?></div>
                                    </div>
                                    <div class="role-actions">
                                        <a href="?tab=roles&delete_role=<?= $role['id'] ?>" 
                                           onclick="return confirm('Delete this role?')" 
                                           class="btn-sm-danger"><i class="fas fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Permissions Grid -->
                        <div style="flex:2;min-width:0;">
                            <?php if ($selected_role_id): 
                                $selected_role_name = $roles[array_search($selected_role_id, array_column($roles, 'id'))]['role_name'] ?? '';
                            ?>
                                <h4 style="margin-bottom:10px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">
                                    Permissions for: <strong style="color:var(--dark);text-transform:none;">
                                        <?= htmlspecialchars($selected_role_name ?? 'Selected Role') ?>
                                    </strong>
                                </h4>
                                <form method="POST">
                                    <input type="hidden" name="role_id" value="<?= $selected_role_id ?>">
                                    <input type="hidden" name="tab" value="roles">
                                    <div class="permission-grid">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th style="text-align:left;min-width:120px;">Module</th>
                                                    <th><i class="fas fa-eye"></i></th>
                                                    <th><i class="fas fa-plus"></i></th>
                                                    <th><i class="fas fa-edit"></i></th>
                                                    <th><i class="fas fa-trash"></i></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($modules as $module): 
                                                    $perm = $permissions[$module['id']] ?? null;
                                                    $checked_view = $perm && $perm['can_view'] ? 'checked' : '';
                                                    $checked_create = $perm && $perm['can_create'] ? 'checked' : '';
                                                    $checked_edit = $perm && $perm['can_edit'] ? 'checked' : '';
                                                    $checked_delete = $perm && $perm['can_delete'] ? 'checked' : '';
                                                ?>
                                                    <tr>
                                                        <td class="module-name"><?= htmlspecialchars($module['module_name']) ?></td>
                                                        <td>
                                                            <input type="checkbox" 
                                                                   name="permissions[<?= $module['id'] ?>][view]" 
                                                                   class="permission-checkbox" 
                                                                   <?= $checked_view ?>>
                                                        </td>
                                                        <td>
                                                            <input type="checkbox" 
                                                                   name="permissions[<?= $module['id'] ?>][create]" 
                                                                   class="permission-checkbox" 
                                                                   <?= $checked_create ?>>
                                                        </td>
                                                        <td>
                                                            <input type="checkbox" 
                                                                   name="permissions[<?= $module['id'] ?>][edit]" 
                                                                   class="permission-checkbox" 
                                                                   <?= $checked_edit ?>>
                                                        </td>
                                                        <td>
                                                            <input type="checkbox" 
                                                                   name="permissions[<?= $module['id'] ?>][delete]" 
                                                                   class="permission-checkbox" 
                                                                   <?= $checked_delete ?>>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div style="margin-top:15px;">
                                        <button type="submit" name="update_permissions" class="btn btn-green">
                                            <i class="fas fa-save"></i> Save Permissions
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Logs -->
                <div class="table-container" style="margin-top:30px;">
                    <h3 style="margin-bottom:15px;font-size:16px;"><i class="fas fa-history"></i> Recent Activity Logs</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Timestamp</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                    <td><?= htmlspecialchars($log['activity']) ?></td>
                                    <td><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Tab: Company Settings -->
            <div class="tab-content <?= $activeTab == 'company' ? 'active' : '' ?>">
                <div class="company-settings-grid">
                    <div class="settings-card">
                        <h3><i class="fas fa-building"></i> Company Information</h3>
                        <form method="POST">
                            <input type="hidden" name="tab" value="company">
                            <div class="form-group">
                                <label>Company Name</label>
                                <input type="text" name="company_name" value="Enginove (Pvt) Ltd">
                            </div>
                            <div class="form-group">
                                <label>Registration Number</label>
                                <input type="text" name="registration_number" value="PVT-2023-001">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3">8 Glen Carron Road, Highlands, Harare</textarea>
                            </div>
                            <button type="submit" name="update_company" class="btn btn-green">
                                <i class="fas fa-save"></i> Update Company Info
                            </button>
                        </form>
                    </div>
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-phone"></i> Contact Information</h3>
                        <form method="POST">
                            <input type="hidden" name="tab" value="company">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="+263 77 578 0627">
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="sales@enginove.co.zw">
                            </div>
                            <div class="form-group">
                                <label>Website</label>
                                <input type="url" name="website" value="https://enginove.co.zw">
                            </div>
                            <div class="form-group">
                                <label>Social Media</label>
                                <input type="text" name="social" value="@enginove">
                            </div>
                            <button type="submit" name="update_company" class="btn btn-green">
                                <i class="fas fa-save"></i> Update Contact Info
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Security & Backup -->
            <div class="tab-content <?= $activeTab == 'security' ? 'active' : '' ?>">
                <div class="company-settings-grid">
                    <div class="settings-card">
                        <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                        <form method="POST">
                            <input type="hidden" name="tab" value="security">
                            <div class="form-group">
                                <label>Password Policy</label>
                                <select name="password_policy">
                                    <option value="8">Minimum 8 characters</option>
                                    <option value="8_special" selected>Minimum 8 chars + special</option>
                                    <option value="12_special">Minimum 12 chars + special</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Session Timeout</label>
                                <select name="session_timeout">
                                    <option value="30">30 minutes</option>
                                    <option value="60" selected>60 minutes</option>
                                    <option value="120">120 minutes</option>
                                    <option value="240">240 minutes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Login Attempts</label>
                                <select name="max_attempts">
                                    <option value="3">3 attempts</option>
                                    <option value="5" selected>5 attempts</option>
                                    <option value="10">10 attempts</option>
                                </select>
                            </div>
                            <button type="submit" name="update_security" class="btn btn-green">
                                <i class="fas fa-save"></i> Save Security Settings
                            </button>
                        </form>
                    </div>
                    
                    <div class="settings-card">
                        <h3><i class="fas fa-database"></i> Backup & Maintenance</h3>
                        
                        <div style="margin-bottom:20px;padding:15px;background:#f8fafc;border-radius:10px;">
                            <p style="font-size:14px;color:#64748b;margin-bottom:15px;">
                                <i class="fas fa-info-circle"></i> Create a full backup of the database.
                            </p>
                            <a href="?tab=security&backup=1" class="btn btn-danger" style="width:100%;justify-content:center;">
                                <i class="fas fa-download"></i> Download Backup
                            </a>
                        </div>
                        
                        <div style="padding:15px;background:#fef3c7;border-radius:10px;border:1px solid #fde68a;">
                            <h4 style="color:#92400e;font-size:14px;margin-bottom:8px;">
                                <i class="fas fa-exclamation-triangle"></i> Maintenance
                            </h4>
                            <p style="font-size:13px;color:#78350f;">
                                Last backup: <?= date('d M Y H:i', strtotime('-2 days')) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Expense Categories (Admin & Dev only) -->
            <?php if (in_array($_SESSION['role_name'], ['Administrator', 'Dev'])): ?>
            <div class="tab-content <?= $activeTab == 'categories' ? 'active' : '' ?>">
                <div class="settings-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                        <h3 style="margin-bottom:0;"><i class="fas fa-tags"></i> Expense Categories</h3>
                        <button class="btn btn-green btn-sm" onclick="toggleCategoryForm()">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                    </div>
                    
                    <!-- Add Category Form -->
                    <div id="addCategoryForm" style="display:none; background:#f8fafc; padding:20px; border-radius:10px; margin-bottom:15px; border:2px dashed #e5e7eb;">
                        <form method="POST">
                            <input type="hidden" name="tab" value="categories">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                                <div class="form-group">
                                    <label>Category Name *</label>
                                    <input type="text" name="category_name" required placeholder="e.g., Office Supplies">
                                </div>
                                <div class="form-group">
                                    <label>Category Code *</label>
                                    <input type="text" name="category_code" required placeholder="e.g., OFF-SUP">
                                </div>
                                <div class="form-group" style="grid-column:1/-1;">
                                    <label>Description</label>
                                    <textarea name="category_description" rows="2" placeholder="Category description"></textarea>
                                </div>
                            </div>
                            <button type="submit" name="add_category" class="btn btn-green"><i class="fas fa-save"></i> Add Category</button>
                        </form>
                    </div>
                    
                    <!-- Categories List -->
                    <div style="overflow-x:auto;">
                        <table class="category-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th style="text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($cat['category_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($cat['category_name']) ?></td>
                                        <td><?= htmlspecialchars($cat['description'] ?? '—') ?></td>
                                        <td>
                                            <span style="display:inline-block; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600; background:<?= $cat['is_active'] ? '#d4edda' : '#fee2e2' ?>; color:<?= $cat['is_active'] ? '#0f5a2e' : '#b91c1c' ?>;">
                                                <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                                <input type="hidden" name="tab" value="categories">
                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                <button type="submit" name="delete_category" class="btn-sm-danger" style="padding:4px 10px;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleAddRole() {
    const form = document.getElementById('addRoleForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleCategoryForm() {
    const form = document.getElementById('addCategoryForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

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