<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];
$tenantId = (int)($_GET['tenant_id'] ?? 1);

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!$data) {
    echo json_encode(['status' => 'ignored', 'reason' => 'Empty payload']);
    exit;
}

$orderName = $data['name'] ?? ('SHOP-' . ($data['id'] ?? time()));
$customerName = trim(($data['customer']['first_name'] ?? 'Shopify') . ' ' . ($data['customer']['last_name'] ?? 'Customer'));
$customerEmail = $data['customer']['email'] ?? ($data['email'] ?? 'shopify@store.com');
$totalPrice = (float)($data['total_price'] ?? 0);
$currency = $data['currency'] ?? 'USD';

// Find or create client
$stC = $pdo->prepare("SELECT id FROM clients WHERE tenant_id = ? AND email = ?");
$stC->execute([$tenantId, $customerEmail]);
$clientId = (int)$stC->fetchColumn();

if (!$clientId) {
    $stNewC = $pdo->prepare("INSERT INTO clients (tenant_id, company_name, contact_name, email, currency) VALUES (?, ?, ?, ?, ?)");
    $stNewC->execute([$tenantId, $customerName, $customerName, $customerEmail, $currency]);
    $clientId = (int)$pdo->lastInsertId();
}

$invNum = 'SHOPIFY-' . preg_replace('/[^a-zA-Z0-9\-]/', '', $orderName);
$stExist = $pdo->prepare("SELECT id FROM invoices WHERE tenant_id = ? AND invoice_number = ?");
$stExist->execute([$tenantId, $invNum]);

if ($stExist->fetchColumn()) {
    echo json_encode(['status' => 'exists', 'invoice_number' => $invNum]);
    exit;
}

$stInv = $pdo->prepare("INSERT INTO invoices (tenant_id, invoice_number, client_id, invoice_date, status, currency, subtotal, total) VALUES (?, ?, ?, ?, 'paid', ?, ?, ?)");
$stInv->execute([$tenantId, $invNum, $clientId, date('Y-m-d'), $currency, $totalPrice, $totalPrice]);
$invId = (int)$pdo->lastInsertId();

$stItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, qty, unit_price, amount) VALUES (?, ?, ?, ?, ?)");

if (!empty($data['line_items'])) {
    foreach ($data['line_items'] as $item) {
        $q = (float)($item['quantity'] ?? 1);
        $p = (float)($item['price'] ?? 0);
        $stItem->execute([$invId, $item['title'] ?? 'Shopify Product', $q, $p, $q * $p]);
    }
} else {
    $stItem->execute([$invId, "Shopify Order $orderName", 1, $totalPrice, $totalPrice]);
}

$acctService = new \Services\AccountingService($pdo, $tenantId);
$acctService->postInvoicePaid($invId);

echo json_encode(['status' => 'success', 'message' => 'Shopify Order Synced', 'invoice_number' => $invNum]);
