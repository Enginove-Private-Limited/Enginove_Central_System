<?php
// todos.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Todos";
$message = '';

// Handle create/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $stmt = $pdo->prepare("
            INSERT INTO todos (assigned_to, title, description, due_date, priority, status) 
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([
            $_POST['assigned_to'] ?: $_SESSION['user_id'],
            $_POST['title'],
            $_POST['description'],
            $_POST['due_date'],
            $_POST['priority']
        ]);
        $message = 'Task added successfully!';
    }
    
    if ($action === 'toggle') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("
            UPDATE todos SET status = IF(status = 'Pending', 'Completed', 'Pending') 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $message = 'Task status updated!';
    }
    
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Task deleted!';
    }
}

// Get todos for current user or all if admin
$user_id = $_SESSION['user_id'];
$todos = $pdo->prepare("
    SELECT t.*, u.first_name, u.last_name 
    FROM todos t
    LEFT JOIN users u ON u.id = t.assigned_to
    WHERE t.assigned_to = ? OR ? IN (SELECT user_id FROM user_roles WHERE role_id = 1)
    ORDER BY FIELD(t.status, 'Pending', 'Completed'), t.due_date ASC
");
$todos->execute([$user_id, $user_id]);
$todos = $todos->fetchAll();

$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'ACTIVE'")->fetchAll();
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
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-sm { padding:6px 14px; font-size:13px; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .actions { display:flex; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .todo-list { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .todo-column { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); }
        .todo-column h3 { margin-bottom:15px; padding-bottom:10px; border-bottom:2px solid #e5e7eb; }
        .todo-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid #f3f4f6; }
        .todo-item:last-child { border-bottom:none; }
        .todo-item .info { flex:1; }
        .todo-item .info h4 { font-size:15px; color:var(--dark); }
        .todo-item .info p { font-size:13px; color:#64748b; }
        .todo-item .info .meta { display:flex; gap:15px; font-size:12px; margin-top:4px; }
        .todo-item .info .meta .priority-high { color:#dc2626; }
        .todo-item .info .meta .priority-medium { color:#b45309; }
        .todo-item .info .meta .priority-low { color:#6b7280; }
        .todo-item input[type="checkbox"] { width:20px; height:20px; accent-color:var(--green); cursor:pointer; flex-shrink:0; }
        .todo-item .actions { display:flex; gap:8px; }
        .todo-item.completed .info h4 { text-decoration:line-through; opacity:0.6; }
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:500px; }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
        @media(max-width:991px) { .main { margin-left:0; } .todo-list { grid-template-columns:1fr; } }
        @media(max-width:768px) { .content { padding:15px; } .page-title { font-size:22px; } .actions { flex-direction:column; } }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-list-check"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Manage your tasks and todos.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            
            <div class="actions">
                <button class="btn btn-green" onclick="openModal()"><i class="fas fa-plus"></i> New Task</button>
                <span style="color:#64748b;"><?= count(array_filter($todos, fn($t) => $t['status'] == 'Pending')) ?> pending</span>
            </div>
            
            <div class="todo-list">
                <div class="todo-column">
                    <h3><i class="fas fa-clock" style="color:#b45309;"></i> Pending</h3>
                    <?php foreach ($todos as $todo): if ($todo['status'] == 'Pending'): ?>
                        <div class="todo-item">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $todo['id'] ?>">
                                <input type="checkbox" onchange="this.form.submit()">
                            </form>
                            <div class="info">
                                <h4><?= htmlspecialchars($todo['title']) ?></h4>
                                <p><?= htmlspecialchars($todo['description']) ?></p>
                                <div class="meta">
                                    <span>Due: <?= date('d M Y', strtotime($todo['due_date'])) ?></span>
                                    <span class="priority-<?= strtolower($todo['priority']) ?>"><?= $todo['priority'] ?></span>
                                    <span>👤 <?= htmlspecialchars($todo['first_name'] ?? 'Me') ?></span>
                                </div>
                            </div>
                            <div class="actions">
                                <form method="POST" onsubmit="return confirm('Delete this task?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $todo['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                    <?php if (!array_filter($todos, fn($t) => $t['status'] == 'Pending')): ?>
                        <p style="color:#64748b;padding:20px 0;text-align:center;">No pending tasks 🎉</p>
                    <?php endif; ?>
                </div>
                
                <div class="todo-column">
                    <h3><i class="fas fa-check-circle" style="color:var(--green);"></i> Completed</h3>
                    <?php foreach ($todos as $todo): if ($todo['status'] == 'Completed'): ?>
                        <div class="todo-item completed">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $todo['id'] ?>">
                                <input type="checkbox" checked onchange="this.form.submit()">
                            </form>
                            <div class="info">
                                <h4><?= htmlspecialchars($todo['title']) ?></h4>
                                <div class="meta">
                                    <span>Due: <?= date('d M Y', strtotime($todo['due_date'])) ?></span>
                                    <span>✅ Done</span>
                                </div>
                            </div>
                            <div class="actions">
                                <form method="POST" onsubmit="return confirm('Delete this task?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $todo['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                    <?php if (!array_filter($todos, fn($t) => $t['status'] == 'Completed')): ?>
                        <p style="color:#64748b;padding:20px 0;text-align:center;">No completed tasks yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Task Modal -->
<div class="modal" id="todoModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> New Task</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" placeholder="Task title" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Task description"></textarea>
            </div>
            <div class="form-group">
                <label>Assigned To</label>
                <select name="assigned_to">
                    <option value="<?= $_SESSION['user_id'] ?>">Myself</option>
                    <?php foreach ($users as $u): if ($u['id'] != $_SESSION['user_id']): ?>
                        <option value="<?= $u['id'] ?>"><?= $u['first_name'] . ' ' . $u['last_name'] ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Due Date *</label>
                <input type="date" name="due_date" required>
            </div>
            <div class="form-group">
                <label>Priority</label>
                <select name="priority">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                </select>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Create Task</button>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('todoModal').classList.add('show'); }
function closeModal() { document.getElementById('todoModal').classList.remove('show'); }
window.onclick = function(e) { if (e.target === document.getElementById('todoModal')) closeModal(); }
</script>
</body>
</html>