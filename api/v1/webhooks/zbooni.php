<?php
// api/v1/webhooks/zbooni.php - Zbooni Payment Gateway Webhook Handler
require __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

if (!$event || (empty($event['id']) && empty($event['order_id']) && empty($event['reference_id']))) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid Zbooni webhook payload structure']));
}

$externalEventId = (string)($event['id'] ?? $event['order_id'] ?? $event['reference_id'] ?? uniqid('zb_'));
$eventType = (string)($event['status'] ?? $event['event'] ?? 'order.paid');
$notes = (string)($event['notes'] ?? $event['description'] ?? '');

$invId = 0;
if (preg_match('/Invoice ID:\s*(\d+)/i', $notes, $matches)) {
    $invId = (int)$matches[1];
} elseif (!empty($event['order_number'])) {
    $stInvRef = $pdo->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
    $stInvRef->execute([(string)$event['order_number']]);
    $invId = (int)$stInvRef->fetchColumn();
}

if ($invId <= 0 && !empty($event['invoice_id'])) {
    $invId = (int)$event['invoice_id'];
}

if ($invId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Unable to determine invoice ID from Zbooni webhook payload']));
}

$stInv = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stInv->execute([$invId]);
$inv = $stInv->fetch();

if (!$inv) {
    http_response_code(404);
    exit(json_encode(['error' => 'Invoice not found']));
}

$tid = (int)$inv['tenant_id'];

// Idempotency check
$stCheck = $pdo->prepare("SELECT id, status FROM webhook_events WHERE provider = 'zbooni' AND external_event_id = ?");
$stCheck->execute([$externalEventId]);
$existingEvent = $stCheck->fetch();

if ($existingEvent && $existingEvent['status'] === 'processed') {
    http_response_code(200);
    exit(json_encode(['received' => true, 'idempotent' => true]));
}

$payloadHash = hash('sha256', $payload);

if (!$existingEvent) {
    $stIns = $pdo->prepare("
        INSERT INTO webhook_events (provider, external_event_id, event_type, tenant_id, payload_hash, status)
        VALUES ('zbooni', ?, ?, ?, ?, 'pending')
    ");
    $stIns->execute([$externalEventId, 'zbooni.' . $eventType, $tid, $payloadHash]);
    $eventId = (int)$pdo->lastInsertId();
} else {
    $eventId = (int)$existingEvent['id'];
}

$statusStr = strtolower($eventType);
if (in_array($statusStr, ['order.paid', 'order.completed', 'paid', 'completed', 'success', 'captured'], true)) {
    $payAmount = (float)($event['amount'] ?? $inv['total']);
    $currency = (string)($event['currency'] ?? $inv['currency'] ?? 'AED');

    try {
        $pdo->beginTransaction();

        $today = date('Y-m-d H:i:s');
        $notesStr = "Zbooni Online Payment (Ref: $externalEventId)";

        $stPayCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
        $stPayCheck->execute([$invId, $tid, $externalEventId, $externalEventId]);
        $existingPayId = (int)$stPayCheck->fetchColumn();

        if (!$existingPayId) {
            $stPay = $pdo->prepare("
                INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
                VALUES (?, ?, ?, ?, ?, 'zbooni', 'zbooni', ?, ?, ?)
            ");
            $stPay->execute([$tid, $invId, $payAmount, $currency, $today, $externalEventId, $externalEventId, $notesStr]);
            $paymentId = (int)$pdo->lastInsertId();

            $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND tenant_id = ?");
            $stSum->execute([$invId, $tid]);
            $newPaid = (float)$stSum->fetchColumn();

            $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

            $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ? AND tenant_id = ?");
            $stUpd->execute([$newPaid, $newStatus, $invId, $tid]);

            $acctService = new \Services\AccountingService($pdo, $tid);
            $acctService->postPaymentReceived($paymentId);

            log_audit($pdo, 'zbooni_webhook_payment', 'payments', $paymentId, "Zbooni synced payment {$currency} {$payAmount} for Invoice #{$inv['invoice_number']}");
        }

        $stEvUpd = $pdo->prepare("UPDATE webhook_events SET status = 'processed', processed_at = NOW() WHERE id = ?");
        $stEvUpd->execute([$eventId]);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $stEvErr = $pdo->prepare("UPDATE webhook_events SET status = 'failed', error = ? WHERE id = ?");
        $stEvErr->execute([$e->getMessage(), $eventId]);

        http_response_code(500);
        exit(json_encode(['error' => 'Webhook processing failure: ' . $e->getMessage()]));
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
exit;
