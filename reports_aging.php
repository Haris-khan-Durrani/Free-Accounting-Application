<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Parameters
$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');
$clientId = (int)($_GET['client_id'] ?? 0);
$bucketFilter = $_GET['bucket'] ?? 'all';

// Fetch Client List
$stAllClients = $pdo->prepare("SELECT id, company_name FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
$stAllClients->execute([$tid]);
$allClients = $stAllClients->fetchAll();

// Query Open Invoices
$whereClause = "WHERE i.tenant_id = ? AND i.status IN ('draft', 'sent', 'overdue', 'partially_paid') AND i.invoice_date <= ?";
$queryParams = [$tid, $asOfDate];

if ($clientId > 0) {
    $whereClause .= " AND i.client_id = ?";
    $queryParams[] = $clientId;
}

$st = $pdo->prepare("
    SELECT i.*, c.company_name, 
           DATEDIFF(?, i.valid_until) days_overdue,
           (i.total - i.paid_amount) outstanding_amount
    FROM invoices i 
    JOIN clients c ON c.id = i.client_id 
    $whereClause 
    ORDER BY days_overdue DESC
");
$st->execute(array_merge([$asOfDate], $queryParams));
$allOpenInvoices = $st->fetchAll();

$agingBuckets = [
    'current' => 0.00,
    '1_30' => 0.00,
    '31_60' => 0.00,
    '61_90' => 0.00,
    'over_90' => 0.00
];

$filteredInvoices = [];

foreach ($allOpenInvoices as $inv) {
    $days = (int)$inv['days_overdue'];
    $amt = (float)$inv['outstanding_amount'];

    if ($amt <= 0) continue;

    $cat = 'current';
    if ($days <= 0) {
        $agingBuckets['current'] += $amt;
        $cat = 'current';
    } elseif ($days <= 30) {
        $agingBuckets['1_30'] += $amt;
        $cat = '1_30';
    } elseif ($days <= 60) {
        $agingBuckets['31_60'] += $amt;
        $cat = '31_60';
    } elseif ($days <= 90) {
        $agingBuckets['61_90'] += $amt;
        $cat = '61_90';
    } else {
        $agingBuckets['over_90'] += $amt;
        $cat = 'over_90';
    }

    if ($bucketFilter === 'all' || $bucketFilter === $cat) {
        $filteredInvoices[] = $inv;
    }
}

$totalAR = array_sum($agingBuckets);

page_start('Accounts Receivable Aging Report');
?>

<!-- Print Executive Corporate Header -->
<div class="hidden print:block mb-6 border-b-2 border-slate-900 pb-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight"><?=e(tenant()['name'])?></h1>
            <p class="text-xs text-slate-600 font-semibold mt-0.5">TRN Tax Reg No: <strong><?=e(tenant()['tax_number'] ?? branding()['tax_number'] ?? '100293847500003')?></strong> | <?=e(tenant()['address'] ?? branding()['address'] ?? 'United Arab Emirates')?></p>
        </div>
        <div class="text-right">
            <h2 class="text-lg font-black text-slate-900 uppercase">A/R Aging Schedule Report</h2>
            <p class="text-xs text-slate-500 font-bold">As of <?=e(date('d M Y', strtotime($asOfDate)))?></p>
        </div>
    </div>
</div>

<!-- Executive Screen Header & Actions -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80 no-print">
    <div>
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-600 text-3xs font-black uppercase tracking-widest mb-2">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Receivables Ledger & Collection Risk</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Accounts Receivable (A/R) Aging Report</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-3xl">
            Uncollected client balances categorized by overdue age for <strong><?=e(tenant()['name'])?></strong> as of <strong><?=e(date('d M Y', strtotime($asOfDate)))?></strong>.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3 shrink-0">
        <a href="export_report?type=aging&as_of_date=<?=urlencode($asOfDate)?>&client_id=<?=$clientId?>&bucket=<?=$bucketFilter?>" class="inline-flex items-center px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-extrabold text-xs rounded-xl shadow-xs transition-all space-x-2">
            <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
            <span>Export CSV</span>
        </a>
        <a href="report_pdf.php?type=aging&as_of_date=<?=urlencode($asOfDate)?><?=$clientId?'&client_id='.$clientId:''?>" target="_blank" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-2 border border-slate-800">
            <i class="fa-solid fa-file-pdf text-amber-400 text-sm"></i>
            <span>Print / Export PDF</span>
        </a>
    </div>
</div>

<!-- Interactive Advance Filter Toolbar -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm mb-8 no-print">
    <form method="get" id="agingFilterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        
        <!-- Target Date -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Aging Target Date</label>
            <input type="date" name="as_of_date" value="<?=e($asOfDate)?>" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
        </div>

        <!-- Client Filter -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Client Account</label>
            <select name="client_id" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="0" <?=$clientId===0?'selected':''?>>All Client Accounts</option>
                <?php foreach ($allClients as $ac): ?>
                    <option value="<?=$ac['id']?>" <?=$clientId===(int)$ac['id']?'selected':''?>><?=e($ac['company_name'])?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Overdue Bucket Filter & Submit -->
        <div class="flex items-center space-x-2 col-span-1 lg:col-span-2">
            <div class="flex-grow">
                <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Aging Bracket Filter</label>
                <select name="bucket" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="all" <?=$bucketFilter==='all'?'selected':''?>>All Aging Brackets</option>
                    <option value="current" <?=$bucketFilter==='current'?'selected':''?>>Current / Not Due Yet</option>
                    <option value="1_30" <?=$bucketFilter==='1_30'?'selected':''?>>1 – 30 Days Overdue</option>
                    <option value="31_60" <?=$bucketFilter==='31_60'?'selected':''?>>31 – 60 Days Overdue</option>
                    <option value="61_90" <?=$bucketFilter==='61_90'?'selected':''?>>61 – 90 Days Overdue</option>
                    <option value="over_90" <?=$bucketFilter==='over_90'?'selected':''?>>90+ Days Overdue (High Risk)</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-xs transition-all shrink-0">
                <i class="fa-solid fa-filter mr-1"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Aging Buckets KPI Grid -->
<div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
        <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-wider block mb-1">Current / Not Due</span>
        <div class="text-xl font-black text-emerald-600 tracking-tight"><?=money($agingBuckets['current'])?></div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
        <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-wider block mb-1">1 – 30 Days</span>
        <div class="text-xl font-black text-amber-600 tracking-tight"><?=money($agingBuckets['1_30'])?></div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
        <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-wider block mb-1">31 – 60 Days</span>
        <div class="text-xl font-black text-amber-700 tracking-tight"><?=money($agingBuckets['31_60'])?></div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
        <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-wider block mb-1">61 – 90 Days</span>
        <div class="text-xl font-black text-rose-600 tracking-tight"><?=money($agingBuckets['61_90'])?></div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
        <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-wider block mb-1">90+ Days (High Risk)</span>
        <div class="text-xl font-black text-rose-800 tracking-tight"><?=money($agingBuckets['over_90'])?></div>
    </div>
</div>

<!-- Open Invoices Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Outstanding Invoices Log (<?=count($filteredInvoices)?>)</h2>
        <span class="text-xs font-black text-slate-700">Total Uncollected A/R: <span class="text-amber-600 font-black text-base ml-1.5"><?=money($totalAR)?></span></span>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                <th class="px-5 py-3.5">Invoice #</th>
                <th class="px-5 py-3.5">Client Account</th>
                <th class="px-5 py-3.5">Due Date</th>
                <th class="px-5 py-3.5">Days Overdue</th>
                <th class="px-5 py-3.5 text-right">Outstanding Balance</th>
                <th class="px-5 py-3.5 text-right no-print">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
            <?php if (empty($filteredInvoices)): ?>
                <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400 font-bold">All client invoices cleared matching this filter! No outstanding receivables.</td></tr>
            <?php endif; ?>
            <?php foreach ($filteredInvoices as $inv): ?>
                <?php $d = (int)$inv['days_overdue']; ?>
                <tr class="hover:bg-slate-50/80 transition-all">
                    <td class="px-5 py-3.5 font-extrabold text-slate-900">
                        <a href="invoice_view.php?id=<?=$inv['id']?>" class="text-blue-600 hover:underline"><?=e($inv['invoice_number'])?></a>
                    </td>
                    <td class="px-5 py-3.5 font-bold text-slate-800"><?=e($inv['company_name'])?></td>
                    <td class="px-5 py-3.5 text-slate-500 font-medium"><?=e(date('d M Y', strtotime($inv['valid_until'])))?></td>
                    <td class="px-5 py-3.5">
                        <?php if ($d <= 0): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-3xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200">Current / Not Due</span>
                        <?php elseif ($d <= 30): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-3xs font-extrabold bg-amber-100 text-amber-800 border border-amber-200"><?=$d?> Days Overdue</span>
                        <?php elseif ($d <= 60): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-3xs font-extrabold bg-amber-200 text-amber-900 border border-amber-300"><?=$d?> Days Overdue</span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded-full text-3xs font-extrabold bg-rose-100 text-rose-800 border border-rose-200"><?=$d?> Days Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-right font-black text-slate-900 font-mono"><?=money((float)$inv['outstanding_amount'], $inv['currency'])?></td>
                    <td class="px-5 py-3.5 text-right no-print">
                        <a href="invoice_view.php?id=<?=$inv['id']?>" class="px-2.5 py-1 text-3xs font-extrabold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all">View Invoice</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
