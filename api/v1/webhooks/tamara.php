<?php
// api/v1/webhooks/tamara.php - Tamara BNPL Webhook Handler
require __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

if (!$event || (empty($event['order_id']) && empty($event['order_reference_id']))) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid Tamara webhook payload structure']));
}

$externalEventId = (string)($event['order_id'] ?? $event['event_id'] ?? uniqid('tamara_'));
$orderRef = (string)($event['order_reference_id'] ?? $event['order_number'] ?? '');
$eventType = (string)($event['event_type'] ?? $event['status'] ?? 'order_approved');

if (empty($orderRef)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing order reference ID']));
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

$apiToken = \Services\PaymentGatewayService::getSetting($pdo, 'tamara_api_token', '', $tid);
if (empty($apiToken)) {
    http_response_code(503);
    exit(json_encode(['error' => 'Tamara gateway is not configured for this tenant']));
}

// Idempotency check
$stCheck = $pdo->prepare("SELECT id, status FROM webhook_events WHERE provider = 'tamara' AND external_event_id = ?");
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
        VALUES ('tamara', ?, ?, ?, ?, 'pending')
    ");
    $stIns->execute([$externalEventId, 'tamara.' . $eventType, $tid, $payloadHash]);
    $eventId = (int)$pdo->lastInsertId();
} else {
    $eventId = (int)$existingEvent['id'];
}

if (in_array(strtolower($eventType), ['order_approved', 'approved', 'success', 'authorised', 'captured'], true)) {
    $payAmount = (float)$inv['total'];
    $currency = (string)($inv['currency'] ?? 'AED');

    try {
        $pdo->beginTransaction();

        $today = date('Y-m-d');
        $notes = "Tamara BNPL Payment (Order: $externalEventId)";

        $stPayCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
        $stPayCheck->execute([$invId, $tid, $externalEventId, $externalEventId]);
        $existingPayId = (int)$stPayCheck->fetchColumn();

        if (!$existingPayId) {
            $stPay = $pdo->prepare("
                INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
                VALUES (?, ?, ?, ?, ?, 'tamara', 'tamara', ?, ?, ?)
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

            log_audit($pdo, 'tamara_webhook_payment', 'payments', $paymentId, "Tamara synced payment {$currency} {$payAmount} for Invoice #{$inv['invoice_number']}");
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
