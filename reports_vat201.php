<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Parameters
$preset    = $_GET['preset'] ?? 'custom';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$method    = in_array($_GET['method'] ?? '', ['accrual', 'cash'], true) ? $_GET['method'] : 'accrual';
$emirateFilter = $_GET['emirate'] ?? 'all';

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
        case 'vat_q1':
            $y = date('Y');
            $startDate = "$y-01-01";
            $endDate   = "$y-03-31";
            break;
        case 'vat_q2':
            $y = date('Y');
            $startDate = "$y-04-01";
            $endDate   = "$y-06-30";
            break;
        case 'vat_q3':
            $y = date('Y');
            $startDate = "$y-07-01";
            $endDate   = "$y-09-30";
            break;
        case 'vat_q4':
            $y = date('Y');
            $startDate = "$y-10-01";
            $endDate   = "$y-12-31";
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

// 1. Sales VAT (Output Tax)
if ($method === 'cash') {
    $stSales = $pdo->prepare("
        SELECT 
            COALESCE(SUM(p.amount), 0) as total,
            COALESCE(SUM(p.amount * (i.subtotal / NULLIF(i.total, 0))), 0) as subtotal,
            COALESCE(SUM(p.amount * (i.tax_amount / NULLIF(i.total, 0))), 0) as tax_amount
        FROM payments p 
        JOIN invoices i ON i.id = p.invoice_id 
        WHERE p.tenant_id = ? AND p.payment_date BETWEEN ? AND ?
    ");
    $stSales->execute([$tid, $startDate, $endDate]);
} else {
    $stSales = $pdo->prepare("
        SELECT 
            COALESCE(SUM(subtotal), 0) as subtotal,
            COALESCE(SUM(tax_amount), 0) as tax_amount,
            COALESCE(SUM(total), 0) as total
        FROM invoices 
        WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?
    ");
    $stSales->execute([$tid, $startDate, $endDate]);
}
$salesTotals = $stSales->fetch();

// 2. Emirate Sales Breakdown (Box 1a to 1g)
$emiratesList = [
    'Abu Dhabi' => '1a',
    'Dubai' => '1b',
    'Sharjah' => '1c',
    'Ajman' => '1d',
    'Umm Al Quwain' => '1e',
    'Ras Al Khaimah' => '1f',
    'Fujairah' => '1g'
];

$emirateSales = [];
foreach ($emiratesList as $em => $box) {
    if ($method === 'cash') {
        $stEm = $pdo->prepare("
            SELECT 
                COALESCE(SUM(p.amount * (i.subtotal / NULLIF(i.total, 0))), 0) as subtotal,
                COALESCE(SUM(p.amount * (i.tax_amount / NULLIF(i.total, 0))), 0) as tax_amount
            FROM payments p
            JOIN invoices i ON i.id = p.invoice_id
            JOIN clients c ON c.id = i.client_id
            WHERE p.tenant_id = ? 
              AND (c.city LIKE ? OR c.address LIKE ?) 
              AND p.payment_date BETWEEN ? AND ?
        ");
    } else {
        $stEm = $pdo->prepare("
            SELECT 
                COALESCE(SUM(i.subtotal), 0) as subtotal,
                COALESCE(SUM(i.tax_amount), 0) as tax_amount
            FROM invoices i
            JOIN clients c ON c.id = i.client_id
            WHERE i.tenant_id = ? AND i.status != 'cancelled' 
              AND (c.city LIKE ? OR c.address LIKE ?) 
              AND i.invoice_date BETWEEN ? AND ?
        ");
    }
    $stEm->execute([$tid, "%$em%", "%$em%", $startDate, $endDate]);
    $row = $stEm->fetch();
    $emirateSales[$em] = [
        'box' => $box,
        'subtotal' => (float)$row['subtotal'],
        'tax_amount' => (float)$row['tax_amount']
    ];
}

// Unclassified emirate sales assigned to Dubai (default UAE HQ)
$mappedSubtotal = array_sum(array_column($emirateSales, 'subtotal'));
$unmappedSubtotal = max(0, (float)$salesTotals['subtotal'] - $mappedSubtotal);
$mappedTax = array_sum(array_column($emirateSales, 'tax_amount'));
$unmappedTax = max(0, (float)$salesTotals['tax_amount'] - $mappedTax);

$emirateSales['Dubai']['subtotal'] += $unmappedSubtotal;
$emirateSales['Dubai']['tax_amount'] += $unmappedTax;

// 3. Expenses VAT (Input Tax Recoverable - Box 9)
$stExp = $pdo->prepare("
    SELECT 
        COALESCE(SUM(subtotal), 0) as subtotal,
        COALESCE(SUM(tax_amount), 0) as tax_amount,
        COALESCE(SUM(total), 0) as total
    FROM expenses 
    WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?
");
$stExp->execute([$tid, $startDate, $endDate]);
$expTotals = $stExp->fetch();

$netVatPayable = (float)$salesTotals['tax_amount'] - (float)$expTotals['tax_amount'];

page_start('UAE FTA VAT 201 Return');
?>

<!-- Official Print Corporate Header -->
<div class="hidden print:block mb-6 border-b-2 border-slate-900 pb-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight"><?=e(tenant()['name'])?></h1>
            <p class="text-xs text-slate-600 font-semibold mt-0.5">TRN Tax Reg No: <strong><?=e(tenant()['tax_number'] ?? branding()['tax_number'] ?? '100293847500003')?></strong> | <?=e(tenant()['address'] ?? branding()['address'] ?? 'United Arab Emirates')?></p>
        </div>
        <div class="text-right">
            <h2 class="text-lg font-black text-slate-900 uppercase">UAE FTA VAT 201 Return Form</h2>
            <p class="text-xs text-slate-500 font-bold">Filing Period: <?=e(date('d M Y', strtotime($startDate)))?> to <?=e(date('d M Y', strtotime($endDate)))?> (<?=strtoupper($method)?> Basis)</p>
        </div>
    </div>
</div>

<!-- Executive Screen Header & Actions -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80 no-print">
    <div>
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 text-3xs font-black uppercase tracking-widest mb-2">
            <i class="fa-solid fa-building-columns"></i>
            <span>Federal Tax Authority (FTA) Return</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">UAE VAT 201 Declaration Return</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-3xl">
            Official 7-Emirate VAT filing schedule for <strong><?=e(tenant()['name'])?></strong> (TRN: <strong><?=e(tenant()['tax_number'] ?? '100293847500003')?></strong>).
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3 shrink-0">
        <a href="export_report?type=tax&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?>&method=<?=$method?>" class="inline-flex items-center px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-extrabold text-xs rounded-xl shadow-xs transition-all space-x-2">
            <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
            <span>Export CSV</span>
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-2 border border-slate-800">
            <i class="fa-solid fa-print text-amber-400 text-sm"></i>
            <span>Print VAT 201 PDF</span>
        </button>
    </div>
</div>

<!-- Interactive Advance Filter Toolbar -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm mb-8 no-print">
    <form method="get" id="vat201FilterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        
        <!-- Preset Dropdown -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Tax Period Preset</label>
            <select name="preset" onchange="if(this.value!=='custom') document.getElementById('vat201FilterForm').submit();" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="custom" <?=$preset==='custom'?'selected':''?>>Custom Filing Period</option>
                <option value="vat_q1" <?=$preset==='vat_q1'?'selected':''?>>VAT Q1 (Jan - Mar)</option>
                <option value="vat_q2" <?=$preset==='vat_q2'?'selected':''?>>VAT Q2 (Apr - Jun)</option>
                <option value="vat_q3" <?=$preset==='vat_q3'?'selected':''?>>VAT Q3 (Jul - Sep)</option>
                <option value="vat_q4" <?=$preset==='vat_q4'?'selected':''?>>VAT Q4 (Oct - Dec)</option>
                <option value="this_month" <?=$preset==='this_month'?'selected':''?>>This Month (<?=date('M Y')?>)</option>
                <option value="last_month" <?=$preset==='last_month'?'selected':''?>>Last Month</option>
                <option value="this_year" <?=$preset==='this_year'?'selected':''?>>Full Tax Year (<?=date('Y')?>)</option>
                <option value="last_year" <?=$preset==='last_year'?'selected':''?>>Last Tax Year</option>
            </select>
        </div>

        <!-- Start Date -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Tax Period Start</label>
            <input type="date" name="start_date" value="<?=e($startDate)?>" onchange="document.querySelector('select[name=preset]').value='custom';" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
        </div>

        <!-- End Date -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Tax Period End</label>
            <input type="date" name="end_date" value="<?=e($endDate)?>" onchange="document.querySelector('select[name=preset]').value='custom';" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
        </div>

        <!-- Emirate Filter -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Emirate Scope</label>
            <select name="emirate" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="all" <?=$emirateFilter==='all'?'selected':''?>>All 7 Emirates (National)</option>
                <?php foreach (array_keys($emiratesList) as $emName): ?>
                    <option value="<?=$emName?>" <?=$emirateFilter===$emName?'selected':''?>><?=$emName?> Only</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Accounting Basis & Submit -->
        <div class="flex items-center space-x-2">
            <div class="flex-grow">
                <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Tax Basis</label>
                <select name="method" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="accrual" <?=$method==='accrual'?'selected':''?>>Accrual Basis</option>
                    <option value="cash" <?=$method==='cash'?'selected':''?>>Cash Basis</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-xs transition-all shrink-0">
                <i class="fa-solid fa-calculator mr-1"></i>Recalculate
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Box 1: Output Tax Collected</span>
            <div class="text-2xl font-black text-blue-600 tracking-tight"><?=money($salesTotals['tax_amount'])?></div>
            <span class="text-xs text-slate-500 font-medium">5% Sales Tax Collected</span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Box 9: Recoverable Input Tax</span>
            <div class="text-2xl font-black text-emerald-600 tracking-tight"><?=money($expTotals['tax_amount'])?></div>
            <span class="text-xs text-slate-500 font-medium">Tax Incurred on Expenses</span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Box 14: Net VAT Payable</span>
            <div class="text-2xl font-black <?= $netVatPayable >= 0 ? 'text-amber-600' : 'text-emerald-600' ?> tracking-tight"><?=money($netVatPayable)?></div>
            <span class="text-xs text-slate-500 font-medium"><?= $netVatPayable >= 0 ? 'Tax Amount Payable to FTA' : 'Tax Refund Credit Balance' ?></span>
        </div>
        <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-base">
            <i class="fa-solid fa-building-columns"></i>
        </div>
    </div>
</div>

<!-- VAT 201 Official Return Schedule Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="bg-slate-900 px-6 py-4 text-white flex justify-between items-center">
        <div>
            <h3 class="font-extrabold text-base">VAT ON SALES AND ALL OTHER OUTPUTS (SECTION 1)</h3>
            <p class="text-xs text-slate-300">Standard rated supplies at 5% broken down by Emirate</p>
        </div>
        <span class="px-3 py-1 bg-amber-500/20 text-amber-300 text-xs font-black rounded-lg">FTA Form VAT 201</span>
    </div>

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Tax Box / Line Item</th>
                <th class="px-6 py-3.5 text-right">Taxable Amount (AED)</th>
                <th class="px-6 py-3.5 text-right">VAT Amount (5%)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <?php foreach ($emiratesList as $emName => $boxId): ?>
                <?php if ($emirateFilter !== 'all' && $emirateFilter !== $emName) continue; ?>
                <?php $data = $emirateSales[$emName]; ?>
                <tr class="hover:bg-slate-50/80 transition-all">
                    <td class="px-6 py-3.5 font-bold text-slate-800">
                        Box <?=$boxId?>: Standard Rated Supplies in <?=$emName?>
                    </td>
                    <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900"><?=money($data['subtotal'])?></td>
                    <td class="px-6 py-3.5 text-right font-mono font-extrabold text-blue-600"><?=money($data['tax_amount'])?></td>
                </tr>
            <?php endforeach; ?>

            <tr class="bg-slate-100 font-black text-slate-900">
                <td class="px-6 py-4">Box 1 Total: Total Output Tax Collected (Sales)</td>
                <td class="px-6 py-4 text-right font-mono text-base"><?=money($salesTotals['subtotal'])?></td>
                <td class="px-6 py-4 text-right font-mono text-blue-700 text-base"><?=money($salesTotals['tax_amount'])?></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Input Tax Recoverable & Net Payable Summary Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="bg-slate-900 px-6 py-4 text-white">
        <h3 class="font-extrabold text-base">VAT ON EXPENSES AND PURCHASES (SECTION 2)</h3>
        <p class="text-xs text-slate-300">Recoverable input tax incurred on business operations</p>
    </div>

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Tax Box / Line Item</th>
                <th class="px-6 py-3.5 text-right">Taxable Amount (AED)</th>
                <th class="px-6 py-3.5 text-right">Recoverable VAT (5%)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">Box 9: Standard Rated Expenses & Purchases</td>
                <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900"><?=money($expTotals['subtotal'])?></td>
                <td class="px-6 py-3.5 text-right font-mono font-extrabold text-emerald-600"><?=money($expTotals['tax_amount'])?></td>
            </tr>
            <tr class="bg-slate-900 text-white font-black text-base">
                <td class="px-6 py-4">Box 14: NET VAT PAYABLE / (REFUND CREDIT) TO FTA</td>
                <td class="px-6 py-4 text-right font-mono text-slate-400">—</td>
                <td class="px-6 py-4 text-right font-mono <?= $netVatPayable >= 0 ? 'text-amber-400' : 'text-emerald-400' ?>"><?=money($netVatPayable)?></td>
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
                I hereby declare that the information contained in this Tax Return is complete, true, and accurate in accordance with Federal Decree-Law No. (8) of 2017 on Value Added Tax.
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
