<?php
require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];
$error = '';
$message = '';
$step = 'email'; // 'email' or 'otp'

// Handle Reset / Change Email
if (isset($_GET['action']) && $_GET['action'] === 'change_email') {
    unset($_SESSION['pending_otp_email']);
    redirect('client_login.php');
}

$pendingEmail = $_SESSION['pending_otp_email'] ?? '';
if (!empty($pendingEmail)) {
    $step = 'otp';
}

// Handle Post Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'request_otp';

    if ($action === 'request_otp') {
        $email = trim($_POST['email'] ?? '');

        $st = $pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $client = $st->fetch();

        if ($client) {
            $otpCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Store OTP in Database
            $stOtp = $pdo->prepare("UPDATE clients SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
            $stOtp->execute([$otpCode, $expiresAt, $client['id']]);

            // Dispatch Email OTP Notification
            $subject = "🔐 Your Client Portal Security Code: {$otpCode}";
            $body = "
                <div style='font-family: Arial, sans-serif; background: #0f172a; color: #ffffff; padding: 30px; border-radius: 16px;'>
                    <h2 style='color: #f59e0b; margin-top: 0;'>Client Portal Security Code</h2>
                    <p style='color: #94a3b8; font-size: 14px;'>Use the following 6-digit OTP code to sign in to your self-service client account portal:</p>
                    <div style='background: #1e293b; color: #fbbf24; font-size: 32px; font-weight: 900; letter-spacing: 8px; text-align: center; padding: 20px; border-radius: 12px; margin: 20px 0;'>
                        {$otpCode}
                    </div>
                    <p style='color: #64748b; font-size: 12px;'>This code is valid for 15 minutes. If you did not request this login, please contact support.</p>
                </div>
            ";
            
            // Try sending email via Mailer service
            try {
                \Services\Mailer::send($client['tenant_id'], $email, $subject, $body);
            } catch (Exception $e) {
                // Ignore mail failure in local sandbox mode
            }

            $_SESSION['pending_otp_email'] = $email;
            $pendingEmail = $email;
            $step = 'otp';
            $message = "🔐 A 6-digit security code has been sent to <strong>" . e($email) . "</strong>. Please check your inbox.";
        } else {
            $error = 'Client billing email address not found in our records. Please verify your email.';
        }
    } elseif ($action === 'verify_otp') {
        $email   = trim($_SESSION['pending_otp_email'] ?? $_POST['email'] ?? '');
        $otpCode = trim($_POST['otp_code'] ?? '');

        $st = $pdo->prepare("SELECT * FROM clients WHERE email = ? AND otp_code = ? AND otp_expires_at >= NOW() LIMIT 1");
        $st->execute([$email, $otpCode]);
        $client = $st->fetch();

        if ($client) {
            // Clear OTP after successful verification
            $stClear = $pdo->prepare("UPDATE clients SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
            $stClear->execute([$client['id']]);

            unset($_SESSION['pending_otp_email']);

            $_SESSION['client_id']        = $client['id'];
            $_SESSION['client_tenant_id'] = $client['tenant_id'];
            $_SESSION['client_name']      = $client['company_name'];
            $_SESSION['client_email']     = $client['email'];

            log_audit($pdo, 'client_portal_otp_login', 'clients', $client['id'], "Client logged into portal via Email OTP: {$client['company_name']}");
            redirect('client_portal.php');
        } else {
            $step = 'otp';
            $error = 'Invalid or expired 6-digit security code. Please check your email or request a new code.';
        }
    }
}

$brand = branding();
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Self-Service Portal - Sign In</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950 text-slate-100">
<div class="max-w-md w-full space-y-8 bg-slate-900/90 border border-slate-800 p-8 rounded-3xl shadow-2xl backdrop-blur-xl">
    <div class="text-center space-y-2">
        <div class="w-16 h-16 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-3xl font-black mx-auto border border-amber-500/30 shadow-lg">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <h2 class="text-2xl font-black text-white tracking-tight">Client Portal Security</h2>
        <p class="text-xs text-slate-400">View invoices, download statements of account, and pay online.</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs p-4 rounded-2xl font-semibold flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
            <span><?=$message?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs p-4 rounded-2xl font-semibold flex items-center space-x-2">
            <i class="fa-solid fa-circle-exclamation text-rose-400 text-base"></i>
            <span><?=e($error)?></span>
        </div>
    <?php endif; ?>

    <?php if ($step === 'email'): ?>
        <!-- Step 1: Request Email OTP Form -->
        <form method="post" class="space-y-6">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="request_otp">
            <div>
                <label class="block text-xs font-extrabold uppercase text-slate-400 mb-2">Registered Billing Email</label>
                <div class="relative">
                    <i class="fa-solid fa-envelope absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                    <input type="email" name="email" required placeholder="client@company.com" class="w-full pl-11 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm font-bold text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all">
                </div>
            </div>

            <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-black rounded-xl text-sm shadow-xl transition-all transform hover:-translate-y-0.5">
                Send Email Security Code <i class="fa-solid fa-paper-plane ml-1.5"></i>
            </button>
        </form>
    <?php else: ?>
        <!-- Step 2: Verify 6-Digit OTP Form -->
        <form method="post" class="space-y-6">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="verify_otp">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-extrabold uppercase text-slate-400">Enter 6-Digit Code</label>
                    <a href="client_login?action=change_email" class="text-2xs text-amber-400 hover:underline">Change Email</a>
                </div>
                <div class="relative">
                    <i class="fa-solid fa-key absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                    <input type="text" name="otp_code" required maxlength="6" pattern="[0-9]{6}" placeholder="123456" autofocus class="w-full pl-11 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-lg font-black text-amber-400 font-mono tracking-widest focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all">
                </div>
                <p class="text-2xs text-slate-500 mt-2">Code sent to <strong><?=e($pendingEmail)?></strong>. Valid for 15 minutes.</p>
            </div>

            <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-slate-950 font-black rounded-xl text-sm shadow-xl transition-all transform hover:-translate-y-0.5">
                Verify OTP & Access Portal <i class="fa-solid fa-shield-check ml-1.5"></i>
            </button>
        </form>
    <?php endif; ?>

    <div class="text-center pt-4 border-t border-slate-800 text-2xs text-slate-500">
        Secured by <?=e($brand['company_name'] ?: 'OneSol Solutions')?> Enterprise Security
    </div>
</div>
</body>
</html>
