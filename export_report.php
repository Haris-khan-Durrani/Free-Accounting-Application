<?php
require __DIR__ . '/bootstrap.php';
require_login();

if (!has_role(['owner', 'admin', 'accountant'])) {
    flash('error', 'Access denied. Financial report CSV exports require accounting role permissions.');
    redirect('index');
}

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();


$type = $_GET['type'] ?? 'pnl';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

header('Content-Type: text/csv; charset=utf-8');
$out = fopen('php://output', 'w');

if ($type === 'pnl') {
    header('Content-Disposition: attachment; filename="pnl_report_' . date('Ymd') . '.csv"');
    fputcsv($out, ['Category / Description', 'Revenue / Expense Amount', 'Currency']);

    $stRev = $pdo->prepare("SELECT SUM(total) total FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
    $stRev->execute([$tid, $startDate, $endDate]);
    $rev = (float)($stRev->fetch()['total'] ?? 0);

    $stExp = $pdo->prepare("SELECT SUM(total) total FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
    $stExp->execute([$tid, $startDate, $endDate]);
    $exp = (float)($stExp->fetch()['total'] ?? 0);

    $netProfit = $rev - $exp;

    fputcsv($out, ['Total Revenue (Gross Invoiced)', number_format($rev, 2, '.', ''), tenant()['currency']]);
    fputcsv($out, ['Total Business Expenses', number_format($exp, 2, '.', ''), tenant()['currency']]);
    fputcsv($out, ['Net Operating Profit', number_format($netProfit, 2, '.', ''), tenant()['currency']]);

} elseif ($type === 'aging') {
    header('Content-Disposition: attachment; filename="aging_report_' . date('Ymd') . '.csv"');
    fputcsv($out, ['Invoice #', 'Client Name', 'Invoice Date', 'Due Date', 'Days Overdue', 'Total Amount', 'Paid Amount', 'Balance Due']);

    $st = $pdo->prepare("
        SELECT i.*, c.company_name, DATEDIFF(CURRENT_DATE(), i.valid_until) days_overdue 
        FROM invoices i 
        JOIN clients c ON c.id = i.client_id 
        WHERE i.tenant_id = ? AND i.status IN ('draft', 'sent', 'overdue') 
        ORDER BY days_overdue DESC
    ");
    $st->execute([$tid]);
    $rows = $st->fetchAll();

    foreach ($rows as $r) {
        $bal = max(0, (float)$r['total'] - (float)$r['paid_amount']);
        fputcsv($out, [
            $r['invoice_number'],
            $r['company_name'],
            $r['invoice_date'],
            $r['valid_until'],
            max(0, (int)$r['days_overdue']),
            number_format((float)$r['total'], 2, '.', ''),
            number_format((float)$r['paid_amount'], 2, '.', ''),
            number_format($bal, 2, '.', '')
        ]);
    }

} elseif ($type === 'tax') {
    header('Content-Disposition: attachment; filename="vat_tax_report_' . date('Ymd') . '.csv"');
    fputcsv($out, ['Invoice #', 'Client Name', 'Invoice Date', 'Subtotal', 'VAT Tax Amount', 'Total Amount']);

    $st = $pdo->prepare("
        SELECT i.*, c.company_name 
        FROM invoices i 
        JOIN clients c ON c.id = i.client_id 
        WHERE i.tenant_id = ? AND i.status != 'cancelled' AND i.invoice_date BETWEEN ? AND ? 
        ORDER BY i.invoice_date ASC
    ");
    $st->execute([$tid, $startDate, $endDate]);
    $rows = $st->fetchAll();

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['invoice_number'],
            $r['company_name'],
            $r['invoice_date'],
            number_format((float)$r['subtotal'], 2, '.', ''),
            number_format((float)$r['tax_amount'], 2, '.', ''),
            number_format((float)$r['total'], 2, '.', '')
        ]);
    }

} elseif ($type === 'balance_sheet') {
    $asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');
    header('Content-Disposition: attachment; filename="balance_sheet_' . date('Ymd') . '.csv"');
    fputcsv($out, ['Account Section', 'Heading / Account Title', 'Amount', 'Currency']);

    $stPay = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND payment_date <= ?");
    $stPay->execute([$tid, $asOfDate]);
    $totalCashCollected = (float)$stPay->fetchColumn();

    $stExp = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date <= ?");
    $stExp->execute([$tid, $asOfDate]);
    $totalExpensesPaid = (float)$stExp->fetchColumn();

    $cashBalance = max(0, $totalCashCollected - $totalExpensesPaid);

    $stAr = $pdo->prepare("SELECT COALESCE(SUM(total - paid_amount), 0) FROM invoices WHERE tenant_id = ? AND status IN ('draft', 'sent', 'overdue', 'partially_paid') AND invoice_date <= ?");
    $stAr->execute([$tid, $asOfDate]);
    $accountsReceivable = max(0, (float)$stAr->fetchColumn());
    $totalAssets = $cashBalance + $accountsReceivable;

    $stOutVat = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date <= ?");
    $stOutVat->execute([$tid, $asOfDate]);
    $outputVat = (float)$stOutVat->fetchColumn();

    $stInVat = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM expenses WHERE tenant_id = ? AND expense_date <= ?");
    $stInVat->execute([$tid, $asOfDate]);
    $inputVat = (float)$stInVat->fetchColumn();

    $netVatPayable = max(0, $outputVat - $inputVat);
    $totalLiabilities = $netVatPayable;
    $retainedEarnings = $totalAssets - $totalLiabilities;
    $totalEquity = $retainedEarnings;

    $curr = tenant()['currency'];

    fputcsv($out, ['Current Assets', 'Cash & Bank Accounts (Net Collected - Expenses)', number_format($cashBalance, 2, '.', ''), $curr]);
    fputcsv($out, ['Current Assets', 'Accounts Receivable (A/R Outstanding Invoices)', number_format($accountsReceivable, 2, '.', ''), $curr]);
    fputcsv($out, ['Current Assets', 'TOTAL CURRENT ASSETS', number_format($totalAssets, 2, '.', ''), $curr]);
    fputcsv($out, ['Current Liabilities', 'Net Output VAT Payable (Output VAT - Input VAT)', number_format($netVatPayable, 2, '.', ''), $curr]);
    fputcsv($out, ['Current Liabilities', 'TOTAL CURRENT LIABILITIES', number_format($totalLiabilities, 2, '.', ''), $curr]);
    fputcsv($out, ['Equity', 'Retained Earnings / Accumulated Surplus', number_format($retainedEarnings, 2, '.', ''), $curr]);
    fputcsv($out, ['Equity', 'TOTAL EQUITY', number_format($totalEquity, 2, '.', ''), $curr]);
    fputcsv($out, ['Summary', 'TOTAL LIABILITIES & EQUITY', number_format($totalLiabilities + $totalEquity, 2, '.', ''), $curr]);
}

fclose($out);
exit;
