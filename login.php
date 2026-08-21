<?php
require __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index');
}

$error = '';

if (\Core\SecurityThrottle::isLockedOut()) {
    $remainingMins = ceil(\Core\SecurityThrottle::getRemainingLockoutTime() / 60);
    $error = "Too many failed login attempts. Account temporarily locked for security. Please try again in $remainingMins minute(s).";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare('SELECT u.*, t.require_2fa FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
    } catch (PDOException $ex) {
        // Auto-migrate missing 2FA columns if legacy schema is present
        try { $pdo->exec("ALTER TABLE tenants ADD COLUMN require_2fa TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $t) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $t) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_code VARCHAR(10) NULL"); } catch (Throwable $t) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_expires_at DATETIME NULL"); } catch (Throwable $t) {}

        $stmt = $pdo->prepare('SELECT u.*, t.require_2fa FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
    }

    if ($u && password_verify($password, $u['password_hash'])) {
        \Core\SecurityThrottle::clearAttempts();

        $tenantId = (int)($u['tenant_id'] ?: 1);
        $is2faRequired = (!empty($u['two_factor_enabled']) || !empty($u['require_2fa']));

        if ($is2faRequired) {
            // Generate 6-Digit Cryptographically Secure OTP & Hash before storing
            $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = hash('sha256', $otpCode);
            $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stOtp = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
            $stOtp->execute([$otpHash, $otpExpires, $u['id']]);

            // Send OTP Code via Tenant Custom SMTP Mailer
            $subject = "Your 6-Digit Login Security Code (OTP) - OneSol";
            $htmlBody = "
                <div style='font-family: sans-serif; max-width: 500px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;'>
                    <h2 style='color: #0f172a; margin-top: 0;'>Security Verification Required</h2>
                    <p style='color: #475569;'>Use the following 6-digit security code to complete your login sign-in:</p>
                    <div style='background: #f8fafc; border: 2px dashed #f59e0b; padding: 16px; text-align: center; border-radius: 12px; font-size: 32px; font-weight: 900; letter-spacing: 8px; color: #0f172a; font-family: monospace; margin: 20px 0;'>
                        $otpCode
                    </div>
                    <p style='color: #94a3b8; font-size: 12px;'>This security code is valid for 10 minutes. If you did not request this code, please secure your account password immediately.</p>
                </div>
            ";

            \Services\Mailer::send($pdo, $tenantId, $u['email'], $subject, $htmlBody);

            $_SESSION['2fa_pending_user_id'] = $u['id'];
            redirect('2fa_verify');
        }

        // Resolve the workspace-scoped role from user_tenants (overrides the global users.role)
        // This ensures sub-account members (accountant, sales, viewer) don't inherit owner-level access
        $stRole = $pdo->prepare("SELECT role FROM user_tenants WHERE user_id = ? AND tenant_id = ? LIMIT 1");
        $stRole->execute([$u['id'], $tenantId]);
        $workspaceRole = $stRole->fetchColumn();
        // Use workspace role if found, otherwise fall back to users.role (for primary owner account)
        $effectiveRole = $workspaceRole ?: ($u['role'] ?? 'owner');

        // Direct Login without 2FA
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['user_name'] = $u['name'];
        $_SESSION['user_role'] = $effectiveRole;
        $_SESSION['active_tenant_id'] = $tenantId;
        $_SESSION['user_tenant_id'] = $tenantId;
        $_SESSION['tenant_id'] = $tenantId;

        log_audit($pdo, 'login', 'users', $u['id'], "User {$u['email']} logged in successfully with role '{$effectiveRole}'");
        redirect('index');
    } else {
        \Core\SecurityThrottle::recordFailedAttempt();
        $error = 'Invalid email address or password provided.';
    }
}

$brand = branding();
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <title>Sign In - <?=e($brand['company_name'])?> Multi-Tenant SaaS</title>
    
    <!-- Tailwind CSS, FontAwesome 6, and GSAP -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
</head>
<body class="h-full font-sans antialiased text-slate-100 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 flex items-center justify-center p-4">

<div class="max-w-md w-full relative">
    
    <!-- Glow Ambient Blur Effects -->
    <div class="absolute -top-12 -left-12 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-12 -right-12 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Login Card Container -->
    <div class="bg-white rounded-3xl p-8 sm:p-10 shadow-2xl border border-slate-200/80 text-slate-900 relative z-10">
        
        <!-- Brand Header Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-slate-950 text-amber-500 text-2xl font-black shadow-lg mb-4 border border-slate-800">
                <?php if (!empty($brand['logo_url'])): ?>
                    <img src="<?=e($brand['logo_url'])?>" alt="Logo" class="h-10 w-auto object-contain">
                <?php else: ?>
                    <i class="fa-solid fa-bolt text-amber-400"></i>
                <?php endif; ?>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?=e($brand['company_name'])?></h1>
            <p class="text-xs text-slate-500 font-semibold mt-1">Enterprise Multi-Tenant Invoicing Portal</p>
        </div>

        <!-- Flash Alerts -->
        <?php if ($flashMsg = get_flash()): ?>
            <div class="<?=$flashMsg['type'] === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'?> border rounded-xl p-4 mb-6 text-xs font-semibold flex items-center">
                <i class="fa-solid <?=$flashMsg['type'] === 'error' ? 'fa-triangle-exclamation text-rose-500' : 'fa-circle-check text-emerald-500'?> mr-2.5 text-base"></i>
                <span><?=e($flashMsg['message'])?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['installed'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 mb-6 text-xs font-semibold flex items-center">
                <i class="fa-solid fa-circle-check text-emerald-500 mr-2.5 text-base"></i>
                <span>Installation complete! Sign in with your administrator account.</span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 mb-6 text-xs font-semibold flex items-center">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-2.5 text-base"></i>
                <span><?=e($error)?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="post" class="space-y-5">
            <?=csrf_field()?>

            <div>
                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-envelope text-sm"></i>
                    </div>
                    <input type="email" name="email" id="email-field" value="<?=e($_POST['email'] ?? '')?>" placeholder="name@company.com" required autofocus class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-10 pr-3.5 py-3 text-base sm:text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider">Password</label>
                    <a href="forgot_password" class="text-2xs font-bold text-amber-600 hover:text-amber-700 hover:underline">Forgot Password?</a>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-lock text-sm"></i>
                    </div>
                    <input type="password" name="password" id="password-field" placeholder="••••••••" required class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-10 pr-3.5 py-3 text-base sm:text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>
            </div>

            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3.5 border border-transparent text-sm font-black rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg transition-all transform hover:-translate-y-0.5">
                <i class="fa-solid fa-right-to-bracket mr-2"></i>Sign In to Dashboard
            </button>
        </form>

    </div>

    <!-- Footer Copyright -->
    <div class="text-center mt-6 text-xs text-slate-500 font-semibold">
        &copy; <?=date('Y')?> <?=e($brand['company_name'])?> SaaS. All rights reserved.
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    if (typeof gsap !== "undefined") {
        gsap.from(".relative.z-10", { opacity: 0, y: 20, duration: 0.5, ease: "power2.out" });
    }
});
</script>
</body>
</html>
