<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
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
    try { $pdo->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'owner'"); } catch (Throwable $t) {}
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
            session_unset();
            session_destroy();
            http_response_code(503);
            exit('Service unavailable. Database session verification failed.');
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

function log_audit(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void {
    $st = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->execute([
        tenant_id(),
        $_SESSION['user_id'] ?? null,
        $action,
        $entityType,
        $entityId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
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

