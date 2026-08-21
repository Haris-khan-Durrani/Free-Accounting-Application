<?php
require __DIR__ . '/bootstrap.php';
require_login();

use Services\PdfReportService;

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$brand = branding();
$tenant = tenant();

$type = $_GET['type'] ?? 'pnl';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$asOfDate  = $_GET['as_of_date'] ?? date('Y-m-d');
$clientId  = (int)($_GET['client_id'] ?? 0);

$reportTitle = 'Financial Report';
$subtitle = '';
$filters = [];
$contentHtml = '';

if ($type === 'pnl') {
    $reportTitle = 'Profit & Loss Statement (P&L)';
    $subtitle = 'Income Statement from ' . date('d M Y', strtotime($startDate)) . ' to ' . date('d M Y', strtotime($endDate));
    $filters = ['start_date' => $startDate, 'end_date' => $endDate];

    // Calculate Invoiced Revenue
    $stRev = $pdo->prepare("SELECT SUM(total) total, SUM(subtotal) subtotal, SUM(tax_amount) tax FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
    $stRev->execute([$tid, $startDate, $endDate]);
    $revData = $stRev->fetch();
    $grossRev = (float)($revData['total'] ?? 0);

    // Calculate Payments Collected
    $stPay = $pdo->prepare("SELECT SUM(amount) total FROM payments WHERE tenant_id = ? AND payment_date BETWEEN ? AND ?");
    $stPay->execute([$tid, $startDate, $endDate]);
    $cashCollected = (float)($stPay->fetchColumn() ?? 0);

    // Calculate Expenses
    $stExp = $pdo->prepare("SELECT SUM(total) total FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
    $stExp->execute([$tid, $startDate, $endDate]);
    $totalExp = (float)($stExp->fetchColumn() ?? 0);

    $netProfit = $grossRev - $totalExp;

    $contentHtml = '
    <table style="width:100%;">
        <thead>
            <tr><th>Financial Metrics</th><th class="text-right">Amount (' . e($tenant['currency']) . ')</th></tr>
        </thead>
        <tbody>
            <tr><td class="font-bold">Gross Invoiced Revenue</td><td class="text-right font-bold">' . money($grossRev, $tenant['currency']) . '</td></tr>
            <tr><td>Total Cash Collected (Settled)</td><td class="text-right text-emerald-600">' . money($cashCollected, $tenant['currency']) . '</td></tr>
            <tr><td class="font-bold">Total Operating Expenses</td><td class="text-right font-bold text-rose-600">(' . money($totalExp, $tenant['currency']) . ')</td></tr>
            <tr class="bg-total"><td class="font-black">NET OPERATING PROFIT / SURPLUS</td><td class="text-right font-black" style="font-size: 13px; color:' . ($netProfit >= 0 ? '#166534' : '#9f1239') . ';">' . money($netProfit, $tenant['currency']) . '</td></tr>
        </tbody>
    </table>
    ';

} elseif ($type === 'balance_sheet') {
    $reportTitle = 'Balance Sheet Statement';
    $subtitle = 'Financial Position As Of ' . date('d M Y', strtotime($asOfDate));
    $filters = ['as_of_date' => $asOfDate];

    // Cash & Bank (Collected Payments - Expenses)
    $stPay = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND payment_date <= ?");
    $stPay->execute([$tid, $asOfDate]);
    $totalCashCollected = (float)$stPay->fetchColumn();

    $stExp = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date <= ?");
    $stExp->execute([$tid, $asOfDate]);
    $totalExpensesPaid = (float)$stExp->fetchColumn();

    $cashBalance = max(0, $totalCashCollected - $totalExpensesPaid);

    // Accounts Receivable
    $stAr = $pdo->prepare("SELECT COALESCE(SUM(total - paid_amount), 0) FROM invoices WHERE tenant_id = ? AND status IN ('draft', 'sent', 'overdue', 'partially_paid') AND invoice_date <= ?");
    $stAr->execute([$tid, $asOfDate]);
    $accountsReceivable = max(0, (float)$stAr->fetchColumn());

    $totalAssets = $cashBalance + $accountsReceivable;

    // Liabilities
    $stOutVat = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date <= ?");
    $stOutVat->execute([$tid, $asOfDate]);
    $outputVat = (float)$stOutVat->fetchColumn();

    $stInVat = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM expenses WHERE tenant_id = ? AND expense_date <= ?");
    $stInVat->execute([$tid, $asOfDate]);
    $inputVat = (float)$stInVat->fetchColumn();

    $netVatPayable = max(0, $outputVat - $inputVat);
    $totalLiabilities = $netVatPayable;

    $equity = $totalAssets - $totalLiabilities;

    $contentHtml = '
    <table style="width:100%;">
        <thead><tr><th colspan="2">ASSETS</th></tr></thead>
        <tbody>
            <tr><td>Cash & Bank Balances</td><td class="text-right">' . money($cashBalance, $tenant['currency']) . '</td></tr>
            <tr><td>Accounts Receivable (A/R Invoices)</td><td class="text-right">' . money($accountsReceivable, $tenant['currency']) . '</td></tr>
            <tr class="bg-total"><td class="font-black">TOTAL ASSETS</td><td class="text-right font-black">' . money($totalAssets, $tenant['currency']) . '</td></tr>
        </tbody>
    </table>

    <table style="width:100%; margin-top: 20px;">
        <thead><tr><th colspan="2">LIABILITIES & EQUITY</th></tr></thead>
        <tbody>
            <tr><td>Net Output VAT Payable</td><td class="text-right">' . money($netVatPayable, $tenant['currency']) . '</td></tr>
            <tr class="bg-total"><td class="font-bold">TOTAL LIABILITIES</td><td class="text-right font-bold">' . money($totalLiabilities, $tenant['currency']) . '</td></tr>
            <tr><td>Retained Earnings / Accumulated Equity</td><td class="text-right">' . money($equity, $tenant['currency']) . '</td></tr>
            <tr class="bg-total"><td class="font-black">TOTAL LIABILITIES & EQUITY</td><td class="text-right font-black">' . money($totalLiabilities + $equity, $tenant['currency']) . '</td></tr>
        </tbody>
    </table>
    ';

} elseif ($type === 'aging') {
    $reportTitle = 'Accounts Receivable (A/R) Aging Report';
    $subtitle = 'Outstanding Balances As Of ' . date('d M Y', strtotime($asOfDate));
    $filters = ['as_of_date' => $asOfDate];

    $sql = "SELECT i.*, c.company_name, DATEDIFF(?, i.valid_until) days_overdue 
            FROM invoices i 
            JOIN clients c ON c.id = i.client_id 
            WHERE i.tenant_id = ? AND i.status IN ('draft', 'sent', 'overdue', 'partially_paid')";
    $params = [$asOfDate, $tid];

    if ($clientId > 0) {
        $sql .= " AND i.client_id = ?";
        $params[] = $clientId;
        $filters['client_id'] = $clientId;
    }
    $sql .= " ORDER BY days_overdue DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $contentHtml = '
    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Client Name</th>
                <th>Invoice Date</th>
                <th>Due Date</th>
                <th>Overdue</th>
                <th class="text-right">Total</th>
                <th class="text-right">Balance Due</th>
            </tr>
        </thead>
        <tbody>';
    
    $totalBal = 0;
    foreach ($rows as $r) {
        $bal = max(0, (float)$r['total'] - (float)$r['paid_amount']);
        $totalBal += $bal;
        $days = max(0, (int)$r['days_overdue']);
        $badgeClass = $days > 60 ? 'badge-danger' : ($days > 30 ? 'badge-warning' : 'badge-success');

        $contentHtml .= '
        <tr>
            <td class="font-bold">' . e($r['invoice_number']) . '</td>
            <td>' . e($r['company_name']) . '</td>
            <td>' . e(date('d M Y', strtotime($r['invoice_date']))) . '</td>
            <td>' . e(date('d M Y', strtotime($r['valid_until']))) . '</td>
            <td><span class="' . $badgeClass . '">' . $days . ' days</span></td>
            <td class="text-right">' . money((float)$r['total'], $r['currency'] ?: $tenant['currency']) . '</td>
            <td class="text-right font-bold">' . money($bal, $r['currency'] ?: $tenant['currency']) . '</td>
        </tr>';
    }

    $contentHtml .= '
        <tr class="bg-total">
            <td colspan="6" class="font-black">TOTAL RECEIVABLE BALANCE</td>
            <td class="text-right font-black">' . money($totalBal, $tenant['currency']) . '</td>
        </tr>
        </tbody>
    </table>';

} elseif ($type === 'vat201') {
    $reportTitle = 'UAE FTA VAT 201 Declaration Report';
    $subtitle = 'Tax Return Period: ' . date('d M Y', strtotime($startDate)) . ' to ' . date('d M Y', strtotime($endDate));
    $filters = ['start_date' => $startDate, 'end_date' => $endDate];

    // Output VAT (Standard 5% Rate Invoices)
    $stOut = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) subtotal, COALESCE(SUM(tax_amount), 0) tax FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
    $stOut->execute([$tid, $startDate, $endDate]);
    $outVat = $stOut->fetch();

    // Input VAT (Recoverable Expense VAT)
    $stIn = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) subtotal, COALESCE(SUM(tax_amount), 0) tax FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
    $stIn->execute([$tid, $startDate, $endDate]);
    $inVat = $stIn->fetch();

    $netVat = (float)$outVat['tax'] - (float)$inVat['tax'];

    $contentHtml = '
    <table>
        <thead>
            <tr><th>UAE FTA VAT 201 Box Description</th><th class="text-right">Amount (' . e($tenant['currency']) . ')</th><th class="text-right">VAT Amount (5%)</th></tr>
        </thead>
        <tbody>
            <tr><td class="font-bold">Box 1a: Standard Rated Sales (Supplies in UAE 5%)</td><td class="text-right">' . money((float)$outVat['subtotal'], $tenant['currency']) . '</td><td class="text-right font-bold text-emerald-700">' . money((float)$outVat['tax'], $tenant['currency']) . '</td></tr>
            <tr><td class="font-bold">Box 9: Standard Rated Expenses (Recoverable Input VAT 5%)</td><td class="text-right">' . money((float)$inVat['subtotal'], $tenant['currency']) . '</td><td class="text-right font-bold text-rose-700">(' . money((float)$inVat['tax'], $tenant['currency']) . ')</td></tr>
            <tr class="bg-total"><td class="font-black">NET VAT PAYABLE / (RECLAIMABLE) TO FTA</td><td colspan="2" class="text-right font-black" style="font-size: 13px;">' . money($netVat, $tenant['currency']) . '</td></tr>
        </tbody>
    </table>';

} elseif ($type === 'client_statement') {
    $reportTitle = 'Statement of Account';
    $subtitle = 'Period: ' . date('d M Y', strtotime($startDate)) . ' to ' . date('d M Y', strtotime($endDate));
    $filters = ['client_id' => $clientId, 'start_date' => $startDate, 'end_date' => $endDate];

    $stClient = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
    $stClient->execute([$clientId, $tid]);
    $client = $stClient->fetch();

    if ($client) {
        $subtitle .= ' &bull; Client: ' . $client['company_name'];
    }

    $stInv = $pdo->prepare("SELECT * FROM invoices WHERE tenant_id = ? AND client_id = ? AND invoice_date BETWEEN ? AND ? ORDER BY invoice_date ASC");
    $stInv->execute([$tid, $clientId, $startDate, $endDate]);
    $invoices = $stInv->fetchAll();

    $contentHtml = '
    <table>
        <thead>
            <tr><th>Date</th><th>Type</th><th>Ref / Invoice #</th><th class="text-right">Total Amount</th><th class="text-right">Paid Amount</th><th class="text-right">Balance</th></tr>
        </thead>
        <tbody>';
    
    $totAmt = 0; $totPaid = 0; $totBal = 0;
    foreach ($invoices as $inv) {
        $bal = max(0, (float)$inv['total'] - (float)$inv['paid_amount']);
        $totAmt += (float)$inv['total'];
        $totPaid += (float)$inv['paid_amount'];
        $totBal += $bal;

        $contentHtml .= '
        <tr>
            <td>' . e(date('d M Y', strtotime($inv['invoice_date']))) . '</td>
            <td>Invoice</td>
            <td class="font-bold">' . e($inv['invoice_number']) . '</td>
            <td class="text-right">' . money((float)$inv['total'], $inv['currency'] ?: $tenant['currency']) . '</td>
            <td class="text-right text-emerald-700">' . money((float)$inv['paid_amount'], $inv['currency'] ?: $tenant['currency']) . '</td>
            <td class="text-right font-bold">' . money($bal, $inv['currency'] ?: $tenant['currency']) . '</td>
        </tr>';
    }

    $contentHtml .= '
        <tr class="bg-total">
            <td colspan="3" class="font-black">TOTAL STATEMENT SUMMARY</td>
            <td class="text-right font-black">' . money($totAmt, $tenant['currency']) . '</td>
            <td class="text-right font-black text-emerald-700">' . money($totPaid, $tenant['currency']) . '</td>
            <td class="text-right font-black">' . money($totBal, $tenant['currency']) . '</td>
        </tr>
        </tbody>
    </table>';
}

$headerHtml = PdfReportService::renderHeader($reportTitle, $subtitle, $filters);
$footerHtml = PdfReportService::renderFooter();
$document = PdfReportService::wrapDocument($reportTitle, $headerHtml, $contentHtml, $footerHtml);

echo $document;
