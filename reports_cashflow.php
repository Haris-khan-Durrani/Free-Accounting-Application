<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-12-31');

// Cash Inflows from Customers (Paid Invoices)
$stIn = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE tenant_id = ? AND status = 'paid' AND invoice_date BETWEEN ? AND ?");
$stIn->execute([$tid, $startDate, $endDate]);
$customerCashInflows = (float)$stIn->fetchColumn();

// Cash Outflows for Expenses Paid
$stOut = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
$stOut->execute([$tid, $startDate, $endDate]);
$expenseCashOutflows = (float)$stOut->fetchColumn();

$netOperatingCash = $customerCashInflows - $expenseCashOutflows;

page_start('Cash Flow Statement');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Statement of Cash Flows</h1>
        <p class="mt-1 text-sm text-slate-500">Overview of cash inflows and outflows for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-print mr-2"></i>Print Cash Flow
    </button>
</div>

<!-- Date Filter -->
<div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-8">
    <form method="get" class="flex flex-wrap items-center gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Start Date</label>
            <input type="date" name="start_date" value="<?=e($startDate)?>" class="rounded-xl border-slate-300 text-sm py-1.5 px-3">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">End Date</label>
            <input type="date" name="end_date" value="<?=e($endDate)?>" class="rounded-xl border-slate-300 text-sm py-1.5 px-3">
        </div>
        <div class="pt-5">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800">Filter Cash Flow</button>
        </div>
    </form>
</div>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operating Cash Inflows</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($customerCashInflows)?></div>
        <span class="text-xs text-slate-500">Customer Payments Received</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operating Cash Outflows</span>
        <div class="text-2xl font-extrabold text-rose-600 mt-1"><?=money($expenseCashOutflows)?></div>
        <span class="text-xs text-slate-500">Vendor Expenses Paid</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Net Operating Cash</span>
        <div class="text-2xl font-extrabold <?= $netOperatingCash >= 0 ? 'text-purple-600' : 'text-rose-600' ?> mt-1"><?=money($netOperatingCash)?></div>
        <span class="text-xs text-slate-500"><?= $netOperatingCash >= 0 ? 'Positive Cash Surplus' : 'Net Cash Deficit' ?></span>
    </div>
</div>

<!-- Cash Flow Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Cash Flow Statement Breakdown</h2>
        <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700"><?=e(date('d M Y', strtotime($startDate)))?> – <?=e(date('d M Y', strtotime($endDate)))?></span>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Cash Flow Activity</th>
                <th class="px-6 py-3.5 text-right">Amount (<?=e(tenant()['currency'])?>)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <tr class="bg-emerald-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-emerald-800 uppercase tracking-wider">1. CASH FLOWS FROM OPERATING ACTIVITIES</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Cash Receipts from Customers (Paid Invoices)</td><td class="px-6 py-3 text-right font-bold text-emerald-600"><?=money($customerCashInflows)?></td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Cash Payments for Operating Expenses</td><td class="px-6 py-3 text-right font-bold text-rose-600">(<?=money($expenseCashOutflows)?>)</td></tr>
            <tr class="bg-emerald-100/50 font-bold text-emerald-900"><td class="px-6 py-3.5">NET CASH FROM OPERATING ACTIVITIES</td><td class="px-6 py-3.5 text-right text-base"><?=money($netOperatingCash)?></td></tr>

            <tr class="bg-blue-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-blue-800 uppercase tracking-wider">2. CASH FLOWS FROM INVESTING ACTIVITIES</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Capital Assets & Investments</td><td class="px-6 py-3 text-right font-bold text-slate-500"><?=money(0.00)?></td></tr>

            <tr class="bg-purple-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-purple-800 uppercase tracking-wider">3. CASH FLOWS FROM FINANCING ACTIVITIES</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Owner Contributions & Dividends</td><td class="px-6 py-3 text-right font-bold text-slate-500"><?=money(0.00)?></td></tr>

            <tr class="bg-slate-900 text-white font-extrabold text-base"><td class="px-6 py-4">NET INCREASE / (DECREASE) IN CASH & CASH EQUIVALENTS</td><td class="px-6 py-4 text-right <?= $netOperatingCash >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>"><?=money($netOperatingCash)?></td></tr>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
