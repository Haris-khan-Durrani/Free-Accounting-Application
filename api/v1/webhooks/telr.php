<?php
// api/v1/webhooks/telr.php - Server-to-Server Webhook Listener for Telr Payments
require __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];
$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true) ?: $_POST;

if (empty($data)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Empty payload']));
}

$ref = $data['order']['ref'] ?? ($data['tran_ref'] ?? '');
$cartId = $data['order']['cartid'] ?? ($data['cartid'] ?? '');
$status = $data['order']['status']['code'] ?? ($data['auth']['status'] ?? '');
$amount = (float)($data['order']['amount'] ?? 0);

if (empty($ref) || empty($cartId)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing Telr order reference or cart ID']));
}

preg_match('/inv_(\d+)/', $cartId, $matches);
$invId = (int)($matches[1] ?? 0);

if ($invId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid invoice reference']));
}

$stInv = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stInv->execute([$invId]);
$inv = $stInv->fetch();

if (!$inv) {
    http_response_code(404);
    exit(json_encode(['error' => 'Invoice not found']));
}

$tid = (int)$inv['tenant_id'];

// Check status (3 = Paid/Authorised in Telr API)
if ((string)$status === '3' || strtoupper((string)$status) === 'A') {
    $today = date('Y-m-d H:i:s');
    $pdo->beginTransaction();

    $stCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
    $stCheck->execute([$invId, $tid, $ref, $ref]);
    $existingPayId = (int)$stCheck->fetchColumn();

    if (!$existingPayId) {
        $notes = "Telr Webhook Payment (Ref: $ref)";
        $stPay = $pdo->prepare("
            INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
            VALUES (?, ?, ?, ?, ?, 'telr', 'telr', ?, ?, ?)
        ");
        $stPay->execute([$tid, $invId, $amount, $inv['currency'], $today, $ref, $ref, $notes]);
        $paymentId = (int)$pdo->lastInsertId();

        $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND tenant_id = ?");
        $stSum->execute([$invId, $tid]);
        $newPaid = (float)$stSum->fetchColumn();

        $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

        $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ? AND tenant_id = ?");
        $stUpd->execute([$newPaid, $newStatus, $invId, $tid]);

        try {
            $acctService = new \Services\AccountingService($pdo, $tid);
            $acctService->postPaymentReceived($paymentId);
        } catch (Throwable $t) {}
    }

    $pdo->commit();
}

http_response_code(200);
echo json_encode(['received' => true]);
