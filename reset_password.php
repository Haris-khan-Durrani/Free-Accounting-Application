<?php
require __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index');
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$user = null;

if (!empty($token)) {
    $tokenHash = hash('sha256', $token);
    $st = $pdo->prepare("SELECT * FROM users WHERE (reset_token = ? OR reset_token = ?) AND reset_token_expires_at >= CURRENT_TIMESTAMP() LIMIT 1");
    $st->execute([$tokenHash, $token]);
    $user = $st->fetch();
}

if (!$user && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = 'The password reset link is invalid or has expired. Please request a new password reset.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$user) {
        $error = 'Invalid or expired password reset token.';
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 12) {
            $error = 'New password must be at least 12 characters long for financial SaaS security.';
        } elseif (strlen($newPassword) > 128) {
            $error = 'New password cannot exceed 128 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            
            // Auto-migrate session_version column if missing
            try { $pdo->exec("ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1"); } catch (Throwable $t) {}

            $stUpd = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL, session_version = COALESCE(session_version, 1) + 1 WHERE id = ?");
            $stUpd->execute([$passwordHash, $user['id']]);

            log_audit($pdo, 'password_reset_success', 'users', $user['id'], "Password reset completed successfully. Invalidated all existing active sessions for {$user['email']}");

            flash('success', 'Your password has been reset successfully! All existing active sessions have been revoked. Please sign in with your new password.');
            redirect('login');
        }
    }
}

$brand = branding();
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <title>Set New Password - <?=e($brand['company_name'])?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
</head>
<body class="h-full font-sans antialiased text-slate-100 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 flex items-center justify-center p-4">

<div class="max-w-md w-full relative">
    
    <div class="absolute -top-12 -left-12 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-12 -right-12 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="bg-white rounded-3xl p-8 sm:p-10 shadow-2xl border border-slate-200/80 text-slate-900 relative z-10">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-slate-950 text-amber-500 text-2xl font-black shadow-lg mb-4 border border-slate-800">
                <i class="fa-solid fa-lock-open text-amber-400"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Set New Password</h1>
            <?php if ($user): ?>
                <p class="text-xs text-slate-500 font-semibold mt-1">Create a new secure password for <strong><?=e($user['email'])?></strong></p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 mb-6 text-xs font-semibold flex items-center">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-2.5 text-base"></i>
                <span><?=e($error)?></span>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="post" class="space-y-5">
                <?=csrf_field()?>
                <input type="hidden" name="token" value="<?=e($token)?>">

                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">New Password (Min 12 chars)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input type="password" name="new_password" required minlength="12" placeholder="••••••••••••" class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-10 pr-3.5 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Confirm New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-shield-check text-sm"></i>
                        </div>
                        <input type="password" name="confirm_password" required minlength="8" placeholder="••••••••" class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-10 pr-3.5 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    </div>
                </div>

                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3.5 border border-transparent text-sm font-black rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg transition-all transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-check-circle mr-2"></i>Update Password & Sign In
                </button>
            </form>
        <?php else: ?>
            <div class="text-center py-4">
                <a href="forgot_password" class="inline-flex items-center px-5 py-3 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs rounded-xl shadow-md transition-all">
                    <i class="fa-solid fa-rotate-right mr-2"></i>Request New Reset Link
                </a>
            </div>
        <?php endif; ?>

        <div class="mt-6 text-center border-t border-slate-100 pt-5">
            <a href="login" class="text-xs font-extrabold text-slate-600 hover:text-amber-600 inline-flex items-center transition-colors">
                <i class="fa-solid fa-arrow-left mr-1.5 text-2xs"></i> Back to Sign In
            </a>
        </div>

    </div>

    <div class="text-center mt-6 text-xs text-slate-500 font-semibold">
        &copy; <?=date('Y')?> <?=e($brand['company_name'])?>. All rights reserved.
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
