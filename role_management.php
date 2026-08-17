<?php
// role_management.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Role Management";
$message = '';
$error = '';

// Only Admin and Dev can access this page
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
        
        // Log the activity - using the function from database.php
        logActivity($pdo, $_SESSION['user_id'], "Updated permissions for role ID: " . $_POST['role_id']);
        
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
            logActivity($pdo, $_SESSION['user_id'], "Created new role: " . $_POST['role_name']);
        }
    } catch (Exception $e) {
        $error = "Error creating role: " . $e->getMessage();
    }
}

// Handle role deletion
if (isset($_GET['delete_role'])) {
    $role_id = (int)$_GET['delete_role'];
    try {
        // Prevent deletion of Admin and Dev roles
        $check = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
        $check->execute([$role_id]);
        $role_name = $check->fetchColumn();
        
        if (in_array($role_name, ['Administrator', 'Dev'])) {
            $error = "Cannot delete the '" . $role_name . "' role!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$role_id]);
            $message = "Role deleted successfully!";
            logActivity($pdo, $_SESSION['user_id'], "Deleted role ID: " . $role_id);
        }
    } catch (Exception $e) {
        $error = "Error deleting role: " . $e->getMessage();
    }
}

// Handle expense category actions (for Dev role)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add category
    if (isset($_POST['add_category'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO expense_categories (category_name, category_code, description) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['category_name'], $_POST['category_code'], $_POST['category_description']]);
            $message = "Expense category added successfully!";
            logActivity($pdo, $_SESSION['user_id'], "Added expense category: " . $_POST['category_name']);
        } catch (Exception $e) {
            $error = "Error adding category: " . $e->getMessage();
        }
    }
    
    // Edit category
    if (isset($_POST['edit_category'])) {
        try {
            $stmt = $pdo->prepare("UPDATE expense_categories SET category_name = ?, category_code = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([
                $_POST['category_name'],
                $_POST['category_code'],
                $_POST['category_description'],
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['category_id']
            ]);
            $message = "Expense category updated successfully!";
            logActivity($pdo, $_SESSION['user_id'], "Updated expense category: " . $_POST['category_name']);
        } catch (Exception $e) {
            $error = "Error updating category: " . $e->getMessage();
        }
    }
    
    // Delete category
    if (isset($_POST['delete_category'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
            $stmt->execute([$_POST['category_id']]);
            $message = "Expense category deleted successfully!";
            logActivity($pdo, $_SESSION['user_id'], "Deleted expense category ID: " . $_POST['category_id']);
        } catch (Exception $e) {
            $error = "Error deleting category: " . $e->getMessage();
        }
    }
}

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
<!-- Rest of your HTML remains the same -->

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
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-1px); box-shadow:0 4px 12px rgba(31,139,76,0.3); }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-sm { padding:6px 14px; font-size:12px; }
        .btn-sm-danger { background:#fee2e2; color:#dc2626; border:none; cursor:pointer; padding:4px 12px; font-size:12px; border-radius:6px; transition:0.2s; }
        .btn-sm-danger:hover { background:#fecaca; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        
        .grid-2col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        
        .permission-grid { overflow-x:auto; }
        .permission-grid table { width:100%; border-collapse:collapse; min-width:600px; }
        .permission-grid th { background:#f8fafc; text-align:center; padding:10px; font-size:12px; }
        .permission-grid td { text-align:center; padding:8px; border-bottom:1px solid #f1f5f9; }
        .permission-grid .module-name { text-align:left; font-weight:500; font-size:13px; }
        .permission-checkbox { width:16px; height:16px; cursor:pointer; accent-color:var(--green); }
        .permission-checkbox:disabled { opacity:0.5; cursor:not-allowed; }
        
        .role-list { max-height:400px; overflow-y:auto; }
        .role-list-item { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #f1f5f9; cursor:pointer; transition:0.2s; border-radius:8px; }
        .role-list-item:hover { background:#f8fafc; }
        .role-list-item.active { background:var(--light-green); }
        .role-list-item .role-name { font-weight:500; font-size:14px; }
        .role-list-item .role-desc { font-size:12px; color:#64748b; }
        
        .role-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:10px; font-weight:600; margin-left:6px; }
        .role-badge.admin { background:#dc2626; color:white; }
        .role-badge.dev { background:#7c3aed; color:white; }
        .role-badge.manager { background:#2563eb; color:white; }
        .role-badge.viewer { background:#64748b; color:white; }
        .role-badge.default { background:#e5e7eb; color:#374151; }
        
        .category-table { width:100%; border-collapse:collapse; }
        .category-table th { text-align:left; padding:10px; background:var(--light-green); font-size:12px; }
        .category-table td { padding:10px; border-bottom:1px solid #f1f5f9; font-size:13px; }
        
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; color:#374151; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; transition:.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--green); outline:none; box-shadow:0 0 0 3px rgba(31,139,76,0.1); }
        .form-group textarea { resize:vertical; min-height:60px; }
        
        .add-role-form { background:#f8fafc; padding:20px; border-radius:10px; margin-top:15px; border:2px dashed #e5e7eb; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; } 
            .grid-2col { grid-template-columns:1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-title { font-size:22px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-user-cog"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Manage roles, permissions, and expense categories.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <div class="grid-2col">
                <!-- Left Column: Roles & Permissions -->
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                        <h3 style="margin-bottom:0;"><i class="fas fa-users"></i> Roles</h3>
                        <button class="btn btn-green btn-sm" onclick="toggleAddRole()">
                            <i class="fas fa-plus"></i> Add Role
                        </button>
                    </div>
                    
                    <!-- Add Role Form -->
                    <div id="addRoleForm" class="add-role-form" style="display:none;">
                        <form method="POST">
                            <div class="form-group">
                                <label>Role Name *</label>
                                <input type="text" name="role_name" required placeholder="e.g., Developer">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="role_description" placeholder="Brief description">
                            </div>
                            <button type="submit" name="create_role" class="btn btn-green" style="width:100%;">
                                <i class="fas fa-save"></i> Create Role
                            </button>
                        </form>
                    </div>
                    
                    <div class="role-list">
                        <?php foreach ($roles as $role): ?>
                            <div class="role-list-item <?= $selected_role_id == $role['id'] ? 'active' : '' ?>" 
                                 onclick="window.location.href='?role_id=<?= $role['id'] ?>'">
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
                                <?php if (!in_array($role['role_name'], ['Administrator', 'Dev'])): ?>
                                    <a href="?delete_role=<?= $role['id'] ?>" 
                                       onclick="return confirm('Delete this role?')" 
                                       class="btn-sm-danger"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Right Column: Permissions -->
                <div class="card">
                    <h3><i class="fas fa-lock"></i> Permissions</h3>
                    <?php if ($selected_role_id): 
                        $selected_role_name = $roles[array_search($selected_role_id, array_column($roles, 'id'))]['role_name'] ?? '';
                        $isProtected = in_array($selected_role_name, ['Administrator', 'Dev']);
                    ?>
                        <p style="font-size:13px; color:#64748b; margin-bottom:15px;">
                            <strong><?= htmlspecialchars($selected_role_name) ?></strong> 
                            <?php if ($isProtected): ?>
                                <span style="background:#fef3c7; color:#92400e; padding:2px 10px; border-radius:12px; font-size:11px; margin-left:5px;">
                                    <i class="fas fa-shield-alt"></i> Protected Role
                                </span>
                            <?php endif; ?>
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="role_id" value="<?= $selected_role_id ?>">
                            <div class="permission-grid">
                                <table>
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;">Module</th>
                                            <th><i class="fas fa-eye"></i></th>
                                            <th><i class="fas fa-plus"></i></th>
                                            <th><i class="fas fa-edit"></i></th>
                                            <th><i class="fas fa-trash"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modules as $module): 
                                            $perm = $permissions[$module['id']] ?? null;
                                            $checked_view = $isProtected ? 'checked' : ($perm && $perm['can_view'] ? 'checked' : '');
                                            $checked_create = $isProtected ? 'checked' : ($perm && $perm['can_create'] ? 'checked' : '');
                                            $checked_edit = $isProtected ? 'checked' : ($perm && $perm['can_edit'] ? 'checked' : '');
                                            $checked_delete = $isProtected ? 'checked' : ($perm && $perm['can_delete'] ? 'checked' : '');
                                            $disabled = $isProtected ? 'disabled' : '';
                                        ?>
                                            <tr>
                                                <td class="module-name"><?= htmlspecialchars($module['module_name']) ?></td>
                                                <td><input type="checkbox" name="permissions[<?= $module['id'] ?>][view]" class="permission-checkbox" <?= $checked_view ?> <?= $disabled ?>></td>
                                                <td><input type="checkbox" name="permissions[<?= $module['id'] ?>][create]" class="permission-checkbox" <?= $checked_create ?> <?= $disabled ?>></td>
                                                <td><input type="checkbox" name="permissions[<?= $module['id'] ?>][edit]" class="permission-checkbox" <?= $checked_edit ?> <?= $disabled ?>></td>
                                                <td><input type="checkbox" name="permissions[<?= $module['id'] ?>][delete]" class="permission-checkbox" <?= $checked_delete ?> <?= $disabled ?>></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (!$isProtected): ?>
                                <div style="margin-top:15px;">
                                    <button type="submit" name="update_permissions" class="btn btn-green" style="width:100%;">
                                        <i class="fas fa-save"></i> Save Permissions
                                    </button>
                                </div>
                            <?php else: ?>
                                <div style="margin-top:15px; padding:12px 16px; background:#fef3c7; border-radius:8px; color:#92400e; font-size:13px; display:flex; align-items:center; gap:10px;">
                                    <i class="fas fa-info-circle"></i> 
                                    <span>The <?= $selected_role_name ?> role has full access to all modules.</span>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Expense Categories Management (Only for Admin and Dev) -->
            <?php if (in_array($_SESSION['role_name'], ['Administrator', 'Dev'])): ?>
            <div class="card" style="margin-top:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                    <h3 style="margin-bottom:0;"><i class="fas fa-tags"></i> Expense Categories</h3>
                    <button class="btn btn-green btn-sm" onclick="toggleCategoryForm()">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </div>
                
                <!-- Add Category Form -->
                <div id="addCategoryForm" style="display:none; background:#f8fafc; padding:20px; border-radius:10px; margin-bottom:15px; border:2px dashed #e5e7eb;">
                    <form method="POST">
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
                                        <button onclick="editCategory(<?= $cat['id'] ?>)" class="btn btn-outline" style="padding:4px 10px; font-size:11px;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?');">
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

function editCategory(id) {
    // Simple edit functionality - you can expand this
    alert('Edit category ID: ' + id + '\n(Full edit functionality can be added here)');
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