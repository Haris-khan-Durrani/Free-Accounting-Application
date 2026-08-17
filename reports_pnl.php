<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-12-31');

// 1. Operating Revenue
$stRev = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
$stRev->execute([$tid, $startDate, $endDate]);
$totalRevenue = (float)$stRev->fetchColumn();

// Breakdown Revenue by Client
$stRevClients = $pdo->prepare("SELECT c.company_name, COUNT(i.id) invoice_count, SUM(i.total) revenue_total FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? AND i.status != 'cancelled' AND i.invoice_date BETWEEN ? AND ? GROUP BY c.id ORDER BY revenue_total DESC");
$stRevClients->execute([$tid, $startDate, $endDate]);
$revenueBreakdown = $stRevClients->fetchAll();

// 2. Operating Expenses
$stExp = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
$stExp->execute([$tid, $startDate, $endDate]);
$totalExpenses = (float)$stExp->fetchColumn();

// Breakdown Expenses by Category
$stExpCats = $pdo->prepare("SELECT COALESCE(ec.name, 'Uncategorized') cat_name, COUNT(e.id) exp_count, SUM(e.total) exp_total FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id WHERE e.tenant_id = ? AND e.expense_date BETWEEN ? AND ? GROUP BY ec.id ORDER BY exp_total DESC");
$stExpCats->execute([$tid, $startDate, $endDate]);
$expenseBreakdown = $stExpCats->fetchAll();

// 3. Tax Collected
$stTax = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
$stTax->execute([$tid, $startDate, $endDate]);
$taxCollected = (float)$stTax->fetchColumn();

$netProfit = $totalRevenue - $totalExpenses;

page_start('Profit & Loss Statement');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Profit & Loss Statement (P&L)</h1>
        <p class="mt-1 text-sm text-slate-500">Income statement summary for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3">
        <a href="export_report?type=pnl&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?>" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-file-csv mr-2 text-emerald-600"></i>Export CSV
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print / Export PDF
        </button>
    </div>
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
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800">Filter Statement</button>
        </div>
    </form>
</div>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Gross Revenue</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($totalRevenue)?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Expenses</span>
        <div class="text-2xl font-extrabold text-rose-600 mt-1"><?=money($totalExpenses)?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Net Operating Profit</span>
        <div class="text-2xl font-extrabold <?= $netProfit >= 0 ? 'text-purple-600' : 'text-rose-600' ?> mt-1"><?=money($netProfit)?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tax / VAT Collected</span>
        <div class="text-2xl font-extrabold text-blue-600 mt-1"><?=money($taxCollected)?></div>
    </div>
</div>

<!-- Financial Statement Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Statement of Financial Performance</h2>
        <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700"><?=e(date('d M Y', strtotime($startDate)))?> – <?=e(date('d M Y', strtotime($endDate)))?></span>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Account / Client Category</th>
                <th class="px-6 py-3.5 text-right">Items Count</th>
                <th class="px-6 py-3.5 text-right">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <tr class="bg-slate-100/70"><td colspan="3" class="px-6 py-2.5 font-bold text-xs text-slate-700 uppercase tracking-wider">1. OPERATING REVENUE (INCOME)</td></tr>
            <?php foreach ($revenueBreakdown as $r): ?>
                <tr>
                    <td class="px-8 py-3 text-slate-800 font-semibold"><?=e($r['company_name'])?></td>
                    <td class="px-6 py-3 text-right text-slate-500"><?=e((string)$r['invoice_count'])?> Invoices</td>
                    <td class="px-6 py-3 text-right font-extrabold text-emerald-600"><?=money((float)$r['revenue_total'])?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="bg-emerald-50/50 font-bold"><td class="px-6 py-3.5">TOTAL OPERATING REVENUE</td><td></td><td class="px-6 py-3.5 text-right text-emerald-700 text-base"><?=money($totalRevenue)?></td></tr>

            <tr class="bg-slate-100/70"><td colspan="3" class="px-6 py-2.5 font-bold text-xs text-slate-700 uppercase tracking-wider">2. OPERATING EXPENSES</td></tr>
            <?php foreach ($expenseBreakdown as $ex): ?>
                <tr>
                    <td class="px-8 py-3 text-slate-800 font-semibold"><?=e($ex['cat_name'])?></td>
                    <td class="px-6 py-3 text-right text-slate-500"><?=e((string)$ex['exp_count'])?> Receipts</td>
                    <td class="px-6 py-3 text-right font-extrabold text-rose-600"><?=money((float)$ex['exp_total'])?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="bg-rose-50/50 font-bold"><td class="px-6 py-3.5">TOTAL OPERATING EXPENSES</td><td></td><td class="px-6 py-3.5 text-right text-rose-700 text-base">(<?=money($totalExpenses)?>)</td></tr>

            <tr class="bg-slate-900 text-white font-extrabold text-base"><td class="px-6 py-4">NET PROFIT BEFORE TAX</td><td></td><td class="px-6 py-4 text-right <?= $netProfit >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>"><?=money($netProfit)?></td></tr>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
