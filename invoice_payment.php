<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// 1. Record Payment (Full or Partial)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    verify_csrf();

    $invId = (int)($_POST['invoice_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payDate = $_POST['payment_date'] ?? date('Y-m-d');
    $payMethod = $_POST['payment_method'] ?? 'Bank Transfer';
    $notes = trim($_POST['notes'] ?? '');

    $stInv = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?");
    $stInv->execute([$invId, $tid]);
    $inv = $stInv->fetch();

    if (!$inv) {
        flash('error', 'Invoice record not found.');
        redirect('index');
    }

    if ($amount <= 0) {
        flash('error', 'Payment amount must be greater than zero.');
        redirect('invoice_view?id=' . $invId);
    }

    // Insert Payment Record
    $stPay = $pdo->prepare("INSERT INTO payments (tenant_id, invoice_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stPay->execute([$tid, $invId, $amount, $payDate, $payMethod, $notes]);

    // Calculate New Cumulative Paid Total
    $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?");
    $stSum->execute([$invId]);
    $totalPaid = (float)$stSum->fetchColumn();

    $invoiceTotal = (float)$inv['total'];
    $newStatus = ($totalPaid >= $invoiceTotal) ? 'paid' : 'partially_paid';

    // Update Invoice Status & Paid Amount
    $stUpd = $pdo->prepare("UPDATE invoices SET status = ?, paid_amount = ? WHERE id = ? AND tenant_id = ?");
    $stUpd->execute([$newStatus, $totalPaid, $invId, $tid]);

    // Record Ledger Journal Entry
    \Services\AccountingService::postInvoicePayment($pdo, $tid, $invId, $amount, $payMethod);

    // Trigger n8n Automation Engine
    \Services\AutomationService::trigger($pdo, $tid, 'invoice_paid', [
        'invoice_number' => $inv['invoice_number'],
        'amount_paid' => $amount,
        'total_paid' => $totalPaid,
        'remaining_balance' => max(0, $invoiceTotal - $totalPaid),
        'status' => $newStatus,
        'payment_method' => $payMethod
    ]);

    log_audit($pdo, 'record_payment', 'invoices', $invId, "Recorded payment of " . money($amount, $inv['currency']) . " ($newStatus)");
    flash('success', "Payment of " . money($amount, $inv['currency']) . " recorded successfully! Status updated to: " . strtoupper($newStatus));
    redirect('invoice_view?id=' . $invId);
}

// 2. Void Invoice Action
if (isset($_GET['action']) && $_GET['action'] === 'void' && isset($_GET['id'])) {
    verify_csrf();
    $invId = (int)$_GET['id'];

    $stInv = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?");
    $stInv->execute([$invId, $tid]);
    $inv = $stInv->fetch();

    if ($inv) {
        $stVoid = $pdo->prepare("UPDATE invoices SET status = 'void' WHERE id = ? AND tenant_id = ?");
        $stVoid->execute([$invId, $tid]);

        log_audit($pdo, 'void_invoice', 'invoices', $invId, "Voided invoice " . $inv['invoice_number']);
        flash('success', "Invoice " . e($inv['invoice_number']) . " has been voided.");
    }
    redirect('invoice_view?id=' . $invId);
}

redirect('index');
