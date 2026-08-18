<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Parameters
$preset = $_GET['preset'] ?? 'custom';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-12-31');
$clientId = (int)($_GET['client_id'] ?? 0);

// Handle Presets
if ($preset !== 'custom') {
    switch ($preset) {
        case 'today':
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            break;
        case 'this_month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            break;
        case 'last_month':
            $startDate = date('Y-m-01', strtotime('first day of previous month'));
            $endDate = date('Y-m-t', strtotime('last day of previous month'));
            break;
        case 'this_quarter':
            $m = (int)date('m');
            if ($m <= 3) { $startDate = date('Y-01-01'); $endDate = date('Y-03-31'); }
            elseif ($m <= 6) { $startDate = date('Y-04-01'); $endDate = date('Y-06-30'); }
            elseif ($m <= 9) { $startDate = date('Y-07-01'); $endDate = date('Y-09-30'); }
            else { $startDate = date('Y-10-01'); $endDate = date('Y-12-31'); }
            break;
        case 'this_year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-12-31');
            break;
        case 'last_year':
            $prevY = date('Y') - 1;
            $startDate = "$prevY-01-01";
            $endDate = "$prevY-12-31";
            break;
    }
}

// Fetch Client List
$stAllClients = $pdo->prepare("SELECT id, company_name FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
$stAllClients->execute([$tid]);
$allClients = $stAllClients->fetchAll();

// Cash Inflows from Customer Payments
$inWhere = "WHERE p.tenant_id = ? AND p.payment_date BETWEEN ? AND ?";
$inParams = [$tid, $startDate, $endDate];

if ($clientId > 0) {
    $inWhere .= " AND i.client_id = ?";
    $inParams[] = $clientId;
}

$stIn = $pdo->prepare("SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN invoices i ON i.id = p.invoice_id $inWhere");
$stIn->execute($inParams);
$customerCashInflows = (float)$stIn->fetchColumn();

// Cash Outflows for Expenses Paid
$stOut = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
$stOut->execute([$tid, $startDate, $endDate]);
$expenseCashOutflows = (float)$stOut->fetchColumn();

$netOperatingCash = $customerCashInflows - $expenseCashOutflows;

page_start('Cash Flow Statement');
?>

<!-- Print Executive Corporate Header -->
<div class="hidden print:block mb-6 border-b-2 border-slate-900 pb-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight"><?=e(tenant()['name'])?></h1>
            <p class="text-xs text-slate-600 font-semibold mt-0.5">TRN Tax Reg No: <strong><?=e(tenant()['tax_number'] ?? branding()['tax_number'] ?? '100293847500003')?></strong> | <?=e(tenant()['address'] ?? branding()['address'] ?? 'United Arab Emirates')?></p>
        </div>
        <div class="text-right">
            <h2 class="text-lg font-black text-slate-900 uppercase">Statement of Cash Flows</h2>
            <p class="text-xs text-slate-500 font-bold"><?=e(date('d M Y', strtotime($startDate)))?> to <?=e(date('d M Y', strtotime($endDate)))?></p>
        </div>
    </div>
</div>

<!-- Executive Screen Header & Actions -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80 no-print">
    <div>
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 text-3xs font-black uppercase tracking-widest mb-2">
            <i class="fa-solid fa-money-bill-transfer"></i>
            <span>Liquidity & Cash Position</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Statement of Cash Flows</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-3xl">
            Overview of operating cash inflows and expense outflows for <strong><?=e(tenant()['name'])?></strong>.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3 shrink-0">
        <a href="export_report?type=cashflow&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?>&client_id=<?=$clientId?>" class="inline-flex items-center px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-extrabold text-xs rounded-xl shadow-xs transition-all space-x-2">
            <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
            <span>Export CSV</span>
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-2 border border-slate-800">
            <i class="fa-solid fa-print text-amber-400 text-sm"></i>
            <span>Print / Export PDF</span>
        </button>
    </div>
</div>

<!-- Interactive Advance Filter Toolbar -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm mb-8 no-print">
    <form method="get" id="cashflowFilterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        
        <!-- Preset Dropdown -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Quick Date Preset</label>
            <select name="preset" onchange="if(this.value!=='custom') document.getElementById('cashflowFilterForm').submit();" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="custom" <?=$preset==='custom'?'selected':''?>>Custom Date Range</option>
                <option value="today" <?=$preset==='today'?'selected':''?>>Today</option>
                <option value="this_month" <?=$preset==='this_month'?'selected':''?>>This Month (<?=date('M Y')?>)</option>
                <option value="last_month" <?=$preset==='last_month'?'selected':''?>>Last Month</option>
                <option value="this_quarter" <?=$preset==='this_quarter'?'selected':''?>>This Quarter</option>
                <option value="this_year" <?=$preset==='this_year'?'selected':''?>>This Financial Year (<?=date('Y')?>)</option>
                <option value="last_year" <?=$preset==='last_year'?'selected':''?>>Last Financial Year</option>
            </select>
        </div>

        <!-- Start Date -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Start Date</label>
            <input type="date" name="start_date" value="<?=e($startDate)?>" onchange="document.querySelector('select[name=preset]').value='custom';" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
        </div>

        <!-- End Date -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">End Date</label>
            <input type="date" name="end_date" value="<?=e($endDate)?>" onchange="document.querySelector('select[name=preset]').value='custom';" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
        </div>

        <!-- Client Filter & Submit -->
        <div class="flex items-center space-x-2">
            <div class="flex-grow">
                <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Client Account</label>
                <select name="client_id" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="0" <?=$clientId===0?'selected':''?>>All Client Accounts</option>
                    <?php foreach ($allClients as $ac): ?>
                        <option value="<?=$ac['id']?>" <?=$clientId===(int)$ac['id']?'selected':''?>><?=e($ac['company_name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-xs transition-all shrink-0">
                <i class="fa-solid fa-filter mr-1"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Key KPI Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Operating Cash Inflows</span>
            <div class="text-2xl font-black text-emerald-600 tracking-tight"><?=money($customerCashInflows)?></div>
            <span class="text-xs text-slate-500 font-medium">Customer Payments Received</span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Operating Cash Outflows</span>
            <div class="text-2xl font-black text-rose-600 tracking-tight"><?=money($expenseCashOutflows)?></div>
            <span class="text-xs text-slate-500 font-medium">Vendor Expenses Paid</span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Net Operating Cash</span>
            <div class="text-2xl font-black <?= $netOperatingCash >= 0 ? 'text-purple-600' : 'text-rose-600' ?> tracking-tight"><?=money($netOperatingCash)?></div>
            <span class="text-xs text-slate-500 font-medium"><?= $netOperatingCash >= 0 ? 'Positive Cash Surplus' : 'Net Cash Deficit' ?></span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-vault"></i>
        </div>
    </div>
</div>

<!-- Cash Flow Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Cash Flow Statement Breakdown</h2>
        <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
            <?=e(date('d M Y', strtotime($startDate)))?> – <?=e(date('d M Y', strtotime($endDate)))?>
        </span>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Cash Flow Activity</th>
                <th class="px-6 py-3.5 text-right">Amount (<?=e(tenant()['currency'])?>)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <tr class="bg-emerald-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-emerald-800 uppercase tracking-wider">1. CASH FLOWS FROM OPERATING ACTIVITIES</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Cash Receipts from Customers (Paid Invoices)</td><td class="px-6 py-3 text-right font-bold text-emerald-600 font-mono"><?=money($customerCashInflows)?></td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Cash Payments for Operating Expenses</td><td class="px-6 py-3 text-right font-bold text-rose-600 font-mono">(<?=money($expenseCashOutflows)?>)</td></tr>
            <tr class="bg-emerald-100/50 font-bold text-emerald-900"><td class="px-6 py-3.5">NET CASH FROM OPERATING ACTIVITIES</td><td class="px-6 py-3.5 text-right text-base font-mono"><?=money($netOperatingCash)?></td></tr>

            <tr class="bg-blue-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-blue-800 uppercase tracking-wider">2. CASH FLOWS FROM INVESTING ACTIVITIES</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Capital Assets & Investments</td><td class="px-6 py-3 text-right font-bold text-slate-500 font-mono"><?=money(0.00)?></td></tr>

            <tr class="bg-purple-50/70"><td colspan="2" class="px-6 py-2.5 font-bold text-xs text-purple-800 uppercase tracking-wider">3. CASH FLOWS FROM FINANCING ACTIVITIES</td></tr>
            <tr><td class="px-8 py-3 text-slate-800 font-semibold">Owner Contributions & Dividends</td><td class="px-6 py-3 text-right font-bold text-slate-500 font-mono"><?=money(0.00)?></td></tr>

            <tr class="bg-slate-900 text-white font-extrabold text-base"><td class="px-6 py-4">NET INCREASE / (DECREASE) IN CASH & CASH EQUIVALENTS</td><td class="px-6 py-4 text-right font-mono <?= $netOperatingCash >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>"><?=money($netOperatingCash)?></td></tr>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
