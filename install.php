<?php

declare(strict_types=1);

session_start();

/**
 * Escape HTML output.
 */
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| Installer Configuration
|--------------------------------------------------------------------------
*/

$error = '';
$dbErrorDetails = '';

$configFile = __DIR__ . '/config.php';
$sqlFile    = __DIR__ . '/database.sql';

$configExists = file_exists($configFile);
$installed    = false;

/*
|--------------------------------------------------------------------------
| Prevent HTTP Reinstallation (Fail-Closed Lock when config.php exists)
|--------------------------------------------------------------------------
*/
if ($configExists) {
    http_response_code(403);
    echo '<!doctype html><html><head><title>Installer Locked</title><style>body{font-family:sans-serif;padding:40px;background:#0f172a;color:#f8fafc;text-align:center;}.card{max-width:500px;margin:0 auto;background:#1e293b;padding:32px;border-radius:16px;border:1px solid #334155;}h1{color:#ef4444;font-size:24px;}</style></head><body><div class="card"><h1>Installation Locked</h1><p>OneSol Invoice Manager is already configured. For security reasons, HTTP reinstallation is strictly disabled.</p><p style="color:#94a3b8;font-size:13px;">If you need to reinstall or reconfigure the application, remove <code>config.php</code> from the server filesystem via SSH or console access.</p><a href="login.php" style="display:inline-block;margin-top:16px;padding:10px 20px;background:#3b82f6;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Return to Application Login</a></div></body></html>';
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['installer_csrf']) ||
    !is_string($_SESSION['installer_csrf'])
) {
    $_SESSION['installer_csrf'] = bin2hex(
        random_bytes(32)
    );
}

/*
|--------------------------------------------------------------------------
| Installation Request Handler
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */

    $submittedCsrf = (string) (
        $_POST['csrf_token'] ?? ''
    );

        if (
            $submittedCsrf === '' ||
            !hash_equals(
                $_SESSION['installer_csrf'],
                $submittedCsrf
            )
        ) {
            $error =
                'Invalid or expired installer request. Please refresh the page and try again.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | Input
            |--------------------------------------------------------------------------
            */

            $host = trim(
                (string) ($_POST['db_host'] ?? 'localhost')
            );

            $port = trim(
                (string) ($_POST['db_port'] ?? '3306')
            );

            $db = trim(
                (string) ($_POST['db_name'] ?? 'onesol_invoices')
            );

            $user = trim(
                (string) ($_POST['db_user'] ?? 'root')
            );

            $pass = (string) (
                $_POST['db_pass'] ?? ''
            );

            $email = trim(
                (string) (
                    $_POST['admin_email']
                    ?? 'admin@onesol.ae'
                )
            );

            $adminPass = (string) (
                $_POST['admin_password'] ?? ''
            );

            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            if ($host === '') {
                $error = 'Database host is required.';
            } elseif ($port === '') {
                $error = 'Database port is required.';
            } elseif (!ctype_digit($port)) {
                $error = 'Database port must be numeric.';
            } elseif ($db === '') {
                $error = 'Database name is required.';
            } elseif ($user === '') {
                $error = 'Database user is required.';
            } elseif (
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                $error =
                    'Please enter a valid administrator email address.';
            } elseif (strlen($adminPass) < 8) {
                $error =
                    'Admin password must be at least 8 characters.';
            }

            /*
            |--------------------------------------------------------------------------
            | Perform Installation
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | 1. Connect To MySQL Server
                    |--------------------------------------------------------------------------
                    */

                    $serverDsn = sprintf(
                        'mysql:host=%s;port=%s;charset=utf8mb4',
                        $host,
                        $port
                    );

                    $pdo = new PDO(
                        $serverDsn,
                        $user,
                        $pass,
                        [
                            PDO::ATTR_ERRMODE
                                => PDO::ERRMODE_EXCEPTION,

                            PDO::ATTR_DEFAULT_FETCH_MODE
                                => PDO::FETCH_ASSOC,

                            PDO::ATTR_EMULATE_PREPARES
                                => false,
                        ]
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | 2. Validate Database Name
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !preg_match(
                            '/^[a-zA-Z0-9_]+$/',
                            $db
                        )
                    ) {
                        throw new RuntimeException(
                            'Database name can contain only letters, numbers and underscores.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 3. Create Database
                    |--------------------------------------------------------------------------
                    */

                    $pdo->exec(
                        "CREATE DATABASE IF NOT EXISTS `{$db}`
                         CHARACTER SET utf8mb4
                         COLLATE utf8mb4_unicode_ci"
                    );

                    $pdo->exec(
                        "USE `{$db}`"
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | 4. Import Database Schema
                    |--------------------------------------------------------------------------
                    */

                    if (!file_exists($sqlFile)) {
                        throw new RuntimeException(
                            'database.sql file is missing from the application root directory.'
                        );
                    }

                    $sql = file_get_contents(
                        $sqlFile
                    );

                    if ($sql === false) {
                        throw new RuntimeException(
                            'Unable to read database.sql.'
                        );
                    }

                    if (trim($sql) === '') {
                        throw new RuntimeException(
                            'database.sql is empty.'
                        );
                    }

                    $pdo->exec($sql);

                    /*
                    |--------------------------------------------------------------------------
                    | 5. Verify Users Table
                    |--------------------------------------------------------------------------
                    */

                    $usersTable = $pdo->query(
                        "SHOW TABLES LIKE 'users'"
                    );

                    if (
                        !$usersTable ||
                        $usersTable->rowCount() === 0
                    ) {
                        throw new RuntimeException(
                            'Installation schema did not create the users table.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 6. Create / Update Super Admin
                    |--------------------------------------------------------------------------
                    */

                    $passwordHash = password_hash(
                        $adminPass,
                        PASSWORD_DEFAULT
                    );

                    $userLookup = $pdo->prepare(
                        "SELECT id
                         FROM users
                         WHERE email = ?
                         LIMIT 1"
                    );

                    $userLookup->execute([
                        $email
                    ]);

                    $existingUserId =
                        $userLookup->fetchColumn();

                    if ($existingUserId) {

                        $updateUser = $pdo->prepare(
                            "UPDATE users
                             SET
                                name = ?,
                                password_hash = ?,
                                role = ?
                             WHERE id = ?"
                        );

                        $updateUser->execute([
                            'OneSol Admin',
                            $passwordHash,
                            'admin',
                            $existingUserId
                        ]);

                    } else {

                        $insertUser = $pdo->prepare(
                            "INSERT INTO users
                            (
                                tenant_id,
                                name,
                                email,
                                password_hash,
                                role
                            )
                            VALUES
                            (
                                1,
                                ?,
                                ?,
                                ?,
                                ?
                            )"
                        );

                        $insertUser->execute([
                            'OneSol Admin',
                            $email,
                            $passwordHash,
                            'admin'
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 7. Seed Demo Invoice
                    |--------------------------------------------------------------------------
                    |
                    | Only seed if there are no invoices.
                    |
                    */

                    $invoiceCount = 0;

                    $invoiceTable = $pdo->query(
                        "SHOW TABLES LIKE 'invoices'"
                    );

                    if (
                        $invoiceTable &&
                        $invoiceTable->rowCount() > 0
                    ) {
                        $invoiceCount = (int) $pdo
                            ->query(
                                "SELECT COUNT(*) FROM invoices"
                            )
                            ->fetchColumn();
                    }

                    if ($invoiceCount === 0) {

                        /*
                        |--------------------------------------------------------------------------
                        | Demo Client
                        |--------------------------------------------------------------------------
                        */

                        $clientInsert = $pdo->prepare(
                            "INSERT INTO clients
                            (
                                tenant_id,
                                company_name,
                                address
                            )
                            VALUES
                            (
                                1,
                                ?,
                                ?
                            )"
                        );

                        $clientInsert->execute([
                            '360 Business Consultants',
                            'Dubai, United Arab Emirates'
                        ]);

                        $clientId = (int)
                            $pdo->lastInsertId();

                        /*
                        |--------------------------------------------------------------------------
                        | Demo Invoice
                        |--------------------------------------------------------------------------
                        */

                        $notes =
                            "This proposal is based on the mutually agreed project scope and deliverables.\n" .
                            "Any additional scope, integrations or major changes may be quoted separately.\n" .
                            "Payment schedule and project start date will follow the agreed confirmation.";

                        $invoiceInsert = $pdo->prepare(
                            "INSERT INTO invoices
                            (
                                tenant_id,
                                invoice_number,
                                client_id,
                                invoice_date,
                                valid_until,
                                status,
                                subtotal,
                                discount_type,
                                discount_value,
                                discount_amount,
                                total,
                                notes
                            )
                            VALUES
                            (
                                1,
                                ?,
                                ?,
                                ?,
                                ?,
                                'draft',
                                4000,
                                'fixed',
                                1500,
                                1500,
                                2500,
                                ?
                            )"
                        );

                        $invoiceInsert->execute([
                            'OS-PI-20260807-001',
                            $clientId,
                            '2026-08-07',
                            '2026-08-22',
                            $notes
                        ]);

                        $invoiceId = (int)
                            $pdo->lastInsertId();

                        /*
                        |--------------------------------------------------------------------------
                        | Demo Invoice Item
                        |--------------------------------------------------------------------------
                        */

                        $itemInsert = $pdo->prepare(
                            "INSERT INTO invoice_items
                            (
                                invoice_id,
                                description,
                                details,
                                qty,
                                unit_price,
                                amount,
                                sort_order
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                1,
                                4000,
                                4000,
                                0
                            )"
                        );

                        $itemInsert->execute([
                            $invoiceId,

                            'Software Development & Implementation Services',

                            'Includes setup, configuration, customization and delivery as per agreed scope.'
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 8. Write config.php
                    |--------------------------------------------------------------------------
                    */

                    $configData = [
                        'db_host' => $host,
                        'db_port' => $port,
                        'db_name' => $db,
                        'db_user' => $user,
                        'db_pass' => $pass,
                    ];

                    $configContent =
                        "<?php\n\n" .
                        "return " .
                        var_export(
                            $configData,
                            true
                        ) .
                        ";\n";

                    if (
                        file_put_contents(
                            $configFile,
                            $configContent,
                            LOCK_EX
                        ) === false
                    ) {
                        throw new RuntimeException(
                            'Could not write config.php. Check directory permissions.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 9. Secure Config File Permissions
                    |--------------------------------------------------------------------------
                    */

                    @chmod(
                        $configFile,
                        0640
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | 10. Regenerate Session / CSRF
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);

                    unset(
                        $_SESSION['installer_csrf']
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Installation Complete
                    |--------------------------------------------------------------------------
                    */

                    header(
                        'Location: login.php?installed=1'
                    );

                    exit;

                } catch (Throwable $exception) {

                    $error =
                        'Installation Failed: ' .
                        $exception->getMessage();
                }
            }
        }
    }

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>
        Install OneSol Invoice Manager
    </title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
    </style>

</head>

<body
    class="bg-slate-950 min-h-screen flex items-center justify-center p-4"
>

<div
    class="max-w-xl w-full bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-800"
>

    <!-- Header -->
    <div
        class="bg-slate-900 p-8 text-center border-b border-slate-800 relative"
    >

        <div
            class="w-16 h-16 bg-gradient-to-tr from-amber-500 to-amber-300 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-amber-500/20"
        >
            <i
                class="fa-solid fa-file-invoice text-slate-950 text-2xl font-black"
            ></i>
        </div>

        <h1
            class="text-2xl font-black text-white tracking-tight"
        >
            OneSol Invoice Manager
        </h1>

        <p
            class="text-xs text-slate-400 font-medium mt-1"
        >
            Multi-Tenant Accounting &amp; Invoicing SaaS Installer
        </p>

    </div>

    <div class="p-8">

        <?php if ($installed && !$forceReinstall): ?>

            <!-- Installed -->
            <div
                class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center"
            >

                <div
                    class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3 text-lg font-bold"
                >
                    <i class="fa-solid fa-check"></i>
                </div>

                <h3
                    class="text-lg font-bold text-emerald-900 mb-1"
                >
                    Application is Installed &amp; Ready
                </h3>

                <p
                    class="text-xs text-emerald-700 mb-6"
                >
                    Database connection verified and core tables are ready.
                </p>

                <a
                    href="login"
                    class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition-all shadow-md flex items-center justify-center space-x-2"
                >

                    <i
                        class="fa-solid fa-right-to-bracket"
                    ></i>

                    <span>
                        Proceed to Login
                    </span>

                </a>

            </div>

        <?php else: ?>

            <?php if ($forceReinstall && $installed): ?>

                <div
                    class="mb-6 bg-rose-50 border border-rose-200 rounded-2xl p-4 text-rose-800"
                >

                    <div
                        class="flex items-start space-x-3"
                    >

                        <i
                            class="fa-solid fa-triangle-exclamation text-rose-600 mt-0.5"
                        ></i>

                        <div>

                            <strong
                                class="font-black block text-sm text-rose-900 mb-1"
                            >
                                Reinstallation Mode
                            </strong>

                            <p class="text-xs">
                                Force reinstallation has been authorized.
                                Existing database data may be affected depending
                                on the contents of database.sql.
                            </p>

                        </div>

                    </div>

                </div>

            <?php endif; ?>


            <?php if ($dbErrorDetails && !$forceReinstall): ?>

                <div
                    class="mb-6 bg-amber-50 border border-amber-200 rounded-2xl p-4 text-amber-800 text-xs flex items-start space-x-3"
                >

                    <i
                        class="fa-solid fa-triangle-exclamation text-amber-600 text-sm mt-0.5"
                    ></i>

                    <div>

                        <strong
                            class="font-bold block text-amber-900 mb-0.5"
                        >
                            Setup Required
                        </strong>

                        <?= e($dbErrorDetails) ?>

                    </div>

                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div
                    class="mb-6 bg-rose-50 border border-rose-200 rounded-2xl p-4 text-rose-800 text-xs flex items-start space-x-3"
                >

                    <i
                        class="fa-solid fa-circle-exclamation text-rose-600 text-sm mt-0.5"
                    ></i>

                    <div>

                        <strong
                            class="font-bold block text-rose-900 mb-0.5"
                        >
                            Installation Error
                        </strong>

                        <?= e($error) ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- Installer Form -->
            <form
                method="post"
                class="space-y-5"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['installer_csrf']) ?>"
                >


                <!-- Database -->
                <div
                    class="border-b border-slate-200 pb-2 mb-4"
                >

                    <h3
                        class="text-xs font-black uppercase text-slate-400 tracking-wider"
                    >
                        1. Database Configuration
                    </h3>

                </div>


                <div
                    class="grid grid-cols-1 sm:grid-cols-2 gap-4"
                >

                    <div>

                        <label
                            class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5"
                        >
                            Database Host
                        </label>

                        <input
                            type="text"
                            name="db_host"
                            value="<?= e($_POST['db_host'] ?? 'localhost') ?>"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                        >

                    </div>


                    <div>

                        <label
                            class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5"
                        >
                            Database Port
                        </label>

                        <input
                            type="text"
                            inputmode="numeric"
                            name="db_port"
                            value="<?= e($_POST['db_port'] ?? '3306') ?>"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                        >

                    </div>

                </div>


                <div
                    class="grid grid-cols-1 sm:grid-cols-2 gap-4"
                >

                    <div>

                        <label
                            class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5"
                        >
                            Database Name
                        </label>

                        <input
                            type="text"
                            name="db_name"
                            value="<?= e($_POST['db_name'] ?? 'onesol_invoices') ?>"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                        >

                    </div>


                    <div>

                        <label
                            class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5"
                        >
                            Database User
                        </label>

                        <input
                            type="text"
                            name="db_user"
                            value="<?= e($_POST['db_user'] ?? 'root') ?>"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                        >

                    </div>

                </div>


                <div>

                    <label
                        class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5"
                    >
                        Database Password
                    </label>

                    <input
                        type="password"
                        name="db_pass"
                        placeholder="Enter MySQL Password"
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                    >

                </div>


                <!-- Admin -->
                <div
                    class="border-b border-slate-200 pb-2 mb-4 pt-3"
                >

                    <h3
                        class="text-xs font-black uppercase text-slate-400 tracking-wider"
                    >
                        2. Admin User Credentials
                    </h3>

                </div>


                <div
                    class="grid grid-cols-1 sm:grid-cols-2 gap-4"
                >

                    <div>

                        <label
                            class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5"
                        >
                            Admin Email
                        </label>

                        <input
                            type="email"
                            name="admin_email"
                            value="<?= e($_POST['admin_email'] ?? 'admin@onesol.ae') ?>"
                            required
                            autocomplete="off"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                        >

                    </div>


                    <div>

                        <label
                            class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5"
                        >
                            Admin Password
                        </label>

                        <input
                            type="password"
                            name="admin_password"
                            placeholder="Minimum 8 characters"
                            minlength="8"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                        >

                    </div>

                </div>


                <div class="pt-4">

                    <button
                        type="submit"
                        class="w-full py-3.5 px-6 bg-gradient-to-r from-slate-900 to-slate-800 hover:from-slate-800 hover:to-slate-700 text-white rounded-xl text-xs font-black tracking-wide shadow-xl transition-all flex items-center justify-center space-x-2 border border-slate-700"
                    >

                        <i
                            class="fa-solid fa-wand-magic-sparkles text-amber-400 text-sm"
                        ></i>

                        <span>
                            <?= $forceReinstall
                                ? 'Re-install Application'
                                : 'Create Database & Install Application'
                            ?>
                        </span>

                    </button>

                </div>

            </form>

        <?php endif; ?>

    </div>


    <!-- Footer -->
    <div
        class="bg-slate-50 px-8 py-4 border-t border-slate-100 text-center text-xs text-slate-400 font-bold uppercase tracking-wider"
    >
        OneSol Solutions
        &copy;
        <?= date('Y') ?>
        &bull;
        Enterprise SaaS Suite
    </div>

</div>

</body>

</html>