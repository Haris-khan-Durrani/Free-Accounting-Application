<?php
session_start();
$error = '';
$success = '';

$configExists = file_exists(__DIR__ . '/config.php');
$installed = false;
$dbErrorDetails = '';

if ($configExists && empty($_GET['force'])) {
    try {
        $c = require __DIR__ . '/config.php';
        $testDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['db_host'] ?? 'localhost', $c['db_port'] ?? '3306', $c['db_name'] ?? '');
        $testPdo = new PDO($testDsn, $c['db_user'] ?? '', $c['db_pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Verify users table exists
        $stCheck = $testPdo->query("SHOW TABLES LIKE 'users'");
        if ($stCheck && $stCheck->rowCount() > 0) {
            $installed = true;
        } else {
            $dbErrorDetails = 'Database connection succeeded, but database tables are missing.';
        }
    } catch (Throwable $ex) {
        $installed = false;
        $dbErrorDetails = 'Database connection failed: ' . $ex->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$installed || !empty($_GET['force']))) {
    $host      = trim($_POST['db_host'] ?? 'localhost');
    $port      = trim($_POST['db_port'] ?? '3306');
    $db        = trim($_POST['db_name'] ?? 'onesol_invoices');
    $user      = trim($_POST['db_user'] ?? 'root');
    $pass      = $_POST['db_pass'] ?? '';
    $email     = trim($_POST['admin_email'] ?? 'admin@onesol.ae');
    $adminPass = $_POST['admin_password'] ?? '';

    if (!$db || !$user || !$email || strlen($adminPass) < 8) {
        $error = 'Please complete all required fields. Admin password must be at least 8 characters.';
    } else {
        try {
            // 1. Connect to MySQL server
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // 2. Create Database if not exists & switch to database
            $safeDb = str_replace('`', '', $db);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$safeDb}`");

            // 3. Import database schema SQL
            $sqlFile = __DIR__ . '/database.sql';
            if (!file_exists($sqlFile)) {
                throw new RuntimeException('database.sql file missing in application root directory.');
            }
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);

            // 4. Create or Update Master SuperAdmin User Account
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stUser->execute([$email]);
            $existingUserId = $stUser->fetchColumn();

            if ($existingUserId) {
                $stUp = $pdo->prepare("UPDATE users SET name = 'OneSol Admin', password_hash = ?, role = 'superadmin' WHERE id = ?");
                $stUp->execute([$hash, $existingUserId]);
            } else {
                $stIns = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (1, 'OneSol Admin', ?, ?, 'superadmin')");
                $stIns->execute([$email, $hash]);
            }

            // 5. Seed default demo data if invoices empty
            $count = (int)$pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
            if ($count === 0) {
                $stClient = $pdo->prepare("INSERT INTO clients (tenant_id, company_name, address) VALUES (1, ?, ?)");
                $stClient->execute(['360 Business Consultants', 'Dubai, United Arab Emirates']);
                $clientId = (int)$pdo->lastInsertId();

                $notes = "This proposal is based on the mutually agreed project scope and deliverables.\nAny additional scope, integrations or major changes may be quoted separately.\nPayment schedule and project start date will follow the agreed confirmation.";
                $stInv = $pdo->prepare("INSERT INTO invoices (tenant_id, invoice_number, client_id, invoice_date, valid_until, status, subtotal, discount_type, discount_value, discount_amount, total, notes) VALUES (1, ?, ?, ?, ?, 'draft', 4000, 'fixed', 1500, 1500, 2500, ?)");
                $stInv->execute(['OS-PI-20260807-001', $clientId, '2026-08-07', '2026-08-22', $notes]);
                $invoiceId = (int)$pdo->lastInsertId();

                $stItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, details, qty, unit_price, amount, sort_order) VALUES (?, ?, ?, 1, 4000, 4000, 0)");
                $stItem->execute([$invoiceId, 'Software Development & Implementation Services', 'Includes setup, configuration, customization and delivery as per agreed scope.']);
            }

            // 6. Write config.php
            $configContent = "<?php\nreturn " . var_export([
                'db_host' => $host,
                'db_port' => $port,
                'db_name' => $db,
                'db_user' => $user,
                'db_pass' => $pass
            ], true) . ";\n";

            if (file_put_contents(__DIR__ . '/config.php', $configContent) === false) {
                throw new RuntimeException('Could not write config.php file. Check file permissions.');
            }

            header('Location: login.php?installed=1');
            exit;

        } catch (Throwable $e) {
            $error = 'Installation Failed: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install OneSol Invoice Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-800">
        
        <!-- Header Banner -->
        <div class="bg-slate-900 p-8 text-center border-b border-slate-800 relative">
            <div class="w-16 h-16 bg-gradient-to-tr from-amber-500 to-amber-300 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-amber-500/20">
                <i class="fa-solid fa-file-invoice text-slate-950 text-2xl font-black"></i>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">OneSol Invoice Manager</h1>
            <p class="text-xs text-slate-400 font-medium mt-1">Multi-Tenant Accounting & Invoicing SaaS Installer</p>
        </div>

        <div class="p-8">
            <?php if ($installed && empty($_GET['force'])): ?>
                <!-- Installed Alert Box -->
                <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center">
                    <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3 text-lg font-bold">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h3 class="text-lg font-bold text-emerald-900 mb-1">Application is Installed & Ready</h3>
                    <p class="text-xs text-emerald-700 mb-6">Database connection verified and core tables are ready.</p>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="login.php" class="flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition-all shadow-md flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <span>Proceed to Login</span>
                        </a>
                        <a href="install.php?force=1" class="py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all flex items-center justify-center space-x-2 border border-slate-300">
                            <i class="fa-solid fa-rotate-right text-slate-500"></i>
                            <span>Re-install / Setup Database</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <?php if ($dbErrorDetails): ?>
                    <div class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 text-amber-800 text-xs flex items-start space-x-3">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm mt-0.5"></i>
                        <div>
                            <strong class="font-bold block text-amber-900 mb-0.5">Setup Required</strong>
                            <?=e($dbErrorDetails)?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-6 bg-rose-50 border border-rose-200 rounded-2xl p-4 text-rose-800 text-xs flex items-start space-x-3">
                        <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm mt-0.5"></i>
                        <div>
                            <strong class="font-bold block text-rose-900 mb-0.5">Installation Error</strong>
                            <?=e($error)?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Installation Form -->
                <form method="post" class="space-y-5">
                    
                    <div class="border-b border-slate-200 pb-2 mb-4">
                        <h3 class="text-xs font-black uppercase text-slate-400 tracking-wider">1. Database Configuration</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Database Host</label>
                            <input type="text" name="db_host" value="<?=e($_POST['db_host'] ?? 'localhost')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Database Port</label>
                            <input type="text" name="db_port" value="<?=e($_POST['db_port'] ?? '3306')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Database Name</label>
                            <input type="text" name="db_name" value="<?=e($_POST['db_name'] ?? 'onesol_invoices')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Database User</label>
                            <input type="text" name="db_user" value="<?=e($_POST['db_user'] ?? 'root')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Database Password</label>
                        <input type="password" name="db_pass" placeholder="Enter MySQL Password" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    </div>

                    <div class="border-b border-slate-200 pb-2 mb-4 pt-3">
                        <h3 class="text-xs font-black uppercase text-slate-400 tracking-wider">2. Admin User Credentials</h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Admin Email</label>
                            <input type="email" name="admin_email" value="<?=e($_POST['admin_email'] ?? 'admin@onesol.ae')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Admin Password</label>
                            <input type="password" name="admin_password" placeholder="Min 8 characters" minlength="8" required class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full py-3.5 px-6 bg-gradient-to-r from-slate-900 to-slate-800 hover:from-slate-800 hover:to-slate-700 text-white rounded-xl text-xs font-black tracking-wide shadow-xl transition-all flex items-center justify-center space-x-2 border border-slate-700">
                            <i class="fa-solid fa-wand-magic-sparkles text-amber-400 text-sm"></i>
                            <span>Create Database & Install Application</span>
                        </button>
                    </div>

                </form>
            <?php endif; ?>
        </div>

        <div class="bg-slate-50 px-8 py-4 border-t border-slate-100 text-center text-3xs text-slate-400 font-bold uppercase tracking-wider">
            OneSol Solutions &copy; <?=date('Y')?> &bull; Enterprise SaaS Suite
        </div>
    </div>
</body>
</html>
