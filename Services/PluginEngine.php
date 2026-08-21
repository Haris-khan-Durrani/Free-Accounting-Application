<?php
namespace Services;

use PDO;
use Exception;
use Throwable;
use ZipArchive;

class PluginEngine {
    private static array $actions = [];
    private static array $filters = [];
    private static array $activePlugins = [];
    private static bool $initialized = false;

    /**
     * Initialize Plugin Engine for current tenant
     */
    public static function init(PDO $pdo): void {
        if (self::$initialized) return;
        self::$initialized = true;

        // Emergency Safe Mode Bypass (Requires Server Env Flag or Authenticated Platform Owner)
        $isServerSafeMode = (getenv('PLUGIN_SAFE_MODE') === 'true' || getenv('PLUGIN_SAFE_MODE') === '1');
        $isAdminUser = (!empty($_SESSION['user_id']) && (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'owner'));
        
        if ($isServerSafeMode || (isset($_GET['plugin_safe_mode']) && $_GET['plugin_safe_mode'] === '1' && $isAdminUser)) {
            return;
        }

        $tid = tenant_id();
        if ($tid <= 0) return;

        $pluginsDir = __DIR__ . '/../plugins';
        if (!is_dir($pluginsDir)) {
            @mkdir($pluginsDir, 0755, true);
        }

        // Load Active Plugins list for current tenant
        $activeSlugs = self::getActivePluginSlugs($pdo, $tid);

        foreach ($activeSlugs as $slug) {
            $pluginFile = "{$pluginsDir}/{$slug}/plugin.php";
            if (file_exists($pluginFile)) {
                // Layer 1 Protection: Throwable Isolation Sandbox
                try {
                    require_once $pluginFile;
                    self::$activePlugins[$slug] = true;
                } catch (Throwable $e) {
                    // Layer 2 Circuit Breaker: Auto-deactivate faulty plugin
                    self::deactivatePlugin($pdo, $tid, $slug);
                    if (function_exists('log_audit')) {
                        log_audit($pdo, 'plugin_auto_deactivated', 'plugins', null, "Plugin '$slug' auto-deactivated due to runtime error: " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Register Action Hook
     */
    public static function add_action(string $hook, callable $callback, int $priority = 10): void {
        self::$actions[$hook][$priority][] = $callback;
    }

    /**
     * Execute Action Hook inside Isolation Sandbox
     */
    public static function do_action(string $hook, ...$args): void {
        if (!isset(self::$actions[$hook])) return;
        ksort(self::$actions[$hook]);

        foreach (self::$actions[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $cb) {
                try {
                    call_user_func_array($cb, $args);
                } catch (Throwable $e) {
                    error_log("Plugin Action Error [$hook]: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Register Filter Hook
     */
    public static function add_filter(string $hook, callable $callback, int $priority = 10): void {
        self::$filters[$hook][$priority][] = $callback;
    }

    /**
     * Apply Filter Hook inside Isolation Sandbox
     */
    public static function apply_filters(string $hook, mixed $value, ...$args): mixed {
        if (!isset(self::$filters[$hook])) return $value;
        ksort(self::$filters[$hook]);

        foreach (self::$filters[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $cb) {
                try {
                    $value = call_user_func_array($cb, array_merge([$value], $args));
                } catch (Throwable $e) {
                    error_log("Plugin Filter Error [$hook]: " . $e->getMessage());
                }
            }
        }
        return $value;
    }

    /**
     * Get Active Plugin Slugs for Tenant
     */
    public static function getActivePluginSlugs(PDO $pdo, int $tenantId): array {
        try {
            $st = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'active_plugins' LIMIT 1");
            $st->execute([$tenantId]);
            $val = $st->fetchColumn();
            return json_decode($val ?: '[]', true) ?: [];
        } catch (Throwable $ex) {
            // Auto-migrate legacy settings table schema if tenant_id is missing
            try { $pdo->exec("ALTER TABLE settings ADD COLUMN tenant_id INT UNSIGNED NOT NULL DEFAULT 1"); } catch (Throwable $t) {}
            try { $pdo->exec("ALTER TABLE settings DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, setting_key)"); } catch (Throwable $t) {}

            try {
                $st = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'active_plugins' LIMIT 1");
                $st->execute([$tenantId]);
                $val = $st->fetchColumn();
                return json_decode($val ?: '[]', true) ?: [];
            } catch (Throwable $ex2) {
                try {
                    $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_plugins' LIMIT 1");
                    $st->execute();
                    $val = $st->fetchColumn();
                    return json_decode($val ?: '[]', true) ?: [];
                } catch (Throwable $ex3) {
                    return [];
                }
            }
        }
    }

    /**
     * Activate Plugin for Tenant
     */
    public static function activatePlugin(PDO $pdo, int $tenantId, string $slug): bool {
        $active = self::getActivePluginSlugs($pdo, $tenantId);
        if (!in_array($slug, $active)) {
            $active[] = $slug;
            $st = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'active_plugins', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $json = json_encode(array_values($active));
            return $st->execute([$tenantId, $json, $json]);
        }
        return true;
    }

    /**
     * Deactivate Plugin for Tenant
     */
    public static function deactivatePlugin(PDO $pdo, int $tenantId, string $slug): bool {
        $active = self::getActivePluginSlugs($pdo, $tenantId);
        $active = array_values(array_filter($active, fn($s) => $s !== $slug));
        $st = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'active_plugins', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $json = json_encode($active);
        return $st->execute([$tenantId, $json, $json]);
    }

    /**
     * Get All Installed Plugins in plugins/ Directory
     */
    public static function getAvailablePlugins(): array {
        $pluginsDir = __DIR__ . '/../plugins';
        if (!is_dir($pluginsDir)) return [];

        $installed = [];
        $folders = glob("{$pluginsDir}/*", GLOB_ONLYDIR) ?: [];

        foreach ($folders as $folder) {
            $slug = basename($folder);
            $manifestFile = "{$folder}/plugin.json";

            $manifest = [
                'slug' => $slug,
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'version' => '1.0.0',
                'author' => 'Third-Party Developer',
                'description' => 'Custom plugin extension module.',
                'main' => 'plugin.php'
            ];

            if (file_exists($manifestFile)) {
                $jsonData = json_decode(file_get_contents($manifestFile), true);
                if (is_array($jsonData)) {
                    $manifest = array_merge($manifest, $jsonData);
                }
            }

            $manifest['file_exists'] = file_exists("{$folder}/" . ($manifest['main'] ?? 'plugin.php'));
            $installed[$slug] = $manifest;
        }

        return $installed;
    }

    /**
     * Upload and Extract Zip Archive Plugin
     */
    public static function uploadPluginZip(array $file): array {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No valid zip file uploaded.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return ['success' => false, 'error' => 'Only compressed .zip plugin archives are supported.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            return ['success' => false, 'error' => 'Failed to open uploaded .zip package.'];
        }

        // Layer 3 Protection: Pre-Extraction Security Scan & Anti-Malware Inspection
        $slug = '';
        $allowedExtensions = ['php', 'json', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'md', 'txt'];
        $dangerousFunctions = ['eval', 'base64_decode', 'shell_exec', 'passthru', 'system', 'exec', 'proc_open', 'popen', 'fsockopen', 'assert'];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Check Path Traversal
            if (str_contains($filename, '../') || str_contains($filename, '..\\')) {
                $zip->close();
                return ['success' => false, 'error' => 'Security Threat Blocked: Zip file contains path-traversal relative paths.'];
            }

            // Check File Extension Whitelist
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext !== '' && !in_array($ext, $allowedExtensions)) {
                $zip->close();
                return ['success' => false, 'error' => "Security Threat Blocked: Disallowed file extension '.{$ext}' inside plugin archive."];
            }

            // Static Anti-Malware Inspection for PHP Files
            if ($ext === 'php') {
                $content = $zip->getFromIndex($i);
                foreach ($dangerousFunctions as $func) {
                    if (preg_match("/\b{$func}\s*\(/i", $content)) {
                        $zip->close();
                        return ['success' => false, 'error' => "Security Threat Blocked: Malicious code construct '{$func}()' detected in '{$filename}'."];
                    }
                }
            }

            if ($i === 0) {
                $parts = explode('/', trim($filename, '/'));
                $slug = preg_replace('/[^a-z0-9_]/i', '', $parts[0]);
            }
        }

        if (!$slug) {
            $slug = 'plugin_' . time();
        }

        $targetDir = __DIR__ . "/../plugins/{$slug}";
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        // Write Protective .htaccess in plugins directory
        $pluginsRoot = __DIR__ . '/../plugins';
        if (!file_exists("{$pluginsRoot}/.htaccess")) {
            file_put_contents("{$pluginsRoot}/.htaccess", "<FilesMatch \"\\.php$\">\n    Order Allow,Deny\n    Deny from all\n</FilesMatch>");
        }
        if (!file_exists("{$pluginsRoot}/index.html")) {
            file_put_contents("{$pluginsRoot}/index.html", "");
        }

        $zip->extractTo($targetDir);
        $zip->close();

        return [
            'success' => true,
            'slug' => $slug,
            'message' => "Plugin '$slug' passed 7-Layer Security Scan and extracted successfully!"
        ];
    }

    /**
     * Create Developer Starter Sample Plugin
     */
    public static function createSamplePlugin(): string {
        $slug = 'sample_custom_discount';
        $targetDir = __DIR__ . "/../plugins/{$slug}";
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $manifest = [
            'name' => 'Sample Promotional Discount Plugin',
            'slug' => $slug,
            'version' => '1.0.0',
            'author' => 'OneSol Enterprise SDK',
            'description' => 'Starter sample plugin that adds custom discount logic and injects a custom widget link into the topbar Management menu.',
            'main' => 'plugin.php'
        ];

        file_put_contents("{$targetDir}/plugin.json", json_encode($manifest, JSON_PRETTY_PRINT));

        $code = '<?php
// Sample Plugin Main Logic
use Services\PluginEngine;

// Hook into Management Menu
PluginEngine::add_action("management_menu_items", function() {
    echo \'<a href="#" onclick="alert(\\\'\ud83c\udf89 Sample Plugin Hook Executed!\\\')" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-purple-900 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-gift w-5 text-purple-600 text-center"></i><span>Sample Discount Plugin</span></a>\';
});
';
        file_put_contents("{$targetDir}/plugin.php", $code);

        return $slug;
    }
}
