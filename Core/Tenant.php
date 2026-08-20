<?php
namespace Core;

use PDO;

class Tenant {
    public static function hasActiveId(): bool {
        return !empty($_SESSION['active_tenant_id'])
            || !empty($_SESSION['user_tenant_id'])
            || !empty($_SESSION['tenant_id'])
            || php_sapi_name() === 'cli';
    }

    public static function resolveFromDomain(PDO $pdo): ?int {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (empty($host)) return null;

        $host = preg_replace('#:\d+$#', '', strtolower(trim($host)));

        // Query DB directly — no caching so domain changes take effect immediately
        try {
            $st = $pdo->prepare("SELECT tenant_id FROM branding_settings WHERE custom_domain = ?");
            $st->execute([$host]);
            $tid = $st->fetchColumn();
            return $tid ? (int)$tid : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getActiveId(): int {
        if (!empty($_SESSION['active_tenant_id'])) {
            return (int)$_SESSION['active_tenant_id'];
        }
        if (!empty($_SESSION['user_tenant_id'])) {
            return (int)$_SESSION['user_tenant_id'];
        }
        if (!empty($_SESSION['tenant_id'])) {
            return (int)$_SESSION['tenant_id'];
        }
        // Auto-resolve tenant from HTTP host header if mapped to custom whitelabel domain
        if (!empty($GLOBALS['pdo'])) {
            $domainTenantId = self::resolveFromDomain($GLOBALS['pdo']);
            if ($domainTenantId) {
                $_SESSION['active_tenant_id'] = $domainTenantId;
                return $domainTenantId;
            }
        }
        // In CLI environment (cron jobs, migrations), fallback to tenant 1
        if (php_sapi_name() === 'cli') {
            return 1;
        }
        return 1;
    }

    public static function setActiveId(int $tenantId, PDO $pdo, int $userId): bool {
        // Verify user has access to this tenant
        $st = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id = ?");
        $st->execute([$userId, $tenantId]);
        if ($st->fetchColumn() > 0 || ($_SESSION['user_role'] ?? '') === 'owner') {
            $_SESSION['active_tenant_id'] = $tenantId;
            $_SESSION['tenant_id'] = $tenantId;
            return true;
        }
        return false;
    }

    public static function getActive(PDO $pdo): array {
        $id = self::getActiveId();
        // Query DB directly — no caching so workspace name/settings changes are instant
        $st = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $st->execute([$id]);
        $t = $st->fetch();
        if (!$t) {
            throw new TenantContextException("Active tenant record [ID {$id}] not found in database.");
        }
        return $t;
    }

    public static function forgetCache(?int $tenantId = null): void {
        $id = $tenantId ?? self::getActiveId();
        Cache::forget('tenant_active_info', $id);
    }

    public static function getUserTenants(PDO $pdo, int $userId): array {
        // If Master Super-Admin (Tenant #1 Owner), return all active tenants
        try {
            $stMaster = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id = 1 AND role = 'owner'");
            $stMaster->execute([$userId]);
            if ((int)$stMaster->fetchColumn() > 0) {
                return $pdo->query("SELECT t.*, 'owner' AS role FROM tenants t WHERE t.status = 'active' ORDER BY t.id ASC")->fetchAll() ?: [];
            }
        } catch (\Throwable $e) {}

        // Otherwise return ONLY workspaces assigned to this user ID in user_tenants or primary user tenant_id
        $st = $pdo->prepare("SELECT DISTINCT t.*, ut.role 
                             FROM tenants t 
                             JOIN user_tenants ut ON ut.tenant_id = t.id 
                             WHERE ut.user_id = ? AND t.status = 'active' 
                             ORDER BY t.id ASC");
        $st->execute([$userId]);
        $list = $st->fetchAll() ?: [];

        if (empty($list)) {
            // Fallback: check primary user tenant_id from users table
            $stUser = $pdo->prepare("SELECT u.tenant_id, u.role, t.name, t.code, t.currency, t.status 
                                     FROM users u 
                                     JOIN tenants t ON t.id = u.tenant_id 
                                     WHERE u.id = ? AND t.status = 'active'");
            $stUser->execute([$userId]);
            $uRow = $stUser->fetch();
            if ($uRow) {
                $list = [[
                    'id' => (int)$uRow['tenant_id'],
                    'name' => $uRow['name'],
                    'code' => $uRow['code'],
                    'currency' => $uRow['currency'],
                    'status' => $uRow['status'],
                    'role' => $uRow['role']
                ]];
            }
        }

        return $list;
    }


    public static function seedAccounts(PDO $pdo, int $tenantId): void {
        // Seed default branding settings if not existing
        try {
            $stB = $pdo->prepare("SELECT COUNT(*) FROM branding_settings WHERE tenant_id = ?");
            $stB->execute([$tenantId]);
            if ($stB->fetchColumn() == 0) {
                $stT = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
                $stT->execute([$tenantId]);
                $tName = $stT->fetchColumn() ?: 'Company Workspace';
                
                $stInsB = $pdo->prepare("INSERT INTO branding_settings (tenant_id, company_name) VALUES (?, ?)");
                $stInsB->execute([$tenantId, $tName]);
            }
        } catch (\PDOException $e) {}

        // Seed comprehensive default Chart of Accounts if not existing
        try {
            $defaultAccounts = [
                // ASSETS (1000s)
                ['1010', 'Cash & Petty Cash', 'asset', 'Main operating cash and petty cash fund', 1],
                ['1020', 'Main Operating Bank Account', 'asset', 'Primary corporate bank account', 1],
                ['1100', 'Accounts Receivable (A/R)', 'asset', 'Outstanding customer invoice balances', 1],
                ['1200', 'Inventory & Stock Assets', 'asset', 'Physical products and merchandise in stock', 0],
                ['1300', 'Prepaid Expenses & Deposits', 'asset', 'Advance payments and security deposits', 0],
                ['1500', 'Office Equipment & Furniture', 'asset', 'Fixed assets, computers, and office furniture', 0],

                // LIABILITIES (2000s)
                ['2000', 'Accounts Payable (A/P)', 'liability', 'Vendor bills and supplier balances payable', 1],
                ['2100', 'Sales Tax / VAT Payable (5%)', 'liability', 'Accrued VAT payable to tax authorities', 1],
                ['2110', 'Corporate Tax Payable (9%)', 'liability', 'Accrued UAE Corporate Tax provision', 1],
                ['2200', 'Accrued Payroll & Salaries', 'liability', 'Employee wages and benefits payable', 0],

                // EQUITY (3000s)
                ['3000', 'Owner\'s Equity & Capital', 'equity', 'Owner capital investment and retained earnings', 1],
                ['3100', 'Owner\'s Drawings & Capital Withdrawals', 'equity', 'Owner distributions and personal withdrawals', 0],

                // REVENUE (4000s)
                ['4000', 'Sales & Product Income', 'revenue', 'Revenue earned from sales of products and items', 1],
                ['4100', 'Service & Consulting Revenue', 'revenue', 'Revenue from professional services and fees', 1],
                ['4200', 'Subscription & Recurring Revenue', 'revenue', 'Income from auto-recurring subscription plans', 0],

                // COST OF GOODS SOLD (5000s)
                ['5000', 'Cost of Goods Sold (COGS)', 'expense', 'Direct cost of goods and materials sold', 1],
                ['5100', 'Direct Subcontractor Costs', 'expense', 'Outsourced labor and direct project execution costs', 0],

                // OPERATING EXPENSES (6000s)
                ['6000', 'Advertising & Marketing', 'expense', 'Promotions, digital ads, and branding expenses', 0],
                ['6100', 'Rent & Office Utilities', 'expense', 'Office lease, electricity, water, and internet', 0],
                ['6200', 'Salaries & Employee Compensation', 'expense', 'Staff salaries, bonuses, and medical insurance', 0],
                ['6300', 'Software & Cloud Subscriptions', 'expense', 'SaaS tools, web hosting, and software licenses', 0],
                ['6400', 'Professional & Legal Fees', 'expense', 'Audit, accounting, legal, and consultancy fees', 0],
                ['6500', 'Bank Fees & Merchant Charges', 'expense', 'Credit card gateway fees and bank service charges', 0],
                ['6600', 'Travel, Meals & Entertainment', 'expense', 'Business travel, lodging, and client entertainment', 0],
                ['6800', 'General Office Expenses', 'expense', 'Stationery, office supplies, and administrative costs', 1],
            ];

            $stCheck = $pdo->prepare("SELECT COUNT(*) FROM chart_of_accounts WHERE tenant_id = ? AND account_code = ?");
            $stIns = $pdo->prepare("INSERT INTO chart_of_accounts (tenant_id, account_code, account_name, account_type, description, is_system) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($defaultAccounts as $acc) {
                $stCheck->execute([$tenantId, $acc[0]]);
                if ((int)$stCheck->fetchColumn() === 0) {
                    try {
                        $stIns->execute([$tenantId, $acc[0], $acc[1], $acc[2], $acc[3], $acc[4]]);
                    } catch (\PDOException $e) {}
                }
            }
        } catch (\PDOException $e) {}
    }
}
