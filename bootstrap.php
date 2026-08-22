<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    if ($isHttps || getenv('APP_ENV') === 'production') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Autoload Core namespace classes
spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    $base_dir = __DIR__ . '/Core/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Autoload Services namespace classes
spl_autoload_register(function ($class) {
    $prefix = 'Services\\';
    $base_dir = __DIR__ . '/Services/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

if (!file_exists(__DIR__ . '/config.php')) {
    if (basename($_SERVER['PHP_SELF'] ?? '') !== 'install.php' && basename($_SERVER['PHP_SELF'] ?? '') !== 'install') {
        header('Location: install.php');
        exit;
    }
    return;
}

$config = require __DIR__ . '/config.php';
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config.php.');
}

// Security Headers
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https:;");
}

// Auto-migrate legacy tables (Gated for CLI or explicit migration trigger to prevent per-request DDL lock overhead)
if (php_sapi_name() === 'cli' || getenv('RUN_MIGRATIONS') === 'true') {
    try { $pdo->exec("ALTER TABLE invoices ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER tax_amount"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','sent','partially_paid','paid','overdue','void','cancelled') NOT NULL DEFAULT 'draft'"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE settings ADD COLUMN tenant_id INT UNSIGNED NOT NULL DEFAULT 1"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE settings DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, setting_key)"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE tenants ADD COLUMN require_2fa TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE tenants ADD COLUMN smtp_host VARCHAR(255) NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE tenants ADD COLUMN smtp_port INT NOT NULL DEFAULT 587"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE tenants ADD COLUMN smtp_encryption VARCHAR(10) NOT NULL DEFAULT 'tls'"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE tenants ADD COLUMN smtp_username VARCHAR(255) NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE tenants ADD COLUMN smtp_password TEXT NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE tenants ADD COLUMN from_email VARCHAR(255) NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE tenants ADD COLUMN from_name VARCHAR(255) NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_code VARCHAR(64) NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE users MODIFY COLUMN otp_code VARCHAR(64) NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_expires_at DATETIME NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires_at DATETIME NULL"); } catch (Throwable $t) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1"); } catch (Throwable $t) {}
}


// Helpers
function e(?string $value): string { 
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); 
}

function money(float $value, string $currencyCode = 'AED', ?PDO $pdoInstance = null): string {
    global $pdo;
    return \Core\Currency::format($value, $currencyCode, $pdoInstance ?: $pdo);
}

function seed_chart_of_accounts(PDO $pdo, int $tenantId): void {
    \Core\Tenant::seedAccounts($pdo, $tenantId);
}

function tenant(): array {
    global $pdo;
    if (!\Core\Tenant::hasActiveId()) {
        return [
            'id' => 1,
            'name' => 'OneSol Headquarters',
            'code' => 'onesol-hq',
            'currency' => 'AED',
            'country_code' => 'AE'
        ];
    }
    return \Core\Tenant::getActive($pdo);
}

function tenant_id(): int {
    return \Core\Tenant::getActiveId();
}

function branding(?int $tenantId = null): array {
    global $pdo;
    return \Core\Branding::get($pdo, $tenantId);
}

function redirect(string $url): never { 
    $targetUrl = strpos($url, '.php') === false && strpos($url, '/') === false && strpos($url, '?') === false ? $url . '.php' : $url;
    header('Location: ' . $targetUrl); 
    exit; 
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function csrf_field(): string { 
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; 
}

function verify_csrf(): void {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
        http_response_code(419); 
        exit('Invalid CSRF token.');
    }
    // Rotate token after successful validation to prevent token replay
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function require_login(): void { 
    if (empty($_SESSION['user_id'])) {
        redirect('login'); 
    }

    // Fail-Closed Runtime Session Revocation Check
    if (!isset($_SESSION['session_version'])) {
        session_unset();
        session_destroy();
        redirect('login');
    }

    global $pdo;
    if ($pdo) {
        try {
            $stV = $pdo->prepare("SELECT session_version FROM users WHERE id = ?");
            $stV->execute([(int)$_SESSION['user_id']]);
            $dbVer = $stV->fetchColumn();
            if ($dbVer === false || (int)$_SESSION['session_version'] !== (int)$dbVer) {
                session_unset();
                session_destroy();
                redirect('login');
            }
        } catch (\Throwable $t) {
            // Self-healing schema migration for missing session_version column
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1");
                $stV = $pdo->prepare("SELECT session_version FROM users WHERE id = ?");
                $stV->execute([(int)$_SESSION['user_id']]);
                $dbVer = $stV->fetchColumn();
                if ($dbVer === false || (int)$_SESSION['session_version'] !== (int)$dbVer) {
                    session_unset();
                    session_destroy();
                    redirect('login');
                }
            } catch (\Throwable $t2) {
                session_unset();
                session_destroy();
                redirect('login');
            }
        }
    }
}

function has_role(array $allowedRoles): bool {
    $userRole = $_SESSION['user_role'] ?? 'viewer'; // Default to least-privilege on missing session key
    return in_array($userRole, $allowedRoles, true);
}

function require_role(array $allowedRoles): void {
    require_login();
    if (!has_role($allowedRoles)) {
        http_response_code(403);
        flash('error', 'Access denied. You do not have permission to access this resource.');
        redirect('index');
    }
}

function require_platform_admin(): void {
    require_login();

    $tid = tenant_id();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $userRole = $_SESSION['user_role'] ?? '';

    if ($tid !== 1 || $userRole !== 'owner') {
        http_response_code(403);
        flash('error', 'Access denied. Restricted to SaaS Master Platform Administrator.');
        redirect('index');
    }

    global $pdo;
    if ($pdo) {
        try {
            $stMaster = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id = 1 AND role = 'owner'");
            $stMaster->execute([$userId]);
            if ((int)$stMaster->fetchColumn() === 0) {
                http_response_code(403);
                flash('error', 'Access denied. Restricted to SaaS Master Platform Administrator.');
                redirect('index');
            }
        } catch (\Throwable $t) {
            http_response_code(503);
            exit('Service unavailable. Platform administration verification failed.');
        }
    }
}

function flash(string $type, string $message): void { 
    $_SESSION['flash'] = ['type'=>$type, 'message'=>$message]; 
}

function get_flash(): ?array { 
    $f = $_SESSION['flash'] ?? null; 
    unset($_SESSION['flash']); 
    return $f; 
}

function invoice_number(PDO $pdo): string {
    $tid = tenant_id();
    $prefix = 'INV-' . date('Y') . '-';
    $stmt = $pdo->prepare('SELECT invoice_number FROM invoices WHERE tenant_id = ? AND invoice_number LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$tid, $prefix . '%']);
    $last = $stmt->fetchColumn();
    $n = $last ? ((int)substr($last, -4) + 1) : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function quote_number(PDO $pdo): string {
    $tid = tenant_id();
    $prefix = 'QT-' . date('Y') . '-';
    $stmt = $pdo->prepare('SELECT quote_number FROM quotes WHERE tenant_id = ? AND quote_number LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$tid, $prefix . '%']);
    $last = $stmt->fetchColumn();
    $n = $last ? ((int)substr($last, -4) + 1) : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function calc_discount(float $subtotal, string $type, float $value): float {
    if ($type === 'percent') return min($subtotal, max(0, $subtotal * $value / 100));
    return min($subtotal, max(0, $value));
}

function log_audit(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $tenantId = null): void {
    $tid = $tenantId;
    if (empty($tid)) {
        try {
            $tid = tenant_id();
        } catch (\Throwable $e) {
            $tid = 1;
        }
    }
    try {
        $st = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $st->execute([
            $tid,
            $_SESSION['user_id'] ?? null,
            $action,
            $entityType,
            $entityId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (\Throwable $ex) {}
}

function get_invoice_token(array|object $inv): string {
    $id = is_array($inv) ? ($inv['id'] ?? 0) : ($inv->id ?? 0);
    $num = is_array($inv) ? ($inv['invoice_number'] ?? '') : ($inv->invoice_number ?? '');
    $tid = is_array($inv) ? ($inv['tenant_id'] ?? 1) : ($inv->tenant_id ?? 1);
    global $config;
    $secret = $config['invoice_link_key'] ?? $config['app_key'] ?? getenv('APP_KEY') ?? '';

    if (empty($secret)) {
        $keyFile = __DIR__ . '/storage/app_key.txt';
        if (file_exists($keyFile)) {
            $secret = trim((string)file_get_contents($keyFile));
        } else {
            $secret = bin2hex(random_bytes(32));
            @file_put_contents($keyFile, $secret, LOCK_EX);
        }
    }

    return substr(hash_hmac('sha256', "inv_{$id}_{$num}_{$tid}", $secret), 0, 32);
}

function get_public_invoice_url(array|object $inv): string {
    $id = is_array($inv) ? ($inv['id'] ?? 0) : ($inv->id ?? 0);
    $token = get_invoice_token($inv);
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
    return "{$scheme}://{$host}{$dir}/public_invoice.php?id={$id}&token={$token}";
}

function send_payment_receipt_email(PDO $pdo, int $paymentId): bool {
    try {
        $st = $pdo->prepare("
            SELECT p.*, i.invoice_number, i.total invoice_total, i.paid_amount invoice_paid_amount, i.currency invoice_currency, i.status invoice_status, 
                   c.company_name, c.contact_name, c.email client_email
            FROM payments p 
            JOIN invoices i ON i.id = p.invoice_id
            JOIN clients c ON c.id = i.client_id
            WHERE p.id = ?
        ");
        $st->execute([$paymentId]);
        $pay = $st->fetch();

        if (!$pay || empty($pay['client_email'])) {
            return false;
        }

        $tid = (int)$pay['tenant_id'];

        // Check if tenant has enabled automated payment receipt emails (Default: Enabled '1')
        $autoSend = \Services\PaymentGatewayService::getSetting($pdo, 'auto_send_payment_receipt_email', '1', $tid);
        if ($autoSend !== '1') {
            return false;
        }

        $brand = \Core\Branding::get($pdo, $tid);
        $companyName = $brand['company_name'] ?? 'OneSol Solutions';
        $logoUrl = $brand['logo_url'] ?? '';
        $primaryColor = $brand['primary_color'] ?? '#0f172a';

        // Fetch full invoice record to generate secure public URL
        $stInvFull = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $stInvFull->execute([$pay['invoice_id']]);
        $invFull = $stInvFull->fetch();
        $publicUrl = get_public_invoice_url($invFull);

        $currency = $pay['currency'] ?: $pay['invoice_currency'];
        $amountStr = function_exists('money') ? money((float)$pay['amount'], $currency) : ($currency . ' ' . number_format((float)$pay['amount'], 2));
        $payDateStr = date('d M Y, h:i A', strtotime($pay['created_at'] ?: $pay['payment_date']));
        $gatewayTxnId = $pay['gateway_transaction_id'] ?: ($pay['reference'] ?: 'N/A');
        $payMethodStr = ucwords(str_replace('_', ' ', $pay['payment_method']));

        $balanceRemaining = max(0, (float)$pay['invoice_total'] - (float)$pay['invoice_paid_amount']);
        $balanceStr = function_exists('money') ? money($balanceRemaining, $currency) : ($currency . ' ' . number_format($balanceRemaining, 2));

        $subject = "Payment Receipt for Invoice {$pay['invoice_number']} - {$companyName}";

        // Branded Email Header Logo / Text
        $logoHtml = !empty($logoUrl) 
            ? '<img src="' . e($logoUrl) . '" alt="' . e($companyName) . '" style="max-height: 48px; width: auto; display: block; margin: 0 auto;">'
            : '<h2 style="color:#ffffff; margin:0; font-size:22px; font-weight:900;">' . e($companyName) . '</h2>';

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
                .email-container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
                .header { background: ' . e($primaryColor) . '; color: #ffffff; padding: 32px 24px; text-align: center; }
                .content { padding: 32px 24px; }
                .receipt-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 24px; }
                .amount-badge { font-size: 28px; font-weight: 900; color: #166534; font-family: monospace; margin: 8px 0; }
                .details-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
                .details-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
                .label { color: #64748b; font-weight: 600; }
                .val { color: #0f172a; font-weight: 800; text-align: right; }
                .btn { display: inline-block; background: #10b981; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 10px; font-weight: 800; font-size: 14px; text-align: center; box-shadow: 0 4px 6px -1px rgba(16,185,129,0.3); }
                .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    ' . $logoHtml . '
                    <p style="margin: 8px 0 0 0; font-size: 13px; opacity: 0.85; text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">Official Payment Confirmation</p>
                </div>
                <div class="content">
                    <h3 style="margin-top: 0; font-size: 18px; color: #0f172a;">Dear ' . e($pay['contact_name'] ?: $pay['company_name']) . ',</h3>
                    <p style="font-size: 14px; color: #475569; line-height: 1.6;">Thank you for your payment. We have successfully received and processed your payment for Invoice <strong>' . e($pay['invoice_number']) . '</strong>.</p>
                    
                    <div class="receipt-card">
                        <div style="font-size: 12px; font-weight: 800; color: #15803d; text-transform: uppercase; letter-spacing: 1px;">✔ Payment Confirmed & Received</div>
                        <div class="amount-badge">' . $amountStr . '</div>
                        <div style="font-size: 12px; color: #166534; font-weight: 600;">Processed via ' . e($payMethodStr) . '</div>
                    </div>

                    <table class="details-table">
                        <tr>
                            <td class="label">Invoice Number</td>
                            <td class="val">' . e($pay['invoice_number']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Payment Date & Time</td>
                            <td class="val">' . e($payDateStr) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Transaction / Stripe ID</td>
                            <td class="val" style="font-family: monospace;">' . e($gatewayTxnId) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Remaining Balance Due</td>
                            <td class="val" style="color: ' . ($balanceRemaining > 0 ? '#b45309' : '#059669') . ';">' . $balanceStr . '</td>
                        </tr>
                    </table>

                    <div style="text-align: center; margin-top: 28px;">
                        <a href="' . e($publicUrl) . '" class="btn" target="_blank">View Official Tax Invoice & Receipt →</a>
                    </div>
                </div>
                <div class="footer">
                    <strong>' . e($companyName) . '</strong><br>
                    ' . e($brand['company_email'] ?? '') . ' | ' . e($brand['company_phone'] ?? '') . '<br>
                    ' . e($brand['company_website'] ?? '') . '
                </div>
            </div>
        </body>
        </html>';

        $sent = \Services\Mailer::send($pdo, $tid, $pay['client_email'], $subject, $htmlBody);
        if ($sent) {
            log_audit($pdo, 'payment_receipt_email_sent', 'payments', $paymentId, "Automated payment receipt emailed to {$pay['client_email']} for Invoice #{$pay['invoice_number']}", $tid);
        }
        return $sent;
    } catch (\Throwable $e) {
        error_log("Failed to send automated payment receipt email for payment #{$paymentId}: " . $e->getMessage());
        return false;
    }
}

function get_custom_wording(PDO $pdo, int $tenantId): array {
    $defaults = [
        'title'        => 'TAX INVOICE',
        'invoice_no'   => 'Invoice Number',
        'invoice_date' => 'Invoice Date',
        'due_date'     => 'Payment Due Date',
        'billed_to'    => 'Billed To (Client Details)',
        'tax_label'    => 'TRN / Tax ID',
        'subtotal'     => 'Subtotal',
        'discount'     => 'Discount',
        'tax_amount'   => 'VAT (5%)',
        'total'        => 'Total Amount Due',
        'paid_amount'  => 'Amount Paid',
        'balance_due'  => 'Balance Due',
        'terms_label'  => 'Terms & Conditions',
        'bank_label'   => 'Remittance Bank Details',
        'sign_label'   => 'Authorized Signatory',
    ];

    $st = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'custom_invoice_wording'");
    $st->execute([$tenantId]);
    $json = $st->fetchColumn();
    if ($json) {
        $saved = json_decode($json, true);
        if (is_array($saved)) {
            foreach ($saved as $k => $v) {
                if (!empty(trim((string)$v))) {
                    $defaults[$k] = trim((string)$v);
                }
            }
        }
    }
    return $defaults;
}



// Initialize Modular Plugin Engine for Active Tenant
if (\Core\Tenant::hasActiveId()) {
    try {
        \Services\PluginEngine::init($pdo);
    } catch (\Core\TenantContextException $e) {
        // Unauthenticated bootstrap fallback
    }
}

