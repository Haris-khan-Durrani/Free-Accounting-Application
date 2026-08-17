<?php
namespace Core;

use PDO;

class Tenant {
    public static function getActiveId(): int {
        if (!empty($_SESSION['active_tenant_id'])) {
            return (int)$_SESSION['active_tenant_id'];
        }
        if (!empty($_SESSION['user_tenant_id'])) {
            return (int)$_SESSION['user_tenant_id'];
        }
        return 1;
    }

    public static function setActiveId(int $tenantId, PDO $pdo, int $userId): bool {
        // Verify user has access to this tenant
        $st = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id = ?");
        $st->execute([$userId, $tenantId]);
        if ($st->fetchColumn() > 0 || ($_SESSION['user_role'] ?? '') === 'owner') {
            $_SESSION['active_tenant_id'] = $tenantId;
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
                return [
                    'id' => 1,
                    'name' => 'OneSol Headquarters',
                    'code' => 'onesol-hq',
                    'currency' => 'AED',
                    'country_code' => 'AE'
                ];
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
        $tenants = $st->fetchAll();
        if (empty($tenants)) {
            // Fallback for default tenant
            return [['id' => 1, 'name' => 'OneSol Headquarters', 'code' => 'onesol-hq', 'currency' => 'AED', 'role' => 'owner']];
        }
        return $tenants;
    }
}
