<?php
// api/v1/webhooks/network.php - Network International (NGenius) Webhook Handler
require __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

if (!$event || (empty($event['orderId']) && empty($event['reference']))) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid Network International webhook payload structure']));
}

$externalEventId = (string)($event['orderId'] ?? $event['reference'] ?? uniqid('net_'));
$orderRef = (string)($event['merchantOrderReference'] ?? $event['orderReference'] ?? '');
$eventType = (string)($event['eventName'] ?? $event['state'] ?? 'ORDER_CLOSED');
$amount = ((float)($event['amount']['value'] ?? 0)) / 100;
if ($amount <= 0 && isset($event['amount'])) {
    $amount = (float)$event['amount'];
}

if (empty($orderRef) && isset($event['orderId'])) {
    $orderRef = (string)$event['orderId'];
}

$stInv = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? OR id = ?");
$stInv->execute([$orderRef, (int)$orderRef]);
$inv = $stInv->fetch();

if (!$inv) {
    http_response_code(404);
    exit(json_encode(['error' => 'Invoice not found']));
}

$tid = (int)$inv['tenant_id'];
$invId = (int)$inv['id'];

// Idempotency check
$stCheck = $pdo->prepare("SELECT id, status FROM webhook_events WHERE provider = 'network' AND external_event_id = ?");
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
        VALUES ('network', ?, ?, ?, ?, 'pending')
    ");
    $stIns->execute([$externalEventId, 'network.' . $eventType, $tid, $payloadHash]);
    $eventId = (int)$pdo->lastInsertId();
} else {
    $eventId = (int)$existingEvent['id'];
}

if (in_array(strtoupper($eventType), ['ORDER_CLOSED', 'CAPTURED', 'PURCHASE_SUCCESS', 'SUCCESS', 'PAID'], true)) {
    $payAmount = ($amount > 0) ? $amount : (float)$inv['total'];
    $currency = (string)($inv['currency'] ?? 'AED');

    try {
        $pdo->beginTransaction();

        $today = date('Y-m-d H:i:s');
        $notes = "Network International Payment (Ref: $externalEventId)";

        $stPayCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
        $stPayCheck->execute([$invId, $tid, $externalEventId, $externalEventId]);
        $existingPayId = (int)$stPayCheck->fetchColumn();

        if (!$existingPayId) {
            $stPay = $pdo->prepare("
                INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
                VALUES (?, ?, ?, ?, ?, 'network', 'network', ?, ?, ?)
            ");
            $stPay->execute([$tid, $invId, $payAmount, $currency, $today, $externalEventId, $externalEventId, $notes]);
            $paymentId = (int)$pdo->lastInsertId();

            $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND tenant_id = ?");
            $stSum->execute([$invId, $tid]);
            $newPaid = (float)$stSum->fetchColumn();

            $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

            $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ? AND tenant_id = ?");
            $stUpd->execute([$newPaid, $newStatus, $invId, $tid]);

            $acctService = new \Services\AccountingService($pdo, $tid);
            $acctService->postPaymentReceived($paymentId);

            log_audit($pdo, 'network_webhook_payment', 'payments', $paymentId, "Network synced payment {$currency} {$payAmount} for Invoice #{$inv['invoice_number']}");
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
