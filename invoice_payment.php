<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Only owner, admin, and accountant can record payments or void invoices
if (!has_role(['owner', 'admin', 'accountant'])) {
    flash('error', 'Permission denied. You do not have access to record payments.');
    redirect('invoices');
}

// 1. Record Payment (Full or Partial)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    verify_csrf();

    $invId = (int)($_POST['invoice_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payDate = $_POST['payment_date'] ?? date('Y-m-d H:i:s');
    if (strlen($payDate) === 10) {
        $payDate .= ' ' . date('H:i:s');
    }
    $payMethod = $_POST['payment_method'] ?? 'Bank Transfer';
    $notes = trim($_POST['notes'] ?? '');
    $reference = trim($_POST['reference'] ?? $_POST['transaction_id'] ?? $_POST['stripe_id'] ?? '');
    $gateway = (str_contains(strtolower($payMethod), 'stripe')) ? 'stripe' : 'manual';

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

    try {
        $pdo->beginTransaction();

        // Insert Payment Record into Ledger
        $stPay = $pdo->prepare("INSERT INTO payments (tenant_id, invoice_id, amount, currency, payment_date, payment_method, gateway, gateway_transaction_id, reference, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stPay->execute([$tid, $invId, $amount, $inv['currency'], $payDate, $payMethod, $gateway, $reference, $reference, $notes, $_SESSION['user_id'] ?? null]);
        $paymentId = (int)$pdo->lastInsertId();

        // Calculate New Cumulative Paid Total
        $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?");
        $stSum->execute([$invId]);
        $totalPaid = (float)$stSum->fetchColumn();

        $invoiceTotal = (float)$inv['total'];
        $newStatus = ($totalPaid >= $invoiceTotal - 0.01) ? 'paid' : 'partially_paid';

        // Update Invoice Status & Paid Amount
        $stUpd = $pdo->prepare("UPDATE invoices SET status = ?, paid_amount = ? WHERE id = ? AND tenant_id = ?");
        $stUpd->execute([$newStatus, $totalPaid, $invId, $tid]);

        // Record Ledger Journal Entry atomically for this payment
        $acct = new \Services\AccountingService($pdo, $tid);
        $acct->postPaymentReceived($paymentId);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Payment recording failed: ' . $e->getMessage());
        redirect('invoice_view?id=' . $invId);
    }

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

// 2. Void Invoice Action — now POST-based with CSRF to prevent CSRF GET attacks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'void') {
    verify_csrf();
    $invId = (int)($_POST['invoice_id'] ?? 0);

    $stInv = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?");
    $stInv->execute([$invId, $tid]);
    $inv = $stInv->fetch();

    if ($inv) {
        $stVoid = $pdo->prepare("UPDATE invoices SET status = 'void' WHERE id = ? AND tenant_id = ?");
        $stVoid->execute([$invId, $tid]);

        log_audit($pdo, 'void_invoice', 'invoices', $invId, "Voided invoice " . $inv['invoice_number']);
        flash('success', "Invoice " . e($inv['invoice_number']) . " has been voided.");
    } else {
        flash('error', 'Invoice not found or access denied.');
    }
    redirect('invoice_view?id=' . $invId);
}

redirect('index');
