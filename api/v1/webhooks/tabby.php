<?php
// api/v1/webhooks/tabby.php - Tabby BNPL Webhook Handler
require __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

if (!$event || empty($event['id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid Tabby webhook payload structure']));
}

$externalEventId = (string)$event['id'];
$status = (string)($event['status'] ?? $event['payment']['status'] ?? 'authorized');
$orderRef = (string)($event['order']['reference_id'] ?? $event['payment']['order']['reference_id'] ?? '');
$amount = (float)($event['amount'] ?? $event['payment']['amount'] ?? 0);
$currency = (string)($event['currency'] ?? $event['payment']['currency'] ?? 'AED');

if (empty($orderRef)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing order reference ID']));
}

// Find invoice by invoice_number or id
$stInv = $pdo->prepare("SELECT * FROM invoices WHERE invoice_number = ? OR id = ?");
$stInv->execute([$orderRef, (int)$orderRef]);
$inv = $stInv->fetch();

if (!$inv) {
    http_response_code(404);
    exit(json_encode(['error' => 'Invoice not found']));
}

$tid = (int)$inv['tenant_id'];
$invId = (int)$inv['id'];

// Check secret key / webhook configured for tenant
$secretKey = \Services\PaymentGatewayService::getSetting($pdo, 'tabby_secret_key', '', $tid);
if (empty($secretKey)) {
    http_response_code(503);
    exit(json_encode(['error' => 'Tabby gateway is not configured for this tenant']));
}

// Idempotency check
$stCheck = $pdo->prepare("SELECT id, status FROM webhook_events WHERE provider = 'tabby' AND external_event_id = ?");
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
        VALUES ('tabby', ?, ?, ?, ?, 'pending')
    ");
    $stIns->execute([$externalEventId, 'tabby.' . $status, $tid, $payloadHash]);
    $eventId = (int)$pdo->lastInsertId();
} else {
    $eventId = (int)$existingEvent['id'];
}

// Handle payment status
if (in_array(strtolower($status), ['authorized', 'captured', 'closed', 'success'], true)) {
    $payAmount = ($amount > 0) ? $amount : (float)$inv['total'];

    try {
        $pdo->beginTransaction();

        $today = date('Y-m-d H:i:s');
        $notes = "Tabby BNPL Payment (Event: $externalEventId)";

        // Check for duplicate payment record
        $stPayCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
        $stPayCheck->execute([$invId, $tid, $externalEventId, $externalEventId]);
        $existingPayId = (int)$stPayCheck->fetchColumn();

        if (!$existingPayId) {
            $stPay = $pdo->prepare("
                INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
                VALUES (?, ?, ?, ?, ?, 'tabby', 'tabby', ?, ?, ?)
            ");
            $stPay->execute([$tid, $invId, $payAmount, $currency, $today, $externalEventId, $externalEventId, $notes]);
            $paymentId = (int)$pdo->lastInsertId();

            // Calculate cumulative payment total
            $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND tenant_id = ?");
            $stSum->execute([$invId, $tid]);
            $newPaid = (float)$stSum->fetchColumn();

            $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

            $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ? AND tenant_id = ?");
            $stUpd->execute([$newPaid, $newStatus, $invId, $tid]);

            // Post to General Ledger using AccountingService
            $acctService = new \Services\AccountingService($pdo, $tid);
            $acctService->postPaymentReceived($paymentId);

            log_audit($pdo, 'tabby_webhook_payment', 'payments', $paymentId, "Tabby synced payment {$currency} {$payAmount} for Invoice #{$inv['invoice_number']}");
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
