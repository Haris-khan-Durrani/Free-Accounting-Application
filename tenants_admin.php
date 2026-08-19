<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];

// Process Tenant CRUD, API Key Regeneration, and Trial Extensions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_tenant') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $currency = trim($_POST['currency'] ?? 'AED');
        $planId = (int)($_POST['plan_id'] ?? 2);
        $trialMonths = max(1, (int)($_POST['trial_months'] ?? 4));
        $isUnlimited = (int)($_POST['is_unlimited'] ?? 0);

        if (!$name || !$code) {
            flash('error', 'Company name and workspace code are required.');
            redirect('tenants_admin');
        }

        $apiKey = 'os_' . bin2hex(random_bytes(16));
        $subStatus = ($isUnlimited === 1) ? 'lifetime' : 'trial';
        $trialEndsAt = ($isUnlimited === 1) ? null : date('Y-m-d', strtotime("+$trialMonths months"));

        try {
            $st = $pdo->prepare("INSERT INTO tenants (name, code, currency, status, plan_id, subscription_status, trial_ends_at, api_key, custom_trial_months) VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?)");
            $st->execute([$name, strtolower($code), $currency, $planId, $subStatus, $trialEndsAt, $apiKey, $trialMonths]);
            $tenantId = (int)$pdo->lastInsertId();

            \Core\Tenant::seedAccounts($pdo, $tenantId);

            $msg = ($isUnlimited === 1) 
                ? "Tenant Workspace '$name' created with Permanent Internal Unlimited Access." 
                : "Tenant Workspace '$name' created with a $trialMonths-month free trial.";
            flash('success', $msg);
        } catch (PDOException $e) {
            flash('error', 'Failed to create workspace. Workspace code must be unique.');
        }
        redirect('tenants_admin');
    }

    if ($action === 'toggle_unlimited') {
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        $currentStatus = $_POST['current_status'] ?? '';
        $newStatus = ($currentStatus === 'lifetime') ? 'trial' : 'lifetime';
        $newExpiry = ($newStatus === 'trial') ? date('Y-m-d', strtotime('+4 months')) : null;

        $st = $pdo->prepare("UPDATE tenants SET subscription_status = ?, trial_ends_at = ? WHERE id = ?");
        $st->execute([$newStatus, $newExpiry, $targetTenantId]);

        flash('success', ($newStatus === 'lifetime') ? "Permanent Internal Unlimited Access granted to workspace." : "Switched workspace to standard trial mode (+4 Mo).");
        redirect('tenants_admin');
    }

    if ($action === 'regenerate_api_key') {
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        $newApiKey = 'os_' . bin2hex(random_bytes(16));

        $st = $pdo->prepare("UPDATE tenants SET api_key = ? WHERE id = ?");
        $st->execute([$newApiKey, $targetTenantId]);

        flash('success', "REST API Access Key regenerated successfully.");
        redirect('tenants_admin');
    }

    if ($action === 'extend_trial') {
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        $trialMonths = (int)($_POST['trial_months'] ?? 0);

        $trialMonths = max(1, $trialMonths);
        $newExpiry = date('Y-m-d', strtotime("+$trialMonths months"));

        $st = $pdo->prepare("UPDATE tenants SET subscription_status = 'trial', trial_ends_at = ? WHERE id = ?");
        $st->execute([$newExpiry, $targetTenantId]);

        flash('success', "Trial period extended to $newExpiry.");
        redirect('tenants_admin');
    }
}

// Fetch All Registered SaaS Tenants
$stTenants = $pdo->query("SELECT t.*, p.name plan_name, p.price_monthly, (SELECT COUNT(*) FROM invoices WHERE tenant_id = t.id) invoice_count FROM tenants t LEFT JOIN saas_plans p ON p.id = t.plan_id ORDER BY t.id DESC");
$tenants = $stTenants->fetchAll();

// Fetch SaaS Plans for dropdown
$stPlans = $pdo->query("SELECT * FROM saas_plans ORDER BY price_monthly ASC");
$plans = $stPlans->fetchAll();

page_start('SaaS Tenant & Trial Manager (REST API)');
?>

<!-- Header -->
<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">SaaS Tenant & Trial Management</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Configure free trial periods (e.g. 4 Months Free), issue REST API keys, and manage multi-tenant accounts.</p>
    </div>
    <div class="mt-4 sm:mt-0 flex space-x-3">
        <a href="api_playground" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-code text-amber-500 mr-2"></i>REST API Documentation & Playground
        </a>
    </div>
</div>

<!-- API Banner -->
<div class="bg-slate-950 rounded-2xl p-6 text-white shadow-xl mb-8 border border-slate-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div class="flex items-center space-x-4">
        <div class="h-12 w-12 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 font-bold text-xl">
            <i class="fa-solid fa-plug"></i>
        </div>
        <div>
            <h3 class="text-base font-extrabold text-white">External REST API Onboarding Enabled</h3>
            <p class="text-xs text-slate-400 mt-0.5">Programmatically onboard sub-accounts with custom 4-month free trials via <code class="bg-slate-900 text-amber-300 px-2 py-0.5 rounded font-mono text-2xs">POST /api?action=create_tenant</code></p>
        </div>
    </div>
    <a href="api_playground" class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold text-slate-950 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-500 hover:to-amber-600 shadow-md">
        Launch Interactive API Playground →
    </a>
</div>

<!-- Registered Tenants Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Registered SaaS Tenants (<?=count($tenants)?>)</h2>
        <button onclick="document.getElementById('new-tenant-modal').classList.remove('hidden')" class="inline-flex items-center px-3.5 py-1.5 border border-transparent text-xs font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-sm">
            <i class="fa-solid fa-plus mr-1.5"></i>Create New Tenant
        </button>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Tenant / Workspace</th>
                    <th class="px-6 py-3.5">Subscription Plan</th>
                    <th class="px-6 py-3.5 text-center">Status</th>
                    <th class="px-6 py-3.5">Trial Expiry Date</th>
                    <th class="px-6 py-3.5">API Access Key</th>
                    <th class="px-6 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($tenants as $t): ?>
                    <?php
                    $trialEnds = $t['trial_ends_at'] ?: date('Y-m-d', strtotime('+30 days'));
                    $daysLeft = (int)floor((strtotime($trialEnds) - time()) / 86400);
                    $isExpired = ($t['subscription_status'] === 'trial' && $daysLeft < 0);
                    $isLifetime = ($t['subscription_status'] === 'lifetime');
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4">
                            <div class="font-extrabold text-slate-900 text-sm"><?=e($t['name'])?></div>
                            <div class="text-2xs font-mono text-slate-400">Code: <?=e($t['code'])?> | Invoices: <?=e((string)$t['invoice_count'])?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-2xs font-bold bg-amber-50 text-amber-800 border border-amber-200"><?=e($t['plan_name'] ?: 'Enterprise')?></span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($isLifetime): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-purple-100 text-purple-800">UNLIMITED</span>
                            <?php elseif ($isExpired): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-rose-100 text-rose-800">EXPIRED</span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-emerald-100 text-emerald-800">ACTIVE PAID</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-600">
                            <?php if ($isLifetime): ?>
                                <span class="text-purple-700 font-bold">Lifetime Access</span>
                            <?php else: ?>
                                <div><?=e(date('d M Y', strtotime($trialEnds)))?></div>
                                <div class="text-2xs font-bold <?=$daysLeft >= 0 ? 'text-emerald-600' : 'text-rose-600'?>"><?=$daysLeft >= 0 ? "$daysLeft Days Remaining" : "Expired"?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 font-mono text-xs">
                            <div class="flex items-center space-x-1">
                                <span class="bg-slate-100 px-2 py-1 rounded text-2xs font-bold text-slate-700 select-all"><?=e($t['api_key'] ? substr($t['api_key'], 0, 16) . '...' : 'None')?></span>
                                <form method="post" class="inline">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="regenerate_api_key">
                                    <input type="hidden" name="tenant_id" value="<?=$t['id']?>">
                                    <button type="submit" onclick="return confirm('Regenerate REST API Access Key?')" class="text-slate-400 hover:text-amber-600 p-1" title="Regenerate Key"><i class="fa-solid fa-arrows-rotate"></i></button>
                                </form>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right space-x-1">
                            <a href="branding.php?tenant_id=<?=$t['id']?>" class="inline-flex items-center px-2.5 py-1 text-2xs font-extrabold text-amber-800 bg-amber-50 hover:bg-amber-100 rounded-lg border border-amber-200 transition-all" title="Configure Logo, Theme Colors & Invoice Customization">
                                <i class="fa-solid fa-palette mr-1 text-amber-600"></i>Branding
                            </a>

                            <form method="post" class="inline">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="toggle_unlimited">
                                <input type="hidden" name="tenant_id" value="<?=$t['id']?>">
                                <input type="hidden" name="current_status" value="<?=e($t['subscription_status'])?>">
                                <?php if ($isLifetime): ?>
                                    <button type="submit" class="px-2.5 py-1 text-2xs font-extrabold text-purple-800 bg-purple-100 hover:bg-purple-200 rounded-lg border border-purple-300" title="Click to convert to standard trial">
                                        <i class="fa-solid fa-infinity mr-1"></i>Unlimited (Active)
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="px-2.5 py-1 text-2xs font-extrabold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg border border-purple-200" title="Grant Permanent Internal Unlimited Access">
                                        <i class="fa-solid fa-bolt mr-1"></i>Grant Unlimited
                                    </button>
                                <?php endif; ?>
                            </form>

                            <form method="post" class="inline">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="extend_trial">
                                <input type="hidden" name="tenant_id" value="<?=$t['id']?>">
                                <input type="hidden" name="trial_months" value="4">
                                <button type="submit" class="px-2.5 py-1 text-2xs font-extrabold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200">+4 Mo Free</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Tenant -->
<div id="new-tenant-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-lg font-bold text-slate-900">Create New Tenant Workspace</h3>
            <button onclick="document.getElementById('new-tenant-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="create_tenant">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Company Name *</label>
                <input type="text" name="name" required placeholder="e.g. Dubai Trade LLC" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Unique Workspace Code *</label>
                <input type="text" name="code" required placeholder="e.g. dubaitrade" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-mono text-slate-900">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">SaaS Plan Tier</label>
                    <select name="plan_id" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900">
                        <?php foreach ($plans as $p): ?>
                            <option value="<?=$p['id']?>" <?=$p['slug']==='professional'?'selected':''?>><?=e($p['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Free Trial Months</label>
                    <input type="number" name="trial_months" value="4" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900">
                </div>
            </div>

            <!-- Unlimited Internal Access Checkbox -->
            <div class="p-3.5 bg-purple-50 rounded-xl border border-purple-200">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" name="is_unlimited" value="1" class="w-4 h-4 text-purple-600 rounded border-slate-300 focus:ring-purple-500">
                    <div>
                        <div class="text-xs font-extrabold text-purple-900">Assign Permanent Internal Unlimited Access</div>
                        <div class="text-2xs text-purple-700">Bypasses trial expiration dates and invoice/user quotas for internal corporate use.</div>
                    </div>
                </label>
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('new-tenant-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md">Create Tenant</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>
