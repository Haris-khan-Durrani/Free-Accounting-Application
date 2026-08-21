<?php
// api/v1/webhooks/stripe.php - Cryptographically Verified & Idempotent Stripe Webhook Handler
require __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$event = json_decode($payload, true);

if (!$event || empty($event['type']) || empty($event['id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid webhook payload structure']));
}

$externalEventId = (string)$event['id'];
$eventType = (string)$event['type'];
$payloadHash = hash('sha256', $payload);

// Determine tenant ID if available in metadata
$object = $event['data']['object'] ?? [];
$metadata = $object['metadata'] ?? [];
$invId = (int)($metadata['invoice_id'] ?? 0);
$tenantId = (int)($metadata['tenant_id'] ?? 0);

if ($invId > 0 && $tenantId === 0) {
    $stTid = $pdo->prepare("SELECT tenant_id FROM invoices WHERE id = ?");
    $stTid->execute([$invId]);
    $tenantId = (int)$stTid->fetchColumn();
}

// Fetch webhook secret (Tenant setting -> Superadmin setting -> Environment variable fallback)
$webhookSecret = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_webhook_secret', getenv('STRIPE_WEBHOOK_SECRET') ?: '', $tenantId ?: null);

// Cryptographically verify signature (fail closed)
if (empty($webhookSecret)) {
    http_response_code(503);
    exit(json_encode(['error' => 'Stripe webhook is not configured']));
}

$isValid = \Services\PaymentGatewayService::verifyStripeSignature($payload, $sigHeader, $webhookSecret);
if (!$isValid) {
    http_response_code(401);
    exit(json_encode(['error' => 'Invalid Stripe webhook signature']));
}

// Idempotency check: verify if event was already processed
$stCheck = $pdo->prepare("SELECT id, status FROM webhook_events WHERE provider = 'stripe' AND external_event_id = ?");
$stCheck->execute([$externalEventId]);
$existingEvent = $stCheck->fetch();

if ($existingEvent) {
    if ($existingEvent['status'] === 'processed') {
        http_response_code(200);
        exit(json_encode(['received' => true, 'idempotent' => true, 'message' => 'Event already processed']));
    }
    $eventId = (int)$existingEvent['id'];
} else {
    // Record inbound webhook event
    $stIns = $pdo->prepare("
        INSERT INTO webhook_events (provider, external_event_id, event_type, tenant_id, payload_hash, status)
        VALUES ('stripe', ?, ?, ?, ?, 'pending')
    ");
    $stIns->execute([$externalEventId, $eventType, $tenantId ?: null, $payloadHash]);
    $eventId = (int)$pdo->lastInsertId();
}

// Process Event Atomically
if ($eventType === 'checkout.session.completed' || $eventType === 'payment_intent.succeeded') {
    $sessionId = $object['id'] ?? '';
    $amount = ((float)($object['amount_total'] ?? ($object['amount'] ?? 0))) / 100;

    if ($invId > 0 && $amount > 0) {
        try {
            $pdo->beginTransaction();

            $stInv = $pdo->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
            $stInv->execute([$invId]);
            $inv = $stInv->fetch();

            if ($inv) {
                $tid = (int)$inv['tenant_id'];
                $today = date('Y-m-d');

                // Check for duplicate payment record
                $stPayCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND (gateway_transaction_id = ? OR reference = ?)");
                $stPayCheck->execute([$invId, $sessionId, $externalEventId]);
                $existingPayId = (int)$stPayCheck->fetchColumn();

                if (!$existingPayId) {
                    $notes = "Stripe Webhook Payment (Event: $externalEventId)";
                    $stPay = $pdo->prepare("
                        INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes)
                        VALUES (?, ?, ?, ?, ?, 'stripe', 'stripe', ?, ?, ?)
                    ");
                    $stPay->execute([$tid, $invId, $amount, $inv['currency'], $today, $sessionId, $externalEventId, $notes]);
                    $paymentId = (int)$pdo->lastInsertId();

                    // Calculate cumulative payment total
                    $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?");
                    $stSum->execute([$invId]);
                    $newPaid = (float)$stSum->fetchColumn();

                    $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

                    $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ?");
                    $stUpd->execute([$newPaid, $newStatus, $invId]);

                    // Post to General Ledger using AccountingService
                    $acctService = new \Services\AccountingService($pdo, $tid);
                    $acctService->postPaymentReceived($paymentId);

                    log_audit($pdo, 'stripe_webhook_payment', 'payments', $paymentId, "Stripe Webhook synced payment {$inv['currency']} $amount for Invoice #{$inv['invoice_number']}");
                }

                // Update webhook event status to processed
                $stEvUpd = $pdo->prepare("UPDATE webhook_events SET status = 'processed', processed_at = NOW(), tenant_id = ? WHERE id = ?");
                $stEvUpd->execute([$tid, $eventId]);

                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $stEvErr = $pdo->prepare("UPDATE webhook_events SET status = 'failed', error = ? WHERE id = ?");
            $stEvErr->execute([$e->getMessage(), $eventId]);

            http_response_code(500);
            exit(json_encode(['error' => 'Webhook processing failure: ' . $e->getMessage()]));
        }
    }
} else {
    // Non-payment webhook event acknowledged
    $stEvUpd = $pdo->prepare("UPDATE webhook_events SET status = 'processed', processed_at = NOW() WHERE id = ?");
    $stEvUpd->execute([$eventId]);
}

http_response_code(200);
echo json_encode(['received' => true]);
exit;
