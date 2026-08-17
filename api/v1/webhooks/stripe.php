<?php
// api/v1/webhooks/stripe.php - Stripe Webhook Handler for Real-Time Finance Ledger Sync
require __DIR__ . '/../../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

$payload = file_get_input_stream();
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$event = json_decode($payload, true);

if (!$event || empty($event['type'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid webhook payload']));
}

$eventType = $event['type'];

if ($eventType === 'checkout.session.completed' || $eventType === 'payment_intent.succeeded') {
    $object = $event['data']['object'];
    $metadata = $object['metadata'] ?? [];
    $invId = (int)($metadata['invoice_id'] ?? 0);
    $sessionId = $object['id'] ?? '';
    $amount = ((float)($object['amount_total'] ?? ($object['amount'] ?? 0))) / 100;

    if ($invId > 0 && $amount > 0) {
        $st = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $st->execute([$invId]);
        $inv = $st->fetch();

        if ($inv) {
            $tid = (int)$inv['tenant_id'];
            $today = date('Y-m-d');

            // Check if payment exists
            $stCheck = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND notes LIKE ?");
            $stCheck->execute([$invId, "%Stripe Session: $sessionId%"]);
            $existing = $stCheck->fetchColumn();

            if (!$existing) {
                $notes = "Stripe Webhook Sync (Ref: $sessionId)";
                $stPay = $pdo->prepare("
                    INSERT INTO payments (tenant_id, invoice_id, amount, payment_date, payment_method, notes)
                    VALUES (?, ?, ?, ?, 'stripe', ?)
                ");
                $stPay->execute([$tid, $invId, $amount, $today, $notes]);

                $newPaid = (float)$inv['paid_amount'] + $amount;
                $newStatus = ($newPaid >= (float)$inv['total'] - 0.01) ? 'paid' : 'partially_paid';

                $stUpd = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ?");
                $stUpd->execute([$newPaid, $newStatus, $invId]);

                \Services\AccountingService::postPaymentReceived($pdo, $tid, $invId, $amount);
                log_audit($pdo, 'stripe_webhook_payment', 'payments', $invId, "Stripe Webhook synced payment {$inv['currency']} $amount");
            }
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
exit;
