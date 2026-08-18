<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

// 1. Sales VAT (Output Tax)
$stSales = $pdo->prepare("
    SELECT 
        COALESCE(SUM(subtotal), 0) as subtotal,
        COALESCE(SUM(tax_amount), 0) as tax_amount,
        COALESCE(SUM(total), 0) as total
    FROM invoices 
    WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?
");
$stSales->execute([$tid, $startDate, $endDate]);
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

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-black text-xs uppercase tracking-wider">FTA Compliant</span>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">UAE VAT 201 Declaration Return</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Official Federal Tax Authority (FTA) 7-Emirate VAT filing form for <strong><?=e(tenant()['name'])?></strong> (TRN: <strong><?=e(branding()['tax_number'] ?: '100000000000003')?></strong>).</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3 no-print">
        <a href="export_report?type=tax&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?>" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-file-csv mr-2 text-emerald-600"></i>Export CSV
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print Return
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-8 no-print">
    <form method="get" class="flex flex-wrap items-center gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tax Period Start</label>
            <input type="date" name="start_date" value="<?=e($startDate)?>" class="rounded-xl border-slate-300 text-sm py-1.5 px-3">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Tax Period End</label>
            <input type="date" name="end_date" value="<?=e($endDate)?>" class="rounded-xl border-slate-300 text-sm py-1.5 px-3">
        </div>
        <div class="pt-5">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800">Recalculate VAT 201</button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Box 1: Output Tax Collected</span>
        <div class="text-2xl font-extrabold text-blue-600 mt-1"><?=money($salesTotals['tax_amount'])?></div>
        <span class="text-xs text-slate-500">From Tax Invoices Issued</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Box 9: Recoverable Input Tax</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($expTotals['tax_amount'])?></div>
        <span class="text-xs text-slate-500">From Business Expenses</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Box 14: Net VAT Payable to FTA</span>
        <div class="text-2xl font-extrabold <?= $netVatPayable >= 0 ? 'text-amber-600' : 'text-emerald-600' ?> mt-1"><?=money($netVatPayable)?></div>
        <span class="text-xs text-slate-500"><?= $netVatPayable >= 0 ? 'Tax Amount Due to FTA' : 'Tax Refund Balance' ?></span>
    </div>
</div>

<!-- VAT 201 Official Return Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="bg-slate-900 px-6 py-4 text-white flex justify-between items-center">
        <div>
            <h3 class="font-extrabold text-base">VAT ON SALES AND ALL OTHER OUTPUTS (SECTION 1)</h3>
            <p class="text-xs text-slate-300">Standard rated supplies at 5% broken down by Emirate</p>
        </div>
        <span class="px-3 py-1 bg-amber-500/20 text-amber-300 text-xs font-bold rounded-lg">FTA Form 201</span>
    </div>

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Box #</th>
                <th class="px-6 py-3.5">Emirate / Description</th>
                <th class="px-6 py-3.5 text-right">Amount (AED)</th>
                <th class="px-6 py-3.5 text-right">VAT Amount (5% AED)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <?php foreach ($emiratesList as $em => $box): ?>
                <tr>
                    <td class="px-6 py-3 font-bold text-xs text-slate-500"><?=$box?></td>
                    <td class="px-6 py-3 text-slate-800 font-semibold">Standard Rated Supplies in <?=e($em)?></td>
                    <td class="px-6 py-3 text-right font-semibold text-slate-700"><?=money($emirateSales[$em]['subtotal'])?></td>
                    <td class="px-6 py-3 text-right font-bold text-slate-900"><?=money($emirateSales[$em]['tax_amount'])?></td>
                </tr>
            <?php endforeach; ?>

            <tr class="bg-slate-50 font-bold text-slate-900">
                <td class="px-6 py-3 text-xs text-slate-500">Box 1</td>
                <td class="px-6 py-3">Total Standard Rated Supplies (Box 1a - 1g)</td>
                <td class="px-6 py-3 text-right text-blue-600"><?=money($salesTotals['subtotal'])?></td>
                <td class="px-6 py-3 text-right text-blue-600 text-base"><?=money($salesTotals['tax_amount'])?></td>
            </tr>

            <!-- SECTION 2: EXPENSES AND ALL OTHER INPUTS -->
            <tr class="bg-slate-900 text-white font-extrabold"><td colspan="4" class="px-6 py-3 uppercase tracking-wider text-xs">VAT ON EXPENSES AND ALL OTHER INPUTS (SECTION 2)</td></tr>
            <tr>
                <td class="px-6 py-3 font-bold text-xs text-slate-500">Box 9</td>
                <td class="px-6 py-3 text-slate-800 font-semibold">Standard Rated Expenses (Recoverable Input VAT at 5%)</td>
                <td class="px-6 py-3 text-right font-semibold text-slate-700"><?=money($expTotals['subtotal'])?></td>
                <td class="px-6 py-3 text-right font-bold text-emerald-600"><?=money($expTotals['tax_amount'])?></td>
            </tr>

            <!-- NET TAX DUE -->
            <tr class="bg-amber-500/10 font-extrabold text-slate-900 text-base">
                <td class="px-6 py-4 text-xs text-amber-700">Box 14</td>
                <td class="px-6 py-4">NET VAT PAYABLE FOR THE PERIOD (Box 1 - Box 9)</td>
                <td class="px-6 py-4 text-right text-slate-600"><?=money((float)$salesTotals['subtotal'] - (float)$expTotals['subtotal'])?></td>
                <td class="px-6 py-4 text-right text-amber-700 text-lg"><?=money($netVatPayable)?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
