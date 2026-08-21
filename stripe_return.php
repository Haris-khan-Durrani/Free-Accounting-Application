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

// Token & Session Authorization Guard: Require valid public invoice token OR authenticated tenant user
$token = trim($_GET['token'] ?? '');
$expectedToken = get_invoice_token($inv);
$isAuthorizedUser = !empty($_SESSION['user_id']) && (int)($_SESSION['active_tenant_id'] ?? $_SESSION['tenant_id'] ?? 0) === $tid;
$isValidToken = !empty($token) && hash_equals($expectedToken, $token);

if (!$isAuthorizedUser && !$isValidToken) {
    http_response_code(403);
    exit('Access denied. Invalid or missing invoice access token.');
}

// Instant Direct Verification via Stripe API
if ($inv['status'] !== 'paid' && !empty($sessionId) && str_starts_with($sessionId, 'cs_')) {
    $secretKey = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_secret_key', '', $tid);
    if (!empty($secretKey)) {
        $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/" . urlencode($sessionId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secretKey],
            CURLOPT_TIMEOUT => 10
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $sessionData = json_decode($res, true);
        if (isset($sessionData['payment_status']) && ($sessionData['payment_status'] === 'paid' || ($sessionData['status'] ?? '') === 'complete')) {
            $amount = ((float)($sessionData['amount_total'] ?? 0)) / 100;
            if ($amount <= 0) {
                $amount = (float)$inv['total'];
            }

            try {
                $pdo->beginTransaction();

                // Check for existing payment
                $stPayCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
                $stPayCheck->execute([$invId, $tid, $sessionId, $sessionId]);
                $existingPayId = (int)$stPayCheck->fetchColumn();

                if (!$existingPayId) {
                    $today = date('Y-m-d');
                    $notes = "Stripe Instant Return Verification (Session: $sessionId)";
                    $stPay = $pdo->prepare("
                        INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
                        VALUES (?, ?, ?, ?, ?, 'stripe', 'stripe', ?, ?, ?)
                    ");
                    $stPay->execute([$tid, $invId, $amount, $inv['currency'], $today, $sessionId, $sessionId, $notes]);
                    $paymentId = (int)$pdo->lastInsertId();

                    $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND tenant_id = ?");
                    $stSum->execute([$invId, $tid]);
                    $newPaid = (float)$stSum->fetchColumn();

                    $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

                    $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ? AND tenant_id = ?");
                    $stUpd->execute([$newPaid, $newStatus, $invId, $tid]);

                    $acctService = new \Services\AccountingService($pdo, $tid);
                    $acctService->postPaymentReceived($paymentId);

                    log_audit($pdo, 'stripe_return_payment', 'payments', $paymentId, "Stripe Return verified payment {$inv['currency']} $amount for Invoice #{$inv['invoice_number']}");
                }

                $pdo->commit();

                // Refresh invoice details
                $st->execute([$invId]);
                $inv = $st->fetch();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
    }
}

// Render Status
$isPaid = ($inv['status'] === 'paid');
$isPartiallyPaid = ($inv['status'] === 'partially_paid');
$paidAmount = (float)$inv['paid_amount'];
$totalAmount = (float)$inv['total'];

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
        <span class="px-3 py-1 bg-emerald-500/20 text-emerald-300 text-3xs font-extrabold uppercase tracking-widest rounded-full">Stripe Payment Return</span>
        <h1 class="text-2xl font-black text-white mt-2">Payment Return Received</h1>
        <p class="text-xs text-slate-400 mt-1">Payment confirmations are processed automatically via cryptographically verified Stripe webhooks.</p>
    </div>

    <div class="bg-slate-950/80 rounded-2xl p-4 border border-slate-800 text-left text-xs space-y-2 font-mono">
        <div class="flex justify-between"><span class="text-slate-400">Invoice Number:</span> <strong class="text-white"><?=e($inv['invoice_number'])?></strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Invoice Total:</span> <strong class="text-white"><?=e($inv['currency'])?> <?=number_format($totalAmount, 2)?></strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Amount Paid:</span> <strong class="text-emerald-400"><?=e($inv['currency'])?> <?=number_format($paidAmount, 2)?></strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Invoice Status:</span> <strong class="text-amber-400 uppercase"><?=e($inv['status'])?></strong></div>
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
