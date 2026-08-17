<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

$st = $pdo->prepare("
    SELECT 
        COALESCE(c.name, 'Uncategorized / General') as category_name,
        COUNT(e.id) as expense_count,
        COALESCE(SUM(e.subtotal), 0) as subtotal,
        COALESCE(SUM(e.tax_amount), 0) as tax_amount,
        COALESCE(SUM(e.total), 0) as total
    FROM expenses e
    LEFT JOIN expense_categories c ON c.id = e.category_id
    WHERE e.tenant_id = ? AND e.expense_date BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY total DESC
");
$st->execute([$tid, $startDate, $endDate]);
$categories = $st->fetchAll();

$totalExpensesAll = array_sum(array_column($categories, 'total'));
$totalVatAll = array_sum(array_column($categories, 'tax_amount'));

page_start('Expenses by Category Report');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Expenses Breakdown by Category</h1>
        <p class="mt-1 text-sm text-slate-500">Operational spending breakdown for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3">
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print Report
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
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800">Filter Expenses</button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Business Expenses</span>
        <div class="text-2xl font-extrabold text-rose-600 mt-1"><?=money($totalExpensesAll)?></div>
        <span class="text-xs text-slate-500">Gross Operating Outflows</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Input VAT Recoverable</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($totalVatAll)?></div>
        <span class="text-xs text-slate-500">5% Input VAT Claimable</span>
    </div>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Expense Category</th>
                <th class="px-6 py-3.5 text-center">Receipts</th>
                <th class="px-6 py-3.5 text-right">Net Subtotal</th>
                <th class="px-6 py-3.5 text-right">Input VAT (5%)</th>
                <th class="px-6 py-3.5 text-right">Total Outflow</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td class="px-6 py-3.5 font-bold text-slate-900"><?=e($cat['category_name'])?></td>
                    <td class="px-6 py-3.5 text-center font-bold text-slate-700"><?=$cat['expense_count']?></td>
                    <td class="px-6 py-3.5 text-right font-semibold text-slate-700"><?=money($cat['subtotal'])?></td>
                    <td class="px-6 py-3.5 text-right font-bold text-emerald-600"><?=money($cat['tax_amount'])?></td>
                    <td class="px-6 py-3.5 text-right font-extrabold text-rose-600"><?=money($cat['total'])?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
