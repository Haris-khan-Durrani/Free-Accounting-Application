<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$st = $pdo->prepare("SELECT i.*, c.company_name, DATEDIFF(CURRENT_DATE(), i.valid_until) days_overdue FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? AND i.status IN ('draft', 'sent', 'overdue') ORDER BY days_overdue DESC");
$st->execute([$tid]);
$openInvoices = $st->fetchAll();

$agingBuckets = [
    'current' => 0.00,
    '1_30' => 0.00,
    '31_60' => 0.00,
    '61_90' => 0.00,
    'over_90' => 0.00
];

foreach ($openInvoices as $inv) {
    $days = (int)$inv['days_overdue'];
    $amt = (float)$inv['total'];

    if ($days <= 0) {
        $agingBuckets['current'] += $amt;
    } elseif ($days <= 30) {
        $agingBuckets['1_30'] += $amt;
    } elseif ($days <= 60) {
        $agingBuckets['31_60'] += $amt;
    } elseif ($days <= 90) {
        $agingBuckets['61_90'] += $amt;
    } else {
        $agingBuckets['over_90'] += $amt;
    }
}

$totalAR = array_sum($agingBuckets);

page_start('Accounts Receivable Aging Report');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Accounts Receivable (A/R) Aging Report</h1>
        <p class="mt-1 text-sm text-slate-500">Uncollected client balances categorized by overdue age for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3">
        <a href="export_report?type=aging" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-file-csv mr-2 text-emerald-600"></i>Export CSV
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print Aging Report
        </button>
    </div>
</div>

<!-- Aging Buckets Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Current / Not Due</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($agingBuckets['current'])?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">1 – 30 Days Overdue</span>
        <div class="text-2xl font-extrabold text-amber-600 mt-1"><?=money($agingBuckets['1_30'])?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">31 – 60 Days Overdue</span>
        <div class="text-2xl font-extrabold text-amber-700 mt-1"><?=money($agingBuckets['31_60'])?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">61 – 90 Days Overdue</span>
        <div class="text-2xl font-extrabold text-rose-600 mt-1"><?=money($agingBuckets['61_90'])?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">90+ Days Overdue</span>
        <div class="text-2xl font-extrabold text-rose-800 mt-1"><?=money($agingBuckets['over_90'])?></div>
    </div>
</div>

<!-- Open Invoices Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Outstanding Invoices Log (<?=count($openInvoices)?>)</h2>
        <span class="text-xs font-extrabold text-slate-700">Total Uncollected A/R: <span class="text-amber-600 font-extrabold text-base ml-1"><?=money($totalAR)?></span></span>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Invoice #</th>
                <th class="px-6 py-3.5">Client Name</th>
                <th class="px-6 py-3.5">Due Date</th>
                <th class="px-6 py-3.5">Days Overdue</th>
                <th class="px-6 py-3.5 text-right">Outstanding Amount</th>
                <th class="px-6 py-3.5 text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <?php if (empty($openInvoices)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">All client invoices cleared! No outstanding receivables.</td></tr>
            <?php endif; ?>
            <?php foreach ($openInvoices as $inv): ?>
                <?php $d = (int)$inv['days_overdue']; ?>
                <tr class="hover:bg-slate-50/80 transition-all">
                    <td class="px-6 py-4 font-bold text-blue-600"><a href="invoice_view.php?id=<?=$inv['id']?>" class="hover:underline"><?=e($inv['invoice_number'])?></a></td>
                    <td class="px-6 py-4 font-semibold text-slate-900"><?=e($inv['company_name'])?></td>
                    <td class="px-6 py-4 text-xs text-slate-500"><?=e(date('d M Y', strtotime($inv['valid_until'])))?></td>
                    <td class="px-6 py-4">
                        <?php if ($d <= 0): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Not Due Yet</span>
                        <?php elseif ($d <= 30): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800"><?=$d?> Days Overdue</span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800"><?=$d?> Days Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right font-extrabold text-slate-900"><?=money((float)$inv['total'], $inv['currency'])?></td>
                    <td class="px-6 py-4 text-right">
                        <a href="invoice_view.php?id=<?=$inv['id']?>" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">View Invoice</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
