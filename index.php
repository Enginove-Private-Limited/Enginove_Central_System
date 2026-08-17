<?php
// index.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Dashboard";

// Get comprehensive stats
$stats = $pdo->query("
    SELECT 
        -- Tenders
        (SELECT COUNT(*) FROM tenders) as total_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Open') as open_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Draft') as draft_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Submitted') as submitted_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Awarded') as awarded_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Lost') as lost_tenders,
        (SELECT COUNT(*) FROM tenders WHERE status = 'Cancelled') as cancelled_tenders,
        (SELECT COUNT(*) FROM tenders WHERE due_date < CURDATE() AND status IN ('Open', 'Draft')) as overdue_tenders,
        
        -- Purchase Orders
        (SELECT COUNT(*) FROM purchase_orders) as total_po,
        (SELECT COUNT(*) FROM purchase_orders WHERE status = 'Pending') as pending_po,
        (SELECT COUNT(*) FROM purchase_orders WHERE status = 'Approved') as approved_po,
        (SELECT COUNT(*) FROM purchase_orders WHERE status = 'Ordered') as ordered_po,
        (SELECT COUNT(*) FROM purchase_orders WHERE status = 'Received') as received_po,
        
        -- Suppliers & Artisans
        (SELECT COUNT(*) FROM suppliers) as total_suppliers,
        (SELECT COUNT(*) FROM artisans) as total_artisans,
        
        -- Todos
        (SELECT COUNT(*) FROM todos WHERE status = 'Pending') as pending_todos,
        (SELECT COUNT(*) FROM todos WHERE status = 'Completed') as completed_todos,
        
        -- Expenses
        (SELECT COUNT(*) FROM expenses) as total_expenses,
        (SELECT COALESCE(SUM(total_usd), 0) FROM expenses) as total_expenses_usd,
        (SELECT COALESCE(SUM(total_zwg), 0) FROM expenses) as total_expenses_zwg,
        
        -- Checklists
        (SELECT COUNT(*) FROM tender_checklists) as total_checklists,
        (SELECT COUNT(*) FROM tender_checklists WHERE status IN ('Draft', 'In Progress')) as active_checklists,
        (SELECT COUNT(*) FROM tender_checklists WHERE status = 'Ready for Review') as ready_review_checklists,
        (SELECT COUNT(*) FROM tender_checklists WHERE status = 'Approved') as approved_checklists,
        
        -- UZ Submissions
        (SELECT COUNT(*) FROM uz_submissions) as total_uz,
        (SELECT COUNT(*) FROM uz_submissions WHERE status = 'Pending') as uz_pending,
        (SELECT COUNT(*) FROM uz_submissions WHERE status = 'Submitted') as uz_submitted,
        (SELECT COUNT(*) FROM uz_submissions WHERE status = 'Awarded') as uz_awarded,
        
        -- Supplier Registrations
        (SELECT COUNT(*) FROM supplier_registrations) as total_reg,
        (SELECT COUNT(*) FROM supplier_registrations WHERE status = 'Pending') as reg_pending,
        (SELECT COUNT(*) FROM supplier_registrations WHERE status = 'Approved') as reg_approved,
        
        -- Site Visits
        (SELECT COUNT(*) FROM site_visits) as total_visits,
        (SELECT COUNT(*) FROM site_visits WHERE status = 'Scheduled') as scheduled_visits,
        (SELECT COUNT(*) FROM site_visits WHERE status = 'Completed') as completed_visits,
        (SELECT COUNT(*) FROM site_visits WHERE visit_date < CURDATE() AND status = 'Scheduled') as overdue_visits,
        
        -- Users
        (SELECT COUNT(*) FROM users WHERE status = 'ACTIVE') as active_users,
        (SELECT COUNT(*) FROM users WHERE status = 'DISABLED') as disabled_users,
        
        -- Memos
        (SELECT COUNT(*) FROM memos) as total_memos,
        (SELECT COUNT(*) FROM memos WHERE status = 'Sent') as sent_memos,
        
        -- Activity Logs (last 7 days)
        (SELECT COUNT(*) FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)) as activity_last_7d,
        (SELECT COUNT(*) FROM activity_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)) as activity_today
")->fetch();

// Get recent tenders with more details
$recent_tenders = $pdo->query("
    SELECT t.*, 
           u.first_name, u.last_name,
           CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_name
    FROM tenders t
    LEFT JOIN users u ON u.id = t.created_by
    LEFT JOIN users assigned ON assigned.id = t.assigned_to
    ORDER BY t.created_at DESC LIMIT 5
")->fetchAll();

// Get upcoming deadlines (tenders due in next 7 days)
$upcoming_deadlines = $pdo->query("
    SELECT t.*, 
           CONCAT(u.first_name, ' ', u.last_name) as assigned_name,
           DATEDIFF(t.due_date, CURDATE()) as days_left
    FROM tenders t
    LEFT JOIN users u ON u.id = t.assigned_to
    WHERE t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND t.status IN ('Open', 'Draft')
    ORDER BY t.due_date ASC
    LIMIT 5
")->fetchAll();

// Get user's todos
$user_todos = $pdo->prepare("
    SELECT * FROM todos 
    WHERE assigned_to = ? AND status = 'Pending'
    ORDER BY due_date ASC LIMIT 5
");
$user_todos->execute([$_SESSION['user_id']]);
$user_todos = $user_todos->fetchAll();

// Get recent expenses
$recent_expenses = $pdo->query("
    SELECT e.*, ec.category_name 
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    ORDER BY e.expense_date DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | Enginove Central System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
        :root { --green:#1f8b4c; --light-green:#d4edda; --dark:#1e2a2f; --off-white:#f4f9f6; --white:#ffffff; }
        body { background:var(--off-white); }
        .wrapper { display:flex; min-height:100vh; }
        .main { flex:1; margin-left:270px; transition:.3s; }
        .content { padding:30px; }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .dashboard-header .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .dashboard-header .subtitle {
            margin: 0;
            font-size: 15px;
            color: #64748b;
        }
        .welcome-text {
            font-size: 14px;
            color: #64748b;
        }
        
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 18px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border-left: 4px solid var(--green);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .stat-card .stat-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-top: 4px;
        }
        .stat-card .stat-icon {
            float: right;
            font-size: 24px;
            opacity: 0.2;
            margin-top: -10px;
        }
        .stat-card .stat-link {
            font-size: 11px;
            color: var(--green);
            font-weight: 500;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .stat-card .stat-link:hover {
            text-decoration: underline;
        }
        .stat-card.blue { border-left-color: #2563eb; }
        .stat-card.orange { border-left-color: #f59e0b; }
        .stat-card.red { border-left-color: #dc2626; }
        .stat-card.purple { border-left-color: #7c3aed; }
        .stat-card.pink { border-left-color: #ec4899; }
        .stat-card.teal { border-left-color: #14b8a6; }
        .stat-card.indigo { border-left-color: #4f46e5; }
        
        .grid { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-top:25px; }
        .table-card { background:white; padding:20px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.04); overflow-x:auto; }
        .table-card h3 { margin-bottom:15px; color:var(--dark); font-size:16px; display:flex; align-items:center; gap:8px; }
        .table-card h3 a { font-size:12px; color:var(--green); text-decoration:none; margin-left:auto; font-weight:500; }
        .table-card h3 a:hover { text-decoration:underline; }
        
        table { width:100%; border-collapse:collapse; min-width:500px; font-size:13px; }
        th { text-align:left; padding:10px 12px; background:var(--light-green); font-weight:600; font-size:12px; }
        td { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
        tr:hover td { background:#fafdfb; }
        
        .status { padding:3px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
        .status-open { background:var(--light-green); color:var(--green); }
        .status-draft { background:#e5e7eb; color:#6b7280; }
        .status-submitted { background:#fef3c7; color:#b45309; }
        .status-awarded { background:#dbeafe; color:#1d4ed8; }
        .status-pending { background:#fef3c7; color:#b45309; }
        .status-approved { background:#d4edda; color:#0f5a2e; }
        .status-lost { background:#fee2e2; color:#b91c1c; }
        .status-cancelled { background:#f3f4f6; color:#6b7280; }
        .status-scheduled { background:#dbeafe; color:#1d4ed8; }
        .status-completed { background:#d4edda; color:#0f5a2e; }
        
        .todo-card { background:white; padding:20px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.04); }
        .todo-card h3 { margin-bottom:15px; font-size:16px; display:flex; align-items:center; gap:8px; }
        .todo-card h3 a { font-size:12px; color:var(--green); text-decoration:none; margin-left:auto; font-weight:500; }
        .todo-card h3 a:hover { text-decoration:underline; }
        .todo-item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #f3f4f6; }
        .todo-item:last-child { border-bottom:none; }
        .todo-item input[type="checkbox"] { width:18px; height:18px; accent-color:var(--green); cursor:pointer; flex-shrink:0; }
        .todo-item span { font-size:13px; color:var(--dark); flex:1; }
        .todo-item .priority { font-size:10px; padding:2px 10px; border-radius:12px; flex-shrink:0; }
        .priority-high { background:#fee2e2; color:#b91c1c; }
        .priority-medium { background:#fef3c7; color:#b45309; }
        .priority-low { background:#e5e7eb; color:#6b7280; }
        .empty-state { color:#94a3b8; text-align:center; padding:20px 0; font-size:13px; }
        
        .expense-amount-usd { color:#2563eb; font-weight:600; }
        .expense-amount-zwg { color:#f59e0b; font-weight:600; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; } 
            .grid { grid-template-columns:1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .dashboard-header .page-title { font-size:22px; }
            .cards { grid-template-columns:repeat(2,1fr); }
            .stat-card .stat-value { font-size:22px; }
        }
        @media(max-width:480px) { 
            .cards { grid-template-columns:1fr; }
            .stat-card { padding:14px 16px; }
            .stat-card .stat-value { font-size:20px; }
            .dashboard-header { flex-direction:column; align-items:flex-start; gap:5px; }
            .dashboard-header .page-title { font-size:20px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <div class="dashboard-header">
                <div>
                    <h1 class="page-title"><i class="fas fa-chart-pie" style="color:var(--green);"></i> <?= $pageTitle ?></h1>
                    <p class="subtitle">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>! Here's your system overview.</p>
                </div>
                <div class="welcome-text">
                    <i class="fas fa-calendar-alt"></i> <?= date('l, d F Y') ?>
                </div>
            </div>
            
            <!-- Stats Cards - Row 1: Tenders -->
            <div class="cards">
                <a href="tender_ongoing.php" class="stat-card">
                    <div class="stat-label"><i class="fas fa-file-signature"></i> Total Tenders</div>
                    <div class="stat-value"><?= $stats['total_tenders'] ?></div>
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                    <div class="stat-link">View all →</div>
                </a>
                <a href="tender_ongoing.php?status=Open" class="stat-card orange">
                    <div class="stat-label"><i class="fas fa-clock"></i> Open Tenders</div>
                    <div class="stat-value"><?= $stats['open_tenders'] ?></div>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-link">View open →</div>
                </a>
                <a href="tender_submitted.php" class="stat-card blue">
                    <div class="stat-label"><i class="fas fa-paper-plane"></i> Submitted</div>
                    <div class="stat-value"><?= $stats['submitted_tenders'] ?></div>
                    <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                    <div class="stat-link">View submitted →</div>
                </a>
                <a href="tender_ongoing.php" class="stat-card red">
                    <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> Overdue</div>
                    <div class="stat-value"><?= $stats['overdue_tenders'] ?></div>
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-link">View overdue →</div>
                </a>
                <a href="tender_ongoing.php?tab=dashboard" class="stat-card purple">
                    <div class="stat-label"><i class="fas fa-award"></i> Awarded</div>
                    <div class="stat-value"><?= $stats['awarded_tenders'] ?></div>
                    <div class="stat-icon"><i class="fas fa-award"></i></div>
                    <div class="stat-link">View awarded →</div>
                </a>
            </div>
            
            <!-- Stats Cards - Row 2: Financial & Resources -->
            <div class="cards">
                <a href="expenses.php" class="stat-card teal">
                    <div class="stat-label"><i class="fas fa-coins"></i> Expenses</div>
                    <div class="stat-value"><?= $stats['total_expenses'] ?></div>
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                    <div class="stat-link" style="font-size:10px;">
                        USD $<?= number_format($stats['total_expenses_usd'], 2) ?> | ZWG <?= number_format($stats['total_expenses_zwg'], 2) ?>
                    </div>
                </a>
                <a href="purchase_orders.php" class="stat-card blue">
                    <div class="stat-label"><i class="fas fa-shopping-cart"></i> Purchase Orders</div>
                    <div class="stat-value"><?= $stats['total_po'] ?></div>
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-link"><?= $stats['pending_po'] ?> pending →</div>
                </a>
                <a href="suppliers.php" class="stat-card orange">
                    <div class="stat-label"><i class="fas fa-truck"></i> Suppliers</div>
                    <div class="stat-value"><?= $stats['total_suppliers'] ?></div>
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                    <div class="stat-link">View suppliers →</div>
                </a>
                <a href="artisans.php" class="stat-card purple">
                    <div class="stat-label"><i class="fas fa-screwdriver-wrench"></i> Artisans</div>
                    <div class="stat-value"><?= $stats['total_artisans'] ?></div>
                    <div class="stat-icon"><i class="fas fa-screwdriver-wrench"></i></div>
                    <div class="stat-link">View artisans →</div>
                </a>
                <a href="todos.php" class="stat-card red">
                    <div class="stat-label"><i class="fas fa-list-check"></i> Pending Todos</div>
                    <div class="stat-value"><?= $stats['pending_todos'] ?></div>
                    <div class="stat-icon"><i class="fas fa-list-check"></i></div>
                    <div class="stat-link">View todos →</div>
                </a>
            </div>
            
            <!-- Stats Cards - Row 3: Checklists, Submissions & More -->
            <div class="cards">
                <a href="tender_checklists.php" class="stat-card indigo">
                    <div class="stat-label"><i class="fas fa-clipboard-check"></i> Checklists</div>
                    <div class="stat-value"><?= $stats['total_checklists'] ?></div>
                    <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="stat-link">
                        <?= $stats['active_checklists'] ?> active | <?= $stats['ready_review_checklists'] ?> ready →
                    </div>
                </a>
                <a href="tender_uz_submitted.php" class="stat-card pink">
                    <div class="stat-label"><i class="fas fa-university"></i> UZ Submissions</div>
                    <div class="stat-value"><?= $stats['total_uz'] ?></div>
                    <div class="stat-icon"><i class="fas fa-university"></i></div>
                    <div class="stat-link"><?= $stats['uz_pending'] ?> pending →</div>
                </a>
                <a href="tender_supplier_reg.php" class="stat-card orange">
                    <div class="stat-label"><i class="fas fa-user-plus"></i> Registrations</div>
                    <div class="stat-value"><?= $stats['total_reg'] ?></div>
                    <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-link"><?= $stats['reg_pending'] ?> pending →</div>
                </a>
                <a href="tender_site_visits.php" class="stat-card teal">
                    <div class="stat-label"><i class="fas fa-map-marker-alt"></i> Site Visits</div>
                    <div class="stat-value"><?= $stats['total_visits'] ?></div>
                    <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="stat-link"><?= $stats['scheduled_visits'] ?> scheduled →</div>
                </a>
                <a href="users.php" class="stat-card blue">
                    <div class="stat-label"><i class="fas fa-users"></i> Users</div>
                    <div class="stat-value"><?= $stats['active_users'] ?></div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-link"><?= $stats['disabled_users'] ?> disabled →</div>
                </a>
            </div>
            
            <!-- Activity Stats -->
            <div class="cards" style="margin-bottom:20px;">
                <div class="stat-card" style="border-left-color:#8b5cf6;">
                    <div class="stat-label"><i class="fas fa-history"></i> Activity (7 days)</div>
                    <div class="stat-value" style="font-size:24px;"><?= $stats['activity_last_7d'] ?></div>
                    <div class="stat-icon"><i class="fas fa-history"></i></div>
                    <div class="stat-link"><?= $stats['activity_today'] ?> today</div>
                </div>
                <div class="stat-card" style="border-left-color:#ec4899;">
                    <div class="stat-label"><i class="fas fa-file-pdf"></i> Memos</div>
                    <div class="stat-value"><?= $stats['total_memos'] ?></div>
                    <div class="stat-icon"><i class="fas fa-file-pdf"></i></div>
                    <div class="stat-link"><?= $stats['sent_memos'] ?> sent</div>
                </div>
                <div class="stat-card" style="border-left-color:#14b8a6;">
                    <div class="stat-label"><i class="fas fa-check-circle"></i> Checklist Complete</div>
                    <div class="stat-value"><?= $stats['approved_checklists'] ?></div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-link">Approved checklists</div>
                </div>
                <div class="stat-card" style="border-left-color:#f59e0b;">
                    <div class="stat-label"><i class="fas fa-calendar-check"></i> Completed Visits</div>
                    <div class="stat-value"><?= $stats['completed_visits'] ?></div>
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-link"><?= $stats['overdue_visits'] ?> overdue</div>
                </div>
            </div>
            
            <div class="grid">
                <!-- Recent Tenders -->
                <div class="table-card">
                    <h3>
                        <i class="fas fa-file-signature" style="color:var(--green);"></i> Recent Tenders
                        <a href="tender_ongoing.php">View all →</a>
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Tender No.</th>
                                <th>Name</th>
                                <th>Assigned To</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_tenders as $t): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($t['tender_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($t['tender_name']) ?></td>
                                    <td><?= htmlspecialchars($t['assigned_name'] ?? 'Unassigned') ?></td>
                                    <td><?= date('d M Y', strtotime($t['due_date'])) ?></td>
                                    <td><span class="status status-<?= strtolower($t['status']) ?>"><?= $t['status'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_tenders)): ?>
                                <tr><td colspan="5" class="empty-state">No tenders found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Upcoming Deadlines & Todos -->
                <div>
                    <!-- Upcoming Deadlines -->
                    <div class="table-card" style="margin-bottom:15px;">
                        <h3>
                            <i class="fas fa-hourglass-half" style="color:#f59e0b;"></i> Upcoming Deadlines
                            <a href="tender_ongoing.php">View all →</a>
                        </h3>
                        <?php if (!empty($upcoming_deadlines)): ?>
                            <?php foreach ($upcoming_deadlines as $t): ?>
                                <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f3f4f6; font-size:13px;">
                                    <div>
                                        <strong><?= htmlspecialchars($t['tender_number']) ?></strong>
                                        <span style="color:#64748b;font-size:12px;"><?= htmlspecialchars($t['tender_name']) ?></span>
                                    </div>
                                    <div>
                                        <span style="color:<?= $t['days_left'] <= 2 ? '#dc2626' : '#f59e0b' ?>;font-weight:600;">
                                            <?= $t['days_left'] ?> days
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No upcoming deadlines</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- My Todos -->
                    <div class="todo-card">
                        <h3>
                            <i class="fas fa-list-check" style="color:var(--green);"></i> My Todos
                            <a href="todos.php">View all →</a>
                        </h3>
                        <?php foreach ($user_todos as $todo): ?>
                            <div class="todo-item">
                                <form method="POST" action="todos.php" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $todo['id'] ?>">
                                    <input type="checkbox" onchange="this.form.submit()">
                                </form>
                                <span><?= htmlspecialchars($todo['title']) ?></span>
                                <span class="priority priority-<?= strtolower($todo['priority']) ?>"><?= $todo['priority'] ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($user_todos)): ?>
                            <div class="empty-state">🎉 No pending tasks</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-hide messages if any
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