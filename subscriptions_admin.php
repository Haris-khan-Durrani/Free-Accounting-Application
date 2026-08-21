<?php
require __DIR__ . '/bootstrap.php';
require_platform_admin();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];

// Process SaaS Plan Tier CRUD, Lifetime Internal Status, and Trial Extensions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_plan') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $priceMonthly = (float)($_POST['price_monthly'] ?? 0);
        $priceYearly = (float)($_POST['price_yearly'] ?? 0);
        $maxSubaccounts = (int)($_POST['max_subaccounts'] ?? 1);
        $maxInvoices = (int)($_POST['max_invoices_per_month'] ?? 100);
        $maxTeamUsers = (int)($_POST['max_team_users'] ?? 5);
        $hasN8n = isset($_POST['has_n8n_automations']) ? 1 : 0;
        $hasBuilder = isset($_POST['has_custom_builder']) ? 1 : 0;

        if (!$name || !$slug) {
            flash('error', 'Plan name and unique slug are required.');
            redirect('subscriptions_admin');
        }

        if ($id > 0) {
            $st = $pdo->prepare("UPDATE saas_plans SET name = ?, slug = ?, price_monthly = ?, price_yearly = ?, max_subaccounts = ?, max_invoices_per_month = ?, max_team_users = ?, has_n8n_automations = ?, has_custom_builder = ? WHERE id = ?");
            $st->execute([$name, $slug, $priceMonthly, $priceYearly, $maxSubaccounts, $maxInvoices, $maxTeamUsers, $hasN8n, $hasBuilder, $id]);
            flash('success', "SaaS Plan '$name' updated successfully.");
        } else {
            $st = $pdo->prepare("INSERT INTO saas_plans (name, slug, price_monthly, price_yearly, max_subaccounts, max_invoices_per_month, max_team_users, has_n8n_automations, has_custom_builder) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $st->execute([$name, $slug, $priceMonthly, $priceYearly, $maxSubaccounts, $maxInvoices, $maxTeamUsers, $hasN8n, $hasBuilder]);
            flash('success', "New SaaS Plan '$name' created.");
        }
        redirect('subscriptions_admin');
    }

    if ($action === 'set_lifetime') {
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        $st = $pdo->prepare("UPDATE tenants SET subscription_status = 'lifetime', trial_ends_at = NULL WHERE id = ?");
        $st->execute([$targetTenantId]);

        flash('success', "Tenant #$targetTenantId marked as Internal Unlimited (Lifetime Access). All limits bypassed!");
        redirect('subscriptions_admin');
    }

    if ($action === 'extend_trial') {
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        $trialMonths = (int)($_POST['trial_months'] ?? 0);
        $customDate = $_POST['custom_date'] ?? '';

        if ($customDate) {
            $newExpiry = $customDate;
        } else {
            $trialMonths = max(1, $trialMonths);
            $newExpiry = date('Y-m-d', strtotime("+$trialMonths months"));
        }

        $st = $pdo->prepare("UPDATE tenants SET subscription_status = 'trial', trial_ends_at = ? WHERE id = ?");
        $st->execute([$newExpiry, $targetTenantId]);

        flash('success', "Trial period for tenant #$targetTenantId extended to $newExpiry.");
        redirect('subscriptions_admin');
    }
}

$editPlan = null;
if (isset($_GET['edit_plan'])) {
    $st = $pdo->prepare("SELECT * FROM saas_plans WHERE id = ?");
    $st->execute([(int)$_GET['edit_plan']]);
    $editPlan = $st->fetch();
}

// Fetch All SaaS Plans
$stPlans = $pdo->query("SELECT p.*, COUNT(t.id) active_tenants_count FROM saas_plans p LEFT JOIN tenants t ON t.plan_id = p.id GROUP BY p.id ORDER BY p.price_monthly ASC");
$plans = $stPlans->fetchAll();

// Fetch Active Tenant Subscriptions Audit
$stSubAudit = $pdo->query("SELECT t.*, p.name plan_name, p.price_monthly FROM tenants t LEFT JOIN saas_plans p ON p.id = t.plan_id ORDER BY t.id DESC");
$subAudit = $stSubAudit->fetchAll();

page_start('SaaS Subscription Plans Manager');
?>

<!-- Header -->
<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Super Admin SaaS Subscription Manager</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Configure subscription tiers, adjust user limits, set lifetime internal unlimited access, and extend trial periods.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
    
    <!-- SaaS Plan Form -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm h-fit">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center">
            <i class="fa-solid fa-crown text-amber-500 mr-2"></i><?=$editPlan ? 'Edit SaaS Tier' : 'Create New SaaS Tier'?>
        </h2>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="save_plan">
            <input type="hidden" name="id" value="<?=e((string)($editPlan['id'] ?? ''))?>">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Plan Name *</label>
                <input type="text" name="name" value="<?=e($editPlan['name'] ?? '')?>" placeholder="e.g. Enterprise Plan" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Unique Slug *</label>
                <input type="text" name="slug" value="<?=e($editPlan['slug'] ?? '')?>" placeholder="e.g. enterprise" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-mono text-slate-900">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Monthly Price (AED)</label>
                    <input type="number" step="0.01" name="price_monthly" value="<?=e((string)($editPlan['price_monthly'] ?? 290.00))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-bold font-mono text-slate-900">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Yearly Price (AED)</label>
                    <input type="number" step="0.01" name="price_yearly" value="<?=e((string)($editPlan['price_yearly'] ?? 2900.00))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-bold font-mono text-slate-900">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="block text-2xs font-bold text-slate-600 uppercase mb-1.5">Max Workspaces</label>
                    <input type="number" name="max_subaccounts" value="<?=e((string)($editPlan['max_subaccounts'] ?? 5))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-2.5 py-2 text-xs font-bold text-slate-900">
                </div>
                <div>
                    <label class="block text-2xs font-bold text-slate-600 uppercase mb-1.5">Max Invoices/Mo</label>
                    <input type="number" name="max_invoices_per_month" value="<?=e((string)($editPlan['max_invoices_per_month'] ?? 500))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-2.5 py-2 text-xs font-bold text-slate-900">
                </div>
                <div>
                    <label class="block text-2xs font-bold text-slate-600 uppercase mb-1.5">Max Team Users</label>
                    <input type="number" name="max_team_users" value="<?=e((string)($editPlan['max_team_users'] ?? 10))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-2.5 py-2 text-xs font-bold text-slate-900">
                </div>
            </div>

            <div class="space-y-2 pt-2 border-t border-slate-100">
                <label class="flex items-center space-x-2 text-xs font-bold text-slate-700">
                    <input type="checkbox" name="has_n8n_automations" value="1" <?=!isset($editPlan) || $editPlan['has_n8n_automations'] ? 'checked' : ''?> class="rounded text-amber-500 focus:ring-amber-500">
                    <span>Include n8n Webhook Automations</span>
                </label>
                <label class="flex items-center space-x-2 text-xs font-bold text-slate-700">
                    <input type="checkbox" name="has_custom_builder" value="1" <?=!isset($editPlan) || $editPlan['has_custom_builder'] ? 'checked' : ''?> class="rounded text-amber-500 focus:ring-amber-500">
                    <span>Include Drag & Drop Custom Builder</span>
                </label>
            </div>

            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
                <i class="fa-solid fa-floppy-disk mr-2"></i><?=$editPlan ? 'Update Plan Tier' : 'Create SaaS Plan Tier'?>
            </button>
        </form>
    </div>

    <!-- Active Subscription Tiers List -->
    <div class="lg:col-span-2 space-y-4">
        <h2 class="text-base font-bold text-slate-900 mb-2">Available Subscription Plan Tiers</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($plans as $p): ?>
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm relative overflow-hidden flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-black text-slate-900"><?=e($p['name'])?></h3>
                            <span class="text-xs font-mono font-bold text-amber-600 uppercase bg-amber-50 px-2.5 py-0.5 rounded-full"><?=e($p['slug'])?></span>
                        </div>
                        <div class="mt-3 flex items-baseline">
                            <span class="text-2xl font-black text-slate-900 font-mono"><?=money((float)$p['price_monthly'], $p['currency'])?></span>
                            <span class="text-xs text-slate-400 font-bold ml-1">/ month</span>
                        </div>
                        <ul class="mt-4 space-y-2 text-xs text-slate-600 font-medium border-t border-slate-100 pt-3">
                            <li class="flex items-center"><i class="fa-solid fa-users-gear text-indigo-500 mr-2"></i>Up to <strong><?=e((string)($p['max_team_users'] ?? 5))?> Team Users Allowed</strong></li>
                            <li class="flex items-center"><i class="fa-solid fa-check text-emerald-500 mr-2"></i>Up to <strong><?=e((string)$p['max_subaccounts'])?> Workspaces</strong></li>
                            <li class="flex items-center"><i class="fa-solid fa-check text-emerald-500 mr-2"></i>Up to <strong><?=e((string)$p['max_invoices_per_month'])?> Invoices</strong> / month</li>
                            <li class="flex items-center"><i class="fa-solid fa-<?=$p['has_n8n_automations']?'check text-emerald-500':'xmark text-slate-300'?> mr-2"></i>n8n Automations</li>
                            <li class="flex items-center"><i class="fa-solid fa-<?=$p['has_custom_builder']?'check text-emerald-500':'xmark text-slate-300'?> mr-2"></i>Visual Drag & Drop Builder</li>
                        </ul>
                    </div>
                    <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-2xs font-bold text-slate-400"><?=e((string)$p['active_tenants_count'])?> Active Subscribers</span>
                        <a href="subscriptions_admin?edit_plan=<?=$p['id']?>" class="text-xs font-bold text-blue-600 hover:underline">Edit Specs →</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Active Tenant Subscriptions Audit Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-5 border-b border-slate-100">
        <h2 class="text-base font-bold text-slate-900">Active Tenant Subscriptions & Internal Status Audit</h2>
        <p class="text-xs text-slate-500">Live subscription status, trial expiry dates, lifetime internal access toggles, and trial extension controls</p>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Company Workspace</th>
                    <th class="px-6 py-3.5">Plan Tier</th>
                    <th class="px-6 py-3.5">Monthly Billing</th>
                    <th class="px-6 py-3.5 text-center">Status</th>
                    <th class="px-6 py-3.5">Trial / Renewal Expiry</th>
                    <th class="px-6 py-3.5 text-right">Trial & Internal Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($subAudit as $s): ?>
                    <?php
                    $trialEnds = $s['trial_ends_at'] ?: date('Y-m-d', strtotime('+30 days'));
                    $daysLeft = (int)floor((strtotime($trialEnds) - time()) / 86400);
                    $isExpired = ($s['subscription_status'] === 'trial' && $daysLeft < 0);
                    $isLifetime = ($s['subscription_status'] === 'lifetime');
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4">
                            <div class="font-extrabold text-slate-900 text-sm"><?=e($s['name'])?></div>
                            <div class="text-2xs font-mono text-slate-400">CODE: <?=e($s['code'])?></div>
                        </td>
                        <td class="px-6 py-4 font-bold text-amber-600"><?=e($s['plan_name'] ?: 'Enterprise Plan')?></td>
                        <td class="px-6 py-4 font-mono font-bold text-slate-900"><?=money((float)($s['price_monthly'] ?: 750), $s['currency'])?></td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($isLifetime): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-purple-100 text-purple-800 border border-purple-200"><i class="fa-solid fa-infinity mr-1"></i>INTERNAL UNLIMITED</span>
                            <?php elseif ($isExpired): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-rose-100 text-rose-800">EXPIRED TRIAL</span>
                            <?php elseif ($s['subscription_status'] === 'trial'): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-amber-100 text-amber-800">FREE TRIAL</span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-emerald-100 text-emerald-800">ACTIVE PAID</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-600">
                            <?php if ($isLifetime): ?>
                                <div class="text-xs font-bold text-purple-700">Permanent Unlimited</div>
                                <div class="text-2xs text-slate-400">Bypasses all limits</div>
                            <?php else: ?>
                                <div><?=e(date('d M Y', strtotime($trialEnds)))?></div>
                                <div class="text-2xs font-bold <?=$daysLeft >= 0 ? 'text-emerald-600' : 'text-rose-600'?>">
                                    <?=$daysLeft >= 0 ? "$daysLeft Days Left" : "Expired " . abs($daysLeft) . " days ago"?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right space-x-1.5">
                            <?php if (!$isLifetime): ?>
                                <form method="post" class="inline">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="set_lifetime">
                                    <input type="hidden" name="tenant_id" value="<?=$s['id']?>">
                                    <button type="submit" onclick="return confirm('Set tenant as Internal Unlimited (Lifetime Access)?')" class="px-2 py-1 text-2xs font-extrabold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg border border-purple-200" title="Grant Permanent Unlimited Internal Status"><i class="fa-solid fa-infinity mr-1"></i>Internal Unlimited</button>
                                </form>
                            <?php endif; ?>

                            <form method="post" class="inline">
                                <?=csrf_field()?>
                                <input type="hidden" name="action" value="extend_trial">
                                <input type="hidden" name="tenant_id" value="<?=$s['id']?>">
                                <input type="hidden" name="trial_months" value="4">
                                <button type="submit" class="px-2.5 py-1 text-2xs font-extrabold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200">+4 Mo Free</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Touch View -->
    <div class="sm:hidden divide-y divide-slate-100">
        <?php foreach ($subAudit as $s): ?>
            <?php
            $trialEnds = $s['trial_ends_at'] ?: date('Y-m-d', strtotime('+30 days'));
            $daysLeft = (int)floor((strtotime($trialEnds) - time()) / 86400);
            $isExpired = ($s['subscription_status'] === 'trial' && $daysLeft < 0);
            $isLifetime = ($s['subscription_status'] === 'lifetime');
            ?>
            <div class="p-4 hover:bg-slate-50 transition-colors">
                <div class="flex items-center justify-between mb-1.5">
                    <div class="font-black text-slate-900 text-sm"><?=e($s['name'])?></div>
                    <?php if ($isLifetime): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-purple-100 text-purple-800">UNLIMITED</span>
                    <?php elseif ($isExpired): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-rose-100 text-rose-800">EXPIRED</span>
                    <?php else: ?>
                        <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-amber-100 text-amber-800">TRIAL (<?=$daysLeft?>d)</span>
                    <?php endif; ?>
                </div>
                <div class="text-2xs text-slate-500 font-semibold mb-3">
                    Plan: <strong><?=e($s['plan_name'] ?: 'Enterprise Plan')?></strong>
                </div>
                <div class="flex items-center justify-end space-x-1.5">
                    <?php if (!$isLifetime): ?>
                        <form method="post" class="inline">
                            <?=csrf_field()?>
                            <input type="hidden" name="action" value="set_lifetime">
                            <input type="hidden" name="tenant_id" value="<?=$s['id']?>">
                            <button type="submit" class="px-2.5 py-1 text-2xs font-bold text-purple-700 bg-purple-50 rounded-lg border border-purple-200">Set Unlimited</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" class="inline">
                        <?=csrf_field()?>
                        <input type="hidden" name="action" value="extend_trial">
                        <input type="hidden" name="tenant_id" value="<?=$s['id']?>">
                        <input type="hidden" name="trial_months" value="4">
                        <button type="submit" class="px-2.5 py-1 text-2xs font-bold text-emerald-700 bg-emerald-50 rounded-lg border border-emerald-200">+4 Mo</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php page_end(); ?>
