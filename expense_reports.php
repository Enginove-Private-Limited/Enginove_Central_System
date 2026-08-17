<?php
// expense_reports.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Expense Reports";

// Get date range from filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$where = "1=1";
$params = [];

if ($from_date) {
    $where .= " AND e.expense_date >= ?";
    $params[] = $from_date;
}
if ($to_date) {
    $where .= " AND e.expense_date <= ?";
    $params[] = $to_date;
}
if ($category_id > 0) {
    $where .= " AND e.category_id = ?";
    $params[] = $category_id;
}
if ($search) {
    $where .= " AND (e.description LIKE ? OR e.expense_number LIKE ? OR e.supplier_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Get summary stats
$stats_query = "SELECT 
    COUNT(*) as total_count,
    COALESCE(SUM(e.total_usd), 0) as total_usd,
    COALESCE(SUM(e.total_zwg), 0) as total_zwg,
    COALESCE(SUM(e.vat_amount_usd), 0) as total_vat_usd,
    COALESCE(SUM(e.vat_amount_zwg), 0) as total_vat_zwg,
    COALESCE(SUM(e.petty_cash_amount), 0) as total_petty_cash
FROM expenses e
WHERE $where";

$stmt = $pdo->prepare($stats_query);
$stmt->execute($params);
$stats = $stmt->fetch();

// Get expenses with category names
$expenses_query = "
    SELECT e.*, ec.category_name 
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    WHERE $where
    ORDER BY e.expense_date DESC
";

$stmt = $pdo->prepare($expenses_query);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Get categories for filter dropdown
$categories = $pdo->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll();
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
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-1px); box-shadow:0 4px 12px rgba(31,139,76,0.3); }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:15px; margin-bottom:20px; }
        .stat-card { background:#f8fafc; padding:15px 20px; border-radius:10px; border-left:4px solid var(--green); }
        .stat-card h4 { font-size:12px; color:#64748b; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-card h2 { font-size:22px; color:var(--dark); }
        .stat-card small { font-size:12px; color:#64748b; }
        .stat-card.blue { border-left-color:#2563eb; }
        .stat-card.orange { border-left-color:#f59e0b; }
        .stat-card.purple { border-left-color:#7c3aed; }
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; margin-top:20px; }
        table { width:100%; border-collapse:collapse; min-width:900px; }
        th { text-align:left; padding:12px; background:var(--light-green); font-weight:600; font-size:12px; white-space:nowrap; }
        td { padding:12px; border-bottom:1px solid #f1f5f9; font-size:13px; }
        tr:hover td { background:#fafdfb; }
        
        .filters { display:flex; gap:15px; flex-wrap:wrap; align-items:flex-end; }
        .filters .form-group { margin-bottom:0; }
        .filters .form-group label { display:block; font-weight:500; font-size:12px; color:#374151; margin-bottom:4px; }
        .filters .form-group input, .filters .form-group select { 
            padding:8px 12px; 
            border:2px solid #e5e7eb; 
            border-radius:8px; 
            font-size:13px;
            min-width:140px;
        }
        .filters .form-group input:focus, .filters .form-group select:focus {
            border-color:var(--green); outline:none; box-shadow:0 0 0 3px rgba(31,139,76,0.1);
        }
        
        .attachment-icon { color:var(--green); cursor:pointer; }
        .attachment-icon:hover { color:#0f6a36; }
        
        .export-buttons { display:flex; gap:10px; flex-wrap:wrap; }
        
        .no-data { text-align:center; padding:40px; color:#64748b; }
        .no-data i { font-size:48px; display:block; margin-bottom:15px; color:#d1d5db; }
        
        .total-row { background:#f8fafc; font-weight:600; }
        .total-row td { border-top:2px solid var(--green); }
        
        @media(max-width:991px) { 
            .main { margin-left:0; } 
            .filters { flex-direction:column; align-items:stretch; }
            .filters .form-group { width:100%; }
            .filters .form-group input, .filters .form-group select { width:100%; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-title { font-size:22px; }
            table { min-width:700px; }
            .stats-grid { grid-template-columns:1fr 1fr; }
        }
        @media(max-width:480px) {
            .stats-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:8px;">
                <div>
                    <h1 class="page-title" style="margin-bottom:0;"><i class="fas fa-chart-bar"></i> <?= $pageTitle ?></h1>
                </div>
                <div class="export-buttons">
                    <a href="expense_add.php" class="btn btn-green">
                        <i class="fas fa-plus"></i> New Expense
                    </a>
                </div>
            </div>
            <p class="subtitle">View and analyze all paid expenses.</p>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Expenses</h4>
                    <h2><?= number_format($stats['total_count'] ?? 0) ?></h2>
                </div>
                <div class="stat-card blue">
                    <h4>Total USD</h4>
                    <h2>$<?= number_format($stats['total_usd'] ?? 0, 2) ?></h2>
                    <small>VAT: $<?= number_format($stats['total_vat_usd'] ?? 0, 2) ?></small>
                </div>
                <div class="stat-card orange">
                    <h4>Total ZWG</h4>
                    <h2><?= number_format($stats['total_zwg'] ?? 0, 2) ?></h2>
                    <small>VAT: <?= number_format($stats['total_vat_zwg'] ?? 0, 2) ?></small>
                </div>
                <div class="stat-card purple">
                    <h4>Petty Cash</h4>
                    <h2><?= number_format($stats['total_petty_cash'] ?? 0, 2) ?></h2>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card">
                <h3><i class="fas fa-filter"></i> Filter Reports</h3>
                <form method="GET" class="filters">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Search expenses..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group" style="display:flex; gap:10px; align-items:center;">
                        <button type="submit" class="btn btn-green"><i class="fas fa-search"></i> Apply Filters</button>
                        <a href="expense_reports.php" class="btn btn-outline"><i class="fas fa-undo"></i> Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Report Table -->
            <div class="table-container">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                    <h3 style="font-size:16px;"><i class="fas fa-list"></i> Expense Report</h3>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <span style="font-size:13px; color:#64748b;">
                            Showing <?= count($expenses) ?> records
                        </span>
                        <button onclick="window.print()" class="btn btn-outline" style="padding:6px 16px; font-size:12px;">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
                
                <?php if (count($expenses) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Supplier</th>
                            <th>Payment</th>
                            <th>USD</th>
                            <th>USD VAT</th>
                            <th>ZWG</th>
                            <th>ZWG VAT</th>
                            <th>Petty Cash</th>
                            <th>Attach</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_usd = 0;
                        $total_zwg = 0;
                        $total_vat_usd = 0;
                        $total_vat_zwg = 0;
                        $total_petty = 0;
                        
                        foreach ($expenses as $exp):
                            $total_usd += $exp['total_usd'];
                            $total_zwg += $exp['total_zwg'];
                            $total_vat_usd += $exp['vat_amount_usd'];
                            $total_vat_zwg += $exp['vat_amount_zwg'];
                            $total_petty += $exp['petty_cash_amount'];
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($exp['expense_number']) ?></strong></td>
                                <td><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                                <td><?= htmlspecialchars($exp['category_name'] ?? 'N/A') ?></td>
                                <td title="<?= htmlspecialchars($exp['description']) ?>">
                                    <?= htmlspecialchars(substr($exp['description'], 0, 25)) ?><?= strlen($exp['description']) > 25 ? '...' : '' ?>
                                </td>
                                <td><?= htmlspecialchars($exp['supplier_name'] ?? '—') ?></td>
                                <td><?= str_replace(' ', '<br>', $exp['payment_method']) ?></td>
                                <td>$<?= number_format($exp['total_usd'], 2) ?></td>
                                <td>$<?= number_format($exp['vat_amount_usd'], 2) ?></td>
                                <td><?= number_format($exp['total_zwg'], 2) ?></td>
                                <td><?= number_format($exp['vat_amount_zwg'], 2) ?></td>
                                <td><?= number_format($exp['petty_cash_amount'], 2) ?></td>
                                <td>
                                    <?php if ($exp['receipt_file']): ?>
                                        <a href="<?= htmlspecialchars($exp['receipt_file']) ?>" target="_blank" class="attachment-icon" title="View Attachment">
                                            <i class="fas fa-paperclip"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="expense_view.php?id=<?= $exp['id'] ?>" class="btn btn-outline" style="padding:4px 10px;font-size:11px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Totals Row -->
                        <tr class="total-row">
                            <td colspan="6" style="text-align:right; font-weight:600;">TOTALS</td>
                            <td>$<?= number_format($total_usd, 2) ?></td>
                            <td>$<?= number_format($total_vat_usd, 2) ?></td>
                            <td><?= number_format($total_zwg, 2) ?></td>
                            <td><?= number_format($total_vat_zwg, 2) ?></td>
                            <td><?= number_format($total_petty, 2) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <p>No expenses found matching your filters.</p>
                        <p style="font-size:14px; margin-top:5px;">
                            <a href="expense_add.php" style="color:var(--green);">Create your first expense</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Print functionality
document.querySelector('.btn-print')?.addEventListener('click', function() {
    window.print();
});

// Auto-submit filters on change (optional)
document.querySelectorAll('.filters select, .filters input[type="date"]').forEach(el => {
    el.addEventListener('change', function() {
        this.closest('form').submit();
    });
});
</script>
</body>
</html>