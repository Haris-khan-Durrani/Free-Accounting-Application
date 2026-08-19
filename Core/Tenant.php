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

        return Cache::remember('tenant_domain_' . $host, 600, function() use ($pdo, $host) {
            try {
                $st = $pdo->prepare("SELECT tenant_id FROM branding_settings WHERE custom_domain = ?");
                $st->execute([$host]);
                $tid = $st->fetchColumn();
                return $tid ? (int)$tid : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
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
        return Cache::remember('tenant_active_info', 600, function() use ($pdo, $id) {
            $st = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
            $st->execute([$id]);
            $t = $st->fetch();
            if (!$t) {
                throw new TenantContextException("Active tenant record [ID {$id}] not found in database.");
            }
            return $t;
        }, $id);
    }

    public static function forgetCache(?int $tenantId = null): void {
        $id = $tenantId ?? self::getActiveId();
        Cache::forget('tenant_active_info', $id);
    }

    public static function getUserTenants(PDO $pdo, int $userId): array {
        $st = $pdo->prepare("SELECT t.*, ut.role FROM tenants t JOIN user_tenants ut ON ut.tenant_id = t.id WHERE ut.user_id = ? AND t.status = 'active'");
        $st->execute([$userId]);
        return $st->fetchAll() ?: [];
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

        // Seed default Chart of Accounts if not existing
        try {
            $stC = $pdo->prepare("SELECT COUNT(*) FROM chart_of_accounts WHERE tenant_id = ?");
            $stC->execute([$tenantId]);
            if ($stC->fetchColumn() == 0) {
                $defaultAccounts = [
                    ['1010', 'Cash & Bank Accounts', 'asset', 'Main operating bank account and petty cash', 1],
                    ['1100', 'Accounts Receivable', 'asset', 'Outstanding customer invoice balances', 1],
                    ['2000', 'Accounts Payable', 'liability', 'Vendor bills and supplier balances payable', 1],
                    ['2100', 'Sales Tax / VAT Payable', 'liability', 'Accrued VAT payable to tax authorities', 1],
                    ['3000', 'Owner\'s Equity', 'equity', 'Owner capital investment and retained earnings', 1],
                    ['4000', 'Sales & Service Income', 'revenue', 'Revenue earned from invoices and sales', 1],
                    ['5000', 'Cost of Goods Sold', 'expense', 'Direct costs associated with sold goods/services', 1],
                    ['6000', 'Operating Expenses', 'expense', 'General business and administrative expenses', 1],
                ];

                $stIns = $pdo->prepare("INSERT INTO chart_of_accounts (tenant_id, account_code, account_name, account_type, description, is_system) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($defaultAccounts as $acc) {
                    try {
                        $stIns->execute([$tenantId, $acc[0], $acc[1], $acc[2], $acc[3], $acc[4]]);
                    } catch (\PDOException $e) {}
                }
            }
        } catch (\PDOException $e) {}
    }
}
