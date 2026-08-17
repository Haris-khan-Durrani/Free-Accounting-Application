<?php
// cron_recurring.php - Automated Background Worker for Recurring Invoices
// Can be executed via CLI: php cron_recurring.php or HTTP GET with cron_key

require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];

// CLI or Cron Key Authentication
$cronKey = $_GET['key'] ?? '';
$cliMode = (php_sapi_name() === 'cli');

if (!$cliMode && $cronKey !== 'onesol_cron_secret_2026') {
    // Allow if logged in as admin
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        exit(json_encode(['error' => 'Unauthorized cron invocation']));
    }
}

$today = date('Y-m-d');
$st = $pdo->prepare("
    SELECT r.*, c.company_name, c.email as client_email, t.name as tenant_name, b.company_name as brand_name
    FROM recurring_invoices r
    JOIN clients c ON c.id = r.client_id
    JOIN tenants t ON t.id = r.tenant_id
    LEFT JOIN branding_settings b ON b.tenant_id = r.tenant_id
    WHERE r.status = 'active' AND r.next_issue_date <= ?
");
$st->execute([$today]);
$schedules = $st->fetchAll();

$generatedCount = 0;
$logMessages = [];

foreach ($schedules as $r) {
    $tid = (int)$r['tenant_id'];
    $data = json_decode($r['template_json'], true) ?: [];

    // Generate Invoice Number
    $stNum = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM invoices WHERE tenant_id = ?");
    $stNum->execute([$tid]);
    $nextId = (int)$stNum->fetchColumn();
    $invNum = 'INV-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    $subtotal = (float)($data['subtotal'] ?? 0);
    $taxAmount = (float)($data['tax_amount'] ?? 0);
    $total = (float)($data['total'] ?? 0);
    $currency = $data['currency'] ?? 'AED';
    $notes = $data['notes'] ?? 'Auto-generated recurring invoice';

    // Insert Invoice
    $stInv = $pdo->prepare("
        INSERT INTO invoices (tenant_id, client_id, invoice_number, invoice_date, valid_until, subtotal, tax_amount, total, currency, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', ?)
    ");
    $validUntil = date('Y-m-d', strtotime('+14 days'));
    $stInv->execute([$tid, $r['client_id'], $invNum, $today, $validUntil, $subtotal, $taxAmount, $total, $currency, $notes]);
    $newInvId = (int)$pdo->lastInsertId();

    // Insert Line Items
    if (!empty($data['items']) && is_array($data['items'])) {
        $stItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_description, qty, unit_price, amount) VALUES (?, ?, ?, ?, ?)");
        foreach ($data['items'] as $item) {
            $stItem->execute([$newInvId, $item['description'] ?? 'Recurring Service', $item['qty'] ?? 1, $item['unit_price'] ?? 0, $item['amount'] ?? 0]);
        }
    }

    // Post Double-Entry Journal Entry
    \Services\AccountingService::postInvoiceCreated($pdo, $tid, $newInvId, $subtotal, $taxAmount, $total);

    // Calculate Next Issue Date based on frequency
    $freq = $r['frequency'];
    if ($freq === 'weekly') {
        $nextDate = date('Y-m-d', strtotime('+1 week'));
    } elseif ($freq === 'monthly') {
        $nextDate = date('Y-m-d', strtotime('+1 month'));
    } elseif ($freq === 'quarterly') {
        $nextDate = date('Y-m-d', strtotime('+3 months'));
    } else {
        $nextDate = date('Y-m-d', strtotime('+1 year'));
    }

    // Update Schedule
    $stUpd = $pdo->prepare("UPDATE recurring_invoices SET last_issued_date = ?, next_issue_date = ? WHERE id = ?");
    $stUpd->execute([$today, $nextDate, $r['id']]);

    // Send Email Notification if client email exists
    if (!empty($r['client_email'])) {
        $brandName = $r['brand_name'] ?: $r['tenant_name'];
        $subject = "Tax Invoice $invNum from $brandName";
        $htmlBody = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;'>
                <h2 style='color: #0f172a;'>Recurring Tax Invoice Generated</h2>
                <p>Dear {$r['company_name']},</p>
                <p>Your recurring tax invoice <strong>$invNum</strong> for total <strong>$currency " . number_format($total, 2) . "</strong> has been generated and is now due.</p>
                <p>Date of Issue: <strong>$today</strong> | Due Date: <strong>$validUntil</strong></p>
            </div>
        ";
        \Services\Mailer::send($pdo, $tid, $r['client_email'], $subject, $htmlBody);
    }

    $generatedCount++;
    $logMessages[] = "Generated $invNum for {$r['company_name']} (Tenant #$tid). Next date: $nextDate";
}

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'invoices_generated' => $generatedCount,
    'logs' => $logMessages
];

if ($cliMode) {
    echo "=== OneSol Recurring Invoice Cron Engine ===\n";
    echo "Executed at: " . date('Y-m-d H:i:s') . "\n";
    echo "Invoices Generated: $generatedCount\n";
    foreach ($logMessages as $m) echo "- $m\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
}
