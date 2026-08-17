<?php
// Automatic Schema Migrator and Seeder
require_once __DIR__ . '/bootstrap.php';

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    try {
        $st = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if ($st && !$st->fetch()) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    } catch (PDOException $e) {
        echo "Column error on {$table}.{$column}: " . $e->getMessage() . "\n";
    }
}

function run_migrations(PDO $pdo): string {
    $output = [];

    // Core table migrations
    ensure_column($pdo, 'users', 'tenant_id', 'INT UNSIGNED NULL AFTER id');
    ensure_column($pdo, 'users', 'role', "ENUM('owner','admin','accountant','sales','viewer') NOT NULL DEFAULT 'owner' AFTER password_hash");
    ensure_column($pdo, 'users', 'phone', 'VARCHAR(60) NULL AFTER role');

    // 2FA Security Columns on Users Table
    ensure_column($pdo, 'users', 'two_factor_enabled', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER phone');
    ensure_column($pdo, 'users', 'otp_code', 'VARCHAR(10) NULL AFTER two_factor_enabled');
    ensure_column($pdo, 'users', 'otp_expires_at', 'DATETIME NULL AFTER otp_code');

    ensure_column($pdo, 'clients', 'tenant_id', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER id');
    ensure_column($pdo, 'clients', 'otp_code', 'VARCHAR(10) NULL AFTER currency');
    ensure_column($pdo, 'clients', 'otp_expires_at', 'DATETIME NULL AFTER otp_code');
    ensure_column($pdo, 'invoices', 'tenant_id', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER id');
    ensure_column($pdo, 'invoices', 'paid_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER tax_amount');
    ensure_column($pdo, 'invoices', 'template_id', "VARCHAR(60) NOT NULL DEFAULT 'onesol_executive_gold' AFTER notes");

    // Modify invoices status ENUM to include partially_paid and void
    try {
        $pdo->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','sent','partially_paid','paid','overdue','void','cancelled') NOT NULL DEFAULT 'draft'");
    } catch (PDOException $e) {}

    ensure_column($pdo, 'settings', 'tenant_id', 'INT UNSIGNED NOT NULL DEFAULT 1 FIRST');

    // Tenants Table Subscription & SMTP / 2FA Columns
    ensure_column($pdo, 'tenants', 'plan_id', 'INT UNSIGNED NULL DEFAULT 2 AFTER status');
    ensure_column($pdo, 'tenants', 'trial_ends_at', 'DATE NULL AFTER plan_id');
    ensure_column($pdo, 'tenants', 'api_key', 'VARCHAR(100) NULL AFTER trial_ends_at');
    ensure_column($pdo, 'tenants', 'custom_trial_months', 'INT NOT NULL DEFAULT 4 AFTER api_key');
    ensure_column($pdo, 'tenants', 'require_2fa', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER custom_trial_months');

    // Tenant Custom SMTP Email Settings
    ensure_column($pdo, 'tenants', 'smtp_host', 'VARCHAR(255) NULL AFTER require_2fa');
    ensure_column($pdo, 'tenants', 'smtp_port', 'INT NOT NULL DEFAULT 587 AFTER smtp_host');
    ensure_column($pdo, 'tenants', 'smtp_encryption', "VARCHAR(10) NOT NULL DEFAULT 'tls' AFTER smtp_port");
    ensure_column($pdo, 'tenants', 'smtp_username', 'VARCHAR(255) NULL AFTER smtp_encryption');
    ensure_column($pdo, 'tenants', 'smtp_password', 'VARCHAR(255) NULL AFTER smtp_username');
    ensure_column($pdo, 'tenants', 'from_email', 'VARCHAR(255) NULL AFTER smtp_password');
    ensure_column($pdo, 'tenants', 'from_name', 'VARCHAR(255) NULL AFTER from_email');

    try {
        $pdo->exec("ALTER TABLE tenants MODIFY COLUMN subscription_status ENUM('trial','active','past_due','canceled','lifetime') NOT NULL DEFAULT 'trial'");
    } catch (PDOException $e) {}

    // SaaS Plans Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS saas_plans (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(60) NOT NULL UNIQUE,
        price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_yearly DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'AED',
        max_subaccounts INT NOT NULL DEFAULT 1,
        max_invoices_per_month INT NOT NULL DEFAULT 100,
        max_team_users INT NOT NULL DEFAULT 5,
        has_n8n_automations TINYINT(1) NOT NULL DEFAULT 1,
        has_custom_builder TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    ensure_column($pdo, 'saas_plans', 'max_team_users', 'INT NOT NULL DEFAULT 5 AFTER max_invoices_per_month');

    // Set Tenant #1 (Headquarters) to Lifetime Unlimited Internal Status
    $pdo->exec("UPDATE tenants SET subscription_status = 'lifetime' WHERE id = 1");

    // Seed SaaS Plans
    $stPlanCheck = $pdo->query("SELECT COUNT(*) FROM saas_plans");
    if ($stPlanCheck->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO saas_plans (name, slug, price_monthly, price_yearly, currency, max_subaccounts, max_invoices_per_month, max_team_users, has_n8n_automations, has_custom_builder) VALUES
            ('Starter Plan', 'starter', 99.00, 990.00, 'AED', 1, 50, 2, 0, 1),
            ('Professional Plan', 'professional', 290.00, 2900.00, 'AED', 5, 500, 10, 1, 1),
            ('Enterprise Plan', 'enterprise', 750.00, 7500.00, 'AED', 999, 99999, 999, 1, 1)");
    }

    // Scoped API Keys Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NOT NULL,
        created_by_user_id INT UNSIGNED NULL,
        name VARCHAR(100) NOT NULL,
        key_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 hash of full key',
        key_prefix VARCHAR(20) NOT NULL COMMENT 'First 12 chars for display',
        scopes JSON NOT NULL DEFAULT ('[]'),
        expires_at DATE NULL,
        last_used_at DATETIME NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        revoked_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_key_hash (key_hash),
        INDEX idx_tenant (tenant_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB");

    // Recurring Invoices Subscription Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS recurring_invoices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NOT NULL,
        client_id INT UNSIGNED NOT NULL,
        frequency ENUM('weekly', 'monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
        next_issue_date DATE NOT NULL,
        status ENUM('active', 'paused', 'cancelled') NOT NULL DEFAULT 'active',
        template_json TEXT NOT NULL,
        last_generated_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tenant (tenant_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB");

    $output[] = "Database schema updated successfully with Scoped API Keys & Recurring Invoices tables.";

    return implode("<br>", $output);
}

if (php_sapi_name() === 'cli') {
    echo strip_tags(str_replace('<br>', "\n", run_migrations($pdo))) . "\n";
}
