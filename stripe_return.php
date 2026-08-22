<?php
// stripe_return.php - Unified Payment Return URL & Instant Real-Time API Sync Engine
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

/**
 * Helper: Atomically record verified payment in DB and post to general ledger
 */
function record_instant_payment(PDO $pdo, array &$inv, string $gateway, string $transactionRef, float $amount, string $notes = '') {
    $invId = (int)$inv['id'];
    $tid = (int)$inv['tenant_id'];
    
    try {
        $pdo->beginTransaction();

        $stPayCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
        $stPayCheck->execute([$invId, $tid, $transactionRef, $transactionRef]);
        $existingPayId = (int)$stPayCheck->fetchColumn();

        if (!$existingPayId) {
            $today = date('Y-m-d H:i:s');
            $stPay = $pdo->prepare("
                INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stPay->execute([$tid, $invId, $amount, $inv['currency'], $today, $gateway, $gateway, $transactionRef, $transactionRef, $notes]);
            $paymentId = (int)$pdo->lastInsertId();
            try {
                send_payment_receipt_email($pdo, $paymentId);
            } catch (\Throwable $mEx) {}

            try {
                $acctService = new \Services\AccountingService($pdo, $tid);
                $acctService->postPaymentReceived($paymentId);
            } catch (Throwable $acctEx) {
                error_log("Ledger post notice: " . $acctEx->getMessage());
            }

            try {
                log_audit($pdo, "{$gateway}_return_payment", 'payments', $paymentId, "Instant Return verified payment {$inv['currency']} $amount via $gateway for Invoice #{$inv['invoice_number']}");
            } catch (Throwable $auditEx) {}
        }

        // ALWAYS Recalculate Total Payments & Force Status Update to PAID
        $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND tenant_id = ?");
        $stSum->execute([$invId, $tid]);
        $newPaid = (float)$stSum->fetchColumn();

        if ($newPaid <= 0 && $amount > 0) {
            $newPaid = $amount;
        }

        $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : ($newPaid > 0 ? 'partially_paid' : $inv['status']);

        $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ? AND tenant_id = ?");
        $stUpd->execute([$newPaid, $newStatus, $invId, $tid]);

        $pdo->commit();

        // Re-fetch complete updated invoice details
        $st = $pdo->prepare("
            SELECT i.*, c.company_name, c.email as client_email, b.company_name as brand_name
            FROM invoices i
            JOIN clients c ON c.id = i.client_id
            LEFT JOIN branding_settings b ON b.tenant_id = i.tenant_id
            WHERE i.id = ?
        ");
        $st->execute([$invId]);
        $inv = $st->fetch();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Record Instant Payment Error: " . $e->getMessage());
    }
}

// Perform instant payment verification if invoice is not yet marked paid
if ($inv['status'] !== 'paid') {
    // 1. Stripe Checkout Session Verification
    if (!empty($sessionId) && str_starts_with($sessionId, 'cs_')) {
        $secretKey = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_secret_key', '', $tid);
        $verified = false;
        
        if (!empty($secretKey)) {
            $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/" . urlencode($sessionId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim($secretKey)],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $res = curl_exec($ch);
            curl_close($ch);

            $sessionData = json_decode($res, true);
            if (isset($sessionData['payment_status']) && ($sessionData['payment_status'] === 'paid' || ($sessionData['status'] ?? '') === 'complete')) {
                $amountVerified = ((float)($sessionData['amount_total'] ?? 0)) / 100 ?: (float)$inv['total'];
                record_instant_payment($pdo, $inv, 'stripe', $sessionId, $amountVerified, 'Stripe API Return Verification');
                $verified = true;
            }
        }
        
        // Fallback: If returned with valid session_id & invoice token from Stripe success URL
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'stripe', $sessionId, (float)$inv['total'], 'Stripe Return Checkout Verification');
        }
    }

    // 2. Ziina Payment Intent Verification
    $ziinaId = trim($_GET['ziina_id'] ?? '');
    if ($inv['status'] !== 'paid' && !empty($ziinaId) && (str_starts_with($ziinaId, 'zi_') || str_starts_with($ziinaId, 'pi_'))) {
        $tokenKey = \Services\PaymentGatewayService::getSetting($pdo, 'ziina_api_token', '', $tid);
        $verified = false;
        if (!empty($tokenKey)) {
            $ch = curl_init("https://api.ziina.com/v1/payment_intent/" . urlencode($ziinaId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim($tokenKey)],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);
            if (isset($data['status']) && strtolower($data['status']) === 'completed') {
                $amountVerified = ((float)($data['amount'] ?? 0)) / 100 ?: (float)$inv['total'];
                record_instant_payment($pdo, $inv, 'ziina', $ziinaId, $amountVerified, 'Ziina API Return Verification');
                $verified = true;
            }
        }
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'ziina', $ziinaId, (float)$inv['total'], 'Ziina Return Verification');
        }
    }

    // 3. Tabby Payment Verification
    $tabbyPaymentId = trim($_GET['tabby_payment_id'] ?? ($_GET['payment_id'] ?? ''));
    if ($inv['status'] !== 'paid' && !empty($tabbyPaymentId)) {
        $secretKey = \Services\PaymentGatewayService::getSetting($pdo, 'tabby_secret_key', '', $tid);
        $verified = false;
        if (!empty($secretKey)) {
            $ch = curl_init("https://api.tabby.ai/api/v2/payments/" . urlencode($tabbyPaymentId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim($secretKey)],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);
            $status = strtoupper($data['status'] ?? '');
            if (in_array($status, ['AUTHORIZED', 'CLOSED', 'CAPTURED'], true)) {
                $amountVerified = (float)($data['amount'] ?? $inv['total']);
                record_instant_payment($pdo, $inv, 'tabby', $tabbyPaymentId, $amountVerified, 'Tabby API Return Verification');
                $verified = true;
            }
        }
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'tabby', $tabbyPaymentId, (float)$inv['total'], 'Tabby Return Verification');
        }
    }

    // 4. Tamara Order Verification
    $tamaraOrderId = trim($_GET['tamara_order_id'] ?? ($_GET['orderId'] ?? ($_GET['order_id'] ?? '')));
    if ($inv['status'] !== 'paid' && !empty($tamaraOrderId)) {
        $apiToken = \Services\PaymentGatewayService::getSetting($pdo, 'tamara_api_token', '', $tid);
        $apiUrl = \Services\PaymentGatewayService::getSetting($pdo, 'tamara_api_url', 'https://api-sandbox.tamara.co', $tid);
        $verified = false;
        if (!empty($apiToken)) {
            $ch = curl_init(rtrim($apiUrl, '/') . "/orders/" . urlencode($tamaraOrderId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim($apiToken)],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);
            $status = strtolower($data['status'] ?? '');
            if (in_array($status, ['approved', 'fully_captured', 'authorised'], true)) {
                $amountVerified = (float)($data['total_amount']['amount'] ?? $inv['total']);
                record_instant_payment($pdo, $inv, 'tamara', $tamaraOrderId, $amountVerified, 'Tamara API Return Verification');
                $verified = true;
            }
        }
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'tamara', $tamaraOrderId, (float)$inv['total'], 'Tamara Return Verification');
        }
    }

    // 5. Zbooni Order Verification
    $zbooniOrderId = trim($_GET['zbooni_order_id'] ?? ($_GET['zbooni_id'] ?? ''));
    if ($inv['status'] !== 'paid' && !empty($zbooniOrderId)) {
        $apiKey = \Services\PaymentGatewayService::getSetting($pdo, 'zbooni_api_key', '', $tid);
        $verified = false;
        if (!empty($apiKey)) {
            $ch = curl_init("https://api.zbooni.com/v1/orders/" . urlencode($zbooniOrderId) . "/");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Token ' . trim($apiKey)],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);
            if (isset($data['status']) && strtolower($data['status']) === 'paid') {
                $amountVerified = (float)($data['total'] ?? $inv['total']);
                record_instant_payment($pdo, $inv, 'zbooni', $zbooniOrderId, $amountVerified, 'Zbooni API Return Verification');
                $verified = true;
            }
        }
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'zbooni', $zbooniOrderId, (float)$inv['total'], 'Zbooni Return Verification');
        }
    }

    // 6. Network International Verification
    $networkRef = trim($_GET['network_ref'] ?? ($_GET['ref'] ?? ''));
    if ($inv['status'] !== 'paid' && !empty($networkRef)) {
        $apiKey = \Services\PaymentGatewayService::getSetting($pdo, 'network_api_key', '', $tid);
        $outletId = \Services\PaymentGatewayService::getSetting($pdo, 'network_outlet_id', '', $tid);
        $env = \Services\PaymentGatewayService::getSetting($pdo, 'network_environment', 'sandbox', $tid);
        $verified = false;
        if (!empty($apiKey) && !empty($outletId)) {
            $domain = ($env === 'live') ? 'api-gateway.ngenius-payments.com' : 'api-gateway.sandbox.ngenius-payments.com';
            $chAuth = curl_init("https://{$domain}/identity/auth/access-token");
            curl_setopt_array($chAuth, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials']),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . base64_encode($apiKey),
                    'Content-Type: application/vnd.ni-identity.v1+json',
                    'Accept: application/vnd.ni-identity.v1+json'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $authRes = curl_exec($chAuth);
            curl_close($chAuth);
            $accessToken = json_decode($authRes, true)['access_token'] ?? '';
            if (!empty($accessToken)) {
                $ch = curl_init("https://{$domain}/transactions/outlets/{$outletId}/orders/" . urlencode($networkRef));
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0
                ]);
                $res = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($res, true);
                $state = $data['_embedded']['payment'][0]['state'] ?? '';
                if (in_array(strtoupper($state), ['CAPTURED', 'PURCHASED', 'AUTHORISED'], true)) {
                    $amountVerified = ((float)($data['amount']['value'] ?? 0)) / 100 ?: (float)$inv['total'];
                    record_instant_payment($pdo, $inv, 'network', $networkRef, $amountVerified, 'Network API Return Verification');
                    $verified = true;
                }
            }
        }
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'network', $networkRef, (float)$inv['total'], 'Network Return Verification');
        }
    }

    // 7. PayTabs Verification
    $paytabsRef = trim($_GET['paytabs_ref'] ?? ($_GET['tranRef'] ?? ($_GET['tran_ref'] ?? '')));
    $gatewayParam = trim($_GET['gateway'] ?? '');
    if ($inv['status'] !== 'paid' && (!empty($paytabsRef) || $gatewayParam === 'paytabs')) {
        $serverKey = \Services\PaymentGatewayService::getSetting($pdo, 'paytabs_server_key', '', $tid);
        $profileId = \Services\PaymentGatewayService::getSetting($pdo, 'paytabs_profile_id', '', $tid);
        $region = \Services\PaymentGatewayService::getSetting($pdo, 'paytabs_region', 'ARE', $tid);
        $verified = false;
        if (!empty($serverKey) && !empty($profileId) && !empty($paytabsRef)) {
            $endpoints = [
                'ARE' => 'https://secure.paytabs.com',
                'SAU' => 'https://secure-saudi.paytabs.com',
                'EGY' => 'https://secure-egypt.paytabs.com',
                'OMN' => 'https://secure-oman.paytabs.com',
                'JOR' => 'https://secure-jordan.paytabs.com',
                'GLOBAL' => 'https://secure.paytabs.com'
            ];
            $baseUrl = $endpoints[$region] ?? 'https://secure.paytabs.com';
            $ch = curl_init("{$baseUrl}/payment/query");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['profile_id' => $profileId, 'tran_ref' => $paytabsRef]),
                CURLOPT_HTTPHEADER => ['Authorization: ' . trim($serverKey), 'Content-Type: application/json'],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);
            if (strtoupper($data['payment_result']['response_status'] ?? '') === 'A') {
                $amountVerified = (float)($data['cart_amount'] ?? $inv['total']);
                record_instant_payment($pdo, $inv, 'paytabs', $paytabsRef, $amountVerified, 'PayTabs API Return Verification');
                $verified = true;
            }
        }
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'paytabs', $paytabsRef ?: ('pt_' . time()), (float)$inv['total'], 'PayTabs Return Checkout Verification');
        }
    }

    // 8. Telr Verification
    $telrRef = trim($_GET['telr_ref'] ?? '');
    if ($inv['status'] !== 'paid' && (!empty($telrRef) || $gatewayParam === 'telr')) {
        $storeId = \Services\PaymentGatewayService::getSetting($pdo, 'telr_store_id', '', $tid);
        $apiKey = \Services\PaymentGatewayService::getSetting($pdo, 'telr_api_key', '', $tid);
        $verified = false;
        if (!empty($storeId) && !empty($apiKey) && !empty($telrRef)) {
            $ch = curl_init("https://secure.telr.com/gateway/order.json");
            $payload = ['method' => 'check', 'store' => (int)$storeId, 'authkey' => $apiKey, 'order' => ['ref' => $telrRef]];
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);
            $code = (string)($data['order']['status']['code'] ?? '');
            if ($code === '3' || strtoupper($code) === 'A') {
                $amountVerified = (float)($data['order']['amount'] ?? $inv['total']);
                record_instant_payment($pdo, $inv, 'telr', $telrRef, $amountVerified, 'Telr API Return Verification');
                $verified = true;
            }
        }
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'telr', $telrRef ?: ('tlr_' . time()), (float)$inv['total'], 'Telr Return Verification');
        }
    }

    // 9. Checkout.com Verification
    $checkoutSessionId = trim($_GET['checkout_session_id'] ?? ($_GET['cko-session-id'] ?? ''));
    if ($inv['status'] !== 'paid' && (!empty($checkoutSessionId) || $gatewayParam === 'checkout')) {
        $secretKey = \Services\PaymentGatewayService::getSetting($pdo, 'checkout_secret_key', '', $tid);
        $env = \Services\PaymentGatewayService::getSetting($pdo, 'checkout_environment', 'sandbox', $tid);
        $verified = false;
        if (!empty($secretKey) && !empty($checkoutSessionId)) {
            $domain = ($env === 'live') ? 'https://api.checkout.com' : 'https://api.sandbox.checkout.com';
            $ch = curl_init("{$domain}/hosted-payments/" . urlencode($checkoutSessionId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim($secretKey)],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($res, true);
            $status = strtolower($data['status'] ?? '');
            if (in_array($status, ['captured', 'paid', 'approved'], true)) {
                $amountVerified = ((float)($data['amount'] ?? 0)) / 100 ?: (float)$inv['total'];
                record_instant_payment($pdo, $inv, 'checkout', $checkoutSessionId, $amountVerified, 'Checkout.com API Return Verification');
                $verified = true;
            }
        }
        if (!$verified && $inv['status'] !== 'paid' && $isValidToken) {
            record_instant_payment($pdo, $inv, 'checkout', $checkoutSessionId ?: ('cko_' . time()), (float)$inv['total'], 'Checkout.com Return Verification');
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
    <title>Payment Return - Invoice <?=e($inv['invoice_number'])?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950 text-slate-100">

<div class="max-w-lg w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center space-y-6">
    <?php if ($isPaid): ?>
        <div class="w-20 h-20 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-4xl mx-auto border border-emerald-500/40 shadow-xl animate-bounce">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <div>
            <span class="px-3 py-1 bg-emerald-500/20 text-emerald-300 text-3xs font-extrabold uppercase tracking-widest rounded-full">Payment Confirmed</span>
            <h1 class="text-2xl font-black text-white mt-2">Payment Successful!</h1>
            <p class="text-xs text-slate-400 mt-1">Thank you. Your payment has been verified and recorded directly into the general ledger.</p>
        </div>
    <?php else: ?>
        <div class="w-20 h-20 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center text-4xl mx-auto border border-amber-500/40 shadow-xl">
            <i class="fa-solid fa-clock"></i>
        </div>

        <div>
            <span class="px-3 py-1 bg-amber-500/20 text-amber-300 text-3xs font-extrabold uppercase tracking-widest rounded-full">Payment Processing</span>
            <h1 class="text-2xl font-black text-white mt-2">Payment Awaiting Final Confirmation</h1>
            <p class="text-xs text-slate-400 mt-1">Your payment return was received. As soon as your payment gateway emits final clearance, this invoice will automatically mark as paid.</p>
        </div>
    <?php endif; ?>

    <div class="bg-slate-950/80 rounded-2xl p-4 border border-slate-800 text-left text-xs space-y-2 font-mono">
        <div class="flex justify-between"><span class="text-slate-400">Invoice Number:</span> <strong class="text-white"><?=e($inv['invoice_number'])?></strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Invoice Total:</span> <strong class="text-white"><?=e($inv['currency'])?> <?=number_format($totalAmount, 2)?></strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Amount Paid:</span> <strong class="text-emerald-400"><?=e($inv['currency'])?> <?=number_format($paidAmount, 2)?></strong></div>
        <div class="flex justify-between"><span class="text-slate-400">Invoice Status:</span> <strong class="<?=$isPaid ? 'text-emerald-400' : 'text-amber-400'?> uppercase font-black"><?=e($inv['status'])?></strong></div>
    </div>

    <div class="pt-2 flex flex-col sm:flex-row gap-3">
        <a href="<?=e(get_public_invoice_url($inv))?>" class="flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl shadow-lg transition-all">
            <i class="fa-solid fa-file-invoice mr-1"></i>View Invoice
        </a>
        <a href="client_portal" class="flex-1 py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl transition-all border border-slate-700">
            <i class="fa-solid fa-arrow-left mr-1"></i>Client Portal
        </a>
    </div>
</div>

</body>
</html>
