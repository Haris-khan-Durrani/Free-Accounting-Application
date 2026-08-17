<?php
require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    $st = $pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $client = $st->fetch();

    if ($client) {
        $_SESSION['client_id'] = $client['id'];
        $_SESSION['client_tenant_id'] = $client['tenant_id'];
        $_SESSION['client_name'] = $client['company_name'];
        $_SESSION['client_email'] = $client['email'];

        log_audit($pdo, 'client_portal_login', 'clients', $client['id'], "Client logged into self-service portal: {$client['company_name']}");
        redirect('client_portal.php');
    } else {
        $error = 'Client account email not found in our records. Please verify your billing email.';
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
        <h2 class="text-2xl font-black text-white tracking-tight">Client Portal Access</h2>
        <p class="text-xs text-slate-400">View invoices, download statements, and pay online.</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs p-4 rounded-2xl font-semibold flex items-center space-x-2">
            <i class="fa-solid fa-circle-exclamation text-rose-400 text-base"></i>
            <span><?=e($error)?></span>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-6">
        <?=csrf_field()?>
        <div>
            <label class="block text-xs font-extrabold uppercase text-slate-400 mb-2">Registered Billing Email</label>
            <div class="relative">
                <i class="fa-solid fa-envelope absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                <input type="email" name="email" required placeholder="client@company.com" class="w-full pl-11 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm font-bold text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all">
            </div>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-black rounded-xl text-sm shadow-xl transition-all transform hover:-translate-y-0.5">
            Sign In to Client Portal <i class="fa-solid fa-arrow-right ml-1"></i>
        </button>
    </form>

    <div class="text-center pt-4 border-t border-slate-800 text-2xs text-slate-500">
        Secured by <?=e($brand['company_name'] ?: 'OneSol Solutions')?> Enterprise Security
    </div>
</div>
</body>
</html>
