<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$currentTid = tenant_id();
$error = '';

// Fetch active plan limit
$stPlan = $pdo->prepare("SELECT p.* FROM saas_plans p JOIN tenants t ON t.plan_id = p.id WHERE t.id = ?");
$stPlan->execute([$currentTid]);
$activePlan = $stPlan->fetch() ?: ['max_subaccounts' => 5];
$maxAllowedSubAccounts = (int)($activePlan['max_subaccounts'] ?? 5);

// Handle Switch Tenant
if (isset($_GET['switch'])) {
    $targetId = (int)$_GET['switch'];
    $userId   = (int)($_SESSION['user_id'] ?? 0);

    // Verify the user actually has access to this tenant (or is owner)
    $hasAccess = has_role(['owner']);
    if (!$hasAccess) {
        $stCheck = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id = ?");
        $stCheck->execute([$userId, $targetId]);
        $hasAccess = (int)$stCheck->fetchColumn() > 0;
    }

    if ($hasAccess) {
        $st = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $st->execute([$targetId]);
        $t = $st->fetch();
        if ($t) {
            // Update all tenant session keys so Tenant::getActiveId() picks up the new one
            $_SESSION['tenant_id']        = $t['id'];
            $_SESSION['active_tenant_id'] = $t['id'];
            $_SESSION['user_tenant_id']   = $t['id'];
            // Clear cached tenant info so the switched workspace is loaded fresh
            \Core\Tenant::forgetCache();
            flash('success', 'Switched active sub-account workspace to: ' . $t['name']);
        }
    } else {
        flash('error', 'Access denied. You are not assigned to that workspace.');
    }
    redirect('subaccounts.php');
}

// Count total sub-accounts
$stSubCount = $pdo->query("SELECT COUNT(*) FROM tenants");
$currentSubAccountCount = (int)$stSubCount->fetchColumn();
$isQuotaExceeded = ($currentSubAccountCount >= $maxAllowedSubAccounts);

// Handle Create New Tenant / Sub-account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_tenant') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $code = strtolower(trim($_POST['code'] ?? ''));
    $currency = $_POST['currency'] ?? 'AED';

    if ($isQuotaExceeded) {
        $error = "Sub-account limit reached ({$currentSubAccountCount} / {$maxAllowedSubAccounts}). Please upgrade your SaaS subscription on the Billing page to create more sub-accounts.";
    } elseif (!$name || !$code) {
        $error = 'Company name and unique workspace code are required.';
    } else {
        try {
            $st = $pdo->prepare("INSERT INTO tenants (name, code, currency, country_code) VALUES (?, ?, ?, 'AE')");
            $st->execute([$name, $code, $currency]);
            $newTenantId = (int)$pdo->lastInsertId();

            // Seed Branding Settings for new tenant
            $stB = $pdo->prepare("INSERT INTO branding_settings (tenant_id, company_name) VALUES (?, ?)");
            $stB->execute([$newTenantId, $name]);

            // Seed Chart of Accounts for new tenant
            seed_chart_of_accounts($pdo, $newTenantId);

            log_audit($pdo, 'create_tenant', 'tenants', $newTenantId, "Created new sub-account workspace: $name ($code)");
            flash('success', "New sub-account workspace '$name' created successfully!");
            redirect('subaccounts.php');
        } catch (PDOException $e) {
            $error = 'Failed to create workspace. Code must be unique across the platform.';
        }
    }
}

$tenants = $pdo->query("SELECT * FROM tenants ORDER BY id ASC")->fetchAll();

page_start('Multi-Tenant Workspaces & Sub-Accounts');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Workspaces & Sub-Accounts</h1>
        <p class="mt-1 text-sm text-slate-500">Manage multiple business entities, branch accounts, and client workspaces from one single dashboard.</p>
    </div>
    <div class="flex space-x-3">
        <a href="billing.php" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-crown mr-2 text-amber-500"></i>Subscription & Quotas (<?=$currentSubAccountCount?> / <?=$maxAllowedSubAccounts >= 999 ? '∞' : $maxAllowedSubAccounts?>)
        </a>
        <button onclick="document.getElementById('new-tenant-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-sm transition-all">
            <i class="fa-solid fa-plus mr-2"></i>+ Create Sub-Account Workspace
        </button>
    </div>
</div>

<?php if ($error): ?><div class="alert error mb-6"><?=$error?></div><?php endif; ?>

<?php if ($isQuotaExceeded): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl p-5 mb-8 flex items-center justify-between shadow-sm">
        <div class="flex items-center space-x-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-2xl"></i>
            <div>
                <strong class="font-bold text-sm block">Sub-Account Workspace Quota Reached</strong>
                <span class="text-xs text-amber-700">Your active plan allows up to <strong><?=$maxAllowedSubAccounts?> sub-accounts</strong>. Upgrade your SaaS subscription to create more workspaces.</span>
            </div>
        </div>
        <a href="billing.php" class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-xs">Upgrade Plan Tier</a>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($tenants as $t): ?>
        <?php $isActive = ($t['id'] == $currentTid); ?>
        <div class="bg-white rounded-2xl p-6 border <?=$isActive ? 'border-amber-500 ring-2 ring-amber-500/20 shadow-md' : 'border-slate-200 shadow-sm'?> flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold <?= $isActive ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' ?>">
                        CODE: <?= strtoupper(e($t['code'])) ?>
                    </span>
                    <?php if ($isActive): ?>
                        <span class="flex items-center text-xs font-extrabold text-amber-600">
                            <i class="fa-solid fa-circle text-[8px] mr-1.5 animate-pulse"></i> ACTIVE WORKSPACE
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-1"><?= e($t['name']) ?></h3>
                <p class="text-xs text-slate-500 mb-4">Default Currency: <strong><?= e($t['currency']) ?></strong></p>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <span class="text-xs text-slate-400">ID: #<?= $t['id'] ?></span>
                <?php if ($isActive): ?>
                    <span class="text-xs font-bold text-slate-400 bg-slate-100 px-3 py-1.5 rounded-xl">Currently Active</span>
                <?php else: ?>
                    <a href="subaccounts.php?switch=<?= $t['id'] ?>" class="inline-flex items-center px-3.5 py-1.5 border border-slate-300 text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50 shadow-2xs">
                        <i class="fa-solid fa-right-to-bracket mr-1.5"></i>Switch to Workspace
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Create Sub-account Workspace -->
<div id="new-tenant-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-lg font-bold text-slate-900">Create Sub-Account Workspace</h3>
            <button onclick="document.getElementById('new-tenant-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="create_tenant">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Company / Entity Name *</label>
                <input type="text" name="name" placeholder="e.g. OneSol Dubai Logistics LLC" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Unique Workspace Code *</label>
                <input type="text" name="code" placeholder="e.g. onesol-dubai" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Default Currency</label>
                <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <option value="AED">AED - United Arab Emirates Dirham</option>
                    <option value="USD">USD - US Dollar</option>
                    <option value="EUR">EUR - Euro</option>
                    <option value="GBP">GBP - British Pound</option>
                    <option value="SAR">SAR - Saudi Riyal</option>
                </select>
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('new-tenant-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md">Create Workspace</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>
