<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$taxYear = $_GET['tax_year'] ?? date('Y');
$startDate = $taxYear . '-01-01';
$endDate   = $taxYear . '-12-31';

// Total Invoiced Revenue
$stRev = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
$stRev->execute([$tid, $startDate, $endDate]);
$totalRevenue = (float)$stRev->fetchColumn();

// Total Operating Expenses
$stExp = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
$stExp->execute([$tid, $startDate, $endDate]);
$totalExpenses = (float)$stExp->fetchColumn();

$netTaxableProfit = $totalRevenue - $totalExpenses;

// UAE Corporate Tax Rules (Federal Decree-Law No. 47 of 2022)
// 0% on Net Profit up to AED 375,000 (Small Business Relief Threshold)
// 9% on Net Profit exceeding AED 375,000
$thresholdLimit = 375000.00;
$taxableExcess = max(0, $netTaxableProfit - $thresholdLimit);
$estimatedCorporateTax = $taxableExcess * 0.09;

$thresholdPercentage = min(100, round(($netTaxableProfit / $thresholdLimit) * 100, 1));

page_start('UAE Corporate Tax Estimator');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 font-black text-xs uppercase tracking-wider">Federal Decree-Law No. 47</span>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">UAE Corporate Tax (9%) & Profit Estimator</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Track your AED 375,000 Small Business Relief (SBR) threshold & estimated 9% Corporate Tax for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex space-x-3">
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print Tax Assessment
        </button>
    </div>
</div>

<!-- Year Filter -->
<div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-8 max-w-xs">
    <form method="get" class="flex items-center space-x-3">
        <div class="flex-grow">
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Corporate Tax Year</label>
            <select name="tax_year" class="w-full rounded-xl border-slate-300 text-sm font-bold py-1.5 px-3" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                    <option value="<?=$y?>" <?=$y == $taxYear ? 'selected' : ''?>>Tax Year <?=$y?></option>
                <?php endfor; ?>
            </select>
        </div>
    </form>
</div>

<!-- Progress Bar to AED 375,000 Threshold -->
<div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm mb-8 space-y-4">
    <div class="flex justify-between items-center text-xs font-extrabold text-slate-700 uppercase tracking-wider">
        <span>AED 375,000 Tax-Free Threshold Progress</span>
        <span class="text-blue-600"><?=$thresholdPercentage?>% Used</span>
    </div>
    
    <div class="w-full bg-slate-100 rounded-full h-4 overflow-hidden border border-slate-200/80 p-0.5">
        <div class="h-full rounded-full bg-gradient-to-r <?= $netTaxableProfit > $thresholdLimit ? 'from-blue-600 to-rose-600' : 'from-emerald-500 to-blue-500' ?> transition-all duration-500" style="width: <?=$thresholdPercentage?>%;"></div>
    </div>

    <div class="flex justify-between text-xs text-slate-500">
        <span>AED 0.00 (0% Tax Rate)</span>
        <span class="font-bold text-slate-800">AED 375,000 (Small Business Exemption Threshold)</span>
        <span class="text-rose-600 font-bold">9% Standard CT Rate Above Threshold</span>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Gross Invoiced Revenue</span>
        <div class="text-2xl font-extrabold text-blue-600 mt-1"><?=money($totalRevenue)?></div>
        <span class="text-xs text-slate-500">Net Sales Revenue</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operating Expenses</span>
        <div class="text-2xl font-extrabold text-rose-600 mt-1"><?=money($totalExpenses)?></div>
        <span class="text-xs text-slate-500">Deductible Outflows</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Net Taxable Profit</span>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?=money($netTaxableProfit)?></div>
        <span class="text-xs text-slate-500">Taxable Income Pool</span>
    </div>
    <div class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-2xl p-5 border border-slate-800 shadow-lg">
        <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Estimated 9% Corporate Tax</span>
        <div class="text-2xl font-extrabold text-white mt-1"><?=money($estimatedCorporateTax)?></div>
        <span class="text-xs text-slate-400"><?= $netTaxableProfit > $thresholdLimit ? '9% Due on Profit Exceeding 375k' : '0% Tax Exempt (Below 375k)' ?></span>
    </div>
</div>

<!-- Detailed Calculation Breakdown Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="bg-slate-900 px-6 py-4 text-white flex justify-between items-center">
        <div>
            <h3 class="font-extrabold text-base">UAE CORPORATE TAX ASSESSMENT SUMMARY (TAX YEAR <?=$taxYear?>)</h3>
            <p class="text-xs text-slate-300">Under Ministry of Finance Cabinet Decision No. 116 of 2022</p>
        </div>
        <span class="px-3 py-1 bg-blue-500/20 text-blue-300 text-xs font-bold rounded-lg">FDL 47 / 2022</span>
    </div>

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Tax Calculation Step</th>
                <th class="px-6 py-3.5 text-right">Amount (AED)</th>
                <th class="px-6 py-3.5 text-right">Tax Rate</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">1. Total Taxable Revenue (Net Invoiced)</td>
                <td class="px-6 py-3.5 text-right font-extrabold text-slate-900"><?=money($totalRevenue)?></td>
                <td class="px-6 py-3.5 text-right font-semibold text-slate-500">—</td>
            </tr>
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">2. Less: Deductible Business Operating Expenses</td>
                <td class="px-6 py-3.5 text-right font-extrabold text-rose-600">(<?=money($totalExpenses)?>)</td>
                <td class="px-6 py-3.5 text-right font-semibold text-slate-500">—</td>
            </tr>
            <tr class="bg-slate-50 font-extrabold text-slate-900">
                <td class="px-6 py-3.5">3. NET OPERATING TAXABLE PROFIT</td>
                <td class="px-6 py-3.5 text-right text-emerald-600 text-base"><?=money($netTaxableProfit)?></td>
                <td class="px-6 py-3.5 text-right font-semibold text-slate-500">—</td>
            </tr>
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">4. Tax-Free Small Business Relief (SBR) Exemption Threshold</td>
                <td class="px-6 py-3.5 text-right font-extrabold text-slate-900">AED 375,000.00</td>
                <td class="px-6 py-3.5 text-right font-black text-emerald-600">0% RATE</td>
            </tr>
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">5. Net Taxable Income Subject to Corporate Tax (Above 375k)</td>
                <td class="px-6 py-3.5 text-right font-extrabold text-blue-600"><?=money($taxableExcess)?></td>
                <td class="px-6 py-3.5 text-right font-bold text-blue-600">9% RATE</td>
            </tr>
            <tr class="bg-slate-900 text-white font-extrabold text-base">
                <td class="px-6 py-4">ESTIMATED CORPORATE TAX DUE TO FTA</td>
                <td class="px-6 py-4 text-right text-amber-400 text-lg"><?=money($estimatedCorporateTax)?></td>
                <td class="px-6 py-4 text-right text-slate-300 text-xs">Filing Due within 9 Months</td>
            </tr>
        </tbody>
    </table>
</div>

<?php page_end(); ?>
