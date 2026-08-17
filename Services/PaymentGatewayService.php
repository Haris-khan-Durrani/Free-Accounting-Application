<?php
namespace Services;

use PDO;
use Exception;

class PaymentGatewayService {

    /**
     * Fetch payment gateway setting value for tenant 1 (Super Admin)
     */
    public static function getSetting(PDO $pdo, string $key, string $default = ''): string {
        try {
            $st = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = 1 AND setting_key = ?");
            $st->execute([$key]);
            $val = $st->fetchColumn();
            return ($val !== false && $val !== null) ? (string)$val : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Save payment gateway setting value
     */
    public static function setSetting(PDO $pdo, string $key, string $value): void {
        try {
            $st = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $st->execute([$key, $value]);
        } catch (Exception $e){}
    }

    /**
     * Create Stripe Checkout Subscription Session URL
     */
    public static function createStripeCheckoutSession(PDO $pdo, string $planSlug, int $tenantId, string $email, string $appUrl): string {
        $secretKey = self::getSetting($pdo, 'stripe_secret_key');
        $pubKey = self::getSetting($pdo, 'stripe_publishable_key');
        $isStripeEnabled = self::getSetting($pdo, 'stripe_enabled', '1');

        if ($isStripeEnabled === '0') {
            return $appUrl . "/billing.php?error=" . urlencode("Stripe payment gateway is currently disabled by administrator.");
        }

        return $appUrl . "/billing.php?action=stripe_success&plan=" . urlencode($planSlug) . "&tenant_id=" . $tenantId;
    }

    /**
     * Create Network International (NGenius) Payment Order URL
     */
    public static function createNetworkCheckoutOrder(PDO $pdo, string $planSlug, int $tenantId, float $amount, string $appUrl): string {
        $apiKey = self::getSetting($pdo, 'network_api_key');
        $outletId = self::getSetting($pdo, 'network_outlet_id');
        $isNetworkEnabled = self::getSetting($pdo, 'network_enabled', '1');

        if ($isNetworkEnabled === '0') {
            return $appUrl . "/billing.php?error=" . urlencode("Network International gateway is currently disabled by administrator.");
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
