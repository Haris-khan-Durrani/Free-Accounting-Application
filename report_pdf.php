<?php
require __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['client_id'])) {
    $tid = (int)$_SESSION['client_tenant_id'];
    $clientId = (int)$_SESSION['client_id'];
} else {
    require_login();
    $tid = tenant_id();
}

$brand = branding($tid);
$tenant = tenant($tid);

$type = $_GET['type'] ?? 'pnl';
$preset = $_GET['preset'] ?? 'custom';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$asOfDate  = $_GET['as_of_date'] ?? date('Y-m-d');
$method    = in_array($_GET['method'] ?? '', ['accrual', 'cash'], true) ? $_GET['method'] : 'accrual';
if (empty($_SESSION['client_id'])) {
    $clientId  = (int)($_GET['client_id'] ?? 0);
}
$categoryId = (int)($_GET['category_id'] ?? 0);

$reportTitle = 'Financial Report';
$subtitle = '';
$filters = [
    'period' => date('d M Y', strtotime($startDate)) . ' to ' . date('d M Y', strtotime($endDate)),
    'method' => strtoupper($method) . ' Basis'
];

if ($clientId > 0) {
    $stC = $pdo->prepare("SELECT company_name FROM clients WHERE id = ? AND tenant_id = ?");
    $stC->execute([$clientId, $tid]);
    $clientRow = $stC->fetch();
    if ($clientRow) {
        $filters['client'] = $clientRow['company_name'];
    }
}

if ($categoryId > 0) {
    $stCat = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ?");
    $stCat->execute([$categoryId, $tid]);
    $catRow = $stCat->fetch();
    if ($catRow) {
        $filters['category'] = $catRow['name'];
    }
}

$contentHtml = '';

if ($type === 'vat201') {
    $reportTitle = 'UAE FTA VAT 201 Declaration Return';
    $subtitle = 'Official Tax Return Schedule (' . date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate)) . ')';

    // 7 Emirates Sales Query
    $emiratesList = [
        'Abu Dhabi' => 'Box 1a',
        'Dubai' => 'Box 1b',
        'Sharjah' => 'Box 1c',
        'Ajman' => 'Box 1d',
        'Umm Al Quwain' => 'Box 1e',
        'Ras Al Khaimah' => 'Box 1f',
        'Fujairah' => 'Box 1g'
    ];

    $emirateSales = [];
    $totalSubtotal = 0;
    $totalSalesVat = 0;

    foreach ($emiratesList as $em => $box) {
        if ($method === 'cash') {
            $sqlEm = "SELECT 
                        COALESCE(SUM(p.amount * (i.subtotal / NULLIF(i.total, 0))), 0) as subtotal,
                        COALESCE(SUM(p.amount * (i.tax_amount / NULLIF(i.total, 0))), 0) as tax_amount
                    FROM payments p
                    JOIN invoices i ON i.id = p.invoice_id
                    JOIN clients c ON c.id = i.client_id
                    WHERE p.tenant_id = ? AND (c.city LIKE ? OR c.address LIKE ?) AND p.payment_date BETWEEN ? AND ?";
            $paramsEm = [$tid, "%$em%", "%$em%", $startDate, $endDate];
            if ($clientId > 0) {
                $sqlEm .= " AND i.client_id = ?";
                $paramsEm[] = $clientId;
            }
        } else {
            $sqlEm = "SELECT 
                        COALESCE(SUM(i.subtotal), 0) as subtotal,
                        COALESCE(SUM(i.tax_amount), 0) as tax_amount
                    FROM invoices i
                    JOIN clients c ON c.id = i.client_id
                    WHERE i.tenant_id = ? AND i.status != 'cancelled' AND (c.city LIKE ? OR c.address LIKE ?) AND i.invoice_date BETWEEN ? AND ?";
            $paramsEm = [$tid, "%$em%", "%$em%", $startDate, $endDate];
            if ($clientId > 0) {
                $sqlEm .= " AND i.client_id = ?";
                $paramsEm[] = $clientId;
            }
        }
        $stEm = $pdo->prepare($sqlEm);
        $stEm->execute($paramsEm);
        $r = $stEm->fetch();
        $sub = (float)$r['subtotal'];
        $vat = (float)$r['tax_amount'];
        $emirateSales[$em] = ['box' => $box, 'subtotal' => $sub, 'tax' => $vat];
        $totalSubtotal += $sub;
        $totalSalesVat += $vat;
    }

    // Recoverable Expense VAT (Box 9)
    $sqlExp = "SELECT COALESCE(SUM(subtotal), 0) subtotal, COALESCE(SUM(tax_amount), 0) tax FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?";
    $paramsExp = [$tid, $startDate, $endDate];
    if ($clientId > 0) {
        $sqlExp .= " AND client_id = ?";
        $paramsExp[] = $clientId;
    }
    if ($categoryId > 0) {
        $sqlExp .= " AND category_id = ?";
        $paramsExp[] = $categoryId;
    }
    $stExp = $pdo->prepare($sqlExp);
    $stExp->execute($paramsExp);
    $expRow = $stExp->fetch();
    $expSubtotal = (float)$expRow['subtotal'];
    $expVat = (float)$expRow['tax'];

    $netVatPayable = $totalSalesVat - $expVat;

    // KPI Summary Header Cards
    $contentHtml .= '
    <div style="display: flex; gap: 12px; margin-bottom: 20px;">
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #0284c7;">
            <div class="kpi-title">Total Supplies (Excl. VAT)</div>
            <div class="kpi-value">' . money($totalSubtotal, $tenant['currency']) . '</div>
        </div>
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #166534;">
            <div class="kpi-title">Output VAT (5% Sales)</div>
            <div class="kpi-value" style="color: #166534;">' . money($totalSalesVat, $tenant['currency']) . '</div>
        </div>
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #9f1239;">
            <div class="kpi-title">Input VAT (5% Expenses)</div>
            <div class="kpi-value" style="color: #9f1239;">(' . money($expVat, $tenant['currency']) . ')</div>
        </div>
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #d97706; background: #fffbeb;">
            <div class="kpi-title">Net Payable / Reclaimable</div>
            <div class="kpi-value" style="color: ' . ($netVatPayable >= 0 ? '#b45309' : '#166534') . ';">' . money($netVatPayable, $tenant['currency']) . '</div>
        </div>
    </div>';

    // 7 Emirates Breakdown Table
    $contentHtml .= '
    <div style="margin-bottom: 12px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #334155;">Section 1: VAT on Sales / Outputs (7 Emirates Breakdown)</div>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">FTA Box #</th>
                <th>Emirate Description</th>
                <th class="text-right" style="width: 30%;">Amount (AED)</th>
                <th class="text-right" style="width: 30%;">VAT Amount (5%)</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($emirateSales as $emName => $emData) {
        $contentHtml .= '
        <tr>
            <td class="font-bold text-center">' . e($emData['box']) . '</td>
            <td class="font-bold">Standard Rated Sales - ' . e($emName) . '</td>
            <td class="text-right">' . money($emData['subtotal'], $tenant['currency']) . '</td>
            <td class="text-right font-bold text-emerald-700">' . money($emData['tax'], $tenant['currency']) . '</td>
        </tr>';
    }

    $contentHtml .= '
        <tr class="bg-total">
            <td colspan="2" class="font-black">TOTAL OUTPUT VAT (Standard Rated Supplies)</td>
            <td class="text-right font-black">' . money($totalSubtotal, $tenant['currency']) . '</td>
            <td class="text-right font-black text-emerald-700">' . money($totalSalesVat, $tenant['currency']) . '</td>
        </tr>
        </tbody>
    </table>';

    // Expenses Input VAT Section
    $contentHtml .= '
    <div style="margin-top: 20px; margin-bottom: 12px; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #334155;">Section 2: VAT on Expenses / Inputs</div>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">FTA Box #</th>
                <th>Description</th>
                <th class="text-right" style="width: 30%;">Amount (AED)</th>
                <th class="text-right" style="width: 30%;">Recoverable VAT (5%)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="font-bold text-center">Box 9</td>
                <td class="font-bold">Standard Rated Expenses (Input VAT Recoverable)</td>
                <td class="text-right">' . money($expSubtotal, $tenant['currency']) . '</td>
                <td class="text-right font-bold text-rose-700">(' . money($expVat, $tenant['currency']) . ')</td>
            </tr>
            <tr class="bg-total">
                <td colspan="2" class="font-black">NET VAT DUE TO FEDERAL TAX AUTHORITY (BOX 1 - BOX 9)</td>
                <td colspan="2" class="text-right font-black" style="font-size: 13px; color: ' . ($netVatPayable >= 0 ? '#9f1239' : '#166534') . ';">' . money($netVatPayable, $tenant['currency']) . '</td>
            </tr>
        </tbody>
    </table>';

} elseif ($type === 'pnl') {
    $reportTitle = 'Profit & Loss Statement (P&L)';
    $subtitle = 'Income Statement Period: ' . date('d M Y', strtotime($startDate)) . ' to ' . date('d M Y', strtotime($endDate));

    // Revenue
    $sqlRev = "SELECT SUM(total) total FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?";
    $paramsRev = [$tid, $startDate, $endDate];
    if ($clientId > 0) {
        $sqlRev .= " AND client_id = ?";
        $paramsRev[] = $clientId;
    }
    $stRev = $pdo->prepare($sqlRev);
    $stRev->execute($paramsRev);
    $grossRev = (float)($stRev->fetchColumn() ?? 0);

    // Cash Collected
    $sqlPay = "SELECT SUM(p.amount) total FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND p.payment_date BETWEEN ? AND ?";
    $paramsPay = [$tid, $startDate, $endDate];
    if ($clientId > 0) {
        $sqlPay .= " AND i.client_id = ?";
        $paramsPay[] = $clientId;
    }
    $stPay = $pdo->prepare($sqlPay);
    $stPay->execute($paramsPay);
    $cashCollected = (float)($stPay->fetchColumn() ?? 0);

    // Expenses
    $sqlExp = "SELECT SUM(total) total FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?";
    $paramsExp = [$tid, $startDate, $endDate];
    if ($clientId > 0) {
        $sqlExp .= " AND client_id = ?";
        $paramsExp[] = $clientId;
    }
    if ($categoryId > 0) {
        $sqlExp .= " AND category_id = ?";
        $paramsExp[] = $categoryId;
    }
    $stExp = $pdo->prepare($sqlExp);
    $stExp->execute($paramsExp);
    $totalExp = (float)($stExp->fetchColumn() ?? 0);

    $netProfit = $grossRev - $totalExp;

    $contentHtml .= '
    <div style="display: flex; gap: 12px; margin-bottom: 20px;">
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #2563eb;">
            <div class="kpi-title">Gross Revenue</div>
            <div class="kpi-value" style="color: #2563eb;">' . money($grossRev, $tenant['currency']) . '</div>
        </div>
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #166534;">
            <div class="kpi-title">Cash Collected</div>
            <div class="kpi-value" style="color: #166534;">' . money($cashCollected, $tenant['currency']) . '</div>
        </div>
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #f43f5e;">
            <div class="kpi-title">Operating Expenses</div>
            <div class="kpi-value" style="color: #f43f5e;">(' . money($totalExp, $tenant['currency']) . ')</div>
        </div>
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #d97706; background: #fffbeb;">
            <div class="kpi-title">Net Profit / Surplus</div>
            <div class="kpi-value" style="color: ' . ($netProfit >= 0 ? '#166534' : '#9f1239') . ';">' . money($netProfit, $tenant['currency']) . '</div>
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Financial Item</th><th class="text-right">Amount (' . e($tenant['currency']) . ')</th></tr>
        </thead>
        <tbody>
            <tr><td class="font-bold">Gross Invoiced Revenue (Accrual Sales)</td><td class="text-right font-bold text-blue-600">' . money($grossRev, $tenant['currency']) . '</td></tr>
            <tr><td>Cash Collected (Settled Invoice Receipts)</td><td class="text-right text-emerald-600">' . money($cashCollected, $tenant['currency']) . '</td></tr>
            <tr><td class="font-bold">Operating Expenses (Bills & Vendor Payments)</td><td class="text-right font-bold text-rose-600">(' . money($totalExp, $tenant['currency']) . ')</td></tr>
            <tr class="bg-total"><td class="font-black">NET OPERATING PROFIT / SURPLUS</td><td class="text-right font-black" style="font-size: 13px; color:' . ($netProfit >= 0 ? '#166534' : '#9f1239') . ';">' . money($netProfit, $tenant['currency']) . '</td></tr>
        </tbody>
    </table>';

} elseif ($type === 'balance_sheet') {
    $reportTitle = 'Balance Sheet Statement';
    $subtitle = 'Financial Position As Of ' . date('d M Y', strtotime($asOfDate));
    $filters['as_of_date'] = $asOfDate;

    // Cash & Bank
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

    $contentHtml .= '
    <div style="display: flex; gap: 12px; margin-bottom: 20px;">
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #0284c7;">
            <div class="kpi-title">Total Assets</div>
            <div class="kpi-value">' . money($totalAssets, $tenant['currency']) . '</div>
        </div>
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #f43f5e;">
            <div class="kpi-title">Total Liabilities</div>
            <div class="kpi-value" style="color: #f43f5e;">' . money($totalLiabilities, $tenant['currency']) . '</div>
        </div>
        <div class="kpi-card" style="flex: 1; border-left: 4px solid #166534; background: #f0fdf4;">
            <div class="kpi-title">Total Equity</div>
            <div class="kpi-value" style="color: #166534;">' . money($equity, $tenant['currency']) . '</div>
        </div>
    </div>

    <table>
        <thead><tr><th colspan="2">1. ASSETS</th></tr></thead>
        <tbody>
            <tr><td>Cash & Bank Accounts (Net Collected - Expenses)</td><td class="text-right font-bold">' . money($cashBalance, $tenant['currency']) . '</td></tr>
            <tr><td>Accounts Receivable (A/R Uncollected Invoices)</td><td class="text-right font-bold">' . money($accountsReceivable, $tenant['currency']) . '</td></tr>
            <tr class="bg-total"><td class="font-black">TOTAL ASSETS</td><td class="text-right font-black">' . money($totalAssets, $tenant['currency']) . '</td></tr>
        </tbody>
    </table>

    <table style="margin-top: 20px;">
        <thead><tr><th colspan="2">2. LIABILITIES & EQUITY</th></tr></thead>
        <tbody>
            <tr><td>Net Output VAT Payable to FTA</td><td class="text-right font-bold text-rose-600">' . money($netVatPayable, $tenant['currency']) . '</td></tr>
            <tr class="bg-total"><td class="font-bold">TOTAL LIABILITIES</td><td class="text-right font-bold text-rose-600">' . money($totalLiabilities, $tenant['currency']) . '</td></tr>
            <tr><td>Retained Earnings / Equity Surplus</td><td class="text-right font-bold text-emerald-600">' . money($equity, $tenant['currency']) . '</td></tr>
            <tr class="bg-total"><td class="font-black">TOTAL LIABILITIES & EQUITY</td><td class="text-right font-black">' . money($totalLiabilities + $equity, $tenant['currency']) . '</td></tr>
        </tbody>
    </table>';

} elseif ($type === 'aging') {
    $reportTitle = 'Accounts Receivable (A/R) Aging Report';
    $subtitle = 'Outstanding Balances As Of ' . date('d M Y', strtotime($asOfDate));
    $filters['as_of_date'] = $asOfDate;

    $sql = "SELECT i.*, c.company_name, DATEDIFF(?, i.valid_until) days_overdue 
            FROM invoices i 
            JOIN clients c ON c.id = i.client_id 
            WHERE i.tenant_id = ? AND i.status IN ('draft', 'sent', 'overdue', 'partially_paid')";
    $params = [$asOfDate, $tid];

    if ($clientId > 0) {
        $sql .= " AND i.client_id = ?";
        $params[] = $clientId;
    }
    $sql .= " ORDER BY days_overdue DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $contentHtml .= '
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
            <td><span class="badge ' . $badgeClass . '">' . $days . ' days</span></td>
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

} elseif ($type === 'client_statement') {
    $reportTitle = 'Statement of Account';
    $subtitle = 'Period: ' . date('d M Y', strtotime($startDate)) . ' to ' . date('d M Y', strtotime($endDate));

    $stClient = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
    $stClient->execute([$clientId, $tid]);
    $client = $stClient->fetch();

    if ($client) {
        $subtitle .= ' &bull; Client: ' . $client['company_name'];
    }

    $stInv = $pdo->prepare("SELECT * FROM invoices WHERE tenant_id = ? AND client_id = ? AND invoice_date BETWEEN ? AND ? ORDER BY invoice_date ASC");
    $stInv->execute([$tid, $clientId, $startDate, $endDate]);
    $invoices = $stInv->fetchAll();

    $contentHtml .= '
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
