<?php
// expenses.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Expense Capture";
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
        .page-title { font-size:28px; font-weight:700; color:var(--dark); margin-bottom:4px; }
        .subtitle { color:#64748b; margin-bottom:25px; }
        
        /* Stats Grid - Better layout */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 16px; 
            margin-bottom: 25px; 
        }
        .stat-card { 
            background: white; 
            padding: 18px 20px; 
            border-radius: 12px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border-left: 4px solid var(--green);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card .stat-label { 
            font-size: 12px; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            font-weight: 600;
        }
        .stat-card .stat-value { 
            font-size: 24px; 
            font-weight: 700; 
            color: var(--dark); 
            margin-top: 4px; 
        }
        .stat-card .stat-icon { 
            float: right; 
            font-size: 28px; 
            opacity: 0.2; 
            margin-top: -10px; 
        }
        .stat-card.blue { border-left-color: #2563eb; }
        .stat-card.orange { border-left-color: #f59e0b; }
        .stat-card.purple { border-left-color: #7c3aed; }
        
        /* Filter Bar - Improved */
        .filter-bar {
            background: white;
            padding: 18px 22px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            min-width: 140px;
            background: white;
            transition: 0.2s;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(31,139,76,0.1);
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-left: auto;
        }
        
        /* Table Container - Improved */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .table-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-header h3 {
            font-size: 16px;
            color: var(--dark);
            margin: 0;
        }
        .table-header .badge-count {
            background: var(--light-green);
            color: var(--green);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .table-scroll {
            overflow-x: auto;
            padding: 0 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 1000px;
        }
        th {
            text-align: left;
            padding: 12px 16px;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e2a2f;
            vertical-align: middle;
        }
        tr:hover td { background: #fafdfb; }
        tr:last-child td { border-bottom: none; }
        
        /* Buttons - Improved */
        .btn { 
            padding: 8px 18px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: .25s; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            font-size: 13px; 
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-green { background: var(--green); color: white; }
        .btn-green:hover { background: #0f6a36; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(31,139,76,0.25); }
        .btn-outline { background: transparent; border: 2px solid var(--green); color: var(--green); }
        .btn-outline:hover { background: var(--green); color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 4px 10px; font-size: 12px; border-radius: 6px; }
        .btn-icon { padding: 6px 10px; font-size: 13px; }
        
        .attachment-icon { color: var(--green); font-size: 16px; cursor: pointer; transition: 0.2s; }
        .attachment-icon:hover { color: #0f6a36; transform: scale(1.1); }
        
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .currency-usd { color: #2563eb; font-weight: 600; }
        .currency-zwg { color: #f59e0b; font-weight: 600; }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #64748b;
        }
        .empty-state i {
            font-size: 48px;
            color: #d1d5db;
            display: block;
            margin-bottom: 15px;
        }
        .empty-state h4 {
            font-size: 18px;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        /* Responsive */
        @media(max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width: 991px) { 
            .main { margin-left: 0; }
            .filter-actions { margin-left: 0; width: 100%; }
            .filter-actions .btn { flex: 1; justify-content: center; }
        }
        @media(max-width: 768px) { 
            .content { padding: 15px; } 
            .page-title { font-size: 22px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card .stat-value { font-size: 20px; }
            .filter-bar { padding: 15px; flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-group input, .filter-group select { width: 100%; min-width: unset; }
            .filter-actions { flex-direction: column; }
            .filter-actions .btn { width: 100%; justify-content: center; }
            table { min-width: 700px; font-size: 12px; }
            th, td { padding: 8px 12px; }
        }
        @media(max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .stat-card .stat-value { font-size: 18px; }
            .table-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <!-- Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:4px;">
                <h1 class="page-title" style="margin-bottom:0;"><i class="fas fa-coins" style="color:var(--green);"></i> <?= $pageTitle ?></h1>
                <a href="expense_add.php" class="btn btn-green"><i class="fas fa-plus"></i> New Expense</a>
            </div>
            <p class="subtitle">Track and manage all paid expenses with dual currency support.</p>
            
            <!-- Stats -->
            <?php
            $total_usd = $pdo->query("SELECT SUM(total_usd) as total FROM expenses")->fetch()['total'] ?? 0;
            $total_zwg = $pdo->query("SELECT SUM(total_zwg) as total FROM expenses")->fetch()['total'] ?? 0;
            $count = $pdo->query("SELECT COUNT(*) as count FROM expenses")->fetch()['count'];
            $total_vat_usd = $pdo->query("SELECT SUM(vat_amount_usd) as total FROM expenses")->fetch()['total'] ?? 0;
            ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label"><i class="fas fa-receipt"></i> Total Expenses</div>
                    <div class="stat-value"><?= $count ?></div>
                    <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-label"><i class="fas fa-dollar-sign"></i> Total USD</div>
                    <div class="stat-value">$<?= number_format($total_usd, 2) ?></div>
                    <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-label"><i class="fas fa-money-bill"></i> Total ZWG</div>
                    <div class="stat-value"><?= number_format($total_zwg, 2) ?></div>
                    <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-label"><i class="fas fa-percent"></i> Total VAT (USD)</div>
                    <div class="stat-value">$<?= number_format($total_vat_usd, 2) ?></div>
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group">
                    <label><i class="far fa-calendar-alt"></i> From</label>
                    <input type="date" id="filter_from">
                </div>
                <div class="filter-group">
                    <label><i class="far fa-calendar-alt"></i> To</label>
                    <input type="date" id="filter_to">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-tag"></i> Category</label>
                    <select id="filter_category">
                        <option value="">All Categories</option>
                        <?php
                        $categories = $pdo->query("SELECT * FROM expense_categories ORDER BY category_name")->fetchAll();
                        foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" id="filter_search" placeholder="Search expenses...">
                </div>
                <div class="filter-actions">
                    <button class="btn btn-green" onclick="applyFilters()"><i class="fas fa-search"></i> Filter</button>
                    <button class="btn btn-outline" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
                    <a href="expense_reports.php" class="btn btn-outline"><i class="fas fa-chart-bar"></i> Reports</a>
                </div>
            </div>
            
            <!-- Expense List -->
            <div class="table-container">
                <div class="table-header">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <h3><i class="fas fa-list" style="color:var(--green);"></i> All Expenses</h3>
                        <span class="badge-count"><?= $count ?> records</span>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span style="font-size:12px; color:#64748b;">
                            <i class="fas fa-sort-amount-down"></i> Latest first
                        </span>
                    </div>
                </div>
                <div class="table-scroll">
                    <table id="expenseTable">
                        <thead>
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:100px;">Date</th>
                                <th style="width:140px;">Category</th>
                                <th>Description</th>
                                <th style="width:130px;">Supplier</th>
                                <th style="width:110px;">Payment</th>
                                <th style="width:100px;text-align:right;">USD</th>
                                <th style="width:100px;text-align:right;">ZWG</th>
                                <th style="width:90px;text-align:right;">Petty Cash</th>
                                <th style="width:50px;text-align:center;">File</th>
                                <th style="width:140px;text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $expenses = $pdo->query("
                                SELECT e.*, ec.category_name 
                                FROM expenses e
                                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                                ORDER BY e.expense_date DESC
                            ")->fetchAll();
                            
                            if (count($expenses) > 0):
                                foreach ($expenses as $exp):
                                    $has_attachment = !empty($exp['receipt_file']);
                            ?>
                                <tr>
                                    <td><strong style="color:var(--green);"><?= htmlspecialchars($exp['expense_number']) ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($exp['expense_date'])) ?></td>
                                    <td>
                                        <span style="background:#f1f5f9;padding:2px 10px;border-radius:12px;font-size:11px;display:inline-block;">
                                            <?= htmlspecialchars($exp['category_name'] ?? 'Uncategorized') ?>
                                        </span>
                                    </td>
                                    <td title="<?= htmlspecialchars($exp['description']) ?>">
                                        <?= htmlspecialchars(substr($exp['description'], 0, 35)) ?><?= strlen($exp['description']) > 35 ? '...' : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars($exp['supplier_name'] ?? '—') ?></td>
                                    <td><span style="font-size:11px;background:#f1f5f9;padding:2px 8px;border-radius:4px;"><?= str_replace(' ', ' ', $exp['payment_method']) ?></span></td>
                                    <td style="text-align:right;font-weight:500;">$<?= number_format($exp['total_usd'], 2) ?></td>
                                    <td style="text-align:right;font-weight:500;color:#f59e0b;"><?= number_format($exp['total_zwg'], 2) ?></td>
                                    <td style="text-align:right;color:#64748b;"><?= number_format($exp['petty_cash_amount'], 2) ?></td>
                                    <td style="text-align:center;">
                                        <?php if ($has_attachment): ?>
                                            <a href="<?= htmlspecialchars($exp['receipt_file']) ?>" target="_blank" class="attachment-icon" title="View Attachment">
                                                <i class="fas fa-paperclip"></i>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#d1d5db;font-size:14px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons" style="justify-content:center;">
                                            <a href="expense_view.php?id=<?= $exp['id'] ?>" class="btn btn-outline btn-sm" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="expense_edit.php?id=<?= $exp['id'] ?>" class="btn btn-outline btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="expense_delete.php?id=<?= $exp['id'] ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Delete this expense?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="11">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <h4>No expenses recorded yet</h4>
                                            <p style="color:#94a3b8;font-size:14px;">Start tracking your expenses by creating your first expense entry.</p>
                                            <br>
                                            <a href="expense_add.php" class="btn btn-green"><i class="fas fa-plus"></i> Create First Expense</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const from = document.getElementById('filter_from').value;
    const to = document.getElementById('filter_to').value;
    const category = document.getElementById('filter_category').value;
    const search = document.getElementById('filter_search').value.toLowerCase();
    
    const rows = document.querySelectorAll('#expenseTable tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        // Skip empty state row
        if (row.querySelector('.empty-state')) {
            row.style.display = 'none';
            return;
        }
        
        const text = row.textContent.toLowerCase();
        let show = true;
        
        if (search && !text.includes(search)) show = false;
        
        // Simple date filtering (can be improved)
        // Category filter
        if (category) {
            const catCell = row.querySelectorAll('td')[2];
            if (catCell) {
                const catText = catCell.textContent.trim();
                const catMap = <?php 
                    $map = [];
                    foreach ($categories as $cat) {
                        $map[$cat['id']] = $cat['category_name'];
                    }
                    echo json_encode($map);
                ?>;
                const catName = catMap[category] || '';
                if (!catText.includes(catName)) show = false;
            }
        }
        
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });
    
    // Update badge count
    const badge = document.querySelector('.badge-count');
    if (badge) {
        badge.textContent = visibleCount + ' records';
    }
}

function resetFilters() {
    document.getElementById('filter_from').value = '';
    document.getElementById('filter_to').value = '';
    document.getElementById('filter_category').value = '';
    document.getElementById('filter_search').value = '';
    
    const rows = document.querySelectorAll('#expenseTable tbody tr');
    let totalCount = 0;
    rows.forEach(row => {
        row.style.display = '';
        if (!row.querySelector('.empty-state')) totalCount++;
    });
    
    const badge = document.querySelector('.badge-count');
    if (badge) {
        badge.textContent = totalCount + ' records';
    }
}

// Auto-filter on input change
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filter_search');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
    }
});
</script>
</body>
</html>