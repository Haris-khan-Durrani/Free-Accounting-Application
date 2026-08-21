<?php
require __DIR__ . '/bootstrap.php';
require_role(['owner', 'admin', 'accountant', 'sales']);

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$client = (int)($_POST['client_id'] ?? 0);
$number = trim($_POST['invoice_number'] ?? '');
$date = $_POST['invoice_date'] ?? date('Y-m-d');
$valid = $_POST['valid_until'] ?: null;
$status = in_array($_POST['status'] ?? '', ['draft', 'sent', 'paid', 'overdue', 'cancelled'], true) ? $_POST['status'] : 'draft';
$currency = $_POST['currency'] ?? 'AED';
$taxRateId = (int)($_POST['tax_rate_id'] ?? 0);
$templateId = $_POST['template_id'] ?? 'modern_minimal';

$desc = $_POST['description'] ?? [];
$details = $_POST['details'] ?? [];
$qty = $_POST['qty'] ?? [];
$price = $_POST['unit_price'] ?? [];
$rows = [];
$subtotal = 0;

foreach ($desc as $k => $d) {
    $d = trim($d);
    if ($d === '') continue;
    $q = max(0, (float)($qty[$k] ?? 0));
    $p = max(0, (float)($price[$k] ?? 0));
    $a = round($q * $p, 2);
    $subtotal += $a;
    $rows[] = ['d' => $d, 'x' => trim($details[$k] ?? ''), 'q' => $q, 'p' => $p, 'a' => $a];
}

if (!$client || !$number || !$rows) {
    flash('error', 'Client, invoice number, and at least one line item are required.');
    redirect('invoice_form.php' . ($id ? '?id=' . $id : ''));
}

// Verify selected client belongs to the active tenant
$stClientCheck = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE id = ? AND tenant_id = ?");
$stClientCheck->execute([$client, $tid]);
if ((int)$stClientCheck->fetchColumn() === 0) {
    flash('error', 'Selected client record was not found in your workspace.');
    redirect('invoice_form.php' . ($id ? '?id=' . $id : ''));
}

$type = ($_POST['discount_type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
$dv = max(0, (float)($_POST['discount_value'] ?? 0));
$da = round(calc_discount($subtotal, $type, $dv), 2);
$netSubtotal = max(0, $subtotal - $da);

// Calculate Tax / VAT
$taxAmount = max(0, round((float)($_POST['tax_amount'] ?? 0), 2));

// If tax_amount was 0 but a specific tax_rate_id was selected
if ($taxAmount == 0 && $taxRateId > 0) {
    $stT = $pdo->prepare("SELECT rate_percent FROM tax_rates WHERE id = ? AND tenant_id = ?");
    $stT->execute([$taxRateId, $tid]);
    $taxPercent = (float)($stT->fetchColumn() ?: 0);
    $taxAmount = round($netSubtotal * ($taxPercent / 100), 2);
}
$total = round($netSubtotal + $taxAmount, 2);
$notes = trim($_POST['notes'] ?? '');

try {
    $pdo->beginTransaction();
    
    if ($id > 0) {
        // Lock and verify parent invoice belongs to active tenant before executing mutations
        $stLock = $pdo->prepare('SELECT id FROM invoices WHERE id = ? AND tenant_id = ? FOR UPDATE');
        $stLock->execute([$id, $tid]);
        if (!$stLock->fetch()) {
            throw new RuntimeException('Invoice not found in your workspace.');
        }

        $st = $pdo->prepare('UPDATE invoices SET invoice_number=?, client_id=?, invoice_date=?, valid_until=?, status=?, currency=?, subtotal=?, discount_type=?, discount_value=?, discount_amount=?, tax_rate_id=?, tax_amount=?, total=?, notes=?, template_id=? WHERE id=? AND tenant_id=?');
        $st->execute([$number, $client, $date, $valid, $status, $currency, $subtotal, $type, $dv, $da, $taxRateId ?: null, $taxAmount, $total, $notes, $templateId, $id, $tid]);

        // Delete line items using tenant-bound JOIN
        $stDelItems = $pdo->prepare('DELETE ii FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE ii.invoice_id = ? AND i.tenant_id = ?');
        $stDelItems->execute([$id, $tid]);
    } else {
        $st = $pdo->prepare('INSERT INTO invoices (tenant_id, invoice_number, client_id, invoice_date, valid_until, status, currency, subtotal, discount_type, discount_value, discount_amount, tax_rate_id, tax_amount, total, notes, template_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$tid, $number, $client, $date, $valid, $status, $currency, $subtotal, $type, $dv, $da, $taxRateId ?: null, $taxAmount, $total, $notes, $templateId]);
        $id = (int)$pdo->lastInsertId();
    }

    $stItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, details, qty, unit_price, amount, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($rows as $k => $r) {
        $stItem->execute([$id, $r['d'], $r['x'], $r['q'], $r['p'], $r['a'], $k]);
    }

    $pdo->commit();

    // Post double-entry accounting journal entries automatically
    $acctService = new \Services\AccountingService($pdo, $tid);
    if ($status === 'paid') {
        $acctService->postInvoicePaid($id);
    } else {
        $acctService->postInvoiceCreated($id);
    }

    log_audit($pdo, 'save_invoice', 'invoices', $id, "Saved invoice $number for total $currency $total");
    flash('success', 'Invoice saved successfully.');
    redirect('invoice_view.php?id=' . $id);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', 'Could not save invoice: ' . $e->getMessage());
    redirect('invoice_form.php' . ($id ? '?id=' . $id : ''));
}
