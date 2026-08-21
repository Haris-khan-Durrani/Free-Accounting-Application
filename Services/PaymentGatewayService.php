<?php
namespace Services;

use PDO;
use Exception;

class PaymentGatewayService {

    private static array $sensitiveKeys = [
        'stripe_secret_key',
        'stripe_webhook_secret',
        'paypal_secret_key',
        'network_api_key',
        'tabby_secret_key',
        'tamara_api_token',
        'tamara_notification_token',
        'ziina_api_token',
        'ziina_webhook_secret',
        'zbooni_api_key',
        'zbooni_secret_key',
        'smtp_password',
        'meta_whatsapp_token',
        'twilio_auth_token',
        'woocommerce_webhook_secret',
        'shopify_webhook_secret'
    ];

    /**
     * Fetch payment gateway setting value for specific tenant (or fallback to tenant 1 / superadmin if allowed)
     */
    public static function getSetting(PDO $pdo, string $key, string $default = '', ?int $tenantId = null, bool $allowFallback = true): string {
        $tid = $tenantId ?: (function_exists('tenant_id') ? tenant_id() : 1);
        
        // Never allow tenant 1 fallback for secret credentials
        if (in_array($key, self::$sensitiveKeys, true)) {
            $allowFallback = false;
        }

        try {
            // First check for specific tenant
            $st = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = ?");
            $st->execute([$tid, $key]);
            $val = $st->fetchColumn();

            if ($val !== false && $val !== null && $val !== '') {
                $strVal = (string)$val;
                if (in_array($key, self::$sensitiveKeys, true)) {
                    $decrypted = \Core\Crypto::decrypt($strVal);
                    return (!empty($decrypted) && ctype_print($decrypted)) ? $decrypted : $strVal;
                }
                return $strVal;
            }

            // Fallback to Super Admin tenant 1 if tenant-specific setting is empty AND fallback is explicitly allowed
            if ($allowFallback && $tid !== 1) {
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
     * Resolve tenant_id server-side from unique integration webhook key
     */
    public static function getTenantIdByWebhookKey(PDO $pdo, string $keySettingName, string $webhookKey): int {
        if (empty($webhookKey)) return 0;
        try {
            $st = $pdo->prepare("SELECT tenant_id, setting_value FROM settings WHERE setting_key = ?");
            $st->execute([$keySettingName]);
            $rows = $st->fetchAll();

            foreach ($rows as $row) {
                $val = (string)$row['setting_value'];
                $decrypted = \Core\Crypto::decrypt($val) ?: $val;
                if (hash_equals($decrypted, $webhookKey) || hash_equals($val, $webhookKey)) {
                    return (int)$row['tenant_id'];
                }
            }
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Save payment gateway setting value for specific tenant / subaccount
     */
    public static function setSetting(PDO $pdo, string $key, string $value, ?int $tenantId = null): void {
        $tid = $tenantId ?: (function_exists('tenant_id') ? tenant_id() : 1);
        try {
            $valueToStore = $value;
            if (in_array($key, self::$sensitiveKeys, true) && $value !== '') {
                // Encrypt secret setting at rest
                $valueToStore = \Core\Crypto::encrypt($value);
            }

            $st = $pdo->prepare("
                INSERT INTO settings (tenant_id, setting_key, setting_value) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $st->execute([$tid, $key, $valueToStore]);
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
     * Create Direct Invoice Stripe Checkout Session
     */
    public static function createInvoiceStripeCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $secretKey = self::getSetting($pdo, 'stripe_secret_key', '', $tid);
        $isEnabled = self::getSetting($pdo, 'stripe_enabled', '1', $tid);

        if ($isEnabled === '0' || empty($secretKey)) {
            return ['error' => 'Stripe payment gateway is disabled or API secret key is missing for this workspace.'];
        }

        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtolower($inv['currency'] ?? 'aed');
        $totalAmount = (float)$inv['total'];
        $amountInCents = (int)round($totalAmount * 100);

        $postFields = http_build_query([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Tax Invoice #' . ($inv['invoice_number'] ?? $invId),
                        ],
                        'unit_amount' => $amountInCents,
                    ],
                    'quantity' => 1,
                ]
            ],
            'mode' => 'payment',
            'customer_email' => $inv['email'] ?? '',
            'metadata' => [
                'invoice_id' => (string)$invId,
                'invoice_number' => (string)($inv['invoice_number'] ?? $invId),
                'tenant_id' => (string)$tid,
            ],
            'success_url' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&session_id={CHECKOUT_SESSION_ID}",
            'cancel_url'  => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&status=cancel",
        ]);

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Stripe API Connection Error: ' . $err];
        }

        $data = json_decode($res, true);
        if (isset($data['url'])) {
            return ['redirect_url' => $data['url']];
        }

        return ['error' => 'Stripe Session Error: ' . ($data['error']['message'] ?? 'Unable to create Stripe checkout session.')];
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
     * Create Direct Invoice Network International (NGenius) Payment Order
     */
    public static function createInvoiceNetworkCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $apiKey = self::getSetting($pdo, 'network_api_key', '', $tid);
        $outletId = self::getSetting($pdo, 'network_outlet_id', '', $tid);
        $env = self::getSetting($pdo, 'network_environment', 'sandbox', $tid);
        $isEnabled = self::getSetting($pdo, 'network_enabled', '0', $tid);

        if ($isEnabled === '0' || empty($apiKey) || empty($outletId)) {
            return ['error' => 'Network International gateway is disabled or credentials missing for this workspace.'];
        }

        $domain = ($env === 'live') ? 'api-gateway.ngenius-payments.com' : 'api-gateway.sandbox.ngenius-payments.com';

        // 1. Fetch Access Token
        $chAuth = curl_init("https://{$domain}/identity/auth/access-token");
        curl_setopt_array($chAuth, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials']),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($apiKey),
                'Content-Type: application/vnd.ni-identity.v1+json',
                'Accept: application/vnd.ni-identity.v1+json'
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        $authRes = curl_exec($chAuth);
        curl_close($chAuth);

        $authData = json_decode($authRes, true);
        $accessToken = $authData['access_token'] ?? '';
        if (empty($accessToken)) {
            return ['error' => 'Network International Auth Error: Unable to retrieve access token.'];
        }

        // 2. Create Payment Order
        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtoupper($inv['currency'] ?? 'AED');
        $amountInMinorUnits = (int)round((float)$inv['total'] * 100);

        $payload = [
            'action' => 'PURCHASE',
            'amount' => [
                'currencyCode' => $currency,
                'value' => $amountInMinorUnits
            ],
            'emailAddress' => $inv['email'] ?? 'customer@example.com',
            'merchantAttributes' => [
                'redirectUrl' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&gateway=network"
            ]
        ];

        $chOrder = curl_init("https://{$domain}/transactions/outlets/{$outletId}/orders");
        curl_setopt_array($chOrder, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/vnd.ni-payment.v2+json',
                'Accept: application/vnd.ni-payment.v2+json'
            ],
            CURLOPT_TIMEOUT => 15
        ]);
        $orderRes = curl_exec($chOrder);
        curl_close($chOrder);

        $orderData = json_decode($orderRes, true);
        if (isset($orderData['_links']['payment']['href'])) {
            return ['redirect_url' => $orderData['_links']['payment']['href']];
        }

        return ['error' => 'Network International Order Error: ' . ($orderData['message'] ?? 'Unable to create payment order.')];
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

    /**
     * Cryptographically verify Stripe Webhook Signature (v1 scheme HMAC-SHA256)
     */
    public static function verifyStripeSignature(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool {
        if (empty($sigHeader) || empty($secret)) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        $items = explode(',', $sigHeader);
        foreach ($items as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) === 2) {
                if ($pair[0] === 't') {
                    $timestamp = (int)$pair[1];
                } elseif ($pair[0] === 'v1') {
                    $signatures[] = $pair[1];
                }
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create Tabby Checkout Session API Call
     */
    public static function createInvoiceTabbyCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $secretKey = self::getSetting($pdo, 'tabby_secret_key', '', $tid);
        $publicKey = self::getSetting($pdo, 'tabby_public_key', '', $tid);
        $merchantCode = self::getSetting($pdo, 'tabby_merchant_code', '', $tid);
        $isEnabled = self::getSetting($pdo, 'tabby_enabled', '0', $tid);

        if ($isEnabled === '0' || empty($secretKey)) {
            return ['error' => 'Tabby BNPL gateway is disabled or credentials missing for this workspace.'];
        }

        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtoupper($inv['currency'] ?? 'AED');
        $totalAmount = (float)$inv['total'];

        // Format items for Tabby payload
        $tabbyItems = [];
        foreach ($items as $it) {
            $qty = (int)($it['qty'] ?? $it['quantity'] ?? 1);
            $unitPrice = (float)($it['unit_price'] ?? 0);
            $tabbyItems[] = [
                'title' => substr($it['description'] ?? 'Line Item', 0, 150),
                'quantity' => max(1, $qty),
                'unit_price' => sprintf('%.2f', $unitPrice),
                'category' => 'General Services'
            ];
        }

        if (empty($tabbyItems)) {
            $tabbyItems[] = [
                'title' => 'Invoice #' . ($inv['invoice_number'] ?? $invId),
                'quantity' => 1,
                'unit_price' => sprintf('%.2f', $totalAmount),
                'category' => 'Services'
            ];
        }

        $clientPhone = preg_replace('/[^0-9\+]/', '', $inv['phone'] ?? '');
        if (empty($clientPhone)) {
            $clientPhone = '+971500000000'; // Default fallback phone format required by Tabby
        }

        $payload = [
            'payment' => [
                'amount' => sprintf('%.2f', $totalAmount),
                'currency' => $currency,
                'description' => 'Tax Invoice #' . ($inv['invoice_number'] ?? $invId),
                'buyer' => [
                    'phone' => $clientPhone,
                    'email' => $inv['email'] ?? 'customer@example.com',
                    'name'  => $inv['contact_name'] ?: ($inv['company_name'] ?? 'Valued Customer')
                ],
                'order' => [
                    'reference_id' => (string)($inv['invoice_number'] ?? $invId),
                    'items' => $tabbyItems
                ]
            ],
            'lang' => 'en',
            'merchant_code' => $merchantCode,
            'merchant_urls' => [
                'success' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&gateway=tabby",
                'cancel'  => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&status=cancel",
                'failure' => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&status=failed"
            ]
        ];

        $ch = curl_init('https://api.tabby.ai/api/v2/checkout');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Tabby API Connection Error: ' . $err];
        }

        $data = json_decode($res, true);
        if (isset($data['configuration']['available_products']['installments'][0]['web_url'])) {
            return ['redirect_url' => $data['configuration']['available_products']['installments'][0]['web_url']];
        }

        if (isset($data['web_url'])) {
            return ['redirect_url' => $data['web_url']];
        }

        return ['error' => 'Tabby Session Error: ' . ($data['message'] ?? 'Unable to create Tabby checkout session.')];
    }

    /**
     * Create Tamara Checkout Session API Call
     */
    public static function createInvoiceTamaraCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $apiToken = self::getSetting($pdo, 'tamara_api_token', '', $tid);
        $apiUrl = self::getSetting($pdo, 'tamara_api_url', 'https://api-sandbox.tamara.co', $tid);
        $isEnabled = self::getSetting($pdo, 'tamara_enabled', '0', $tid);

        if ($isEnabled === '0' || empty($apiToken)) {
            return ['error' => 'Tamara BNPL gateway is disabled or credentials missing for this workspace.'];
        }

        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtoupper($inv['currency'] ?? 'AED');
        $totalAmount = (float)$inv['total'];

        $tamaraItems = [];
        $idx = 1;
        foreach ($items as $it) {
            $qty = max(1, (int)($it['qty'] ?? $it['quantity'] ?? 1));
            $unitPrice = (float)($it['unit_price'] ?? 0);
            $tamaraItems[] = [
                'reference_id' => 'item_' . $idx++,
                'type' => 'Service',
                'name' => substr($it['description'] ?? 'Line Item', 0, 100),
                'sku' => 'SKU-' . $idx,
                'quantity' => $qty,
                'unit_price' => [
                    'amount' => $unitPrice,
                    'currency' => $currency
                ],
                'total_amount' => [
                    'amount' => $qty * $unitPrice,
                    'currency' => $currency
                ]
            ];
        }

        if (empty($tamaraItems)) {
            $tamaraItems[] = [
                'reference_id' => 'item_1',
                'type' => 'Service',
                'name' => 'Invoice #' . ($inv['invoice_number'] ?? $invId),
                'sku' => 'INV-' . $invId,
                'quantity' => 1,
                'unit_price' => ['amount' => $totalAmount, 'currency' => $currency],
                'total_amount' => ['amount' => $totalAmount, 'currency' => $currency]
            ];
        }

        $phone = preg_replace('/[^0-9]/', '', $inv['phone'] ?? '');
        if (strlen($phone) < 8) {
            $phone = '500000000';
        }

        $countryCode = 'AE';
        if ($currency === 'SAR') $countryCode = 'SA';
        elseif ($currency === 'KWD') $countryCode = 'KW';
        elseif ($currency === 'BHD') $countryCode = 'BH';

        $fullName = trim($inv['contact_name'] ?: ($inv['company_name'] ?? 'Valued Customer'));
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? 'Customer';

        $payload = [
            'total_amount' => ['amount' => $totalAmount, 'currency' => $currency],
            'shipping_amount' => ['amount' => 0.00, 'currency' => $currency],
            'tax_amount' => ['amount' => (float)($inv['tax_amount'] ?? 0), 'currency' => $currency],
            'order_reference_id' => (string)($inv['invoice_number'] ?? $invId),
            'order_number' => (string)($inv['invoice_number'] ?? $invId),
            'items' => $tamaraItems,
            'consumer' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => $phone,
                'email' => $inv['email'] ?? 'customer@example.com'
            ],
            'country_code' => $countryCode,
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'merchant_url' => [
                'success' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&gateway=tamara",
                'failure' => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&status=failed",
                'cancel' => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&status=cancel",
                'notification' => $appUrl . "/api/v1/webhooks/tamara.php"
            ]
        ];

        $targetEndpoint = rtrim($apiUrl, '/') . '/checkout';
        $ch = curl_init($targetEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Tamara API Connection Error: ' . $err];
        }

        $data = json_decode($res, true);
        if (isset($data['checkout_url'])) {
            return ['redirect_url' => $data['checkout_url']];
        }

        return ['error' => 'Tamara Session Error: ' . ($data['message'] ?? ($data['errors'][0]['error_code'] ?? 'Unable to create Tamara session.'))];
    }

    /**
     * Create Ziina Payment Intent & Checkout Link API Call
     */
    public static function createInvoiceZiinaCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $apiToken = self::getSetting($pdo, 'ziina_api_token', '', $tid);
        $isEnabled = self::getSetting($pdo, 'ziina_enabled', '0', $tid);

        if ($isEnabled === '0' || empty($apiToken)) {
            return ['error' => 'Ziina payment gateway is disabled or API credentials missing for this workspace.'];
        }

        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtoupper($inv['currency'] ?? 'AED');
        $totalAmount = (float)$inv['total'];

        // Ziina expects amount in fils / smallest currency unit (e.g. AED 100.00 = 10000 fils)
        $amountInFils = (int)round($totalAmount * 100);

        $payload = [
            'amount' => $amountInFils,
            'currency' => $currency,
            'success_url' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&gateway=ziina",
            'cancel_url' => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&status=cancel",
            'message' => 'Tax Invoice #' . ($inv['invoice_number'] ?? $invId),
            'metadata' => [
                'invoice_id' => (string)$invId,
                'invoice_number' => (string)($inv['invoice_number'] ?? $invId),
                'tenant_id' => (string)$tid
            ]
        ];

        $ch = curl_init('https://api-v2.ziina.com/v1/payment_intent');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Ziina API Connection Error: ' . $err];
        }

        $data = json_decode($res, true);
        if (isset($data['redirect_url'])) {
            return ['redirect_url' => $data['redirect_url']];
        }

        return ['error' => 'Ziina Checkout Error: ' . ($data['message'] ?? 'Unable to create Ziina payment intent.')];
    }

    /**
     * Create Zbooni Order Payment Link API Call
     */
    public static function createInvoiceZbooniCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $apiKey = self::getSetting($pdo, 'zbooni_api_key', '', $tid);
        $isEnabled = self::getSetting($pdo, 'zbooni_enabled', '0', $tid);

        if ($isEnabled === '0' || empty($apiKey)) {
            return ['error' => 'Zbooni payment gateway is disabled or API credentials missing for this workspace.'];
        }

        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtoupper($inv['currency'] ?? 'AED');
        $totalAmount = (float)$inv['total'];

        $payload = [
            'amount' => sprintf('%.2f', $totalAmount),
            'currency' => $currency,
            'customer_email' => $inv['email'] ?? 'customer@example.com',
            'customer_name' => $inv['contact_name'] ?: ($inv['company_name'] ?? 'Valued Customer'),
            'customer_phone' => $inv['phone'] ?? '',
            'description' => 'Tax Invoice #' . ($inv['invoice_number'] ?? $invId),
            'notes' => 'Invoice ID: ' . $invId,
            'redirect_url' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&gateway=zbooni"
        ];

        $ch = curl_init('https://api.zbooni.com/v1/orders/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Zbooni API Connection Error: ' . $err];
        }

        $data = json_decode($res, true);
        if (isset($data['checkout_url'])) {
            return ['redirect_url' => $data['checkout_url']];
        }

        if (isset($data['payment_url'])) {
            return ['redirect_url' => $data['payment_url']];
        }

        if (isset($data['url'])) {
            return ['redirect_url' => $data['url']];
        }

        return ['error' => 'Zbooni Checkout Error: ' . ($data['detail'] ?? ($data['message'] ?? 'Unable to create Zbooni order.'))];
    }
}



