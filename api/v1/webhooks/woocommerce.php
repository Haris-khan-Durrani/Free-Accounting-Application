<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

// Resolve tenant_id server-side from unique integration route key if provided
$webhookKey = trim($_GET['key'] ?? ($_GET['integration_key'] ?? ''));
if (!empty($webhookKey)) {
    $tenantId = \Services\PaymentGatewayService::getTenantIdByWebhookKey($pdo, 'woocommerce_webhook_key', $webhookKey);
} else {
    $tenantId = (int)($_GET['tenant_id'] ?? 0);
}

if ($tenantId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Missing or invalid integration webhook key']);
    exit;
}

$payload = file_get_contents('php://input');

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'ignored', 'reason' => 'Empty payload']);
    exit;
}

// Cryptographic Signature Verification
$headers = getallheaders();
$wcSig = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? $headers['X-WC-Webhook-Signature'] ?? $headers['x-wc-webhook-signature'] ?? '';

$wcSecret = \Services\PaymentGatewayService::getSetting($pdo, 'woocommerce_webhook_secret', '', $tenantId, false);

if (empty($wcSecret)) {
    http_response_code(503);
    echo json_encode(['error' => 'WooCommerce webhook secret is not configured for this tenant']);
    exit;
}

if (empty($wcSig)) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing X-WC-Webhook-Signature header']);
    exit;
}

$calculatedSig = base64_encode(hash_hmac('sha256', $payload, $wcSecret, true));

if (!hash_equals($calculatedSig, $wcSig)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid WooCommerce webhook signature']);
    exit;
}

$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'ignored', 'reason' => 'Empty or invalid JSON payload']);
    exit;
}

// Extract WooCommerce Order Details
$orderId = $data['id'] ?? time();
$customerName = trim(($data['billing']['first_name'] ?? 'WooCommerce') . ' ' . ($data['billing']['last_name'] ?? 'Customer'));
$customerEmail = $data['billing']['email'] ?? 'customer@store.com';
$orderTotal = (float)($data['total'] ?? 0);
$currency = $data['currency'] ?? 'AED';

// Find or create client
$stC = $pdo->prepare("SELECT id FROM clients WHERE tenant_id = ? AND email = ?");
$stC->execute([$tenantId, $customerEmail]);
$clientId = (int)$stC->fetchColumn();

if (!$clientId) {
    $stNewC = $pdo->prepare("INSERT INTO clients (tenant_id, company_name, contact_name, email, currency) VALUES (?, ?, ?, ?, ?)");
    $stNewC->execute([$tenantId, $customerName, $customerName, $customerEmail, $currency]);
    $clientId = (int)$pdo->lastInsertId();
}

// Generate Invoice
$invNum = 'WOO-' . $orderId;
$stExist = $pdo->prepare("SELECT id FROM invoices WHERE tenant_id = ? AND invoice_number = ?");
$stExist->execute([$tenantId, $invNum]);

if ($stExist->fetchColumn()) {
    echo json_encode(['status' => 'exists', 'invoice_number' => $invNum]);
    exit;
}

$stInv = $pdo->prepare("INSERT INTO invoices (tenant_id, invoice_number, client_id, invoice_date, status, currency, subtotal, total) VALUES (?, ?, ?, ?, 'paid', ?, ?, ?)");
$stInv->execute([$tenantId, $invNum, $clientId, date('Y-m-d'), $currency, $orderTotal, $orderTotal]);
$invId = (int)$pdo->lastInsertId();

// Line Items
$stItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, qty, unit_price, amount) VALUES (?, ?, ?, ?, ?)");

if (!empty($data['line_items'])) {
    foreach ($data['line_items'] as $item) {
        $q = (float)($item['quantity'] ?? 1);
        $p = (float)($item['price'] ?? 0);
        $stItem->execute([$invId, $item['name'] ?? 'Product Item', $q, $p, $q * $p]);
    }
} else {
    $stItem->execute([$invId, "WooCommerce Order #$orderId", 1, $orderTotal, $orderTotal]);
}

// Post Accounting Entry
$acctService = new \Services\AccountingService($pdo, $tenantId);
$acctService->postInvoicePaid($invId);

echo json_encode(['status' => 'success', 'message' => 'WooCommerce Order Synced', 'invoice_number' => $invNum]);
