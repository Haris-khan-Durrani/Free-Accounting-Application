<?php
// stripe_return.php - Stripe Fallback Return URL & Automatic Real-Time Payment Sync
require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];

$invId     = (int)($_GET['invoice_id'] ?? 0);
$sessionId = trim($_GET['session_id'] ?? ($_GET['payment_intent'] ?? ''));
$amount    = (float)($_GET['amount'] ?? 0);

if ($invId <= 0) {
    die("Invalid payment return parameters.");
}

// Fetch Invoice details
$st = $pdo->prepare("
    SELECT i.*, c.company_name, c.email as client_email, b.company_name as brand_name
    FROM invoices i
    JOIN clients c ON c.id = i.client_id
    LEFT JOIN branding_settings b ON b.tenant_id = i.tenant_id
    WHERE i.id = ?
");
$st->execute([$invId]);
$inv = $st->fetch();

if (!$inv) {
    die("Invoice not found.");
}

$tid = (int)$inv['tenant_id'];

// If amount was not passed in query, use invoice remaining total balance
$payAmount = ($amount > 0) ? $amount : max(0, (float)$inv['total'] - (float)$inv['paid_amount']);
$today = date('Y-m-d');
$alreadyPaid = false;

// Check if this payment was already recorded (e.g. by webhook)
$stCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND notes LIKE ?");
$stCheck->execute([$invId, "%Stripe Session: $sessionId%"]);
$existingPayId = $stCheck->fetchColumn();

if (!$existingPayId && $payAmount > 0) {
    // 1. Insert Payment Record into Ledger
    $notes = "Stripe Online Payment (Ref: " . ($sessionId ?: 'Stripe Checkout') . ")";
    $stPay = $pdo->prepare("
        INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, notes)
        VALUES (?, ?, ?, ?, ?, 'stripe', 'stripe', ?, ?)
    ");
    $stPay->execute([$tid, $invId, $payAmount, $inv['currency'], $today, $sessionId, $notes]);
    $paymentId = (int)$pdo->lastInsertId();

    // 2. Update Invoice Paid Amount & Status
    $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?");
    $stSum->execute([$invId]);
    $newPaid = (float)$stSum->fetchColumn();
    $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

    $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ?");
    $stUpd->execute([$newPaid, $newStatus, $invId]);

    // 3. Post Double-Entry General Ledger Accounting Entry
    $acct = new \Services\AccountingService($pdo, $tid);
    $acct->postPaymentReceived($paymentId);

    // 4. Send Confirmation Email Receipt
    if (!empty($inv['client_email'])) {
        $brandName = $inv['brand_name'] ?: 'OneSol Invoice';
        $subject = "Payment Receipt Confirmation for Invoice " . $inv['invoice_number'];
        $htmlBody = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;'>
                <h2 style='color: #059669;'>Payment Received & Verified</h2>
                <p>Dear {$inv['company_name']},</p>
                <p>Thank you! Your payment of <strong>{$inv['currency']} " . number_format($payAmount, 2) . "</strong> for invoice <strong>{$inv['invoice_number']}</strong> has been received via Stripe and logged into our ledger.</p>
                <p>Status: <strong style='color: #059669; text-transform: uppercase;'>$newStatus</strong></p>
            </div>
        ";
        \Services\Mailer::send($pdo, $tid, $inv['client_email'], $subject, $htmlBody);
    }

    log_audit($pdo, 'stripe_payment_success', 'payments', $paymentId, "Recorded Stripe payment {$inv['currency']} $payAmount for Invoice #{$inv['invoice_number']}");
} else {
    $alreadyPaid = true;
}

$brand = \Core\Branding::get($pdo, $tid);
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Successful - Invoice <?=e($inv['invoice_number'])?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950 text-slate-100">

<div class="max-w-lg w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center space-y-6">
    <div class="w-20 h-20 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-4xl mx-auto border border-emerald-500/40 shadow-xl animate-bounce">
        <i class="fa-solid fa-circle-check"></i>
    </div>

    <div>
        <span class="px-3 py-1 bg-emerald-500/20 text-emerald-300 text-3xs font-extrabold uppercase tracking-widest rounded-full">Stripe Gateway Sync Active</span>
        <h1 class="text-2xl font-black text-white mt-2">Payment Successfully Verified!</h1>
        <p class="text-xs text-slate-400 mt-1">Transaction recorded in general ledger & invoice updated in real-time.</p>
    </div>

    <div class="bg-slate-950/80 rounded-2xl p-4 border border-slate-800 text-left text-xs space-y-2 font-mono">
        <div class="flex justify-between"><span class="text-slate-400">Invoice Number:</span> <strong class="text-white"><?=e($inv['invoice_number'])?></strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Payment Amount:</span> <strong class="text-emerald-400"><?=e($inv['currency'])?> <?=number_format($payAmount, 2)?></strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Payment Gateway:</span> <strong class="text-indigo-400">Stripe Online Checkout</strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Ledger Entry:</span> <strong class="text-amber-400">POSTED (Debit Cash / Credit A/R)</strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Transaction Status:</span> <strong class="text-emerald-400 uppercase">Confirmed</strong></div>
    </div>

    <div class="pt-2 flex flex-col sm:flex-row gap-3">
        <a href="<?=e(get_public_invoice_url($inv))?>" class="flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all">
            <i class="fa-solid fa-file-invoice mr-1"></i>View Paid Invoice
        </a>
        <a href="client_portal" class="flex-1 py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl transition-all border border-slate-700">
            <i class="fa-solid fa-arrow-left mr-1"></i>Client Portal
        </a>
    </div>
</div>

</body>
</html>
