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

// Helpers
function e(?string $value): string { 
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); 
}

function money(float $value, string $currencyCode = 'AED', ?PDO $pdoInstance = null): string {
    global $pdo;
    return \Core\Currency::format($value, $currencyCode, $pdoInstance ?: $pdo);
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
    $cleanUrl = preg_replace('/\.php$/', '', $url);
    header('Location: ' . $cleanUrl); 
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
}

function has_role(array $allowedRoles): bool {
    $userRole = $_SESSION['user_role'] ?? 'owner';
    return in_array($userRole, $allowedRoles, true);
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

function log_audit(PDO $pdo, string $action, string $entityType, ?int $entityId = null, ?string $details = null): void {
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

// Initialize Modular Plugin Engine for Active Tenant
if (\Core\Tenant::hasActiveId()) {
    try {
        \Services\PluginEngine::init($pdo);
    } catch (\Core\TenantContextException $e) {
        // Unauthenticated bootstrap fallback
    }
}

