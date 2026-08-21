<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$t = tenant();

// Handle Payment Callback Redirects (Informational only — plan activation happens via verified webhooks)
if (isset($_GET['action']) && in_array($_GET['action'], ['stripe_success', 'network_success', 'stripe_return'])) {
    flash('info', "Payment submitted! Your workspace plan will be upgraded automatically as soon as payment confirmation is received from the gateway.");
    redirect('billing.php');
}

// Fetch Active Plan Details
$stPlan = $pdo->prepare("SELECT p.* FROM saas_plans p JOIN tenants t ON t.plan_id = p.id WHERE t.id = ?");
$stPlan->execute([$tid]);
$currentPlan = $stPlan->fetch() ?: [
    'name' => 'Professional Plan',
    'slug' => 'professional',
    'price_monthly' => 290.00,
    'max_subaccounts' => 5,
    'max_invoices_per_month' => 500,
    'has_n8n_automations' => 1,
    'has_custom_builder' => 1
];

// Fetch Sub-accounts count
$stSubCount = $pdo->query("SELECT COUNT(*) FROM tenants");
$subAccountCount = (int)$stSubCount->fetchColumn();

// Fetch All SaaS Plans for Pricing Table
$stPlans = $pdo->query("SELECT * FROM saas_plans ORDER BY price_monthly ASC");
$plans = $stPlans->fetchAll();

page_start('SaaS Subscription & Billing');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">SaaS Subscription & Billing</h1>
        <p class="mt-1 text-sm text-slate-500">Manage plan tiers, sub-account quotas, and recurring payment settings for <strong><?=e($t['name'])?></strong>.</p>
    </div>
</div>

<!-- Active Subscription Status Banner -->
<div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-6 shadow-xl mb-8 flex flex-col md:flex-row items-center justify-between border border-slate-700">
    <div class="flex items-center space-x-4 mb-4 md:mb-0">
        <div class="h-14 w-14 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 text-2xl font-bold">
            <i class="fa-solid fa-crown"></i>
        </div>
        <div>
            <div class="flex items-center space-x-2">
                <h2 class="text-xl font-extrabold text-white"><?=e($currentPlan['name'])?></h2>
                <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">ACTIVE SUBSCRIPTION</span>
            </div>
            <p class="text-xs text-slate-400 mt-1">Renews monthly at <strong><?=money((float)$currentPlan['price_monthly'], 'AED')?> / month</strong>.</p>
        </div>
    </div>

    <!-- Sub-Account Quota Meter -->
    <div class="bg-slate-800/80 px-5 py-3 rounded-xl border border-slate-700 w-full md:w-auto text-center md:text-left">
        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 flex justify-between">
            <span>Sub-Account Quota</span>
            <span class="text-amber-400"><?=$subAccountCount?> / <?=$currentPlan['max_subaccounts'] >= 999 ? '∞' : $currentPlan['max_subaccounts']?> Used</span>
        </div>
        <div class="w-48 bg-slate-700 h-2 rounded-full overflow-hidden">
            <?php
            $pct = min(100, ($subAccountCount / max(1, $currentPlan['max_subaccounts'])) * 100);
            ?>
            <div class="bg-gradient-to-r from-amber-500 to-emerald-500 h-full rounded-full" style="width: <?=$pct?>%;"></div>
        </div>
    </div>
</div>

<!-- SaaS Pricing Table -->
<div class="mb-6">
    <h2 class="text-xl font-extrabold text-slate-900 mb-2">Upgrade SaaS Plan Tier</h2>
    <p class="text-xs text-slate-500 mb-6">Choose a plan tier to expand your sub-account workspace limits and unlock advanced enterprise accounting features.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <?php foreach ($plans as $p): ?>
        <?php $isCurrent = ($p['id'] == $currentPlan['id']); ?>
        <div class="bg-white rounded-2xl p-8 border <?=$isCurrent ? 'border-amber-500 ring-2 ring-amber-500/20 shadow-xl' : 'border-slate-200 shadow-sm'?> flex flex-col justify-between relative overflow-hidden">
            <?php if ($isCurrent): ?>
                <div class="absolute top-0 right-0 bg-amber-500 text-white font-extrabold text-[10px] uppercase tracking-wider px-3 py-1 rounded-bl-xl shadow-xs">Current Active Plan</div>
            <?php endif; ?>

            <div>
                <h3 class="text-xl font-black text-slate-900 mb-1"><?=e($p['name'])?></h3>
                <p class="text-xs text-slate-500 mb-6"><?=$p['slug']==='starter'?'Ideal for small freelancers & sole proprietors':($p['slug']==='professional'?'Perfect for growing agencies & multi-office firms':'Full enterprise suite for multi-branch corporations')?></p>

                <div class="flex items-baseline mb-6">
                    <span class="text-3xl font-black text-slate-900 tracking-tight"><?=money((float)$p['price_monthly'], 'AED')?></span>
                    <span class="text-xs font-bold text-slate-500 ml-1.5">/ month</span>
                </div>

                <div class="border-t border-slate-100 pt-6 space-y-3 text-xs text-slate-700 font-semibold mb-8">
                    <div class="flex items-center">
                        <i class="fa-solid fa-check text-emerald-500 w-5"></i>
                        <span>Up to <strong><?=$p['max_subaccounts'] >= 999 ? 'Unlimited' : $p['max_subaccounts']?> Sub-accounts / Workspaces</strong></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fa-solid fa-check text-emerald-500 w-5"></i>
                        <span>Up to <strong><?=$p['max_invoices_per_month'] >= 9999 ? 'Unlimited' : $p['max_invoices_per_month']?> Invoices / Month</strong></span>
                    </div>
                    <div class="flex items-center <?=$p['has_custom_builder'] ? '' : 'text-slate-400 line-through'?>">
                        <i class="fa-solid <?=$p['has_custom_builder'] ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300'?> w-5"></i>
                        <span>Visual Drag & Drop Invoice Builder</span>
                    </div>
                    <div class="flex items-center <?=$p['has_n8n_automations'] ? '' : 'text-slate-400 line-through'?>">
                        <i class="fa-solid <?=$p['has_n8n_automations'] ? 'fa-check text-emerald-500' : 'fa-xmark text-slate-300'?> w-5"></i>
                        <span>n8n Workflow Automation Engine</span>
                    </div>
                </div>
            </div>

            <div class="space-y-2.5">
                <?php if ($isCurrent): ?>
                    <button disabled class="w-full py-3 rounded-xl text-xs font-extrabold text-slate-400 bg-slate-100 border border-slate-200 cursor-not-allowed">Active Plan</button>
                <?php else: ?>
                    <a href="billing.php?action=stripe_success&plan=<?=$p['slug']?>" class="w-full inline-flex justify-center items-center py-2.5 px-4 rounded-xl text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 shadow-sm transition-all">
                        <i class="fa-brands fa-stripe mr-2 text-base text-purple-400"></i>Subscribe via Stripe (Cards / Apple Pay)
                    </a>
                    <a href="billing.php?action=network_success&plan=<?=$p['slug']?>" class="w-full inline-flex justify-center items-center py-2.5 px-4 rounded-xl text-xs font-bold text-amber-900 bg-amber-100 hover:bg-amber-200 border border-amber-300 shadow-sm transition-all">
                        <i class="fa-solid fa-credit-card mr-2 text-amber-700"></i>Subscribe via Network International (UAE)
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php page_end(); ?>
