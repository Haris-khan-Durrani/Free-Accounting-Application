# OneSol Modular Plugin Development Guide & API Specification

Welcome to the **OneSol Invoice Manager Modular Plugin Developer Guide**. This documentation provides everything a developer needs to build, test, and distribute custom plug-and-play feature extensions.

---

## 📂 1. Plugin Folder Structure

Every plugin resides inside its own dedicated directory within the `plugins/` root folder:

```
onesol_invoice_manager/
└── plugins/
    └── my_custom_feature/
        ├── plugin.json       (Required: Manifest metadata)
        ├── plugin.php        (Required: Main PHP entry point)
        ├── helpers.php       (Optional: Helper logic)
        └── assets/           (Optional: CSS/JS assets)
            └── script.js
```

To package a plugin for distribution, compress the contents of `my_custom_feature/` into a **.zip archive** (e.g., `my_custom_feature.zip`).

---

## 📄 2. The `plugin.json` Manifest Specification

Every plugin **MUST** contain a `plugin.json` file at its root. This defines the plugin's metadata, permissions, and main file reference.

```json
{
  "name": "Custom Royalty Fee Calculator",
  "slug": "custom_royalty_fee",
  "version": "1.0.0",
  "author": "Acme Solutions Ltd",
  "description": "Calculates automatic percentage royalty fees on invoice creation.",
  "main": "plugin.php"
}
```

### Manifest Fields:
| Field | Type | Description |
| :--- | :--- | :--- |
| `name` | String | Human-readable title displayed in the Plugin Console. |
| `slug` | String | Unique identifier (lowercase letters, numbers, underscores only). |
| `version` | String | Semantic version string (e.g., `1.0.0`). |
| `author` | String | Name of the developer or organization. |
| `description` | String | Summary of the plugin's features and purpose. |
| `main` | String | Path to the primary PHP entry point file (defaults to `plugin.php`). |

---

## ⚡ 3. Hook System & API Reference

The plugin engine uses an **Event-Driven Hook Architecture** similar to WordPress (`add_action` and `add_filter`).

### Registering an Action Hook
Actions allow plugins to execute custom code at specific lifecycle events (e.g., when rendering menus or saving invoices):

```php
use Services\PluginEngine;

PluginEngine::add_action('management_menu_items', function() {
    echo '<a href="custom_page.php" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10">';
    echo '<i class="fa-solid fa-calculator w-5 text-indigo-600 text-center"></i><span>Royalty Calculator</span>';
    echo '</a>';
});
```

### Registering a Filter Hook
Filters allow plugins to intercept and modify values before they are saved or displayed:

```php
use Services\PluginEngine;

PluginEngine::add_filter('invoice_subtotal_calc', function($subtotal, $invoiceData) {
    // Add custom 2% admin processing fee
    return $subtotal * 1.02;
}, 10, 2);
```

---

## 🪝 4. Available System Hooks

Below is a reference of built-in system hooks available across the application:

| Hook Name | Hook Type | Arguments Provided | Description |
| :--- | :--- | :--- | :--- |
| `management_menu_items` | Action | None | Injects custom links into the Topbar Management Mega Menu. |
| `dashboard_widgets_top` | Action | `$pdo`, `$tenantId` | Renders custom analytical widgets at the top of the main Dashboard. |
| `invoice_before_save` | Filter | `$invoiceData` | Intercepts and alters invoice payload before database insertion. |
| `invoice_after_save` | Action | `$pdo`, `$invoiceId`, `$tenantId` | Executed immediately after an invoice is created/updated (ideal for webhooks). |
| `payment_gateways_register` | Filter | `$gatewaysArray` | Registers custom third-party payment gateways. |
| `client_portal_footer` | Action | `$clientData` | Injects custom HTML/JS (chat widgets, analytics) into client portal views. |

---

## 🗄️ 5. Working with Database Schemas & MySQL

Plugins have full access to PDO database instances via `$GLOBALS['pdo']`. However, developers **MUST** follow multi-tenant database safety rules:

### Rule A: Mandatory Table Prefixing
If your plugin needs custom SQL tables, table names **MUST** be prefixed with `plugin_{slug}_`:

```php
$pdo = $GLOBALS['pdo'];
$pdo->exec("
    CREATE TABLE IF NOT EXISTS plugin_custom_royalty_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        invoice_id INT NOT NULL,
        fee_amount DECIMAL(15,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
```

### Rule B: Enforced Multi-Tenancy (`tenant_id`)
Every custom table **MUST** include a `tenant_id INT` column. All queries executed by plugins **MUST** filter by `tenant_id = tenant_id()`:

```php
$tid = tenant_id();
$st = $pdo->prepare("SELECT * FROM plugin_custom_royalty_logs WHERE tenant_id = ? ORDER BY id DESC");
$st->execute([$tid]);
$logs = $st->fetchAll();
```

### Rule C: Strictly Forbidden SQL Operations
Plugins are **BLOCKED** from running `DROP TABLE`, `TRUNCATE`, or destructive `ALTER TABLE` on core application tables (`invoices`, `clients`, `users`, `tenants`, `settings`, `journal_entries`).

---

## 🛡️ 6. 7-Layer Anti-Hacking & Core Security Protection Suite

To ensure custom plugins can **NEVER** compromise the host application, exploit server permissions, or leak subaccount data, OneSol Invoice Manager implements 7 active defense layers:

1. **🔍 Pre-Extraction Static Malware & Vulnerability Scanner**:
   - Every uploaded `.zip` package is scanned before extraction.
   - **Extension Whitelist**: Only extracts safe extensions (`.php`, `.json`, `.css`, `.js`, `.png`, `.jpg`, `.svg`, `.md`, `.txt`). Blocks `.phtml`, `.exe`, `.bat`, `.sh`, `.htaccess`.
   - **Static Malware Inspection**: Scans PHP files for dangerous RCE / backdoor constructs (`eval()`, `base64_decode()`, `shell_exec()`, `passthru()`, `system()`, `exec()`, `proc_open()`, `assert()`).
2. **🚫 Path Traversal Block**:
   - Inspects zip archive headers to block path-traversal relative paths (`../`, `..\`) and root overrides.
3. **🔒 Isolated Filesystem Lockdown (`.htaccess`)**:
   - Auto-writes protective `.htaccess` inside `plugins/` blocking direct web execution of standalone script files. Code runs ONLY when included safely via `bootstrap.php`.
4. **🔒 Isolated Throwable Sandbox (`try-catch \Throwable`)**:
   - Any syntax error, fatal error, or exception in third-party code is caught silently without crashing the user interface.
5. **⚡ Circuit Breaker Auto-Deactivation**:
   - If a plugin produces a runtime error, `PluginEngine` logs the stack trace to `audit_logs` and automatically deactivates the faulty plugin.
6. **🏢 Enforced Multi-Tenant Data Isolation (`WHERE tenant_id = ?`)**:
   - Enforces parameter binding and `tenant_id` filtering so Subaccount A's plugins can **NEVER** inspect or alter Subaccount B's financial data.
7. **🚨 Emergency Safe Mode (`?plugin_safe_mode=1`)**:
   - Visiting any page with `?plugin_safe_mode=1` bypasses all active plugins for immediate admin recovery.

---

## 💻 7. Complete Production-Ready Example Plugin

Below is a complete, working example plugin package:

### `plugins/custom_discount_rules/plugin.json`
```json
{
  "name": "Custom Volume Discount Rule",
  "slug": "custom_discount_rules",
  "version": "1.0.0",
  "author": "Enterprise Partner SDK",
  "description": "Automatically applies a 5% discount when invoice total exceeds 10,000 AED.",
  "main": "plugin.php"
}
```

### `plugins/custom_discount_rules/plugin.php`
```php
<?php
use Services\PluginEngine;

// 1. Add Navigation Link to Topbar
PluginEngine::add_action('management_menu_items', function() {
    echo '<a href="#" onclick="alert(\'Volume Discount Plugin Active!\')" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-emerald-900 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10">';
    echo '<i class="fa-solid fa-percent w-5 text-emerald-600 text-center"></i><span>Volume Discount Active</span>';
    echo '</a>';
});

// 2. Intercept Invoice Calculation Filter
PluginEngine::add_filter('invoice_before_save', function($invoiceData) {
    if (isset($invoiceData['subtotal']) && $invoiceData['subtotal'] > 10000) {
        $invoiceData['discount_type'] = 'percent';
        $invoiceData['discount_value'] = 5.0; // 5% discount
        log_audit($GLOBALS['pdo'], 'plugin_discount_applied', 'invoice', null, 'Applied 5% volume discount rule');
    }
    return $invoiceData;
});
```
