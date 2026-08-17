<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_tax') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $rate = (float)($_POST['rate_percent'] ?? 0);
    $type = $_POST['tax_type'] ?? 'vat';

    if (!$name) {
        $error = 'Tax rate name is required.';
    } else {
        $st = $pdo->prepare("INSERT INTO tax_rates (tenant_id, name, rate_percent, tax_type, is_default, is_active) VALUES (?, ?, ?, ?, 0, 1)");
        $st->execute([$tid, $name, $rate, $type]);
        log_audit($pdo, 'create_tax_rate', 'tax_rates', (int)$pdo->lastInsertId(), "Created tax rate $name ($rate%)");
        flash('success', "Tax rate '$name' added successfully!");
        redirect('tax_rates.php');
    }
}

$st = $pdo->prepare("SELECT * FROM tax_rates WHERE tenant_id = ? ORDER BY id DESC");
$st->execute([$tid]);
$taxRates = $st->fetchAll();

page_start('Tax Rates & VAT Settings');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Tax Rates & VAT Rules</h1>
        <p class="mt-1 text-sm text-slate-500">Configure regional tax rates, standard VAT, zero-rated, and exempt tax rules for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <button onclick="document.getElementById('new-tax-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-sm transition-all">
        <i class="fa-solid fa-plus mr-2"></i>Add Tax Rate
    </button>
</div>

<?php if ($error): ?><div class="alert error mb-6"><?=$error?></div><?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Configured Tax Rates (<?=count($taxRates)?>)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Tax Name</th>
                    <th class="px-6 py-3.5">Rate Percentage</th>
                    <th class="px-6 py-3.5">Tax Classification</th>
                    <th class="px-6 py-3.5">Default Rule</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($taxRates as $t): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 font-bold text-slate-900"><?=e($t['name'])?></td>
                        <td class="px-6 py-4 font-mono font-extrabold text-blue-600"><?=number_format((float)$t['rate_percent'], 2)?>%</td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700"><?=strtoupper(e($t['tax_type']))?></span>
                        </td>
                        <td class="px-6 py-4 text-xs font-bold text-slate-500"><?=$t['is_default'] ? '<span class="text-emerald-600">Default Rate</span>' : 'Standard Rate'?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Tax Rate -->
<div id="new-tax-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-lg font-bold text-slate-900">Add Tax Rate</h3>
            <button onclick="document.getElementById('new-tax-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="add_tax">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Tax Name *</label>
                <input type="text" name="name" placeholder="e.g. UAE VAT 5%" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Tax Percentage (%) *</label>
                <input type="number" step="0.01" name="rate_percent" placeholder="5.00" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Tax Classification</label>
                <select name="tax_type" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
                    <option value="vat">VAT (Value Added Tax)</option>
                    <option value="sales_tax">Sales Tax</option>
                    <option value="zero_rated">Zero Rated (0%)</option>
                    <option value="exempt">Tax Exempt</option>
                </select>
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('new-tax-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-amber-500 to-amber-600 shadow-md">Save Tax Rate</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>
