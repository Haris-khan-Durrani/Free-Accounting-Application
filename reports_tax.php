<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$brand = branding();

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-12-31');

// Output VAT / Sales Tax Collected
$stOut = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
$stOut->execute([$tid, $startDate, $endDate]);
$outputTax = (float)$stOut->fetchColumn();

// Total Taxable Sales
$stSales = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
$stSales->execute([$tid, $startDate, $endDate]);
$taxableSales = (float)$stSales->fetchColumn();

// Input VAT / Tax Paid on Expenses
$stIn = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
$stIn->execute([$tid, $startDate, $endDate]);
$inputTax = (float)$stIn->fetchColumn();

$netTaxPayable = $outputTax - $inputTax;

page_start('Tax & VAT Return Report');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Tax & VAT Return Filing Summary</h1>
        <p class="mt-1 text-sm text-slate-500">Output tax collected vs recoverable input tax for <strong><?=e(tenant()['name'])?></strong> (Tax ID: <strong><?=e($brand['tax_number'] ?: 'Not Configured')?></strong>).</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3">
        <a href="export_report?type=tax&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?>" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-file-csv mr-2 text-emerald-600"></i>Export CSV
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print Tax Return
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-8">
    <form method="get" class="flex flex-wrap items-center gap-4">
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Filing Start Date</label>
            <input type="date" name="start_date" value="<?=e($startDate)?>" class="rounded-xl border-slate-300 text-sm py-1.5 px-3">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Filing End Date</label>
            <input type="date" name="end_date" value="<?=e($endDate)?>" class="rounded-xl border-slate-300 text-sm py-1.5 px-3">
        </div>
        <div class="pt-5">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800">Generate Return</button>
        </div>
    </form>
</div>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Output Tax Collected (Sales)</span>
        <div class="text-2xl font-extrabold text-blue-600 mt-1"><?=money($outputTax)?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Input Tax Recoverable (Expenses)</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($inputTax)?></div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Net Tax Payable / (Refund)</span>
        <div class="text-2xl font-extrabold <?= $netTaxPayable >= 0 ? 'text-amber-600' : 'text-emerald-600' ?> mt-1"><?=money($netTaxPayable)?></div>
    </div>
</div>

<!-- Tax Return Breakdown Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Official VAT Return Breakdown Schedule</h2>
    </div>
    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Tax Box / Line Item</th>
                <th class="px-6 py-3.5 text-right">Taxable Amount</th>
                <th class="px-6 py-3.5 text-right">VAT / Tax Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <tr>
                <td class="px-6 py-4 font-bold text-slate-900">Box 1: Standard Rated Supplies (Sales Invoices Issued)</td>
                <td class="px-6 py-4 text-right font-semibold text-slate-700"><?=money($taxableSales)?></td>
                <td class="px-6 py-4 text-right font-extrabold text-blue-600"><?=money($outputTax)?></td>
            </tr>
            <tr>
                <td class="px-6 py-4 font-bold text-slate-900">Box 2: Recoverable Input Tax (Expenses & Bills Incurred)</td>
                <td class="px-6 py-4 text-right font-semibold text-slate-700">--</td>
                <td class="px-6 py-4 text-right font-extrabold text-emerald-600">(<?=money($inputTax)?>)</td>
            </tr>
            <tr class="bg-slate-900 text-white font-extrabold text-base">
                <td class="px-6 py-4">NET TAX PAYABLE TO TAX AUTHORITY</td>
                <td></td>
                <td class="px-6 py-4 text-right text-amber-400"><?=money($netTaxPayable)?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
