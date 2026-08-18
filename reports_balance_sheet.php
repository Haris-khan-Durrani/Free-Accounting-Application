<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Parameters
$preset = $_GET['preset'] ?? 'custom';
$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');
$method = in_array($_GET['method'] ?? '', ['accrual', 'cash'], true) ? $_GET['method'] : 'accrual';
$compare = in_array($_GET['compare'] ?? '', ['none', 'prev_month', 'prev_year'], true) ? $_GET['compare'] : 'none';

// Handle Quick Presets
if ($preset !== 'custom') {
    switch ($preset) {
        case 'today':
            $asOfDate = date('Y-m-d');
            break;
        case 'this_month':
            $asOfDate = date('Y-m-t');
            break;
        case 'last_month':
            $asOfDate = date('Y-m-t', strtotime('last day of previous month'));
            break;
        case 'this_quarter':
            $m = (int)date('m');
            if ($m <= 3) $asOfDate = date('Y-03-31');
            elseif ($m <= 6) $asOfDate = date('Y-06-30');
            elseif ($m <= 9) $asOfDate = date('Y-09-30');
            else $asOfDate = date('Y-12-31');
            break;
        case 'this_year':
            $asOfDate = date('Y-12-31');
            break;
        case 'last_year':
            $asOfDate = date((date('Y') - 1) . '-12-31');
            break;
    }
}

// Calculation Function
function calculateBalanceSheetData($pdo, $tid, $asOfDate, $method = 'accrual') {
    // 1. CASH COLLECTED & EXPENSES PAID
    $stPay = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND payment_date <= ?");
    $stPay->execute([$tid, $asOfDate]);
    $totalCashCollected = (float)$stPay->fetchColumn();

    $stExp = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date <= ?");
    $stExp->execute([$tid, $asOfDate]);
    $totalExpensesPaid = (float)$stExp->fetchColumn();

    $cashBalance = max(0, $totalCashCollected - $totalExpensesPaid);

    // 2. ACCOUNTS RECEIVABLE (A/R)
    $accountsReceivable = 0.0;
    if ($method === 'accrual') {
        $stAr = $pdo->prepare("SELECT COALESCE(SUM(total - paid_amount), 0) FROM invoices WHERE tenant_id = ? AND status IN ('draft', 'sent', 'overdue', 'partially_paid') AND invoice_date <= ?");
        $stAr->execute([$tid, $asOfDate]);
        $accountsReceivable = max(0, (float)$stAr->fetchColumn());
    }

    $totalAssets = $cashBalance + $accountsReceivable;

    // 3. LIABILITIES (VAT PAYABLE)
    if ($method === 'accrual') {
        $stOutVat = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date <= ?");
        $stOutVat->execute([$tid, $asOfDate]);
        $outputVat = (float)$stOutVat->fetchColumn();
    } else {
        $stOutVat = $pdo->prepare("SELECT COALESCE(SUM(p.amount * (i.tax_amount / NULLIF(i.total, 0))), 0) FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND p.payment_date <= ?");
        $stOutVat->execute([$tid, $asOfDate]);
        $outputVat = (float)$stOutVat->fetchColumn();
    }

    $stInVat = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM expenses WHERE tenant_id = ? AND expense_date <= ?");
    $stInVat->execute([$tid, $asOfDate]);
    $inputVat = (float)$stInVat->fetchColumn();

    $netVatPayable = max(0, $outputVat - $inputVat);
    $totalLiabilities = $netVatPayable;

    // 4. RETAINED EARNINGS & EQUITY
    $retainedEarnings = $totalAssets - $totalLiabilities;
    $totalEquity = $retainedEarnings;

    return [
        'cash_balance' => $cashBalance,
        'accounts_receivable' => $accountsReceivable,
        'total_assets' => $totalAssets,
        'net_vat_payable' => $netVatPayable,
        'total_liabilities' => $totalLiabilities,
        'retained_earnings' => $retainedEarnings,
        'total_equity' => $totalEquity
    ];
}

// Current Period Balance Sheet
$current = calculateBalanceSheetData($pdo, $tid, $asOfDate, $method);

// Comparative Period Balance Sheet if requested
$compData = null;
$compDate = null;
if ($compare === 'prev_month') {
    $compDate = date('Y-m-d', strtotime($asOfDate . ' -1 month'));
    $compData = calculateBalanceSheetData($pdo, $tid, $compDate, $method);
} elseif ($compare === 'prev_year') {
    $compDate = date('Y-m-d', strtotime($asOfDate . ' -1 year'));
    $compData = calculateBalanceSheetData($pdo, $tid, $compDate, $method);
}

page_start('Balance Sheet Statement');
?>

<!-- Executive Header & Actions -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80">
    <div>
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-600 text-3xs font-black uppercase tracking-widest mb-2">
            <i class="fa-solid fa-scale-balanced"></i>
            <span>Financial Position Report</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Statement of Financial Position (Balance Sheet)</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-3xl">
            Snapshot of corporate assets, liabilities, and retained equity as of <strong><?=e(date('d M Y', strtotime($asOfDate)))?></strong> using <strong><?=strtoupper($method)?> Basis</strong> accounting for <strong><?=e(tenant()['name'])?></strong>.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3 shrink-0 no-print">
        <a href="export_report?type=balance_sheet&as_of_date=<?=urlencode($asOfDate)?>&method=<?=$method?>&compare=<?=$compare?>" class="inline-flex items-center px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-extrabold text-xs rounded-xl shadow-xs transition-all space-x-2">
            <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
            <span>Export CSV</span>
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-2 border border-slate-800">
            <i class="fa-solid fa-print text-amber-400 text-sm"></i>
            <span>Print Balance Sheet</span>
        </button>
    </div>
</div>

<!-- Interactive Financial Filter Toolbar -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm mb-8 no-print">
    <form method="get" id="balanceSheetFilterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        
        <!-- Preset Dropdown -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Quick Date Preset</label>
            <select name="preset" onchange="if(this.value!=='custom') document.getElementById('balanceSheetFilterForm').submit();" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="custom" <?=$preset==='custom'?'selected':''?>>Custom Date Target</option>
                <option value="today" <?=$preset==='today'?'selected':''?>>Today (<?=date('d M Y')?>)</option>
                <option value="this_month" <?=$preset==='this_month'?'selected':''?>>End of This Month (<?=date('t M Y')?>)</option>
                <option value="last_month" <?=$preset==='last_month'?'selected':''?>>End of Last Month</option>
                <option value="this_quarter" <?=$preset==='this_quarter'?'selected':''?>>End of Current Quarter</option>
                <option value="this_year" <?=$preset==='this_year'?'selected':''?>>End of Financial Year (31 Dec)</option>
                <option value="last_year" <?=$preset==='last_year'?'selected':''?>>End of Previous Year</option>
            </select>
        </div>

        <!-- Date Picker -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Statement Target Date</label>
            <input type="date" name="as_of_date" value="<?=e($asOfDate)?>" onchange="document.querySelector('select[name=preset]').value='custom';" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
        </div>

        <!-- Accounting Method -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Accounting Basis</label>
            <select name="method" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="accrual" <?=$method==='accrual'?'selected':''?>>Accrual Basis (Includes A/R & Unpaid Taxes)</option>
                <option value="cash" <?=$method==='cash'?'selected':''?>>Cash Basis (Actual Cash Settled Only)</option>
            </select>
        </div>

        <!-- Period Comparison & Submit -->
        <div class="flex items-center space-x-2">
            <div class="flex-grow">
                <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Period Comparison</label>
                <select name="compare" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="none" <?=$compare==='none'?'selected':''?>>No Comparison</option>
                    <option value="prev_month" <?=$compare==='prev_month'?'selected':''?>>Compare with Previous Month</option>
                    <option value="prev_year" <?=$compare==='prev_year'?'selected':''?>>Compare with Previous Year</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-xs transition-all shrink-0">
                <i class="fa-solid fa-arrows-rotate mr-1"></i>Apply
            </button>
        </div>
    </form>
</div>

<!-- Key KPI Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Total Assets</span>
            <div class="text-2xl font-black text-emerald-600 tracking-tight"><?=money($current['total_assets'])?></div>
            <span class="text-xs text-slate-500 font-medium"><?=$method === 'accrual' ? 'Cash & Accounts Receivable' : 'Realized Cash Collected'?></span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-wallet"></i>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Total Liabilities</span>
            <div class="text-2xl font-black text-rose-600 tracking-tight"><?=money($current['total_liabilities'])?></div>
            <span class="text-xs text-slate-500 font-medium">Net VAT Obligations</span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-building-columns"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Owner's Equity</span>
            <div class="text-2xl font-black text-blue-600 tracking-tight"><?=money($current['total_equity'])?></div>
            <span class="text-xs text-slate-500 font-medium">Retained Earnings / Surplus</span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
    </div>
</div>

<!-- Balance Sheet Ledger Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Account Heading</th>
                <th class="px-6 py-3.5 text-right">As of <?=e(date('d M Y', strtotime($asOfDate)))?> (<?=e(tenant()['currency'])?>)</th>
                <?php if ($compData): ?>
                    <th class="px-6 py-3.5 text-right bg-slate-100/60">As of <?=e(date('d M Y', strtotime($compDate)))?></th>
                    <th class="px-6 py-3.5 text-right">Variance ($)</th>
                    <th class="px-6 py-3.5 text-right">% Change</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <?php
            function renderRow($title, $currVal, $compVal = null, $isHeader = false, $isTotal = false, $colorClass = 'slate') {
                $hasComp = ($compVal !== null);
                $diff = $hasComp ? ($currVal - $compVal) : 0;
                $pct = ($hasComp && $compVal != 0) ? (($diff / abs($compVal)) * 100) : 0;

                $rowBg = '';
                if ($isHeader) {
                    $rowBg = "bg-{$colorClass}-50/70 font-extrabold text-xs text-{$colorClass}-800 uppercase tracking-wider";
                } elseif ($isTotal) {
                    $rowBg = "bg-{$colorClass}-100/60 font-black text-{$colorClass}-900";
                }

                echo "<tr class='{$rowBg}'>";
                if ($isHeader) {
                    $colspan = $hasComp ? 5 : 2;
                    echo "<td colspan='{$colspan}' class='px-6 py-2.5'>{$title}</td>";
                } else {
                    $padClass = $isTotal ? "px-6 py-3.5" : "px-8 py-3 text-slate-800 font-semibold";
                    echo "<td class='{$padClass}'>{$title}</td>";
                    echo "<td class='px-6 py-3 text-right font-mono font-bold'>" . money($currVal) . "</td>";

                    if ($hasComp) {
                        echo "<td class='px-6 py-3 text-right font-mono text-slate-600 bg-slate-50/50'>" . money($compVal) . "</td>";
                        
                        $diffClass = $diff >= 0 ? 'text-emerald-700 font-bold' : 'text-rose-700 font-bold';
                        $diffSign = $diff > 0 ? '+' : '';
                        echo "<td class='px-6 py-3 text-right font-mono {$diffClass}'>{$diffSign}" . money($diff) . "</td>";

                        $pctSign = $pct > 0 ? '+' : '';
                        echo "<td class='px-6 py-3 text-right font-mono {$diffClass}'>{$pctSign}" . number_format($pct, 1) . "%</td>";
                    }
                }
                echo "</tr>";
            }
            ?>

            <!-- 1. ASSETS -->
            <?php renderRow('1. CURRENT ASSETS', 0, null, true, false, 'emerald'); ?>
            <?php renderRow('Cash & Bank Accounts (Net Collected - Expenses)', $current['cash_balance'], $compData ? $compData['cash_balance'] : null); ?>
            <?php if ($method === 'accrual'): ?>
                <?php renderRow('Accounts Receivable (A/R Outstanding Invoices)', $current['accounts_receivable'], $compData ? $compData['accounts_receivable'] : null); ?>
            <?php endif; ?>
            <?php renderRow('TOTAL CURRENT ASSETS', $current['total_assets'], $compData ? $compData['total_assets'] : null, false, true, 'emerald'); ?>

            <!-- 2. LIABILITIES -->
            <?php renderRow('2. CURRENT LIABILITIES', 0, null, true, false, 'rose'); ?>
            <?php renderRow('Net Output VAT Payable (Output VAT - Input VAT)', $current['net_vat_payable'], $compData ? $compData['net_vat_payable'] : null); ?>
            <?php renderRow('TOTAL CURRENT LIABILITIES', $current['total_liabilities'], $compData ? $compData['total_liabilities'] : null, false, true, 'rose'); ?>

            <!-- 3. EQUITY -->
            <?php renderRow('3. EQUITY', 0, null, true, false, 'blue'); ?>
            <?php renderRow('Retained Earnings / Accumulated Surplus', $current['retained_earnings'], $compData ? $compData['retained_earnings'] : null); ?>
            <?php renderRow('TOTAL EQUITY', $current['total_equity'], $compData ? $compData['total_equity'] : null, false, true, 'blue'); ?>

            <!-- GRAND TOTAL -->
            <tr class="bg-slate-900 text-white font-black text-base">
                <td class="px-6 py-4">TOTAL LIABILITIES & EQUITY</td>
                <td class="px-6 py-4 text-right text-emerald-400 font-mono"><?=money($current['total_liabilities'] + $current['total_equity'])?></td>
                <?php if ($compData): ?>
                    <td class="px-6 py-4 text-right text-slate-300 font-mono"><?=money($compData['total_liabilities'] + $compData['total_equity'])?></td>
                    <?php 
                        $grandDiff = ($current['total_liabilities'] + $current['total_equity']) - ($compData['total_liabilities'] + $compData['total_equity']);
                    ?>
                    <td class="px-6 py-4 text-right font-mono text-emerald-300" colspan="2">
                        Variance: <?=$grandDiff >= 0 ? '+' : ''?><?=money($grandDiff)?>
                    </td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
