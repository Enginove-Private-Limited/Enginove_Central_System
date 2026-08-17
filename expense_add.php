<?php
// expense_add.php
require_once 'config/database.php';
requireLogin();

$pageTitle = "Add Expense";
$message = '';
$error = '';

// Get categories for dropdown
$categories = $pdo->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll();

// Get tenders for dropdown
$tenders = $pdo->query("SELECT id, tender_number, tender_name FROM tenders WHERE status != 'Cancelled' ORDER BY tender_number")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_expense'])) {
    try {
        // Generate expense number
        $expense_number = 'EXP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Prepare the SQL
        $stmt = $pdo->prepare("
            INSERT INTO expenses (
                expense_number, tender_id, category_id, expense_date, description,
                supplier_name, supplier_contact, payment_method,
                amount_usd, vat_rate_usd, amount_zwg, vat_rate_zwg,
                currency_used, petty_cash_amount, expense_detail,
                receipt_number, invoice_number,
                created_by
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?
            )
        ");
        
        // Handle file upload
        $receipt_file = null;
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/expenses/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION);
            $filename = 'receipt_' . date('Ymd_His') . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $filepath)) {
                $receipt_file = $filepath;
            }
        }
        
        $stmt->execute([
            $expense_number,
            $_POST['tender_id'] ?: null,
            $_POST['category_id'] ?: null,
            $_POST['expense_date'],
            $_POST['description'],
            $_POST['supplier_name'] ?: null,
            $_POST['supplier_contact'] ?: null,
            $_POST['payment_method'],
            $_POST['amount_usd'] ?: 0,
            $_POST['vat_rate_usd'] ?: 15,
            $_POST['amount_zwg'] ?: 0,
            $_POST['vat_rate_zwg'] ?: 15,
            $_POST['currency_used'],
            $_POST['petty_cash_amount'] ?: 0,
            $_POST['expense_detail'] ?: null,
            $_POST['receipt_number'] ?: null,
            $_POST['invoice_number'] ?: null,
            $_SESSION['user_id']
        ]);
        
        // If receipt was uploaded, update the record with file path
        if ($receipt_file) {
            $expense_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("UPDATE expenses SET receipt_file = ? WHERE id = ?");
            $stmt->execute([$receipt_file, $expense_id]);
        }
        
        $message = "Expense created successfully! Expense Number: " . $expense_number;
        
    } catch (Exception $e) {
        $error = "Error creating expense: " . $e->getMessage();
    }
}
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
        .card h3 { margin-bottom:15px; color:var(--dark); }
        
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; font-weight:500; margin-bottom:6px; font-size:14px; color:#374151; }
        .form-group input, .form-group select, .form-group textarea { 
            width:100%; padding:10px 14px; border:2px solid #e5e7eb; border-radius:8px; font-size:14px; transition:.2s; 
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color:var(--green); outline:none; box-shadow:0 0 0 3px rgba(31,139,76,0.1);
        }
        .form-group textarea { resize:vertical; min-height:80px; }
        .form-group .help-text { font-size:12px; color:#64748b; margin-top:4px; }
        
        .btn { padding:10px 24px; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition:.25s; display:inline-flex; align-items:center; gap:8px; font-size:14px; }
        .btn-green { background:var(--green); color:white; }
        .btn-green:hover { background:#0f6a36; transform:translateY(-1px); box-shadow:0 4px 12px rgba(31,139,76,0.3); }
        .btn-outline { background:transparent; border:2px solid var(--green); color:var(--green); }
        .btn-outline:hover { background:var(--green); color:white; }
        
        .message { background:#d4edda; color:#0f5a2e; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .error { background:#fee2e2; color:#b91c1c; padding:12px 16px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        
        .section-title { 
            font-size:16px; font-weight:600; color:var(--dark); 
            padding-bottom:10px; border-bottom:2px solid var(--light-green); 
            margin-bottom:15px; 
        }
        
        .actions { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap; }
        
        .currency-box { 
            border:1px solid #e5e7eb; 
            padding:15px; 
            border-radius:8px; 
            background:#fafdfb;
        }
        .currency-box h4 { margin-bottom:10px; font-size:14px; }
        
        @media(max-width:991px) { 
            .main { margin-left:0; } 
            .form-grid { grid-template-columns:1fr; }
        }
        @media(max-width:768px) { 
            .content { padding:15px; } 
            .page-title { font-size:22px; }
            .actions { flex-direction:column; }
            .actions .btn { width:100%; justify-content:center; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <?php include 'header.php'; ?>
        <div class="content">
            <h1 class="page-title"><i class="fas fa-plus-circle"></i> <?= $pageTitle ?></h1>
            <p class="subtitle">Record a new paid expense with dual currency support.</p>
            
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <!-- Expense Details -->
                    <div class="section-title"><i class="fas fa-info-circle"></i> Expense Details</div>
                    <div class="form-grid">
                        <div class="form-group">
    <label>Expense Date *</label>
    <input type="date" name="expense_date" required readonly value="<?= date('Y-m-d') ?>">
</div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Description *</label>
                            <textarea name="description" rows="2" required placeholder="Brief description of the expense"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Supplier Name</label>
                            <input type="text" name="supplier_name" placeholder="Supplier or vendor name">
                        </div>
                        <div class="form-group">
                            <label>Supplier Contact</label>
                            <input type="text" name="supplier_contact" placeholder="Phone or email">
                        </div>
                    </div>
                    
                    <!-- Financial Details -->
                    <div class="section-title" style="margin-top:20px;"><i class="fas fa-money-bill-wave"></i> Financial Details</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Payment Method *</label>
                            <select name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Petty Cash">Petty Cash</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Currency Used *</label>
                            <select name="currency_used" required>
                                <option value="USD">USD Only</option>
                                <option value="ZWG">ZWG Only</option>
                                <option value="Both">Both USD & ZWG</option>
                            </select>
                        </div>
                        
                        <!-- USD Section -->
                        <div class="currency-box">
                            <h4 style="color:var(--green);"><i class="fas fa-dollar-sign"></i> USD Amount</h4>
                            <div class="form-group">
                                <label>Amount (USD)</label>
                                <input type="number" step="0.01" name="amount_usd" placeholder="0.00" value="0.00">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>VAT Rate (%)</label>
                                <input type="number" step="0.01" name="vat_rate_usd" value="15">
                                <div class="help-text">VAT will be calculated automatically</div>
                            </div>
                        </div>
                        
                        <!-- ZWG Section -->
                        <div class="currency-box">
                            <h4 style="color:#f59e0b;"><i class="fas fa-money-bill"></i> ZWG Amount</h4>
                            <div class="form-group">
                                <label>Amount (ZWG)</label>
                                <input type="number" step="0.01" name="amount_zwg" placeholder="0.00" value="0.00">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>VAT Rate (%)</label>
                                <input type="number" step="0.01" name="vat_rate_zwg" value="15">
                                <div class="help-text">VAT will be calculated automatically</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Petty Cash Amount</label>
                            <input type="number" step="0.01" name="petty_cash_amount" placeholder="0.00" value="0.00">
                            <div class="help-text">Amount paid from petty cash</div>
                        </div>
                        <div class="form-group">
                            <label>Expense Detail</label>
                            <textarea name="expense_detail" rows="2" placeholder="Additional expense details"></textarea>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="section-title" style="margin-top:20px;"><i class="fas fa-paperclip"></i> Additional Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Receipt Number</label>
                            <input type="text" name="receipt_number" placeholder="Receipt reference number">
                        </div>
                        <div class="form-group">
                            <label>Invoice Number</label>
                            <input type="text" name="invoice_number" placeholder="Invoice reference number">
                        </div>
                        <div class="form-group">
                            <label>Receipt File (Optional)</label>
                            <input type="file" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div class="help-text">Upload receipt or invoice (PDF, JPG, PNG, DOC) - Max 5MB</div>
                        </div>
                        <div class="form-group">
                            <label>Tender/Project (Optional)</label>
                            <select name="tender_id">
                                <option value="">No Tender</option>
                                <?php foreach ($tenders as $tender): ?>
                                    <option value="<?= $tender['id'] ?>"><?= htmlspecialchars($tender['tender_number']) ?> - <?= htmlspecialchars($tender['tender_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="actions">
                        <button type="submit" name="submit_expense" class="btn btn-green">
                            <i class="fas fa-save"></i> Save Expense
                        </button>
                        <a href="expenses.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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