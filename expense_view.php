<?php
// expense_view.php - View detailed expense information
require_once 'config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;

if (!$id) {
    header('Location: expenses.php');
    exit();
}

// Get expense details
$stmt = $pdo->prepare("
    SELECT e.*, 
           ec.category_name,
           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON u.id = e.created_by
    WHERE e.id = ?
");
$stmt->execute([$id]);
$expense = $stmt->fetch();

if (!$expense) {
    header('Location: expenses.php');
    exit();
}

$pageTitle = "Expense: " . $expense['expense_number'];
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
            margin-bottom: 8px;
        }
        .page-header .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .page-header .subtitle {
            margin: 0;
            font-size: 14px;
            color: #64748b;
        }
        
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
        }
        .btn-green { background: var(--green); color: white; }
        .btn-green:hover { background: #0f6a36; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(31,139,76,0.25); }
        .btn-outline { background: transparent; border: 2px solid var(--green); color: var(--green); }
        .btn-outline:hover { background: var(--green); color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 4px 12px; font-size: 12px; border-radius: 6px; }
        
        .actions-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        .detail-card h3 {
            font-size: 15px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-card h3 i {
            color: var(--green);
        }
        .detail-card .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .detail-card .row:last-child { border-bottom: none; }
        .detail-card .label { 
            color: #64748b; 
            font-size: 13px; 
            font-weight: 500;
        }
        .detail-card .value { 
            font-weight: 500; 
            color: var(--dark); 
            font-size: 13px;
            text-align: right;
            max-width: 60%;
        }
        .detail-card .value .highlight {
            color: var(--green);
            font-weight: 600;
        }
        .detail-card .value .usd { color: #2563eb; font-weight: 600; }
        .detail-card .value .zwg { color: #f59e0b; font-weight: 600; }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .attachment-preview {
            border: 2px dashed #e5e7eb;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #fafdfb;
            transition: 0.2s;
        }
        .attachment-preview:hover {
            border-color: var(--green);
            background: #f0fdf4;
        }
        .attachment-preview i {
            font-size: 48px;
            color: var(--green);
            display: block;
            margin-bottom: 10px;
        }
        .attachment-preview .file-name {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 4px;
        }
        .attachment-preview .file-size {
            font-size: 12px;
            color: #64748b;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .currency-box {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            background: #f8fafc;
            font-weight: 600;
        }
        .currency-box.usd { border-left: 3px solid #2563eb; }
        .currency-box.zwg { border-left: 3px solid #f59e0b; }
        
        @media(max-width: 991px) { 
            .main { margin-left: 0; }
            .detail-grid { grid-template-columns: 1fr; }
        }
        @media(max-width: 768px) { 
            .content { padding: 15px; } 
            .page-header .page-title { font-size: 20px; }
            .detail-card { padding: 18px; }
            .actions-bar { flex-direction: column; }
            .actions-bar .btn { justify-content: center; }
        }
        @media(max-width: 480px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .detail-card .row { flex-direction: column; gap: 4px; }
            .detail-card .value { text-align: left; max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="fas fa-receipt" style="color:var(--green);"></i> <?= htmlspecialchars($expense['expense_number']) ?></h1>
                    <p class="subtitle"><?= htmlspecialchars($expense['description']) ?></p>
                </div>
                <div>
                    <span class="status-badge" style="background:var(--light-green);color:var(--green);">
                        <i class="fas fa-check-circle"></i> Paid
                    </span>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="actions-bar">
                <a href="expense_edit.php?id=<?= $id ?>" class="btn btn-green"><i class="fas fa-edit"></i> Edit Expense</a>
                <a href="expenses.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Expenses</a>
                <button onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print</button>
                <a href="expense_delete.php?id=<?= $id ?>" class="btn btn-danger" onclick="return confirm('Delete this expense?')"><i class="fas fa-trash"></i> Delete</a>
            </div>
            
            <!-- Detail Grid -->
            <div class="detail-grid">
                <!-- Basic Information -->
                <div class="detail-card">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    <div class="row">
                        <span class="label">Expense Number</span>
                        <span class="value"><strong><?= htmlspecialchars($expense['expense_number']) ?></strong></span>
                    </div>
                    <div class="row">
                        <span class="label">Date</span>
                        <span class="value"><?= date('d M Y', strtotime($expense['expense_date'])) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Category</span>
                        <span class="value">
                            <span style="background:#f1f5f9;padding:2px 12px;border-radius:12px;font-size:12px;">
                                <?= htmlspecialchars($expense['category_name'] ?? 'Uncategorized') ?>
                            </span>
                        </span>
                    </div>
                    <div class="row">
                        <span class="label">Description</span>
                        <span class="value"><?= nl2br(htmlspecialchars($expense['description'])) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Created By</span>
                        <span class="value"><?= htmlspecialchars($expense['created_by_name'] ?? 'Unknown') ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Created At</span>
                        <span class="value"><?= date('d M Y H:i', strtotime($expense['created_at'])) ?></span>
                    </div>
                </div>
                
                <!-- Supplier & Payment -->
                <div class="detail-card">
                    <h3><i class="fas fa-truck"></i> Supplier & Payment</h3>
                    <div class="row">
                        <span class="label">Supplier Name</span>
                        <span class="value"><?= htmlspecialchars($expense['supplier_name'] ?? '—') ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Supplier Contact</span>
                        <span class="value"><?= htmlspecialchars($expense['supplier_contact'] ?? '—') ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Payment Method</span>
                        <span class="value">
                            <span style="background:#f1f5f9;padding:2px 12px;border-radius:12px;font-size:12px;">
                                <?= htmlspecialchars($expense['payment_method']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="row">
                        <span class="label">Currency Used</span>
                        <span class="value">
                            <span style="background:#f1f5f9;padding:2px 12px;border-radius:12px;font-size:12px;font-weight:600;<?= $expense['currency_used'] == 'USD' ? 'color:#2563eb;' : 'color:#f59e0b;' ?>">
                                <?= htmlspecialchars($expense['currency_used']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="row">
                        <span class="label">Petty Cash Amount</span>
                        <span class="value"><?= number_format($expense['petty_cash_amount'], 2) ?></span>
                    </div>
                    <?php if ($expense['expense_detail']): ?>
                    <div class="row">
                        <span class="label">Expense Detail</span>
                        <span class="value"><?= nl2br(htmlspecialchars($expense['expense_detail'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Financial Details - USD -->
                <div class="detail-card">
                    <h3><i class="fas fa-dollar-sign" style="color:#2563eb;"></i> USD Amount</h3>
                    <div class="row">
                        <span class="label">Amount (USD)</span>
                        <span class="value usd">$<?= number_format($expense['amount_usd'], 2) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">VAT Rate</span>
                        <span class="value"><?= number_format($expense['vat_rate_usd'], 2) ?>%</span>
                    </div>
                    <div class="row">
                        <span class="label">VAT Amount (USD)</span>
                        <span class="value usd">$<?= number_format($expense['vat_amount_usd'], 2) ?></span>
                    </div>
                    <div class="row" style="border-top:2px solid #e5e7eb;padding-top:12px;margin-top:4px;font-weight:600;">
                        <span class="label" style="font-weight:600;">Total USD</span>
                        <span class="value usd" style="font-size:16px;">$<?= number_format($expense['total_usd'], 2) ?></span>
                    </div>
                </div>
                
                <!-- Financial Details - ZWG -->
                <div class="detail-card">
                    <h3><i class="fas fa-money-bill" style="color:#f59e0b;"></i> ZWG Amount</h3>
                    <div class="row">
                        <span class="label">Amount (ZWG)</span>
                        <span class="value zwg"><?= number_format($expense['amount_zwg'], 2) ?></span>
                    </div>
                    <div class="row">
                        <span class="label">VAT Rate</span>
                        <span class="value"><?= number_format($expense['vat_rate_zwg'], 2) ?>%</span>
                    </div>
                    <div class="row">
                        <span class="label">VAT Amount (ZWG)</span>
                        <span class="value zwg"><?= number_format($expense['vat_amount_zwg'], 2) ?></span>
                    </div>
                    <div class="row" style="border-top:2px solid #e5e7eb;padding-top:12px;margin-top:4px;font-weight:600;">
                        <span class="label" style="font-weight:600;">Total ZWG</span>
                        <span class="value zwg" style="font-size:16px;"><?= number_format($expense['total_zwg'], 2) ?></span>
                    </div>
                </div>
                
                <!-- Receipt / Attachment -->
                <div class="detail-card full-width">
                    <h3><i class="fas fa-paperclip"></i> Receipt & Documents</h3>
                    <div class="row">
                        <span class="label">Receipt Number</span>
                        <span class="value"><?= htmlspecialchars($expense['receipt_number'] ?? '—') ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Invoice Number</span>
                        <span class="value"><?= htmlspecialchars($expense['invoice_number'] ?? '—') ?></span>
                    </div>
                    <div class="row" style="border-bottom: none; padding-bottom: 0;">
                        <span class="label">Attachment</span>
                        <span class="value" style="max-width:100%;">
                            <?php if ($expense['receipt_file']): ?>
                                <div class="attachment-preview" style="margin-top:8px;width:100%;">
                                    <i class="fas fa-file-pdf"></i>
                                    <div class="file-name"><?= basename($expense['receipt_file']) ?></div>
                                    <div class="file-size">Uploaded: <?= date('d M Y H:i', strtotime($expense['created_at'])) ?></div>
                                    <br>
                                    <a href="<?= htmlspecialchars($expense['receipt_file']) ?>" target="_blank" class="btn btn-green btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <a href="<?= htmlspecialchars($expense['receipt_file']) ?>" target="_blank" class="btn btn-outline btn-sm">
                                        <i class="fas fa-external-link-alt"></i> View
                                    </a>
                                </div>
                            <?php else: ?>
                                <span style="color:#94a3b8;">No attachment uploaded</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Summary Totals -->
                <div class="detail-card full-width" style="background:#f8fafc;border:2px solid #e5e7eb;">
                    <h3><i class="fas fa-calculator" style="color:var(--green);"></i> Summary</h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; text-align:center; padding:10px 0;">
                        <div>
                            <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Subtotal (USD)</div>
                            <div style="font-size:22px;font-weight:700;color:#2563eb;">$<?= number_format($expense['amount_usd'], 2) ?></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">VAT (USD)</div>
                            <div style="font-size:22px;font-weight:700;color:#64748b;">$<?= number_format($expense['vat_amount_usd'], 2) ?></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Total (USD)</div>
                            <div style="font-size:24px;font-weight:700;color:var(--green);">$<?= number_format($expense['total_usd'], 2) ?></div>
                        </div>
                    </div>
                    <?php if ($expense['amount_zwg'] > 0): ?>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; text-align:center; padding-top:15px; border-top:1px solid #e5e7eb; margin-top:10px;">
                        <div>
                            <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Subtotal (ZWG)</div>
                            <div style="font-size:22px;font-weight:700;color:#f59e0b;"><?= number_format($expense['amount_zwg'], 2) ?></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">VAT (ZWG)</div>
                            <div style="font-size:22px;font-weight:700;color:#64748b;"><?= number_format($expense['vat_amount_zwg'], 2) ?></div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Total (ZWG)</div>
                            <div style="font-size:24px;font-weight:700;color:var(--green);"><?= number_format($expense['total_zwg'], 2) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-print functionality (optional)
document.addEventListener('DOMContentLoaded', function() {
    // You can add any print-specific logic here
});
</script>
</body>
</html>