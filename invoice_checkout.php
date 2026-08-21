<?php
// invoice_checkout.php - Secure Online Invoice Payment Gateway Dispatcher
require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];

$invId = (int)($_REQUEST['invoice_id'] ?? 0);
$token = trim($_REQUEST['token'] ?? '');
$gateway = strtolower(trim($_REQUEST['gateway'] ?? 'stripe'));

if ($invId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid invoice ID']));
}

$stInv = $pdo->prepare("
    SELECT i.*, c.company_name, c.contact_name, c.email, c.phone, c.address, c.tax_number 
    FROM invoices i 
    JOIN clients c ON c.id = i.client_id 
    WHERE i.id = ?
");
$stInv->execute([$invId]);
$inv = $stInv->fetch();

if (!$inv) {
    http_response_code(404);
    exit(json_encode(['error' => 'Invoice record not found']));
}

$tid = (int)$inv['tenant_id'];

// Token & Session Security Guard
$expectedToken = get_invoice_token($inv);
$isAuthorizedUser = !empty($_SESSION['user_id']) && (int)($_SESSION['active_tenant_id'] ?? $_SESSION['tenant_id'] ?? 0) === $tid;
$isValidToken = !empty($token) && hash_equals($expectedToken, $token);

if (!$isAuthorizedUser && !$isValidToken) {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied. Invalid invoice token.']));
}

if (in_array($inv['status'], ['paid', 'void'], true)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invoice is already ' . strtoupper($inv['status']) . ' and cannot receive online payments.']));
}

$stItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id");
$stItems->execute([$invId]);
$items = $stItems->fetchAll();

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . "://" . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$result = ['error' => 'Invalid gateway specified.'];

if ($gateway === 'tabby') {
    $result = \Services\PaymentGatewayService::createInvoiceTabbyCheckout($pdo, $inv, $items, $baseUrl);
} elseif ($gateway === 'tamara') {
    $result = \Services\PaymentGatewayService::createInvoiceTamaraCheckout($pdo, $inv, $items, $baseUrl);
} elseif ($gateway === 'stripe') {
    $checkoutUrl = \Services\PaymentGatewayService::createStripeCheckoutSession($pdo, 'invoice_payment', $tid, $inv['email'] ?? '', $baseUrl);
    $result = ['redirect_url' => $checkoutUrl];
} elseif ($gateway === 'network') {
    $checkoutUrl = \Services\PaymentGatewayService::createNetworkCheckoutOrder($pdo, 'invoice_payment', $tid, (float)$inv['total'], $baseUrl);
    $result = ['redirect_url' => $checkoutUrl];
}

// Return JSON or Redirect
if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if (!empty($result['redirect_url'])) {
    header('Location: ' . $result['redirect_url']);
    exit;
}

flash('error', $result['error'] ?? 'Unable to initialize online checkout.');
redirect("public_invoice.php?id={$invId}&token={$token}");
