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
        c.id,
        c.company_name,
        c.contact_name,
        c.email,
        COUNT(i.id) as total_invoices,
        COALESCE(SUM(i.total), 0) as total_revenue,
        COALESCE(SUM(i.paid_amount), 0) as total_paid,
        COALESCE(SUM(i.total - i.paid_amount), 0) as total_outstanding
    FROM clients c
    LEFT JOIN invoices i ON i.client_id = c.id AND i.tenant_id = ? AND i.status != 'cancelled' AND i.invoice_date BETWEEN ? AND ?
    WHERE c.tenant_id = ?
    GROUP BY c.id, c.company_name, c.contact_name, c.email
    ORDER BY total_revenue DESC
");
$st->execute([$tid, $startDate, $endDate, $tid]);
$clientSales = $st->fetchAll();

$totalRevAll = array_sum(array_column($clientSales, 'total_revenue'));
$totalPaidAll = array_sum(array_column($clientSales, 'total_paid'));
$totalDueAll = array_sum(array_column($clientSales, 'total_outstanding'));

page_start('Client Sales & Revenue Report');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Client Sales & Revenue Analysis</h1>
        <p class="mt-1 text-sm text-slate-500">Revenue breakdown by client account for <strong><?=e(tenant()['name'])?></strong>.</p>
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
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800">Filter Revenue</button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Sales Revenue</span>
        <div class="text-2xl font-extrabold text-blue-600 mt-1"><?=money($totalRevAll)?></div>
        <span class="text-xs text-slate-500">Gross Invoiced Revenue</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Collected</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($totalPaidAll)?></div>
        <span class="text-xs text-slate-500">Payments Received</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Uncollected Balance</span>
        <div class="text-2xl font-extrabold text-amber-600 mt-1"><?=money($totalDueAll)?></div>
        <span class="text-xs text-slate-500">Outstanding A/R</span>
    </div>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Client Company</th>
                <th class="px-6 py-3.5 text-center">Invoices</th>
                <th class="px-6 py-3.5 text-right">Total Revenue</th>
                <th class="px-6 py-3.5 text-right">Collected</th>
                <th class="px-6 py-3.5 text-right">Outstanding</th>
                <th class="px-6 py-3.5 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <?php foreach ($clientSales as $c): ?>
                <tr>
                    <td class="px-6 py-3.5 font-bold text-slate-900">
                        <?=e($c['company_name'])?>
                        <div class="text-xs font-normal text-slate-400"><?=e($c['contact_name'])?> (<?=e($c['email'])?>)</div>
                    </td>
                    <td class="px-6 py-3.5 text-center font-bold text-slate-700"><?=$c['total_invoices']?></td>
                    <td class="px-6 py-3.5 text-right font-extrabold text-slate-900"><?=money($c['total_revenue'])?></td>
                    <td class="px-6 py-3.5 text-right font-bold text-emerald-600"><?=money($c['total_paid'])?></td>
                    <td class="px-6 py-3.5 text-right font-bold text-amber-600"><?=money($c['total_outstanding'])?></td>
                    <td class="px-6 py-3.5 text-center">
                        <a href="client_statement?client_id=<?=$c['id']?>" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-bold transition-all">
                            <i class="fa-solid fa-file-invoice mr-1"></i>Statement
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
