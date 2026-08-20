<?php
namespace Core;

use PDO;

class Branding {
    private static array $cache = [];

    public static function get(PDO $pdo, ?int $tenantId = null): array {
        $tid = $tenantId ?? (Tenant::hasActiveId() ? Tenant::getActiveId() : 1);

        // Use in-memory cache for within-request deduplication only (not cross-request)
        if (isset(self::$cache[$tid])) {
            return self::$cache[$tid];
        }

        // Query DB directly — no file/persistent caching so branding changes are instant
        $st = $pdo->prepare("SELECT * FROM branding_settings WHERE tenant_id = ?");
        $st->execute([$tid]);
        $b = $st->fetch();

        if (!$b) {
            $b = [
                'tenant_id' => $tid,
                'company_name' => 'OneSol Solutions',
                'company_tagline' => 'Enterprise Technology & Software',
                'company_website' => 'www.onesol.ae',
                'company_email' => 'info@onesol.ae',
                'company_phone' => '+971 4 000 0000',
                'tax_number_label' => 'TRN / Tax ID',
                'tax_number' => '100293847500003',
                'registration_number' => 'REG-99201',
                'address' => "OneSol Tower, Business Bay\nDubai, United Arab Emirates",
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
                'bank_name' => 'Emirates NBD',
                'bank_account_name' => 'OneSol Digital Solutions FZ LLC',
                'bank_account_number' => '101293847592',
                'bank_iban' => 'AE29024000101293847592',
                'bank_swift' => 'EBIXAE2D',
                'primary_color' => '#0f172a',
                'secondary_color' => '#2563eb',
                'accent_color' => '#d97706',
                'font_family' => 'Inter',
                'logo_url' => 'assets/img/onesol-logo.png',
                'dark_logo_url' => null,
                'signature_url' => null,
                'stamp_url' => null,
                'default_invoice_template' => 'modern_minimal',
                'invoice_footer_notes' => 'Thank you for choosing OneSol! Payment is due within standard agreed terms.',
                'payment_terms_days' => 14,
                'watermark_enabled' => 1,
                'show_qr_code' => 1,
            ];
        }

        // Check if default_invoice_template override is set in settings table
        try {
            $stTpl = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'default_invoice_template'");
            $stTpl->execute([$tid]);
            $overrideTpl = $stTpl->fetchColumn();
            if ($overrideTpl) {
                $b['default_invoice_template'] = $overrideTpl;
            }
        } catch (\Throwable $t) {}

        self::$cache[$tid] = $b;
        return $b;
    }


    public static function forgetCache(?int $tenantId = null): void {
        $tid = $tenantId ?? (Tenant::hasActiveId() ? Tenant::getActiveId() : 1);
        Cache::forget('branding_settings', $tid);
    }
}
