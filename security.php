<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$userId = (int)$_SESSION['user_id'];
$activeTenant = tenant();

// Fetch User Info
$stU = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stU->execute([$userId]);
$user = $stU->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_user_2fa') {
        $enabled = isset($_POST['enable_2fa']) ? 1 : 0;
        $st = $pdo->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
        $st->execute([$enabled, $userId]);

        log_audit($pdo, 'toggle_2fa', 'users', $userId, "User $userId toggled 2FA to $enabled");
        flash('success', $enabled ? 'Two-Factor Authentication (2FA) is now ENABLED for your user account.' : 'Two-Factor Authentication (2FA) is now DISABLED for your account.');
        redirect('security');
    }

    if ($action === 'toggle_tenant_mandatory_2fa') {
        if (!has_role(['owner', 'admin'])) {
            flash('error', 'Access denied. Only workspace owners and admins can configure workspace 2FA policy.');
            redirect('security');
        }

        $require2fa = isset($_POST['require_2fa']) ? 1 : 0;
        $st = $pdo->prepare("UPDATE tenants SET require_2fa = ? WHERE id = ?");
        $st->execute([$require2fa, $tid]);

        log_audit($pdo, 'mandatory_2fa', 'tenants', $tid, "Tenant $tid set mandatory 2FA enforcement to $require2fa");
        flash('success', $require2fa ? 'Mandatory 2FA enforcement ENABLED for all team members in this workspace.' : 'Mandatory 2FA enforcement DISABLED for workspace.');
        redirect('security');
    }

}

// Fetch Active Security Log
$stAudit = $pdo->prepare("SELECT * FROM audit_logs WHERE tenant_id = ? AND action IN ('login', 'toggle_2fa', 'mandatory_2fa', 'otp_verified') ORDER BY id DESC LIMIT 10");
$stAudit->execute([$tid]);
$securityLogs = $stAudit->fetchAll();

page_start('Security & Two-Factor Authentication');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Security & Two-Factor Authentication (2FA)</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Protect user accounts and company financial data with cryptographic 6-digit OTP security codes sent via custom tenant SMTP.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">

    <!-- User 2FA Toggle Card -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm h-fit">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 mb-4 flex items-center">
            <i class="fa-solid fa-shield-halved text-amber-500 mr-2"></i>My Account 2FA Status
        </h2>

        <div class="mb-5 p-4 rounded-xl <?=!empty($user['two_factor_enabled']) ? 'bg-emerald-50 border border-emerald-200' : 'bg-slate-50 border border-slate-200'?>">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 rounded-xl <?=!empty($user['two_factor_enabled']) ? 'bg-emerald-500 text-white' : 'bg-slate-300 text-slate-600'?> flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-shield"></i>
                </div>
                <div>
                    <div class="font-extrabold text-sm text-slate-900">
                        <?=!empty($user['two_factor_enabled']) ? '2FA Protection Active' : '2FA Protection Disabled'?>
                    </div>
                    <div class="text-2xs text-slate-500 font-semibold">
                        <?=!empty($user['two_factor_enabled']) ? 'Your logins require 6-digit OTP email verification.' : 'Enable 2FA to secure your sign-in.'?>
                    </div>
                </div>
            </div>
        </div>

        <form method="post">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="toggle_user_2fa">
            
            <label class="flex items-center space-x-3 text-sm font-bold text-slate-800 mb-5 cursor-pointer">
                <input type="checkbox" name="enable_2fa" value="1" <?=!empty($user['two_factor_enabled']) ? 'checked' : ''?> onchange="this.form.submit()" class="h-5 w-5 rounded text-amber-500 focus:ring-amber-500">
                <span>Enable 2FA for <?=e($user['email'])?></span>
            </label>
        </form>
    </div>

    <!-- Tenant Workspace Mandatory 2FA Enforcement Card -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm h-fit">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 mb-4 flex items-center">
            <i class="fa-solid fa-building-shield text-indigo-500 mr-2"></i>Workspace 2FA Policy
        </h2>

        <p class="text-xs text-slate-500 mb-5">As Tenant Administrator, enforce mandatory 2FA OTP verification for all team members logging into <strong><?=e($activeTenant['name'])?></strong>.</p>

        <form method="post">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="toggle_tenant_mandatory_2fa">

            <div class="mb-5 p-4 rounded-xl <?=!empty($activeTenant['require_2fa']) ? 'bg-indigo-50 border border-indigo-200 text-indigo-900' : 'bg-slate-50 border border-slate-200 text-slate-700'?>">
                <div class="font-extrabold text-sm">
                    <?=!empty($activeTenant['require_2fa']) ? 'Mandatory Workspace 2FA Active' : 'Optional Workspace 2FA'?>
                </div>
                <div class="text-2xs opacity-80 font-semibold mt-0.5">
                    <?=!empty($activeTenant['require_2fa']) ? 'All team members must complete 2FA upon sign in.' : 'Team members choose their own 2FA status.'?>
                </div>
            </div>

            <label class="flex items-center space-x-3 text-sm font-bold text-slate-800 cursor-pointer">
                <input type="checkbox" name="require_2fa" value="1" <?=!empty($activeTenant['require_2fa']) ? 'checked' : ''?> onchange="this.form.submit()" class="h-5 w-5 rounded text-indigo-600 focus:ring-indigo-500">
                <span>Require 2FA for ALL Team Members</span>
            </label>
        </form>
    </div>

    <!-- Security Audit Logs -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm h-fit">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 mb-4 flex items-center">
            <i class="fa-solid fa-clock-rotate-left text-slate-500 mr-2"></i>Recent Security Logs
        </h2>

        <div class="space-y-3 text-xs">
            <?php foreach ($securityLogs as $log): ?>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-start space-x-2.5">
                    <i class="fa-solid fa-key text-amber-500 mt-0.5"></i>
                    <div>
                        <div class="font-bold text-slate-900"><?=e($log['details'])?></div>
                        <div class="text-2xs text-slate-400 font-mono"><?=e(date('d M Y H:i:s', strtotime($log['created_at'])))?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php page_end(); ?>
