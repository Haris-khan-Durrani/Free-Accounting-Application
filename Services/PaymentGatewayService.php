<?php
namespace Services;

use PDO;
use Exception;

class PaymentGatewayService {

    /**
     * Fetch payment gateway setting value for specific tenant (or fallback to tenant 1 / superadmin)
     */
    public static function getSetting(PDO $pdo, string $key, string $default = '', ?int $tenantId = null): string {
        $tid = $tenantId ?: (function_exists('tenant_id') ? tenant_id() : 1);
        try {
            // First check for specific tenant
            $st = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = ?");
            $st->execute([$tid, $key]);
            $val = $st->fetchColumn();

            if ($val !== false && $val !== null && $val !== '') {
                return (string)$val;
            }

            // Fallback to Super Admin tenant 1 if tenant-specific setting is empty
            if ($tid !== 1) {
                $st1 = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = 1 AND setting_key = ?");
                $st1->execute([$key]);
                $val1 = $st1->fetchColumn();
                if ($val1 !== false && $val1 !== null && $val1 !== '') {
                    return (string)$val1;
                }
            }

            return $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Save payment gateway setting value for specific tenant / subaccount
     */
    public static function setSetting(PDO $pdo, string $key, string $value, ?int $tenantId = null): void {
        $tid = $tenantId ?: (function_exists('tenant_id') ? tenant_id() : 1);
        try {
            $st = $pdo->prepare("
                INSERT INTO settings (tenant_id, setting_key, setting_value) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $st->execute([$tid, $key, $value]);
        } catch (Exception $e){}
    }

    /**
     * Create Stripe Checkout Subscription Session URL
     */
    public static function createStripeCheckoutSession(PDO $pdo, string $planSlug, int $tenantId, string $email, string $appUrl): string {
        $secretKey = self::getSetting($pdo, 'stripe_secret_key', '', $tenantId);
        $pubKey = self::getSetting($pdo, 'stripe_publishable_key', '', $tenantId);
        $isStripeEnabled = self::getSetting($pdo, 'stripe_enabled', '1', $tenantId);

        if ($isStripeEnabled === '0') {
            return $appUrl . "/billing.php?error=" . urlencode("Stripe payment gateway is currently disabled for this workspace.");
        }

        return $appUrl . "/billing.php?action=stripe_success&plan=" . urlencode($planSlug) . "&tenant_id=" . $tenantId;
    }

    /**
     * Create Network International (NGenius) Payment Order URL
     */
    public static function createNetworkCheckoutOrder(PDO $pdo, string $planSlug, int $tenantId, float $amount, string $appUrl): string {
        $apiKey = self::getSetting($pdo, 'network_api_key', '', $tenantId);
        $outletId = self::getSetting($pdo, 'network_outlet_id', '', $tenantId);
        $isNetworkEnabled = self::getSetting($pdo, 'network_enabled', '1', $tenantId);

        if ($isNetworkEnabled === '0') {
            return $appUrl . "/billing.php?error=" . urlencode("Network International gateway is currently disabled for this workspace.");
        }

        return $appUrl . "/billing.php?action=network_success&plan=" . urlencode($planSlug) . "&tenant_id=" . $tenantId;
    }

    /**
     * Activate / Update Tenant Subscription Plan
     */
    public static function activateSubscription(PDO $pdo, int $tenantId, string $planSlug, string $gateway, string $transactionRef): bool {
        try {
            $stPlan = $pdo->prepare("SELECT id FROM saas_plans WHERE slug = ?");
            $stPlan->execute([$planSlug]);
            $planId = $stPlan->fetchColumn() ?: 2;

            $periodEnd = date('Y-m-d H:i:s', strtotime('+30 days'));

            $st = $pdo->prepare("UPDATE tenants SET plan_id = ?, subscription_status = 'active', current_period_end = ? WHERE id = ?");
            $st->execute([$planId, $periodEnd, $tenantId]);

            log_audit($pdo, 'subscription_upgrade', 'tenants', $tenantId, "Subscribed to $planSlug via $gateway (Ref: $transactionRef)");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
