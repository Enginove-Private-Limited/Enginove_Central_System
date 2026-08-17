<?php
// purchase_orders.php - With Comparative Schedule
require_once 'config/database.php';
requireLogin();

$pageTitle = "Purchase Orders";
$message = '';
$active_tab = $_GET['tab'] ?? 'orders';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_po') {
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders (po_number, title, supplier_id, tender_id, total_amount, expected_delivery, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['po_number'],
            $_POST['title'],
            $_POST['supplier_id'] ?: NULL,
            $_POST['tender_id'] ?: NULL,
            $_POST['total_amount'],
            $_POST['expected_delivery'] ?: NULL,
            $_SESSION['user_id']
        ]);
        $message = 'Purchase Order created successfully!';
    }
    
    if ($action === 'add_quotation') {
        $stmt = $pdo->prepare("
            INSERT INTO comparative_quotations (tender_id, product_name, category_id, quantity, unit, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['tender_id'],
            $_POST['product_name'],
            $_POST['category_id'],
            $_POST['quantity'],
            $_POST['unit'],
            $_SESSION['user_id']
        ]);
        $quotation_id = $pdo->lastInsertId();
        
        // Add supplier quotes
        $suppliers = $_POST['supplier_name'] ?? [];
        $prices = $_POST['unit_price'] ?? [];
        
        foreach ($suppliers as $index => $supplier_name) {
            if (!empty($supplier_name) && isset($prices[$index])) {
                $unit_price = $prices[$index];
                $quantity = $_POST['quantity'];
                $total_price = $unit_price * $quantity;
                
                $stmt2 = $pdo->prepare("
                    INSERT INTO quotation_suppliers (quotation_id, supplier_name, unit_price, total_price) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt2->execute([$quotation_id, $supplier_name, $unit_price, $total_price]);
            }
        }
        
        // Update comparative summary
        updateComparativeSummary($_POST['tender_id']);
        
        $message = 'Quotation comparison added successfully!';
    }
    
    if ($action === 'delete_quotation') {
        $stmt = $pdo->prepare("DELETE FROM comparative_quotations WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = 'Quotation deleted!';
    }
}

// Function to update comparative summary
function updateComparativeSummary($tender_id) {
    global $pdo;
    
    // Get all quotations for this tender
    $quotations = $pdo->prepare("
        SELECT cq.*, qs.supplier_name, qs.unit_price, qs.total_price,
               cat.category_name
        FROM comparative_quotations cq
        LEFT JOIN quotation_suppliers qs ON qs.quotation_id = cq.id
        LEFT JOIN product_categories cat ON cat.id = cq.category_id
        WHERE cq.tender_id = ?
    ");
    $quotations->execute([$tender_id]);
    $data = $quotations->fetchAll();
    
    // Clear old summary
    $pdo->prepare("DELETE FROM comparative_summary WHERE tender_id = ?")
        ->execute([$tender_id]);
    
    // Group by product and find best supplier
    $summary = [];
    foreach ($data as $row) {
        $key = $row['product_name'] . '_' . $row['category_id'];
        if (!isset($summary[$key])) {
            $summary[$key] = [
                'category_id' => $row['category_id'],
                'product_name' => $row['product_name'],
                'best_supplier' => $row['supplier_name'],
                'best_price' => $row['total_price'],
                'total_quotes' => 0
            ];
        }
        $summary[$key]['total_quotes']++;
        if ($row['total_price'] < $summary[$key]['best_price']) {
            $summary[$key]['best_supplier'] = $row['supplier_name'];
            $summary[$key]['best_price'] = $row['total_price'];
        }
    }
    
    // Insert summary
    foreach ($summary as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO comparative_summary (tender_id, category_id, product_name, best_supplier, best_price, total_quotes) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tender_id,
            $item['category_id'],
            $item['product_name'],
            $item['best_supplier'],
            $item['best_price'],
            $item['total_quotes']
        ]);
    }
}

// Get all purchase orders
$orders = $pdo->query("
    SELECT po.*, 
           s.supplier_name,
           t.tender_number
    FROM purchase_orders po
    LEFT JOIN suppliers s ON s.id = po.supplier_id
    LEFT JOIN tenders t ON t.id = po.tender_id
    ORDER BY po.created_at DESC
")->fetchAll();

// Get comparative quotations for a tender
$tender_id = $_GET['tender_id'] ?? 0;
if ($tender_id) {
    $comparative_data = $pdo->prepare("
        SELECT cq.*, 
               cat.category_name,
               qs.supplier_name, qs.unit_price, qs.total_price,
               qs.id as quote_id,
               qs.is_selected
        FROM comparative_quotations cq
        LEFT JOIN product_categories cat ON cat.id = cq.category_id
        LEFT JOIN quotation_suppliers qs ON qs.quotation_id = cq.id
        WHERE cq.tender_id = ?
        ORDER BY cq.product_name
    ");
    $comparative_data->execute([$tender_id]);
    $comparative_data = $comparative_data->fetchAll();
    
    // Get summary
    $summary = $pdo->prepare("
        SELECT cs.*, cat.category_name 
        FROM comparative_summary cs
        LEFT JOIN product_categories cat ON cat.id = cs.category_id
        WHERE cs.tender_id = ?
    ");
    $summary->execute([$tender_id]);
    $summary = $summary->fetchAll();
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name")->fetchAll();
$tenders = $pdo->query("SELECT id, tender_number, tender_name FROM tenders WHERE status != 'Cancelled' ORDER BY tender_number")->fetchAll();
$categories = $pdo->query("SELECT * FROM product_categories ORDER BY category_name")->fetchAll();
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        .page-header .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .page-header .subtitle {
            margin: 0;
            font-size: 15px;
            color: #64748b;
        }
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-2px); }
        .btn-sm { padding:6px 14px; font-size:13px; }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        .btn-danger { background:#dc2626; color:white; }
        .btn-danger:hover { background:#b91c1c; }
        
        .actions { display:flex; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:15px; align-items:center; }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 25px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
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
        
        .table-container { background:white; border-radius:15px; padding:20px; box-shadow:0 8px 25px rgba(0,0,0,0.05); overflow-x:auto; margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; min-width:700px; font-size:13px; }
        th { text-align:left; padding:12px 14px; background:var(--light-green); color:var(--dark); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; }
        td { padding:12px 14px; border-bottom:1px solid #f3f4f6; }
        
        .status { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
        .status-pending { background:#fef3c7; color:#b45309; }
        .status-approved { background:var(--light-green); color:var(--green); }
        .status-ordered { background:#dbeafe; color:#1d4ed8; }
        .status-received { background:#d4edda; color:#0f5a2e; }
        .status-cancelled { background:#f3f4f6; color:#6b7280; }
        
        .badge-best { background:var(--green); color:white; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .badge-chain { background:#dbeafe; color:#1d4ed8; padding:2px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; padding:35px; border-radius:20px; width:100%; max-width:700px; max-height:90vh; overflow-y:auto; }
        .modal-content .close { float:right; font-size:24px; cursor:pointer; color:#64748b; }
        .modal-content h2 { margin-bottom:20px; color:var(--dark); }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; color:var(--dark); }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:var(--green); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .form-row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; }
        
        .comparative-table .supplier-price { 
            min-width: 120px;
        }
        .comparative-table .best-price {
            background: #d4edda;
            font-weight: 700;
        }
        .comparative-table .selected {
            background: #dbeafe;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            padding: 18px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border-left: 4px solid var(--green);
        }
        .summary-card .product { font-weight: 600; color: var(--dark); }
        .summary-card .best { color: var(--green); font-weight: 600; }
        .summary-card .price { font-size: 18px; font-weight: 700; color: var(--dark); }
        .summary-card .quotes { font-size: 12px; color: #64748b; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; }
            .page-header .page-title { font-size:24px; }
            .form-row { grid-template-columns:1fr; }
            .form-row-3 { grid-template-columns:1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-header .page-title { font-size:20px; }
            .actions { flex-direction:column; align-items:stretch; }
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
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-shopping-cart"></i> <?= $pageTitle ?></h1>
                <p class="subtitle">Manage purchase orders and comparative quotations.</p>
            </div>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <a href="?tab=orders" class="<?= $active_tab == 'orders' ? 'active' : '' ?>"><i class="fas fa-list"></i> Purchase Orders</a>
                <a href="?tab=comparative" class="<?= $active_tab == 'comparative' ? 'active' : '' ?>"><i class="fas fa-balance-scale"></i> Comparative Schedule</a>
                <a href="?tab=add_comparative" class="<?= $active_tab == 'add_comparative' ? 'active' : '' ?>"><i class="fas fa-plus"></i> Add Comparison</a>
            </div>
            
            <!-- ===== PURCHASE ORDERS TAB ===== -->
            <div class="tab-content <?= $active_tab == 'orders' ? 'active' : '' ?>">
                <div class="actions">
                    <button class="btn btn-green" onclick="openModal('poModal')"><i class="fas fa-plus"></i> New Purchase Order</button>
                    <div>
                        <span style="margin-right:15px;">Approved: <?= count(array_filter($orders, fn($o) => $o['status'] == 'Approved')) ?></span>
                        <span>Pending: <?= count(array_filter($orders, fn($o) => $o['status'] == 'Pending')) ?></span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Title</th>
                                <th>Supplier</th>
                                <th>Tender</th>
                                <th>Total Amount</th>
                                <th>Delivery Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($order['po_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($order['title'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($order['supplier_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($order['tender_number'] ?? 'N/A') ?></td>
                                    <td>$<?= number_format($order['total_amount'] ?? 0, 2) ?></td>
                                    <td><?= $order['expected_delivery'] ? date('d M Y', strtotime($order['expected_delivery'])) : 'N/A' ?></td>
                                    <td><span class="status status-<?= strtolower($order['status'] ?? 'pending') ?>"><?= $order['status'] ?? 'Pending' ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-green"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm" style="background:#e5e7eb;"><i class="fas fa-edit"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="8" style="text-align:center;color:#64748b;padding:30px;">No purchase orders found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ===== COMPARATIVE SCHEDULE TAB ===== -->
            <div class="tab-content <?= $active_tab == 'comparative' ? 'active' : '' ?>">
                <div class="actions">
                    <span style="font-weight:600;color:var(--dark);">Comparative Quotation Summary</span>
                    <select id="tenderFilter" onchange="location.href='?tab=comparative&tender_id='+this.value" style="padding:10px 16px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px;">
                        <option value="0">Select Tender</option>
                        <?php foreach ($tenders as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $tender_id == $t['id'] ? 'selected' : '' ?>>
                                <?= $t['tender_number'] ?> - <?= substr($t['tender_name'], 0, 40) ?>...
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($tender_id && !empty($summary)): ?>
                    <!-- Summary Cards -->
                    <div class="summary-grid">
                        <?php foreach ($summary as $item): ?>
                            <div class="summary-card">
                                <div class="product"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="best">🏆 Best: <?= htmlspecialchars($item['best_supplier']) ?></div>
                                <div class="price">$<?= number_format($item['best_price'], 2) ?></div>
                                <div class="quotes"><?= $item['total_quotes'] ?> quotes received</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Detailed Comparison Table -->
                    <div class="table-container">
                        <h3 style="margin-bottom:15px;">Detailed Quotation Comparison</h3>
                        <table class="comparative-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Qty</th>
                                    <th>Supplier</th>
                                    <th>Unit Price</th>
                                    <th>Total Price</th>
                                    <th>Best</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $products = [];
                                foreach ($comparative_data as $row) {
                                    $key = $row['product_name'] . '_' . $row['category_id'];
                                    if (!isset($products[$key])) {
                                        $products[$key] = [
                                            'product_name' => $row['product_name'],
                                            'category' => $row['category_name'],
                                            'quantity' => $row['quantity'],
                                            'suppliers' => []
                                        ];
                                    }
                                    if ($row['supplier_name']) {
                                        $products[$key]['suppliers'][] = [
                                            'name' => $row['supplier_name'],
                                            'unit_price' => $row['unit_price'],
                                            'total_price' => $row['total_price'],
                                            'is_selected' => $row['is_selected']
                                        ];
                                    }
                                }
                                
                                $best_prices = [];
                                foreach ($products as $key => $product) {
                                    $min_price = PHP_FLOAT_MAX;
                                    foreach ($product['suppliers'] as $s) {
                                        if ($s['total_price'] < $min_price) {
                                            $min_price = $s['total_price'];
                                        }
                                    }
                                    $best_prices[$key] = $min_price;
                                }
                                
                                foreach ($products as $key => $product): 
                                    $first = true;
                                    foreach ($product['suppliers'] as $s):
                                ?>
                                    <tr class="<?= $s['total_price'] == $best_prices[$key] ? 'best-price' : '' ?> <?= $s['is_selected'] ? 'selected' : '' ?>">
                                        <?php if ($first): ?>
                                            <td rowspan="<?= count($product['suppliers']) ?>"><strong><?= htmlspecialchars($product['product_name']) ?></strong></td>
                                            <td rowspan="<?= count($product['suppliers']) ?>"><?= htmlspecialchars($product['category']) ?></td>
                                            <td rowspan="<?= count($product['suppliers']) ?>"><?= $product['quantity'] ?></td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($s['name']) ?></td>
                                        <td>$<?= number_format($s['unit_price'], 2) ?></td>
                                        <td>$<?= number_format($s['total_price'], 2) ?></td>
                                        <td>
                                            <?php if ($s['total_price'] == $best_prices[$key]): ?>
                                                <span class="badge-best">★ Best</span>
                                            <?php endif; ?>
                                            <?php if ($s['is_selected']): ?>
                                                <span class="badge-chain">✓ Selected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php 
                                    $first = false;
                                    endforeach; 
                                endforeach; 
                                ?>
                                <?php if (empty($products)): ?>
                                    <tr><td colspan="7" style="text-align:center;color:#64748b;padding:30px;">No comparative data found for this tender</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($tender_id): ?>
                    <div style="background:white;border-radius:15px;padding:40px;text-align:center;box-shadow:0 8px 25px rgba(0,0,0,0.05);">
                        <i class="fas fa-file-alt" style="font-size:48px;color:#64748b;margin-bottom:15px;"></i>
                        <h3 style="color:#64748b;">No comparative data for this tender</h3>
                        <p style="color:#94a3b8;">Add quotations to compare suppliers</p>
                        <a href="?tab=add_comparative&tender_id=<?= $tender_id ?>" class="btn btn-green" style="margin-top:15px;"><i class="fas fa-plus"></i> Add Quotation</a>
                    </div>
                <?php else: ?>
                    <div style="background:white;border-radius:15px;padding:40px;text-align:center;box-shadow:0 8px 25px rgba(0,0,0,0.05);">
                        <i class="fas fa-balance-scale" style="font-size:48px;color:#64748b;margin-bottom:15px;"></i>
                        <h3 style="color:#64748b;">Select a tender to view comparative schedule</h3>
                        <p style="color:#94a3b8;">Choose a tender from the dropdown above</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ===== ADD COMPARATIVE TAB ===== -->
            <div class="tab-content <?= $active_tab == 'add_comparative' ? 'active' : '' ?>">
                <div class="actions">
                    <span style="font-weight:600;color:var(--dark);">Add New Quotation Comparison</span>
                </div>
                
                <div style="background:white;border-radius:15px;padding:30px;box-shadow:0 8px 25px rgba(0,0,0,0.05);">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_quotation">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Select Tender *</label>
                                <select name="tender_id" required>
                                    <option value="">Select Tender</option>
                                    <?php foreach ($tenders as $t): ?>
                                        <option value="<?= $t['id'] ?>" <?= ($_GET['tender_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
                                            <?= $t['tender_number'] ?> - <?= substr($t['tender_name'], 0, 50) ?>...
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= $cat['category_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Product Name *</label>
                                <input type="text" name="product_name" placeholder="e.g. Interior Paint" required>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" name="quantity" value="1" step="0.01" min="0.01">
                            </div>
                            <div class="form-group">
                                <label>Unit</label>
                                <input type="text" name="unit" placeholder="e.g. liters, each, m²" value="each">
                            </div>
                        </div>
                        
                        <hr style="margin:20px 0;border-color:#e5e7eb;">
                        <h4 style="margin-bottom:15px;color:var(--dark);">Supplier Quotations</h4>
                        
                        <div id="supplierQuotes">
                            <div class="form-row-3 supplier-row">
                                <div class="form-group">
                                    <label>Supplier Name *</label>
                                    <input type="text" name="supplier_name[]" placeholder="e.g. Best Paints" required>
                                </div>
                                <div class="form-group">
                                    <label>Unit Price *</label>
                                    <input type="number" step="0.01" name="unit_price[]" placeholder="0.00" required>
                                </div>
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="button" onclick="removeSupplierRow(this)" style="padding:10px 16px;background:#fee2e2;color:#b91c1c;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" onclick="addSupplierRow()" class="btn btn-outline" style="margin-top:10px;"><i class="fas fa-plus"></i> Add Another Supplier</button>
                        
                        <div style="margin-top:20px;">
                            <button type="submit" class="btn btn-green"><i class="fas fa-save"></i> Save Comparison</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New PO Modal -->
<div class="modal" id="poModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('poModal')">&times;</span>
        <h2><i class="fas fa-plus-circle"></i> New Purchase Order</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create_po">
            
            <div class="form-group">
                <label>PO Number *</label>
                <input type="text" name="po_number" placeholder="e.g. PO-2026-001" required>
            </div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" placeholder="Order title" required>
            </div>
            <div class="form-group">
                <label>Supplier</label>
                <select name="supplier_id">
                    <option value="">Select Supplier</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= $s['supplier_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Related Tender</label>
                <select name="tender_id">
                    <option value="">Select Tender</option>
                    <?php foreach ($tenders as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= $t['tender_number'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Total Amount *</label>
                <input type="number" step="0.01" name="total_amount" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Expected Delivery Date</label>
                <input type="date" name="expected_delivery">
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;"><i class="fas fa-save"></i> Create PO</button>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
window.onclick = function(e) { 
    if (e.target.classList.contains('modal')) { 
        e.target.classList.remove('show'); 
    } 
}

function addSupplierRow() {
    const container = document.getElementById('supplierQuotes');
    const row = document.createElement('div');
    row.className = 'form-row-3 supplier-row';
    row.style.marginTop = '10px';
    row.innerHTML = `
        <div class="form-group">
            <label>Supplier Name *</label>
            <input type="text" name="supplier_name[]" placeholder="e.g. Best Paints" required>
        </div>
        <div class="form-group">
            <label>Unit Price *</label>
            <input type="number" step="0.01" name="unit_price[]" placeholder="0.00" required>
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="button" onclick="removeSupplierRow(this)" style="padding:10px 16px;background:#fee2e2;color:#b91c1c;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-trash"></i></button>
        </div>
    `;
    container.appendChild(row);
}

function removeSupplierRow(btn) {
    const rows = document.querySelectorAll('.supplier-row');
    if (rows.length > 1) {
        btn.closest('.supplier-row').remove();
    } else {
        alert('You need at least one supplier');
    }
}
</script>
</body>
</html>