<?php
require __DIR__ . '/bootstrap.php';

$pendingUserId = (int)($_SESSION['2fa_pending_user_id'] ?? 0);
if (!$pendingUserId) {
    redirect('login');
}

$error = '';
$stUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stUser->execute([$pendingUserId]);
$user = $stUser->fetch();

if (!$user) {
    redirect('login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'verify_otp';

    if ($action === 'verify_otp') {
        $enteredOtp = trim($_POST['otp_code'] ?? '');
        $enteredHash = hash('sha256', $enteredOtp);

        // Attempt counter tracking
        $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;

        if ($_SESSION['2fa_attempts'] > 5) {
            unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_attempts']);
            log_audit($pdo, '2fa_lockout', 'users', $user['id'], "User {$user['email']} exceeded maximum 2FA verification attempts.");
            flash('error', 'Too many failed 2FA verification attempts. Account verification reset for security. Please sign in again.');
            redirect('login');
        }

        // Support both hashed OTP and legacy plaintext fallback
        $isValidOtp = $user['otp_code'] && (
            hash_equals($user['otp_code'], $enteredHash) ||
            hash_equals($user['otp_code'], $enteredOtp)
        );

        if ($isValidOtp && strtotime($user['otp_expires_at']) >= time()) {
            // Clear OTP and attempt counters after successful verification
            $pdo->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?")->execute([$user['id']]);
            unset($_SESSION['2fa_attempts'], $_SESSION['2fa_last_resend']);

            // Resolve workspace role
            $tId = (int)($user['tenant_id'] ?? 1);
            $stRole = $pdo->prepare("SELECT role FROM user_tenants WHERE user_id = ? AND tenant_id = ? LIMIT 1");
            $stRole->execute([$user['id'], $tId]);
            $workspaceRole = $stRole->fetchColumn();
            $effectiveRole = $workspaceRole ?: ($user['role'] ?? 'owner');

            // Set Full Session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $effectiveRole;
            $_SESSION['active_tenant_id'] = $tId;
            $_SESSION['user_tenant_id'] = $tId;
            $_SESSION['tenant_id'] = $tId;
            $_SESSION['session_version'] = (int)($user['session_version'] ?? 1);
            unset($_SESSION['2fa_pending_user_id']);

            log_audit($pdo, 'otp_verified', 'users', $user['id'], "User {$user['email']} completed 2FA verification with role '{$effectiveRole}'");
            flash('success', 'Two-Factor Security Verification Complete! Welcome back.');
            redirect('index');
        } else {
            $attemptsLeft = max(0, 5 - $_SESSION['2fa_attempts']);
            $error = "Invalid or expired 6-digit security code. {$attemptsLeft} attempt(s) remaining before challenge reset.";
        }
    }

    if ($action === 'resend_otp') {
        $lastResend = (int)($_SESSION['2fa_last_resend'] ?? 0);
        if (time() - $lastResend < 60) {
            $waitSecs = 60 - (time() - $lastResend);
            flash('error', "Please wait {$waitSecs} second(s) before requesting another OTP code.");
            redirect('2fa_verify');
        }

        $_SESSION['2fa_last_resend'] = time();
        $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = hash('sha256', $otpCode);
        $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        try { $pdo->exec("ALTER TABLE users MODIFY COLUMN otp_code VARCHAR(64) NULL"); } catch (Throwable $t) {}

        $stOtp = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
        $stOtp->execute([$otpHash, $otpExpires, $pendingUserId]);

        $tenantId = (int)($user['tenant_id'] ?: 1);
        $subject = "Resent: Your 6-Digit Security Code (OTP) - OneSol";
        $htmlBody = "
            <div style='font-family: sans-serif; max-width: 500px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;'>
                <h2 style='color: #0f172a; margin-top: 0;'>New Security Verification Code</h2>
                <p style='color: #475569;'>Your new 6-digit security code is:</p>
                <div style='background: #f8fafc; border: 2px dashed #f59e0b; padding: 16px; text-align: center; border-radius: 12px; font-size: 32px; font-weight: 900; letter-spacing: 8px; color: #0f172a; font-family: monospace; margin: 20px 0;'>
                    $otpCode
                </div>
            </div>
        ";

        \Services\Mailer::send($pdo, $tenantId, $user['email'], $subject, $htmlBody);
        flash('success', 'A new 6-digit security code has been sent to your email.');
        redirect('2fa_verify');
    }
}

$brand = branding();
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <title>2FA Verification - <?=e($brand['company_name'])?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
</head>
<body class="h-full font-sans antialiased text-slate-100 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 flex items-center justify-center p-4">

<div class="max-w-md w-full relative">
    
    <div class="absolute -top-12 -left-12 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="bg-white rounded-3xl p-8 sm:p-10 shadow-2xl border border-slate-200/80 text-slate-900 relative z-10">
        
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center h-14 w-14 rounded-2xl bg-amber-500/10 text-amber-500 text-2xl font-black shadow-xs mb-3 border border-amber-500/30">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">2FA Security Code</h1>
            <p class="text-xs text-slate-500 font-semibold mt-1">Enter the 6-digit OTP code sent to <strong><?=e($user['email'])?></strong></p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 mb-6 text-xs font-semibold flex items-center">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-2.5 text-base"></i>
                <span><?=e($error)?></span>
            </div>
        <?php endif; ?>

        <!-- OTP Verification Form -->
        <form method="post" class="space-y-6">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="verify_otp">

            <div>
                <label class="block text-2xs font-extrabold text-slate-500 uppercase tracking-widest text-center mb-2">6-Digit Security Code</label>
                <input type="text" name="otp_code" maxlength="6" autofocus required placeholder="000000" class="w-full rounded-2xl border-2 border-slate-300 bg-slate-50 px-4 py-3.5 text-center text-3xl font-black font-mono tracking-[12px] text-slate-900 focus:bg-white focus:ring-4 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>

            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3.5 border border-transparent text-sm font-black rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg transition-all transform hover:-translate-y-0.5">
                <i class="fa-solid fa-lock mr-2"></i>Verify & Continue
            </button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-xs">
            <form method="post" class="inline">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="resend_otp">
                <button type="submit" class="font-bold text-amber-600 hover:underline"><i class="fa-solid fa-rotate-right mr-1"></i>Resend OTP Code</button>
            </form>
            <a href="login" class="font-semibold text-slate-400 hover:text-slate-600">Back to Login</a>
        </div>

    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    if (typeof gsap !== "undefined") {
        gsap.from(".relative.z-10", { opacity: 0, scale: 0.95, duration: 0.4, ease: "power2.out" });
    }
});
</script>
</body>
</html>
