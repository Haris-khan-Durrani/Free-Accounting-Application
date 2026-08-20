<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$error = '';

// Handle Adding Custom Head of Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_account') {
    verify_csrf();
    $code = trim($_POST['account_code'] ?? '');
    $name = trim($_POST['account_name'] ?? '');
    $type = $_POST['account_type'] ?? 'expense';
    $desc = trim($_POST['description'] ?? '');

    if (!$code || !$name) {
        $error = 'Account code and account name are required.';
    } else {
        try {
            $st = $pdo->prepare("INSERT INTO chart_of_accounts (tenant_id, account_code, account_name, account_type, description, is_system) VALUES (?, ?, ?, ?, ?, 0)");
            $st->execute([$tid, $code, $name, $type, $desc]);
            log_audit($pdo, 'create_account', 'chart_of_accounts', (int)$pdo->lastInsertId(), "Created ledger account $code - $name");
            flash('success', "Head of account '$code - $name' added successfully!");
            redirect('accounts.php');
        } catch (PDOException $e) {
            $error = 'Failed to add account. Account code must be unique.';
        }
    }
}

// Ensure default Chart of Accounts are pre-seeded
\Core\Tenant::seedAccounts($pdo, $tid);

$st = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE tenant_id = ? ORDER BY account_code ASC");
$st->execute([$tid]);
$accounts = $st->fetchAll();

page_start('Chart of Accounts');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Chart of Accounts (Heads of Accounts)</h1>
        <p class="mt-1 text-sm text-slate-500">Manage general ledger heads of accounts, assets, liabilities, and expense categories for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <button onclick="document.getElementById('new-account-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-sm transition-all">
        <i class="fa-solid fa-plus mr-2"></i>Add Head of Account
    </button>
</div>

<?php if ($error): ?><div class="alert error mb-6"><?=$error?></div><?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Active Heads of Accounts (<?=count($accounts)?>)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Account Code</th>
                    <th class="px-6 py-3.5">Account Name</th>
                    <th class="px-6 py-3.5">Account Type</th>
                    <th class="px-6 py-3.5">Description</th>
                    <th class="px-6 py-3.5 text-right">System Core</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($accounts as $a): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 font-mono font-extrabold text-blue-600"><?=e($a['account_code'])?></td>
                        <td class="px-6 py-4 font-bold text-slate-900"><?=e($a['account_name'])?></td>
                        <td class="px-6 py-4">
                            <?php
                            $typeClasses = [
                                'asset' => 'bg-emerald-100 text-emerald-800',
                                'liability' => 'bg-rose-100 text-rose-800',
                                'equity' => 'bg-blue-100 text-blue-800',
                                'revenue' => 'bg-purple-100 text-purple-800',
                                'expense' => 'bg-amber-100 text-amber-800'
                            ];
                            $tClass = $typeClasses[$a['account_type']] ?? 'bg-slate-100 text-slate-800';
                            ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold <?=$tClass?>"><?=strtoupper(e($a['account_type']))?></span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500"><?=e($a['description'] ?: '--')?></td>
                        <td class="px-6 py-4 text-right text-xs text-slate-500"><?=$a['is_system'] ? '<span class="text-slate-400 font-bold">System Default</span>' : '<span class="text-amber-600 font-bold">Custom Head</span>'?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Head of Account -->
<div id="new-account-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-lg font-bold text-slate-900">Add Custom Head of Account</h3>
            <button onclick="document.getElementById('new-account-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="add_account">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Account Code *</label>
                <input type="text" name="account_code" placeholder="e.g. 5400" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Account Name *</label>
                <input type="text" name="account_name" placeholder="e.g. Software & Subscriptions Expense" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Account Classification Type</label>
                <select name="account_type" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <option value="asset">Asset (Current / Fixed Assets)</option>
                    <option value="liability">Liability (Accounts Payable & Tax)</option>
                    <option value="equity">Equity (Owner Contributions & Retained)</option>
                    <option value="revenue">Revenue (Sales & Service Income)</option>
                    <option value="expense" selected>Expense (Operating Expenses)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Description / Purpose</label>
                <textarea name="description" rows="2" placeholder="Optional notes about this ledger head" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"></textarea>
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('new-account-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md">Save Head of Account</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>
