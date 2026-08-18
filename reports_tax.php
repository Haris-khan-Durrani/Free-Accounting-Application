<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$brand = branding();

// Parameters
$preset    = $_GET['preset'] ?? 'custom';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-12-31');
$clientId  = (int)($_GET['client_id'] ?? 0);
$method    = in_array($_GET['method'] ?? '', ['accrual', 'cash'], true) ? $_GET['method'] : 'accrual';

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

// Where Clauses
$revParams = [$tid, $startDate, $endDate];
$clientClause = "";
if ($clientId > 0) {
    $clientClause = " AND i.client_id = ?";
    $revParams[] = $clientId;
}

if ($method === 'cash') {
    // Output VAT / Sales Tax Collected
    $stOut = $pdo->prepare("SELECT COALESCE(SUM(p.amount * (i.tax_amount / NULLIF(i.total, 0))), 0) FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND p.payment_date BETWEEN ? AND ?" . $clientClause);
    $stOut->execute($revParams);
    $outputTax = (float)$stOut->fetchColumn();

    // Taxable Sales
    $stSales = $pdo->prepare("SELECT COALESCE(SUM(p.amount * (i.subtotal / NULLIF(i.total, 0))), 0) FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND p.payment_date BETWEEN ? AND ?" . $clientClause);
    $stSales->execute($revParams);
    $taxableSales = (float)$stSales->fetchColumn();
} else {
    // Output VAT / Sales Tax Collected
    $stOut = $pdo->prepare("SELECT COALESCE(SUM(i.tax_amount), 0) FROM invoices i WHERE i.tenant_id = ? AND i.status != 'cancelled' AND i.invoice_date BETWEEN ? AND ?" . $clientClause);
    $stOut->execute($revParams);
    $outputTax = (float)$stOut->fetchColumn();

    // Taxable Sales
    $stSales = $pdo->prepare("SELECT COALESCE(SUM(i.subtotal), 0) FROM invoices i WHERE i.tenant_id = ? AND i.status != 'cancelled' AND i.invoice_date BETWEEN ? AND ?" . $clientClause);
    $stSales->execute($revParams);
    $taxableSales = (float)$stSales->fetchColumn();
}

// Input VAT / Tax Paid on Expenses
$stIn = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
$stIn->execute([$tid, $startDate, $endDate]);
$inputTax = (float)$stIn->fetchColumn();

$netTaxPayable = $outputTax - $inputTax;

page_start('Tax & VAT Return Report');
?>

<!-- Official Print Corporate Header -->
<div class="hidden print:block mb-6 border-b-2 border-slate-900 pb-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight"><?=e(tenant()['name'])?></h1>
            <p class="text-xs text-slate-600 font-semibold mt-0.5">TRN Tax Reg No: <strong><?=e($brand['tax_number'] ?? tenant()['tax_number'] ?? '100293847500003')?></strong> | <?=e(tenant()['address'] ?? branding()['address'] ?? 'United Arab Emirates')?></p>
        </div>
        <div class="text-right">
            <h2 class="text-lg font-black text-slate-900 uppercase">Tax & VAT Return Filing Summary</h2>
            <p class="text-xs text-slate-500 font-bold"><?=e(date('d M Y', strtotime($startDate)))?> to <?=e(date('d M Y', strtotime($endDate)))?> (<?=strtoupper($method)?> Basis)</p>
        </div>
    </div>
</div>

<!-- Executive Screen Header & Actions -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80 no-print">
    <div>
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-600 text-3xs font-black uppercase tracking-widest mb-2">
            <i class="fa-solid fa-receipt"></i>
            <span>Tax Ledger & Settlement</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Tax & VAT Return Filing Summary</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-3xl">
            Output tax collected vs recoverable input tax for <strong><?=e(tenant()['name'])?></strong> (TRN: <strong><?=e($brand['tax_number'] ?? '100293847500003')?></strong>).
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3 shrink-0">
        <a href="export_report?type=tax&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?>&client_id=<?=$clientId?>&method=<?=$method?>" class="inline-flex items-center px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-extrabold text-xs rounded-xl shadow-xs transition-all space-x-2">
            <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
            <span>Export CSV</span>
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-2 border border-slate-800">
            <i class="fa-solid fa-print text-amber-400 text-sm"></i>
            <span>Print Tax Return</span>
        </button>
    </div>
</div>

<!-- Interactive Advance Filter Toolbar -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm mb-8 no-print">
    <form method="get" id="taxFilterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        
        <!-- Preset Dropdown -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Filing Preset</label>
            <select name="preset" onchange="if(this.value!=='custom') document.getElementById('taxFilterForm').submit();" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="custom" <?=$preset==='custom'?'selected':''?>>Custom Filing Period</option>
                <option value="today" <?=$preset==='today'?'selected':''?>>Today</option>
                <option value="this_month" <?=$preset==='this_month'?'selected':''?>>This Month (<?=date('M Y')?>)</option>
                <option value="last_month" <?=$preset==='last_month'?'selected':''?>>Last Month</option>
                <option value="this_quarter" <?=$preset==='this_quarter'?'selected':''?>>This Quarter</option>
                <option value="this_year" <?=$preset==='this_year'?'selected':''?>>This Tax Year (<?=date('Y')?>)</option>
                <option value="last_year" <?=$preset==='last_year'?'selected':''?>>Last Tax Year</option>
            </select>
        </div>

        <!-- Start Date -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Filing Start Date</label>
            <input type="date" name="start_date" value="<?=e($startDate)?>" onchange="document.querySelector('select[name=preset]').value='custom';" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
        </div>

        <!-- End Date -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Filing End Date</label>
            <input type="date" name="end_date" value="<?=e($endDate)?>" onchange="document.querySelector('select[name=preset]').value='custom';" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
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

        <!-- Tax Basis & Submit -->
        <div class="flex items-center space-x-2">
            <div class="flex-grow">
                <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Tax Basis</label>
                <select name="method" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="accrual" <?=$method==='accrual'?'selected':''?>>Accrual Basis</option>
                    <option value="cash" <?=$method==='cash'?'selected':''?>>Cash Basis</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-xs transition-all shrink-0">
                <i class="fa-solid fa-filter mr-1"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Key KPI Summary Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Output Tax Collected (Sales)</span>
            <div class="text-2xl font-black text-blue-600 tracking-tight"><?=money($outputTax)?></div>
            <span class="text-xs text-slate-500 font-medium">From Sales Invoices</span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Input Tax Recoverable (Expenses)</span>
            <div class="text-2xl font-black text-emerald-600 tracking-tight"><?=money($inputTax)?></div>
            <span class="text-xs text-slate-500 font-medium">From Business Expenses</span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Net Tax Payable / (Refund)</span>
            <div class="text-2xl font-black <?= $netTaxPayable >= 0 ? 'text-amber-600' : 'text-emerald-600' ?> tracking-tight"><?=money($netTaxPayable)?></div>
            <span class="text-xs text-slate-500 font-medium"><?= $netTaxPayable >= 0 ? 'Net Tax Liability Due' : 'Tax Refund Balance' ?></span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-scale-balanced"></i>
        </div>
    </div>
</div>

<!-- Tax Return Breakdown Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Official Tax Return Breakdown Schedule</h2>
        <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
            <?=e(date('d M Y', strtotime($startDate)))?> – <?=e(date('d M Y', strtotime($endDate)))?>
        </span>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Tax Box / Line Item</th>
                <th class="px-6 py-3.5 text-right">Taxable Amount (<?=e(tenant()['currency'])?>)</th>
                <th class="px-6 py-3.5 text-right">VAT / Tax Amount (<?=e(tenant()['currency'])?>)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <tr>
                <td class="px-6 py-4 font-bold text-slate-900">Box 1: Standard Rated Supplies (Sales Invoices Issued)</td>
                <td class="px-6 py-4 text-right font-mono font-semibold text-slate-700"><?=money($taxableSales)?></td>
                <td class="px-6 py-4 text-right font-mono font-extrabold text-blue-600"><?=money($outputTax)?></td>
            </tr>
            <tr>
                <td class="px-6 py-4 font-bold text-slate-900">Box 2: Recoverable Input Tax (Expenses & Bills Incurred)</td>
                <td class="px-6 py-4 text-right font-mono font-semibold text-slate-400">--</td>
                <td class="px-6 py-4 text-right font-mono font-extrabold text-emerald-600">(<?=money($inputTax)?>)</td>
            </tr>
            <tr class="bg-slate-900 text-white font-black text-base">
                <td class="px-6 py-4">Box 3: NET TAX PAYABLE / (REFUND CREDIT)</td>
                <td class="px-6 py-4 text-right font-mono text-slate-400">--</td>
                <td class="px-6 py-4 text-right font-mono <?= $netTaxPayable >= 0 ? 'text-amber-400' : 'text-emerald-400' ?>"><?=money($netTaxPayable)?></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Official Print Declaration & Authorized Signatory Block -->
<div class="hidden print:block mt-8 pt-6 border-t-2 border-slate-900">
    <div class="grid grid-cols-2 gap-8 text-xs">
        <div>
            <p class="font-black text-slate-900 uppercase">Taxpayer Declaration & Compliance Statement</p>
            <p class="text-slate-600 mt-1 leading-relaxed">
                I hereby declare that the information contained in this Tax Return Filing Summary is complete, true, and accurate.
            </p>
        </div>
        <div class="text-right">
            <div class="border-b-2 border-slate-900 pb-1 w-56 ml-auto font-black text-slate-900 uppercase text-center">
                Authorized Signatory
            </div>
            <p class="text-3xs text-slate-500 mt-1 uppercase font-bold text-center">Signature & Official Corporate Stamp</p>
        </div>
    </div>
</div>

<?php page_end(); ?>
