<?php
// inventory.php - Inventory Management
require_once 'config/database.php';
requireLogin();

$pageTitle = "Inventory Management";
$message = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'items';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add new item
    if ($action === 'add_item') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO inventory_items (
                    product_name, description, category_id, sku, unit_of_measure,
                    stock_in, stock_out, current_stock, unit_cost, total_cost,
                    min_stock_level, max_stock_level, supplier_id, location, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['product_name'],
                $_POST['description'],
                $_POST['category_id'] ?: null,
                $_POST['sku'] ?: null,
                $_POST['unit_of_measure'],
                $_POST['stock_in'] ?? 0,
                $_POST['stock_out'] ?? 0,
                $_POST['current_stock'] ?? 0,
                $_POST['unit_cost'] ?? 0,
                ($_POST['current_stock'] ?? 0) * ($_POST['unit_cost'] ?? 0),
                $_POST['min_stock_level'] ?? 0,
                $_POST['max_stock_level'] ?? 0,
                $_POST['supplier_id'] ?: null,
                $_POST['location'] ?: null,
                $_POST['status'],
                $_SESSION['user_id']
            ]);
            $message = "Inventory item added successfully!";
            logActivity($_SESSION['user_id'], "Added inventory item: " . $_POST['product_name']);
        } catch (Exception $e) {
            $error = "Error adding item: " . $e->getMessage();
        }
    }
    
    // Update item
    if ($action === 'update_item') {
        try {
            // Calculate total cost
            $total_cost = ($_POST['current_stock'] ?? 0) * ($_POST['unit_cost'] ?? 0);
            
            $stmt = $pdo->prepare("
                UPDATE inventory_items SET
                    product_name = ?,
                    description = ?,
                    category_id = ?,
                    sku = ?,
                    unit_of_measure = ?,
                    stock_in = ?,
                    stock_out = ?,
                    current_stock = ?,
                    unit_cost = ?,
                    total_cost = ?,
                    min_stock_level = ?,
                    max_stock_level = ?,
                    supplier_id = ?,
                    location = ?,
                    status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['product_name'],
                $_POST['description'],
                $_POST['category_id'] ?: null,
                $_POST['sku'] ?: null,
                $_POST['unit_of_measure'],
                $_POST['stock_in'] ?? 0,
                $_POST['stock_out'] ?? 0,
                $_POST['current_stock'] ?? 0,
                $_POST['unit_cost'] ?? 0,
                $total_cost,
                $_POST['min_stock_level'] ?? 0,
                $_POST['max_stock_level'] ?? 0,
                $_POST['supplier_id'] ?: null,
                $_POST['location'] ?: null,
                $_POST['status'],
                $_POST['item_id']
            ]);
            $message = "Inventory item updated successfully!";
            logActivity($_SESSION['user_id'], "Updated inventory item: " . $_POST['product_name']);
        } catch (Exception $e) {
            $error = "Error updating item: " . $e->getMessage();
        }
    }
    
    // Delete item
    if ($action === 'delete_item') {
        try {
            $stmt = $pdo->prepare("DELETE FROM inventory_items WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = "Item deleted successfully!";
            logActivity($_SESSION['user_id'], "Deleted inventory item ID: " . $_POST['id']);
        } catch (Exception $e) {
            $error = "Error deleting item: " . $e->getMessage();
        }
    }
    
    // Stock In
    if ($action === 'stock_in') {
        try {
            $pdo->beginTransaction();
            
            $item_id = $_POST['item_id'];
            $quantity = $_POST['quantity'];
            $unit_cost = $_POST['unit_cost'] ?? 0;
            $total_cost = $quantity * $unit_cost;
            
            // Update item
            $stmt = $pdo->prepare("
                UPDATE inventory_items 
                SET stock_in = stock_in + ?,
                    current_stock = current_stock + ?,
                    total_cost = total_cost + ?
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $quantity, $total_cost, $item_id]);
            
            // Log transaction
            $stmt = $pdo->prepare("
                INSERT INTO inventory_transactions (
                    item_id, transaction_type, quantity, unit_cost, total_cost,
                    reference_number, notes, transaction_date, created_by
                ) VALUES (?, 'Stock In', ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $item_id,
                $quantity,
                $unit_cost,
                $total_cost,
                $_POST['reference_number'] ?? null,
                $_POST['notes'] ?? null,
                $_SESSION['user_id']
            ]);
            
            $pdo->commit();
            $message = "Stock added successfully!";
            logActivity($_SESSION['user_id'], "Stock In: " . $quantity . " units for item ID: " . $item_id);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error adding stock: " . $e->getMessage();
        }
    }
    
    // Stock Out
    if ($action === 'stock_out') {
        try {
            $pdo->beginTransaction();
            
            $item_id = $_POST['item_id'];
            $quantity = $_POST['quantity'];
            
            // Check if enough stock
            $stmt = $pdo->prepare("SELECT current_stock FROM inventory_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $current = $stmt->fetch()['current_stock'];
            
            if ($current < $quantity) {
                $error = "Insufficient stock! Available: " . $current;
            } else {
                // Update item
                $stmt = $pdo->prepare("
                    UPDATE inventory_items 
                    SET stock_out = stock_out + ?,
                        current_stock = current_stock - ?
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $quantity, $item_id]);
                
                // Log transaction
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_transactions (
                        item_id, transaction_type, quantity, unit_cost,
                        reference_number, notes, transaction_date, created_by
                    ) VALUES (?, 'Stock Out', ?, (SELECT unit_cost FROM inventory_items WHERE id = ?), ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $item_id,
                    $quantity,
                    $item_id,
                    $_POST['reference_number'] ?? null,
                    $_POST['notes'] ?? null,
                    $_SESSION['user_id']
                ]);
                
                $pdo->commit();
                $message = "Stock removed successfully!";
                logActivity($_SESSION['user_id'], "Stock Out: " . $quantity . " units for item ID: " . $item_id);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error removing stock: " . $e->getMessage();
        }
    }
}

// Get all inventory items
$items = $pdo->query("
    SELECT i.*, 
           c.category_name,
           s.supplier_name
    FROM inventory_items i
    LEFT JOIN inventory_categories c ON c.id = i.category_id
    LEFT JOIN suppliers s ON s.id = i.supplier_id
    ORDER BY i.product_name
")->fetchAll();

// Get categories
$categories = $pdo->query("SELECT * FROM inventory_categories ORDER BY category_name")->fetchAll();

// Get suppliers
$suppliers = $pdo->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name")->fetchAll();

// Get stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(current_stock) as total_stock,
        SUM(total_cost) as total_value,
        COUNT(CASE WHEN current_stock <= min_stock_level AND min_stock_level > 0 THEN 1 END) as low_stock_count,
        COUNT(CASE WHEN status = 'Active' THEN 1 END) as active_count
    FROM inventory_items
")->fetch();

// Get low stock items
$low_stock_items = $pdo->query("
    SELECT product_name, current_stock, min_stock_level 
    FROM inventory_items 
    WHERE current_stock <= min_stock_level AND min_stock_level > 0
    ORDER BY (current_stock / min_stock_level) ASC
")->fetchAll();
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
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-sm { padding:6px 14px; font-size:13px; }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        .btn-edit { background:#e5e7eb; color:#1e2a2f; }
        .btn-edit:hover { background:#d1d5db; }
        .btn-warning { background:#f59e0b; color:white; }
        .btn-warning:hover { background:#d97706; }
        .btn-success { background:#22c55e; color:white; }
        .btn-success:hover { background:#16a34a; }
        
        .stats-grid {
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
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .stat-card .value { font-size: 24px; font-weight: 700; color: var(--dark); margin-top: 2px; }
        .stat-card .value.red { color: #dc2626; }
        .stat-card .value.green { color: var(--green); }
        .stat-card .value.blue { color: #2563eb; }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 25px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            overflow-x: auto;
            flex-wrap: nowrap;
        }
        .tabs a {
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            font-size: 14px;
            transition: .25s;
            white-space: nowrap;
        }
        .tabs a:hover { background: #f1f5f9; }
        .tabs a.active {
            background: var(--green);
            color: white;
        }
        .tabs a i { margin-right: 8px; }
        
        .actions { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
        .search-filter { display:flex; gap:15px; flex-wrap:wrap; }
        .search-filter input, .search-filter select { padding:10px 16px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; background:white; }
        .search-filter input:focus, .search-filter select:focus { outline:none; border-color:var(--green); }
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:900px; font-size:13px; }
        th { text-align:left; padding:12px 14px; background:var(--light-green); color:var(--dark); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        td { padding:12px 14px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
        tr:hover td { background:#fafdfb; }
        
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .status-active { background:var(--light-green); color:var(--green); }
        .status-inactive { background:#fee2e2; color:#b91c1c; }
        .status-discontinued { background:#fef3c7; color:#b45309; }
        
        .low-stock { color: #dc2626; font-weight: 600; }
        .low-stock .badge {
            background: #fee2e2;
            color: #dc2626;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
            margin-left: 4px;
        }
        
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:650px; max-height:90vh; overflow-y:auto; }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; color:#64748b; }
        .modal-content h2 { margin-bottom:20px; color:var(--dark); }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; color:var(--dark); }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--green); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        
        .stock-btn { padding:4px 10px; font-size:11px; border-radius:4px; border:none; cursor:pointer; transition:.2s; }
        .stock-in-btn { background:#dbeafe; color:#1d4ed8; }
        .stock-in-btn:hover { background:#bfdbfe; }
        .stock-out-btn { background:#fee2e2; color:#b91c1c; }
        .stock-out-btn:hover { background:#fecaca; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .form-row { grid-template-columns:1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; }
            .page-title { font-size:22px; }
            .actions { flex-direction:column; align-items:stretch; }
            .search-filter { flex-direction:column; }
            .search-filter input, .search-filter select { width:100%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width:480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .tabs { flex-wrap:nowrap; overflow-x:auto; }
            .tabs a { padding:8px 16px; font-size:13px; }
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
                <h1 class="page-title" style="margin-bottom:0;"><i class="fas fa-boxes" style="color:var(--green);"></i> <?= $pageTitle ?></h1>
                <button class="btn btn-green" onclick="openModal('itemModal')"><i class="fas fa-plus"></i> Add Item</button>
            </div>
            <p class="subtitle">Manage inventory items, stock levels, and transactions.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">Total Items</div>
                    <div class="value"><?= $stats['total_items'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Total Stock Value</div>
                    <div class="value green">$<?= number_format($stats['total_value'] ?? 0, 2) ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Low Stock Items</div>
                    <div class="value red"><?= $stats['low_stock_count'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <div class="label">Active Items</div>
                    <div class="value blue"><?= $stats['active_count'] ?? 0 ?></div>
                </div>
            </div>
            
            <!-- Low Stock Alert -->
            <?php if (!empty($low_stock_items)): ?>
                <div style="background:#fee2e2; border-radius:12px; padding:15px 20px; margin-bottom:20px; display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                    <i class="fas fa-exclamation-triangle" style="color:#dc2626; font-size:20px;"></i>
                    <div>
                        <strong style="color:#b91c1c;">Low Stock Alert!</strong>
                        <span style="color:#991b1b; font-size:14px;">The following items are running low:</span>
                        <span style="color:#b91c1c; font-weight:600; font-size:14px; margin-left:8px;">
                            <?php 
                            $low_names = array_slice(array_column($low_stock_items, 'product_name'), 0, 5);
                            echo implode(', ', $low_names);
                            if (count($low_stock_items) > 5) {
                                echo ' and ' . (count($low_stock_items) - 5) . ' more...';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <a href="?tab=items" class="<?= $active_tab == 'items' ? 'active' : '' ?>"><i class="fas fa-list"></i> All Items</a>
                <a href="?tab=transactions" class="<?= $active_tab == 'transactions' ? 'active' : '' ?>"><i class="fas fa-exchange-alt"></i> Transactions</a>
                <a href="?tab=categories" class="<?= $active_tab == 'categories' ? 'active' : '' ?>"><i class="fas fa-tags"></i> Categories</a>
            </div>
            
            <!-- All Items Tab -->
            <div class="tab-content <?= $active_tab == 'items' ? 'active' : '' ?>">
                <div class="actions">
                    <div class="search-filter">
                        <input type="text" id="itemSearch" placeholder="Search items..." onkeyup="filterTable('itemsTable', 'itemSearch')">
                        <select id="statusFilter" onchange="filterTable('itemsTable', 'itemSearch')">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Discontinued">Discontinued</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="itemsTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Stock In</th>
                                <th>Stock Out</th>
                                <th>Current Stock</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $is_low = $item['min_stock_level'] > 0 && $item['current_stock'] <= $item['min_stock_level'];
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                        <?php if ($is_low): ?>
                                            <span class="low-stock"><span class="badge">Low</span></span>
                                        <?php endif; ?>
                                        <?php if ($item['sku']): ?>
                                            <br><small style="color:#64748b;">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></td>
                                    <td><?= number_format($item['stock_in'], 2) ?></td>
                                    <td><?= number_format($item['stock_out'], 2) ?></td>
                                    <td class="<?= $is_low ? 'low-stock' : '' ?>">
                                        <?= number_format($item['current_stock'], 2) ?>
                                        <?php if ($item['unit_of_measure']): ?>
                                            <small style="color:#64748b;font-size:10px;"><?= htmlspecialchars($item['unit_of_measure']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?= number_format($item['unit_cost'], 2) ?></td>
                                    <td>$<?= number_format($item['total_cost'], 2) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($item['status']) ?>">
                                            <?= $item['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                            <!-- Stock In -->
                                            <button class="stock-btn stock-in-btn" onclick="openStockModal('in', <?= $item['id'] ?>, '<?= htmlspecialchars($item['product_name']) ?>')">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <!-- Stock Out -->
                                            <button class="stock-btn stock-out-btn" onclick="openStockModal('out', <?= $item['id'] ?>, '<?= htmlspecialchars($item['product_name']) ?>')">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <!-- Edit -->
                                            <button class="btn btn-sm btn-edit" onclick="editItem(<?= $item['id'] ?>)"><i class="fas fa-edit"></i></button>
                                            <!-- Delete -->
                                            <button onclick="deleteItem(<?= $item['id'] ?>)" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center;color:#64748b;padding:40px;">
                                        <i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:10px;color:#d1d5db;"></i>
                                        No inventory items found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Transactions Tab -->
            <div class="tab-content <?= $active_tab == 'transactions' ? 'active' : '' ?>">
                <div class="table-container">
                    <h3 style="margin-bottom:15px;"><i class="fas fa-exchange-alt"></i> Recent Transactions</h3>
                    <?php
                    $transactions = $pdo->query("
                        SELECT t.*, i.product_name, u.username
                        FROM inventory_transactions t
                        JOIN inventory_items i ON i.id = t.item_id
                        LEFT JOIN users u ON u.id = t.created_by
                        ORDER BY t.created_at DESC LIMIT 50
                    ")->fetchAll();
                    ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['product_name']) ?></td>
                                    <td>
                                        <span class="status-badge <?= $t['transaction_type'] == 'Stock In' ? 'status-active' : 'status-inactive' ?>">
                                            <?= $t['transaction_type'] ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($t['quantity'], 2) ?></td>
                                    <td>$<?= number_format($t['unit_cost'], 2) ?></td>
                                    <td>$<?= number_format($t['total_cost'], 2) ?></td>
                                    <td><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($t['username'] ?? 'System') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;color:#64748b;padding:40px;">
                                        <i class="fas fa-exchange-alt" style="font-size:32px;display:block;margin-bottom:10px;color:#d1d5db;"></i>
                                        No transactions found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Categories Tab -->
            <div class="tab-content <?= $active_tab == 'categories' ? 'active' : '' ?>">
                <div class="card">
                    <h3><i class="fas fa-tags"></i> Categories</h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                        <?php foreach ($categories as $cat): ?>
                            <div style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e5e7eb;">
                                <strong><?= htmlspecialchars($cat['category_name']) ?></strong>
                                <div style="font-size:12px;color:#64748b;"><?= htmlspecialchars($cat['description'] ?? 'No description') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal" id="itemModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('itemModal')">&times;</span>
        <h2><i class="fas fa-box"></i> <span id="itemModalTitle">Add Inventory Item</span></h2>
        <form method="POST">
            <input type="hidden" name="action" id="itemAction" value="add_item">
            <input type="hidden" name="item_id" id="itemId" value="">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="product_name" id="item_product_name" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="item_description" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="item_category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" id="item_sku" placeholder="Stock Keeping Unit">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Unit of Measure</label>
                    <input type="text" name="unit_of_measure" id="item_unit" value="each" placeholder="each, kg, box">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="item_status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Discontinued">Discontinued</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Current Stock</label>
                    <input type="number" step="0.01" name="current_stock" id="item_current_stock" value="0">
                </div>
                <div class="form-group">
                    <label>Unit Cost ($)</label>
                    <input type="number" step="0.01" name="unit_cost" id="item_unit_cost" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Min Stock Level</label>
                    <input type="number" step="0.01" name="min_stock_level" id="item_min_stock" value="0">
                </div>
                <div class="form-group">
                    <label>Max Stock Level</label>
                    <input type="number" step="0.01" name="max_stock_level" id="item_max_stock" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplier_id" id="item_supplier_id">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['supplier_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="item_location" placeholder="Warehouse location">
                </div>
            </div>
            <div class="form-group">
                <label>Stock In</label>
                <input type="number" step="0.01" name="stock_in" id="item_stock_in" value="0">
            </div>
            <div class="form-group">
                <label>Stock Out</label>
                <input type="number" step="0.01" name="stock_out" id="item_stock_out" value="0">
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> <span id="itemSubmitText">Add Item</span></button>
        </form>
    </div>
</div>

<!-- Stock Modal -->
<div class="modal" id="stockModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('stockModal')">&times;</span>
        <h2><i class="fas fa-exchange-alt"></i> <span id="stockModalTitle">Stock In</span></h2>
        <form method="POST">
            <input type="hidden" name="action" id="stockAction" value="stock_in">
            <input type="hidden" name="item_id" id="stockItemId" value="">
            <div class="form-group">
                <label>Item</label>
                <input type="text" id="stockItemName" readonly style="background:#f8fafc;">
            </div>
            <div class="form-group">
                <label>Quantity *</label>
                <input type="number" step="0.01" name="quantity" id="stockQuantity" required>
            </div>
            <div class="form-group" id="stockCostGroup">
                <label>Unit Cost ($)</label>
                <input type="number" step="0.01" name="unit_cost" id="stockUnitCost" value="0">
            </div>
            <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="reference_number" id="stockReference" placeholder="PO #, Invoice #">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="stockNotes" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> <span id="stockSubmitText">Add Stock</span></button>
        </form>
    </div>
</div>

<script>
// Item functions
function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

function editItem(id) {
    document.getElementById('itemModalTitle').textContent = 'Edit Inventory Item';
    document.getElementById('itemAction').value = 'update_item';
    document.getElementById('itemId').value = id;
    document.getElementById('itemSubmitText').textContent = 'Update Item';
    
    // Fetch data via AJAX or preload
    // For now, we'll show the modal and let user fill
    openModal('itemModal');
}

function deleteItem(id) {
    if(confirm('Delete this item?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="'+id+'">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Stock functions
function openStockModal(type, id, name) {
    document.getElementById('stockModalTitle').textContent = type === 'in' ? 'Stock In' : 'Stock Out';
    document.getElementById('stockAction').value = type === 'in' ? 'stock_in' : 'stock_out';
    document.getElementById('stockItemId').value = id;
    document.getElementById('stockItemName').value = name;
    document.getElementById('stockSubmitText').textContent = type === 'in' ? 'Add Stock' : 'Remove Stock';
    
    if (type === 'out') {
        document.getElementById('stockCostGroup').style.display = 'none';
        document.getElementById('stockQuantity').placeholder = 'Quantity to remove';
    } else {
        document.getElementById('stockCostGroup').style.display = 'block';
        document.getElementById('stockQuantity').placeholder = 'Quantity to add';
    }
    
    document.getElementById('stockQuantity').value = '';
    document.getElementById('stockUnitCost').value = '0';
    document.getElementById('stockReference').value = '';
    document.getElementById('stockNotes').value = '';
    
    openModal('stockModal');
}

// Filter table
function filterTable(tableId, searchId) {
    var input = document.getElementById(searchId).value.toLowerCase();
    var statusFilter = document.getElementById('statusFilter');
    var status = statusFilter ? statusFilter.value : '';
    var rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    
    rows.forEach(row => {
        var text = row.textContent.toLowerCase();
        var rowStatus = row.querySelector('.status-badge')?.textContent || '';
        var match = text.includes(input) && (status === '' || rowStatus.includes(status));
        row.style.display = match ? '' : 'none';
    });
}

// Close modals on outside click
window.onclick = function(e) {
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        if (e.target === modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
}

// Auto-hide messages
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