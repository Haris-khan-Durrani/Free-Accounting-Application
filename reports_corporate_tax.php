<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$taxYear   = $_GET['tax_year'] ?? date('Y');
$method    = in_array($_GET['method'] ?? '', ['accrual', 'cash'], true) ? $_GET['method'] : 'accrual';
$startDate = $taxYear . '-01-01';
$endDate   = $taxYear . '-12-31';

// Total Invoiced Revenue
if ($method === 'cash') {
    $stRev = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND payment_date BETWEEN ? AND ?");
    $stRev->execute([$tid, $startDate, $endDate]);
    $totalRevenue = (float)$stRev->fetchColumn();
} else {
    $stRev = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM invoices WHERE tenant_id = ? AND status != 'cancelled' AND invoice_date BETWEEN ? AND ?");
    $stRev->execute([$tid, $startDate, $endDate]);
    $totalRevenue = (float)$stRev->fetchColumn();
}

// Total Operating Expenses
$stExp = $pdo->prepare("SELECT COALESCE(SUM(subtotal), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
$stExp->execute([$tid, $startDate, $endDate]);
$totalExpenses = (float)$stExp->fetchColumn();

$netTaxableProfit = max(0, $totalRevenue - $totalExpenses);

// UAE Corporate Tax Rules (Federal Decree-Law No. 47 of 2022)
// 0% on Net Profit up to AED 375,000 (Small Business Relief Threshold)
// 9% on Net Profit exceeding AED 375,000
$thresholdLimit = 375000.00;
$taxableExcess = max(0, $netTaxableProfit - $thresholdLimit);
$estimatedCorporateTax = $taxableExcess * 0.09;

$thresholdPercentage = min(100, round(($netTaxableProfit / $thresholdLimit) * 100, 1));

page_start('UAE Corporate Tax Estimator');
?>

<!-- Official Print Corporate Header -->
<div class="hidden print:block mb-6 border-b-2 border-slate-900 pb-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tight"><?=e(tenant()['name'])?></h1>
            <p class="text-xs text-slate-600 font-semibold mt-0.5">TRN Tax Reg No: <strong><?=e(tenant()['tax_number'] ?? '100293847500003')?></strong> | <?=e(tenant()['address'] ?: 'United Arab Emirates')?></p>
        </div>
        <div class="text-right">
            <h2 class="text-lg font-black text-slate-900 uppercase">UAE Corporate Tax Assessment (9%)</h2>
            <p class="text-xs text-slate-500 font-bold">Tax Year <?=$taxYear?> (<?=strtoupper($method)?> Basis)</p>
        </div>
    </div>
</div>

<!-- Executive Screen Header & Actions -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8 pb-6 border-b border-slate-200/80 no-print">
    <div>
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-600 text-3xs font-black uppercase tracking-widest mb-2">
            <i class="fa-solid fa-scale-balanced"></i>
            <span>Federal Decree-Law No. 47 of 2022</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">UAE Corporate Tax (9%) & Profit Estimator</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500 max-w-3xl">
            Track your AED 375,000 Small Business Relief (SBR) threshold & estimated 9% Corporate Tax for <strong><?=e(tenant()['name'])?></strong>.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3 shrink-0">
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-2 border border-slate-800">
            <i class="fa-solid fa-print text-amber-400 text-sm"></i>
            <span>Print Tax Assessment PDF</span>
        </button>
    </div>
</div>

<!-- Interactive Filter Toolbar -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm mb-8 no-print">
    <form method="get" id="corpTaxFilterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
        
        <!-- Corporate Tax Year -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Corporate Tax Year</label>
            <select name="tax_year" onchange="document.getElementById('corpTaxFilterForm').submit()" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
                    <option value="<?=$y?>" <?=$y == $taxYear ? 'selected' : ''?>>Tax Year <?=$y?></option>
                <?php endfor; ?>
            </select>
        </div>

        <!-- Accounting Basis -->
        <div>
            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Accounting Basis</label>
            <select name="method" onchange="document.getElementById('corpTaxFilterForm').submit()" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="accrual" <?=$method==='accrual'?'selected':''?>>Accrual Basis (Invoiced Profit)</option>
                <option value="cash" <?=$method==='cash'?'selected':''?>>Cash Basis (Realized Cash Profit)</option>
            </select>
        </div>

        <div class="flex items-center">
            <button type="submit" class="w-full px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-xs transition-all flex items-center justify-center space-x-2">
                <i class="fa-solid fa-calculator"></i>
                <span>Recalculate Corporate Tax</span>
            </button>
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

    <div class="flex justify-between text-2xs text-slate-500">
        <span>AED 0.00 (0% Tax Rate)</span>
        <span class="font-bold text-slate-800">AED 375,000 (Small Business Exemption Threshold)</span>
        <span class="text-rose-600 font-bold">9% Standard CT Rate Above Threshold</span>
    </div>
</div>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-wider block mb-1">Gross Revenue</span>
        <div class="text-2xl font-black text-blue-600 tracking-tight"><?=money($totalRevenue)?></div>
        <span class="text-xs text-slate-500 font-medium">Total Invoiced Sales</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-wider block mb-1">Operating Expenses</span>
        <div class="text-2xl font-black text-rose-600 tracking-tight"><?=money($totalExpenses)?></div>
        <span class="text-xs text-slate-500 font-medium">Deductible Expenses</span>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-wider block mb-1">Net Taxable Profit</span>
        <div class="text-2xl font-black text-emerald-600 tracking-tight"><?=money($netTaxableProfit)?></div>
        <span class="text-xs text-slate-500 font-medium">Taxable Income Pool</span>
    </div>
    <div class="bg-slate-900 text-white rounded-2xl p-5 border border-slate-800 shadow-lg">
        <span class="text-3xs font-extrabold text-amber-400 uppercase tracking-wider block mb-1">Estimated 9% Corporate Tax</span>
        <div class="text-2xl font-black text-white tracking-tight"><?=money($estimatedCorporateTax)?></div>
        <span class="text-2xs text-slate-400 font-medium"><?= $netTaxableProfit > $thresholdLimit ? '9% Tax Due Above AED 375k' : '0% Exempt (Below AED 375k)' ?></span>
    </div>
</div>

<!-- Detailed Calculation Breakdown Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="bg-slate-900 px-6 py-4 text-white flex justify-between items-center">
        <div>
            <h3 class="font-extrabold text-base">UAE CORPORATE TAX ASSESSMENT SUMMARY (TAX YEAR <?=$taxYear?>)</h3>
            <p class="text-xs text-slate-300">Under Ministry of Finance Cabinet Decision No. 116 of 2022</p>
        </div>
        <span class="px-3 py-1 bg-blue-500/20 text-blue-300 text-xs font-black rounded-lg">FDL 47 / 2022</span>
    </div>

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                <th class="px-6 py-3.5">Tax Calculation Step</th>
                <th class="px-6 py-3.5 text-right">Amount (AED)</th>
                <th class="px-6 py-3.5 text-right">Applicable Tax Rate</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-sm">
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">1. Total Taxable Revenue (Sales Revenue)</td>
                <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900"><?=money($totalRevenue)?></td>
                <td class="px-6 py-3.5 text-right font-semibold text-slate-500">—</td>
            </tr>
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">2. Less: Deductible Business Operating Expenses</td>
                <td class="px-6 py-3.5 text-right font-mono font-bold text-rose-600">(<?=money($totalExpenses)?>)</td>
                <td class="px-6 py-3.5 text-right font-semibold text-slate-500">—</td>
            </tr>
            <tr class="bg-slate-100 font-extrabold text-slate-900">
                <td class="px-6 py-3.5">3. NET OPERATING TAXABLE PROFIT</td>
                <td class="px-6 py-3.5 text-right font-mono text-emerald-600 text-base"><?=money($netTaxableProfit)?></td>
                <td class="px-6 py-3.5 text-right font-semibold text-slate-500">—</td>
            </tr>
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">4. Small Business Relief (SBR) Tax-Free Exemption Threshold</td>
                <td class="px-6 py-3.5 text-right font-mono font-bold text-emerald-600">(<?=money(min($netTaxableProfit, $thresholdLimit))?>)</td>
                <td class="px-6 py-3.5 text-right font-bold text-emerald-700">0% Exemption</td>
            </tr>
            <tr>
                <td class="px-6 py-3.5 font-bold text-slate-800">5. Net Taxable Income Subject to 9% Corporate Tax</td>
                <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900"><?=money($taxableExcess)?></td>
                <td class="px-6 py-3.5 text-right font-bold text-slate-700">9% Standard Rate</td>
            </tr>
            <tr class="bg-slate-900 text-white font-black text-base">
                <td class="px-6 py-4">TOTAL ESTIMATED CORPORATE TAX DUE</td>
                <td class="px-6 py-4 text-right font-mono text-amber-400"><?=money($estimatedCorporateTax)?></td>
                <td class="px-6 py-4 text-right font-mono text-amber-400"><?= $netTaxableProfit > $thresholdLimit ? '9% CT Rate' : '0% Exempt' ?></td>
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
                I hereby declare that this Corporate Tax Calculation Assessment is prepared in compliance with Federal Decree-Law No. 47 of 2022 on the Taxation of Corporations and Businesses.
            </p>
        </div>
        <div class="text-right">
            <div class="border-b-2 border-slate-900 pb-1 w-56 ml-auto font-black text-slate-900 uppercase text-center">
                Authorized Tax Agent / Officer
            </div>
            <p class="text-3xs text-slate-500 mt-1 uppercase font-bold text-center">Signature & Official Corporate Stamp</p>
        </div>
    </div>
</div>

<?php page_end(); ?>
