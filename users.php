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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'accountant';

    if (!$isLifetime && $currentUsersCount >= $maxUsersAllowed) {
        $error = "Team user limit reached ($currentUsersCount/$maxUsersAllowed allowed on your " . ($tPlan['plan_name'] ?? 'Plan') . "). Please upgrade your subscription plan to add more team members.";
    } elseif (!$name || !$email || strlen($password) < 8) {
        $error = 'Name, valid email, and password (min 8 chars) are required.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$tid, $name, $email, $hash, $role]);
            $newUserId = (int)$pdo->lastInsertId();

            $stUt = $pdo->prepare("INSERT INTO user_tenants (user_id, tenant_id, role) VALUES (?, ?, ?)");
            $stUt->execute([$newUserId, $tid, $role]);

            log_audit($pdo, 'create_user', 'users', $newUserId, "Created user $email with role $role");
            flash('success', "User account for $email created successfully!");
            redirect('users');
        } catch (PDOException $e) {
            $error = 'Failed to create user. Email address may already exist.';
        }
    }
}

$st = $pdo->prepare("SELECT u.*, ut.role tenant_role FROM users u JOIN user_tenants ut ON ut.user_id = u.id WHERE ut.tenant_id = ? ORDER BY u.id DESC");
$st->execute([$tid]);
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
    <div class="mt-4 sm:mt-0">
        <?php if ($isLifetime || $currentUsersCount < $maxUsersAllowed): ?>
            <button onclick="document.getElementById('new-user-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2.5 border border-transparent text-xs font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
                <i class="fa-solid fa-user-plus mr-1.5"></i>Add Team Member
            </button>
        <?php else: ?>
            <a href="billing" class="inline-flex items-center px-4 py-2.5 border border-rose-300 text-xs font-extrabold rounded-xl text-rose-700 bg-rose-50 hover:bg-rose-100 shadow-xs">
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
                        <div class="text-2xs text-slate-400"><?=e($u['email'])?></div>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-amber-100 text-amber-800">
                    <?=strtoupper(e($u['tenant_role'] ?: $u['role']))?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add User Modal -->
<div id="new-user-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-lg font-bold text-slate-900">Add Team Member</h3>
            <button onclick="document.getElementById('new-user-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Full Name *</label>
                <input type="text" name="name" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Email Address *</label>
                <input type="email" name="email" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Password * (min 8 chars)</label>
                <input type="password" name="password" required minlength="8" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Permission Role *</label>
                <select name="role" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900">
                    <option value="admin">Admin (Full Access)</option>
                    <option value="accountant" selected>Accountant (Invoices & Reports)</option>
                    <option value="sales">Sales (Proposals & Invoices)</option>
                    <option value="viewer">Viewer (Read Only)</option>
                </select>
            </div>
            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('new-user-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md">Create User Account</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>
