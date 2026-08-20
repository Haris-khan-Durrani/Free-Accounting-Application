<?php
require __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        // Find user by email
        $st = $pdo->prepare("SELECT id, name, email, tenant_id FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stUpd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
            $stUpd->execute([$token, $expiresAt, $u['id']]);

            $tenantId = (int)($u['tenant_id'] ?: 1);
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dir = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
            $resetUrl = "{$scheme}://{$host}{$dir}/reset_password.php?token={$token}";

            $subject = "Password Reset Request - OneSol";
            $htmlBody = "
                <div style='font-family: sans-serif; max-width: 520px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;'>
                    <div style='text-align: center; margin-bottom: 24px;'>
                        <h2 style='color: #0f172a; margin: 0 0 8px 0; font-size: 22px; font-weight: 800;'>Reset Your Password</h2>
                        <p style='color: #64748b; font-size: 13px; margin: 0;'>OneSol Invoice Manager Security System</p>
                    </div>
                    <p style='color: #334155; font-size: 14px; line-height: 1.5;'>Hello <strong>" . e($u['name']) . "</strong>,</p>
                    <p style='color: #475569; font-size: 14px; line-height: 1.5;'>We received a request to reset the password for your account (<strong>" . e($u['email']) . "</strong>). Click the button below to set a new password:</p>
                    
                    <div style='text-align: center; margin: 28px 0;'>
                        <a href='" . e($resetUrl) . "' style='display: inline-block; background: #d97706; color: #ffffff; text-decoration: none; font-weight: 800; font-size: 14px; padding: 12px 28px; border-radius: 12px; shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>Reset Password Now</a>
                    </div>
                    
                    <p style='color: #64748b; font-size: 12px; line-height: 1.5;'>If the button above does not work, copy and paste the following link into your browser:</p>
                    <p style='word-break: break-all; font-size: 11px; color: #2563eb; background: #f8fafc; padding: 10px; border-radius: 8px; font-family: monospace;'>" . e($resetUrl) . "</p>
                    
                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;' />
                    <p style='color: #94a3b8; font-size: 11px; margin: 0;'>This link is valid for <strong>1 hour</strong>. If you did not request a password reset, you can safely ignore this email.</p>
                </div>
            ";

            \Services\Mailer::send($pdo, $tenantId, $u['email'], $subject, $htmlBody);
            log_audit($pdo, 'password_reset_request', 'users', $u['id'], "Password reset link requested for email {$u['email']}");
        }

        // Generic response to prevent email enumeration
        flash('success', 'If an account with that email exists, a password reset link has been sent to your inbox.');
        redirect('login');
    }
}

$brand = branding();
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <title>Forgot Password - <?=e($brand['company_name'])?></title>
    
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
                <i class="fa-solid fa-key text-amber-400"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Forgot Password</h1>
            <p class="text-xs text-slate-500 font-semibold mt-1">Enter your email to receive a password reset link</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 mb-6 text-xs font-semibold flex items-center">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-2.5 text-base"></i>
                <span><?=e($error)?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-5">
            <?=csrf_field()?>

            <div>
                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Registered Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-envelope text-sm"></i>
                    </div>
                    <input type="email" name="email" value="<?=e($_POST['email'] ?? '')?>" placeholder="name@company.com" required autofocus class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-10 pr-3.5 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                </div>
            </div>

            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3.5 border border-transparent text-sm font-black rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg transition-all transform hover:-translate-y-0.5">
                <i class="fa-solid fa-paper-plane mr-2"></i>Send Reset Link via Email
            </button>
        </form>

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
