<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');

// 1. CASH & BANK BALANCE
// Cash Collected from Payments
$stPay = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND payment_date <= ?");
$stPay->execute([$tid, $asOfDate]);
$totalCashCollected = (float)$stPay->fetchColumn();

// Cash Spent on Expenses
$stExp = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date <= ?");
$stExp->execute([$tid, $asOfDate]);
$totalExpensesPaid = (float)$stExp->fetchColumn();

$cashBalance = max(0, $totalCashCollected - $totalExpensesPaid);

// 2. ACCOUNTS RECEIVABLE (A/R)
// Outstanding balance on invoices (Total minus Paid Amount)
$stAr = $pdo->prepare("SELECT COALESCE(SUM(total - paid_amount), 0) FROM invoices WHERE tenant_id = ? AND status IN ('draft', 'sent', 'overdue', 'partially_paid') AND invoice_date <= ?");
$stAr->execute([$tid, $asOfDate]);
$accountsReceivable = max(0, (float)$stAr->fetchColumn());

// TOTAL ASSETS
$totalAssets = $cashBalance + $accountsReceivable;

// 3. LIABILITIES (NET TAX / VAT OBLIGATIONS)
// Output VAT from sales
$stOutVat = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date <= ?");
$stOutVat->execute([$tid, $asOfDate]);
$outputVat = (float)$stOutVat->fetchColumn();

// Input VAT from expenses
$stInVat = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM expenses WHERE tenant_id = ? AND expense_date <= ?");
$stInVat->execute([$tid, $asOfDate]);
$inputVat = (float)$stInVat->fetchColumn();

$netVatPayable = max(0, $outputVat - $inputVat);
$totalLiabilities = $netVatPayable;

// 4. EQUITY (RETAINED EARNINGS / SURPLUS)
// Retained Earnings = Total Assets - Total Liabilities
$retainedEarnings = $totalAssets - $totalLiabilities;
$totalEquity = $retainedEarnings;

page_start('Balance Sheet Statement');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Statement of Financial Position (Balance Sheet)</h1>
        <p class="mt-1 text-sm text-slate-500">Assets, liabilities, and equity snapshot as of <strong><?=e(date('d M Y', strtotime($asOfDate)))?></strong> for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3 no-print">
        <a href="export_report?type=balance_sheet&as_of_date=<?=urlencode($asOfDate)?>" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-file-csv mr-2 text-emerald-600"></i>Export CSV
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print Balance Sheet
        </button>
    </div>
</div>

<!-- As-Of Date Filter -->
<div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-8 max-w-sm no-print">
    <form method="get" class="flex items-center space-x-3">
        <div class="flex-grow">
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Statement Date</label>
            <input type="date" name="as_of_date" value="<?=e($asOfDate)?>" class="w-full rounded-xl border-slate-300 text-sm py-1.5 px-3">
        </div>
        <div class="pt-5">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800">Update</button>
        </div>
    </form>
</div>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Assets</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($totalAssets)?></div>
        <span class="text-xs text-slate-500">Cash & Receivables</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Liabilities</span>
        <div class="text-2xl font-extrabold text-rose-600 mt-1"><?=money($totalLiabilities)?></div>
        <span class="text-xs text-slate-500">Net Tax & VAT Obligations</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Owner's Equity</span>
        <div class="text-2xl font-extrabold text-blue-600 mt-1"><?=money($totalEquity)?></div>
        <span class="text-xs text-slate-500">Retained Earnings / Surplus</span>
    </div>
</div>

<!-- Balance Sheet Statement Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Account Heading</th>
                <th class="px-6 py-3.5 text-right">Amount (<?=e(tenant()['currency'])?>)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <!-- ASSETS -->
            <tr class="bg-emerald-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-emerald-800 uppercase tracking-wider">1. CURRENT ASSETS</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Cash & Bank Accounts (Net Collected - Expenses)</td><td class="px-6 py-3 text-right font-bold text-slate-900"><?=money($cashBalance)?></td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Accounts Receivable (A/R Outstanding Invoices)</td><td class="px-6 py-3 text-right font-bold text-slate-900"><?=money($accountsReceivable)?></td></tr>
            <tr class="bg-emerald-100/50 font-bold text-emerald-900"><td class="px-6 py-3.5">TOTAL CURRENT ASSETS</td><td class="px-6 py-3.5 text-right text-base"><?=money($totalAssets)?></td></tr>

            <!-- LIABILITIES -->
            <tr class="bg-rose-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-rose-800 uppercase tracking-wider">2. CURRENT LIABILITIES</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Net Output VAT Payable (Output VAT - Input VAT)</td><td class="px-6 py-3 text-right font-bold text-slate-900"><?=money($netVatPayable)?></td></tr>
            <tr class="bg-rose-100/50 font-bold text-rose-900"><td class="px-6 py-3.5">TOTAL CURRENT LIABILITIES</td><td class="px-6 py-3.5 text-right text-base"><?=money($totalLiabilities)?></td></tr>

            <!-- EQUITY -->
            <tr class="bg-blue-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-blue-800 uppercase tracking-wider">3. EQUITY</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Retained Earnings / Accumulated Surplus</td><td class="px-6 py-3 text-right font-bold text-slate-900"><?=money($retainedEarnings)?></td></tr>
            <tr class="bg-blue-100/50 font-bold text-blue-900"><td class="px-6 py-3.5">TOTAL EQUITY</td><td class="px-6 py-3.5 text-right text-base"><?=money($totalEquity)?></td></tr>

            <tr class="bg-slate-900 text-white font-extrabold text-base"><td class="px-6 py-4">TOTAL LIABILITIES & EQUITY</td><td class="px-6 py-4 text-right text-emerald-400"><?=money($totalLiabilities + $totalEquity)?></td></tr>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
