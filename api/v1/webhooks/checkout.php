<?php
// api/v1/webhooks/checkout.php - Server-to-Server Webhook Listener for Checkout.com
require __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];
$rawPayload = file_get_contents('php://input');
$event = json_decode($rawPayload, true);

if (empty($event) || empty($event['type'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Empty or invalid event structure']));
}

$eventType = $event['type'];
$data = $event['data'] ?? [];
$paymentId = $data['id'] ?? ($event['id'] ?? '');
$reference = $data['reference'] ?? '';
$amount = ((float)($data['amount'] ?? 0)) / 100;

if (empty($paymentId) || empty($reference)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing payment ID or reference']));
}

preg_match('/inv_(\d+)/', $reference, $matches);
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

if ($eventType === 'payment_captured' || $eventType === 'payment_approved') {
    $today = date('Y-m-d');
    $pdo->beginTransaction();

    $stCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
    $stCheck->execute([$invId, $tid, $paymentId, $paymentId]);
    $existingPayId = (int)$stCheck->fetchColumn();

    if (!$existingPayId) {
        $notes = "Checkout.com Webhook Payment (Event: $eventType, ID: $paymentId)";
        $stPay = $pdo->prepare("
            INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
            VALUES (?, ?, ?, ?, ?, 'checkout', 'checkout', ?, ?, ?)
        ");
        $stPay->execute([$tid, $invId, $amount, $inv['currency'], $today, $paymentId, $paymentId, $notes]);
        $payId = (int)$pdo->lastInsertId();

        $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND tenant_id = ?");
        $stSum->execute([$invId, $tid]);
        $newPaid = (float)$stSum->fetchColumn();

        $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

        $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ? AND tenant_id = ?");
        $stUpd->execute([$newPaid, $newStatus, $invId, $tid]);

        try {
            $acctService = new \Services\AccountingService($pdo, $tid);
            $acctService->postPaymentReceived($payId);
        } catch (Throwable $t) {}
    }

    $pdo->commit();
}

http_response_code(200);
echo json_encode(['received' => true]);
