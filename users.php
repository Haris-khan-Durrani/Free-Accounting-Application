<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();
$error = '';

// Check User Quota for Current Tenant
$stTenantPlan = $pdo->prepare("SELECT t.subscription_status, p.max_team_users, p.name plan_name FROM tenants t LEFT JOIN saas_plans p ON p.id = t.plan_id WHERE t.id = ?");
$stTenantPlan->execute([$tid]);
$tPlan = $stTenantPlan->fetch();

$isLifetime = ($tPlan['subscription_status'] ?? '') === 'lifetime';
$maxUsersAllowed = $isLifetime ? 999999 : (int)($tPlan['max_team_users'] ?? 5);

$stCurrentUsers = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE tenant_id = ?");
$stCurrentUsers->execute([$tid]);
$currentUsersCount = (int)$stCurrentUsers->fetchColumn();

// Fetch All Registered SaaS Tenants for Workspace Assignment
$stAllTenants = $pdo->query("SELECT id, name, code FROM tenants ORDER BY name ASC");
$allTenants = $stAllTenants->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'accountant';
    $targetTenantId = (int)($_POST['target_tenant_id'] ?? $tid);
    $accountScope = $_POST['account_scope'] ?? 'subaccount';

    if ($accountScope === 'tenant_admin') {
        $role = 'admin';
    }

    if (!$isLifetime && $currentUsersCount >= $maxUsersAllowed) {
        $error = "Team user limit reached ($currentUsersCount/$maxUsersAllowed allowed on your " . ($tPlan['plan_name'] ?? 'Plan') . "). Please upgrade your subscription plan to add more team members.";
    } elseif (!$name || !$email || strlen($password) < 8) {
        $error = 'Name, valid email, and password (min 8 chars) are required.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$targetTenantId, $name, $email, $hash, $role]);
            $newUserId = (int)$pdo->lastInsertId();

            $stUt = $pdo->prepare("INSERT INTO user_tenants (user_id, tenant_id, role) VALUES (?, ?, ?)");
            $stUt->execute([$newUserId, $targetTenantId, $role]);

            // Find tenant name for flash message
            $assignedTenantName = $activeTenant['name'];
            foreach ($allTenants as $at) {
                if ($at['id'] == $targetTenantId) {
                    $assignedTenantName = $at['name'];
                    break;
                }
            }

            log_audit($pdo, 'create_user', 'users', $newUserId, "Created user $email with role $role assigned to workspace $assignedTenantName");
            flash('success', "User account for $email created successfully and assigned to '$assignedTenantName'!");
            redirect('users');
        } catch (PDOException $e) {
            $error = 'Failed to create user. Email address may already exist.';
        }
    }
}

$st = $pdo->prepare("SELECT u.*, ut.role tenant_role, t.name tenant_workspace_name, t.code tenant_workspace_code FROM users u JOIN user_tenants ut ON ut.user_id = u.id LEFT JOIN tenants t ON t.id = ut.tenant_id WHERE ut.tenant_id = ? OR ? = 1 ORDER BY u.id DESC");
$st->execute([$tid, ($activeTenant['id'] == 1 ? 1 : 0)]);
$users = $st->fetchAll();

page_start('Team & Permissions');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Team & Permissions</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">
            Manage user access for <strong><?=e($activeTenant['name'])?></strong>. 
            Quota: <strong class="text-slate-800"><?=$currentUsersCount?> / <?=$isLifetime ? 'Unlimited (Internal)' : $maxUsersAllowed?> Allowed Users</strong>
        </p>
    </div>
    <div class="mt-4 sm:mt-0 flex items-center space-x-2">
        <a href="tenants_admin" class="inline-flex items-center px-3.5 py-2 border border-purple-300 text-xs font-extrabold rounded-xl text-purple-800 bg-purple-50 hover:bg-purple-100 shadow-xs transition-all">
            <i class="fa-solid fa-building-user mr-1.5 text-purple-600"></i>+ Create Tenant Workspace
        </a>
        <?php if ($isLifetime || $currentUsersCount < $maxUsersAllowed): ?>
            <button onclick="document.getElementById('new-user-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
                <i class="fa-solid fa-user-plus mr-1.5"></i>Add Team Member
            </button>
        <?php else: ?>
            <a href="billing" class="inline-flex items-center px-4 py-2 border border-rose-300 text-xs font-extrabold rounded-xl text-rose-700 bg-rose-50 hover:bg-rose-100 shadow-xs">
                <i class="fa-solid fa-lock mr-1.5"></i>User Limit Reached - Upgrade Plan
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 mb-6 text-sm font-semibold flex items-center">
        <i class="fa-solid fa-triangle-exclamation mr-2 text-rose-600"></i><?=e($error)?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base sm:text-lg font-bold text-slate-900">Active Team Members (<?=count($users)?>)</h2>
        <span class="text-xs font-extrabold text-slate-500"><?=$currentUsersCount?> / <?=$isLifetime ? 'Unlimited' : $maxUsersAllowed?> Limit</span>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">User Name</th>
                    <th class="px-6 py-3.5">Email Address</th>
                    <th class="px-6 py-3.5">Assigned Workspace</th>
                    <th class="px-6 py-3.5">Role</th>
                    <th class="px-6 py-3.5">Date Added</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 font-bold text-slate-900 flex items-center space-x-3">
                            <div class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center font-bold text-slate-600 text-xs">
                                <?=strtoupper(substr($u['name'], 0, 2))?>
                            </div>
                            <span><?=e($u['name'])?></span>
                        </td>
                        <td class="px-6 py-4 text-slate-600"><?=e($u['email'])?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-2xs font-extrabold bg-slate-100 text-slate-800 border border-slate-200">
                                <i class="fa-solid fa-building text-amber-500 mr-1"></i><?=e($u['tenant_workspace_name'] ?: 'Corporate HQ')?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-amber-100 text-amber-800">
                                <?=strtoupper(e($u['tenant_role'] ?: $u['role']))?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500"><?=e(date('d M Y', strtotime($u['created_at'])))?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Touch View -->
    <div class="sm:hidden divide-y divide-slate-100">
        <?php foreach ($users as $u): ?>
            <div class="p-4 hover:bg-slate-50 transition-colors flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center font-bold text-slate-600 text-xs">
                        <?=strtoupper(substr($u['name'], 0, 2))?>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 text-sm"><?=e($u['name'])?></div>
                        <div class="text-2xs text-slate-400"><?=e($u['email'])?> &bull; <?=e($u['tenant_workspace_name'] ?: 'Corporate HQ')?></div>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-amber-100 text-amber-800">
                    <?=strtoupper(e($u['tenant_role'] ?: $u['role']))?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add User Modal (Sleek 2-Column Responsive Layout - Zero Scrollbar Needed!) -->
<div id="new-user-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-md flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full shadow-2xl border border-slate-200 overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/80">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-600 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 tracking-tight">Add Team Member</h3>
                    <p class="text-2xs text-slate-500 font-medium">Create user account & assign workspace scope</p>
                </div>
            </div>
            <button onclick="document.getElementById('new-user-modal').classList.add('hidden')" class="h-8 w-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center text-lg font-bold transition-all">×</button>
        </div>

        <!-- Form Body -->
        <form method="post" class="p-6 space-y-4">
            <?=csrf_field()?>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Full Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Sarah Jenkins" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Email Address *</label>
                    <input type="email" name="email" required placeholder="sarah@company.com" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Password * (min 8 chars)</label>
                    <input type="password" name="password" required minlength="8" placeholder="••••••••" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Permission Role *</label>
                    <select name="role" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                        <option value="admin">Admin (Full Access)</option>
                        <option value="accountant" selected>Accountant (Invoices & Reports)</option>
                        <option value="sales">Sales (Proposals & Invoices)</option>
                        <option value="viewer">Viewer (Read Only)</option>
                    </select>
                </div>
            </div>

            <!-- Company Workspace Assignment -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider">Assigned Company Workspace *</label>
                    <a href="tenants_admin" target="_blank" class="inline-flex items-center px-2 py-0.5 text-3xs font-extrabold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-md border border-purple-200 transition-all">
                        <i class="fa-solid fa-plus-circle mr-1"></i>+ Create Tenant
                    </a>
                </div>
                <select name="target_tenant_id" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                    <?php foreach ($allTenants as $at): ?>
                        <option value="<?=$at['id']?>" <?=$at['id']==$tid?'selected':''?>><?=e($at['name'])?> (code: <?=e($at['code'])?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Scope Type Selection -->
            <div>
                <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Account Access Scope</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center space-x-3 bg-slate-50/80 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:border-amber-400 hover:bg-amber-50/30 transition-all">
                        <input type="radio" name="account_scope" value="subaccount" checked class="w-4 h-4 text-amber-600 focus:ring-amber-500">
                        <div>
                            <div class="text-xs font-extrabold text-slate-900">Sub-Account Member</div>
                            <div class="text-3xs font-semibold text-slate-500">Scoped role permissions</div>
                        </div>
                    </label>
                    <label class="flex items-center space-x-3 bg-slate-50/80 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:border-purple-400 hover:bg-purple-50/30 transition-all">
                        <input type="radio" name="account_scope" value="tenant_admin" class="w-4 h-4 text-purple-600 focus:ring-purple-500">
                        <div>
                            <div class="text-xs font-extrabold text-slate-900">Tenant Admin</div>
                            <div class="text-3xs font-semibold text-slate-500">Full tenant administration</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('new-user-modal').classList.add('hidden')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-all">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg shadow-amber-500/20 transition-all">Create User Account</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>
