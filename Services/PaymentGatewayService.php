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
        'paytabs_server_key',
        'telr_api_key',
        'checkout_secret_key',
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

        // Safeguard: Check if Stripe API already has a completed payment session for this invoice before creating a new one
        try {
            $chCheck = curl_init('https://api.stripe.com/v1/checkout/sessions?limit=10');
            curl_setopt_array($chCheck, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secretKey],
                CURLOPT_TIMEOUT => 5
            ]);
            $resCheck = curl_exec($chCheck);
            curl_close($chCheck);

            if ($resCheck) {
                $checkData = json_decode($resCheck, true);
                if (!empty($checkData['data'])) {
                    foreach ($checkData['data'] as $sData) {
                        if (isset($sData['metadata']['invoice_id']) && (string)$sData['metadata']['invoice_id'] === (string)$invId) {
                            if (($sData['payment_status'] ?? '') === 'paid' || ($sData['status'] ?? '') === 'complete') {
                                // Auto-reconcile database state immediately
                                if (file_exists(__DIR__ . '/../stripe_return.php')) {
                                    require_once __DIR__ . '/../stripe_return.php';
                                    if (function_exists('record_instant_payment')) {
                                        record_instant_payment($pdo, $inv, 'stripe', $sData['id'], ((float)($sData['amount_total'] ?? 0)) / 100, 'On-Demand Pre-Checkout Reconciliation');
                                    }
                                }
                                return ['redirect_url' => $appUrl . "/public_invoice.php?id={$invId}&token={$token}"];
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $eEx) {}

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

        $stripeErr = $data['error']['message'] ?? 'Unable to create Stripe checkout session.';
        if (str_contains(strtolower($stripeErr), 'account or business name')) {
            $stripeErr = "Stripe Account Setup Required: Please log into your Stripe Dashboard at https://dashboard.stripe.com/account (Settings &rarr; Public Details) and type your Business / Account Name so Stripe can display it on the checkout page.";
        }

        return ['error' => 'Stripe Session Error: ' . $stripeErr];
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

    /**
     * Create PayTabs Checkout Session
     */
    public static function createInvoicePayTabsCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $profileId = self::getSetting($pdo, 'paytabs_profile_id', '', $tid);
        $serverKey = self::getSetting($pdo, 'paytabs_server_key', '', $tid);
        $region = self::getSetting($pdo, 'paytabs_region', 'ARE', $tid);
        $isEnabled = self::getSetting($pdo, 'paytabs_enabled', '0', $tid);

        if ($isEnabled === '0' || empty($profileId) || empty($serverKey)) {
            return ['error' => 'PayTabs payment gateway is disabled or missing Profile ID / Server Key for this workspace.'];
        }

        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtoupper($inv['currency'] ?? 'AED');
        $totalAmount = (float)$inv['total'];

        $endpoints = [
            'ARE' => 'https://secure.paytabs.com',
            'SAU' => 'https://secure-saudi.paytabs.com',
            'EGY' => 'https://secure-egypt.paytabs.com',
            'OMN' => 'https://secure-oman.paytabs.com',
            'JOR' => 'https://secure-jordan.paytabs.com',
            'GLOBAL' => 'https://secure.paytabs.com'
        ];
        $baseUrl = $endpoints[$region] ?? 'https://secure.paytabs.com';

        $payload = [
            'profile_id' => $profileId,
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => 'inv_' . $invId,
            'cart_description' => 'Tax Invoice #' . ($inv['invoice_number'] ?? $invId),
            'cart_currency' => $currency,
            'cart_amount' => $totalAmount,
            'callback' => $appUrl . "/api/v1/webhooks/paytabs.php",
            'return' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&gateway=paytabs",
            'customer_details' => [
                'name' => $inv['contact_name'] ?? ($inv['company_name'] ?? 'Valued Customer'),
                'email' => $inv['email'] ?? 'customer@example.com',
                'phone' => $inv['phone'] ?? '',
                'street1' => $inv['address'] ?? 'Street Address',
                'city' => 'Dubai',
                'state' => 'Dubai',
                'country' => 'AE'
            ]
        ];

        $ch = curl_init($baseUrl . "/payment/request");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $serverKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'PayTabs API Connection Error: ' . $err];
        }

        $data = json_decode($res, true);
        if (isset($data['redirect_url'])) {
            return ['redirect_url' => $data['redirect_url']];
        }

        return ['error' => 'PayTabs Order Error: ' . ($data['message'] ?? 'Unable to generate PayTabs checkout link.')];
    }

    /**
     * Create Telr Payment Gateway Checkout Session
     */
    public static function createInvoiceTelrCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $storeId = self::getSetting($pdo, 'telr_store_id', '', $tid);
        $apiKey = self::getSetting($pdo, 'telr_api_key', '', $tid);
        $mode = self::getSetting($pdo, 'telr_mode', '1', $tid);
        $isEnabled = self::getSetting($pdo, 'telr_enabled', '0', $tid);

        if ($isEnabled === '0' || empty($storeId) || empty($apiKey)) {
            return ['error' => 'Telr payment gateway is disabled or missing Store ID / API Key for this workspace.'];
        }

        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtoupper($inv['currency'] ?? 'AED');
        $totalAmount = (float)$inv['total'];

        $payload = [
            'method' => 'create',
            'store' => (int)$storeId,
            'authkey' => $apiKey,
            'order' => [
                'cartid' => 'inv_' . $invId,
                'test' => (int)$mode,
                'amount' => number_format($totalAmount, 2, '.', ''),
                'currency' => $currency,
                'description' => 'Tax Invoice #' . ($inv['invoice_number'] ?? $invId)
            ],
            'customer' => [
                'email' => $inv['email'] ?? 'customer@example.com',
                'name' => [
                    'forenames' => $inv['contact_name'] ?? ($inv['company_name'] ?? 'Customer')
                ]
            ],
            'return' => [
                'authorised' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&gateway=telr",
                'declined'   => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&error=Telr+Payment+Declined",
                'cancelled'  => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&status=cancel"
            ]
        ];

        $ch = curl_init("https://secure.telr.com/gateway/order.json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Telr API Connection Error: ' . $err];
        }

        $data = json_decode($res, true);
        if (isset($data['order']['url'])) {
            return ['redirect_url' => $data['order']['url']];
        }

        return ['error' => 'Telr Order Error: ' . ($data['error']['message'] ?? ($data['order']['ref'] ?? 'Unable to create Telr payment checkout.'))];
    }

    /**
     * Create Checkout.com Hosted Checkout Session
     */
    public static function createInvoiceCheckoutComCheckout(PDO $pdo, array $inv, array $items, string $appUrl): array {
        $tid = (int)$inv['tenant_id'];
        $secretKey = self::getSetting($pdo, 'checkout_secret_key', '', $tid);
        $env = self::getSetting($pdo, 'checkout_environment', 'sandbox', $tid);
        $isEnabled = self::getSetting($pdo, 'checkout_enabled', '0', $tid);

        if ($isEnabled === '0' || empty($secretKey)) {
            return ['error' => 'Checkout.com payment gateway is disabled or Secret Key is missing for this workspace.'];
        }

        $invId = (int)$inv['id'];
        $token = function_exists('get_invoice_token') ? get_invoice_token($inv) : '';
        $currency = strtoupper($inv['currency'] ?? 'AED');
        $totalAmount = (float)$inv['total'];
        $amountInMinorUnits = (int)round($totalAmount * 100);

        $domain = ($env === 'live') ? 'https://api.checkout.com' : 'https://api.sandbox.checkout.com';

        $payload = [
            'amount' => $amountInMinorUnits,
            'currency' => $currency,
            'reference' => 'inv_' . $invId,
            'description' => 'Tax Invoice #' . ($inv['invoice_number'] ?? $invId),
            'customer' => [
                'email' => $inv['email'] ?? 'customer@example.com',
                'name' => $inv['contact_name'] ?? ($inv['company_name'] ?? 'Customer')
            ],
            'success_url' => $appUrl . "/stripe_return.php?invoice_id={$invId}&token={$token}&gateway=checkout",
            'cancel_url'  => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&status=cancel",
            'failure_url' => $appUrl . "/public_invoice.php?id={$invId}&token={$token}&error=Checkout.com+Payment+Failed"
        ];

        $ch = curl_init("{$domain}/hosted-payments");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . trim($secretKey),
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Checkout.com API Connection Error: ' . $err];
        }

        $data = json_decode($res, true);
        if (isset($data['_links']['redirect']['href'])) {
            return ['redirect_url' => $data['_links']['redirect']['href']];
        }

        return ['error' => 'Checkout.com Error: ' . ($data['error_type'] ?? 'Unable to create Checkout.com session.')];
    }

    /**
     * Test real-time API connection and credentials for a payment gateway
     */
    public static function testGatewayConnection(PDO $pdo, string $gateway, array $overrideParams = [], ?int $tenantId = null): array {
        $tid = $tenantId ?: (function_exists('tenant_id') ? tenant_id() : 1);

        try {
            switch (strtolower($gateway)) {
                case 'stripe':
                    $secKey = !empty($overrideParams['stripe_secret_key']) ? $overrideParams['stripe_secret_key'] : self::getSetting($pdo, 'stripe_secret_key', '', $tid);
                    if (empty($secKey)) {
                        return ['success' => false, 'message' => 'Stripe Secret Key is missing or empty.'];
                    }
                    $ch = curl_init('https://api.stripe.com/v1/balance');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . trim($secKey)]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200) {
                        $data = json_decode($res, true);
                        $currency = strtoupper($data['available'][0]['currency'] ?? 'USD');
                        return ['success' => true, 'message' => 'Stripe API connection successful! Account active (Default Currency: ' . $currency . ').'];
                    } else {
                        $err = json_decode($res, true);
                        $msg = $err['error']['message'] ?? 'Authentication failed (HTTP ' . $code . ').';
                        return ['success' => false, 'message' => 'Stripe connection failed: ' . $msg];
                    }

                case 'tabby':
                    $secKey = !empty($overrideParams['tabby_secret_key']) ? $overrideParams['tabby_secret_key'] : self::getSetting($pdo, 'tabby_secret_key', '', $tid);
                    $pubKey = !empty($overrideParams['tabby_public_key']) ? $overrideParams['tabby_public_key'] : self::getSetting($pdo, 'tabby_public_key', '', $tid);
                    if (empty($secKey) || empty($pubKey)) {
                        return ['success' => false, 'message' => 'Tabby Secret Key or Public Key is missing.'];
                    }
                    $ch = curl_init('https://api.tabby.ai/api/v2/checkout');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['payment' => ['amount' => '1.00', 'currency' => 'AED']]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . trim($secKey),
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code !== 401 && $code !== 403 && $code > 0) {
                        return ['success' => true, 'message' => 'Tabby API connection successful! Credentials authorized.'];
                    } else {
                        return ['success' => false, 'message' => 'Tabby connection failed: Invalid Secret Key or Public Key (HTTP ' . $code . ').'];
                    }

                case 'tamara':
                    $token = !empty($overrideParams['tamara_api_token']) ? $overrideParams['tamara_api_token'] : self::getSetting($pdo, 'tamara_api_token', '', $tid);
                    $envUrl = !empty($overrideParams['tamara_api_url']) ? $overrideParams['tamara_api_url'] : self::getSetting($pdo, 'tamara_api_url', 'https://api.tamara.co', $tid);
                    if (empty($token)) {
                        return ['success' => false, 'message' => 'Tamara API Token is missing.'];
                    }
                    $apiUrl = rtrim($envUrl, '/') . '/checkout/types';
                    $ch = curl_init($apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . trim($token)]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200) {
                        return ['success' => true, 'message' => 'Tamara API connection successful! Merchant account active.'];
                    } else {
                        $err = json_decode($res, true);
                        $msg = $err['message'] ?? 'Authentication failed (HTTP ' . $code . ').';
                        return ['success' => false, 'message' => 'Tamara connection failed: ' . $msg];
                    }

                case 'ziina':
                    $token = !empty($overrideParams['ziina_api_token']) ? $overrideParams['ziina_api_token'] : self::getSetting($pdo, 'ziina_api_token', '', $tid);
                    if (empty($token)) {
                        return ['success' => false, 'message' => 'Ziina API Secret Token is missing.'];
                    }
                    $ch = curl_init('https://api-v2.ziina.com/api/payment_intents');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . trim($token),
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code !== 401 && $code !== 403 && $code > 0) {
                        return ['success' => true, 'message' => 'Ziina API connection successful! Secret Token authorized.'];
                    } else {
                        return ['success' => false, 'message' => 'Ziina connection failed: Unauthorized Secret Token (HTTP ' . $code . ').'];
                    }

                case 'zbooni':
                    $apiKey = !empty($overrideParams['zbooni_api_key']) ? $overrideParams['zbooni_api_key'] : self::getSetting($pdo, 'zbooni_api_key', '', $tid);
                    if (empty($apiKey)) {
                        return ['success' => false, 'message' => 'Zbooni API Key is missing.'];
                    }
                    $ch = curl_init('https://api.zbooni.com/v1/orders/');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . trim($apiKey)]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200 || $code === 404 || $code === 400) {
                        return ['success' => true, 'message' => 'Zbooni API connection successful! Merchant token authorized.'];
                    } else {
                        return ['success' => false, 'message' => 'Zbooni connection failed: Invalid API Key (HTTP ' . $code . ').'];
                    }

                case 'paytabs':
                    $profileId = !empty($overrideParams['paytabs_profile_id']) ? $overrideParams['paytabs_profile_id'] : self::getSetting($pdo, 'paytabs_profile_id', '', $tid);
                    $serverKey = !empty($overrideParams['paytabs_server_key']) ? $overrideParams['paytabs_server_key'] : self::getSetting($pdo, 'paytabs_server_key', '', $tid);
                    $region = !empty($overrideParams['paytabs_region']) ? $overrideParams['paytabs_region'] : self::getSetting($pdo, 'paytabs_region', 'ARE', $tid);
                    if (empty($profileId) || empty($serverKey)) {
                        return ['success' => false, 'message' => 'PayTabs Profile ID or Server Key is missing.'];
                    }
                    $endpoint = 'https://secure.paytabs.com/payment/query';
                    if ($region === 'SAU') $endpoint = 'https://secure-saudi.paytabs.com/payment/query';
                    elseif ($region === 'EGY') $endpoint = 'https://secure-egypt.paytabs.com/payment/query';
                    elseif ($region === 'OMN') $endpoint = 'https://secure-oman.paytabs.com/payment/query';
                    elseif ($region === 'JOR') $endpoint = 'https://secure-jordan.paytabs.com/payment/query';

                    $ch = curl_init($endpoint);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                        'profile_id' => (int)$profileId,
                        'tran_ref' => 'TST000000000'
                    ]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'authorization: ' . trim($serverKey),
                        'content-type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200 || $code === 400) {
                        $json = json_decode($res, true);
                        if (isset($json['message']) && strpos(strtolower($json['message']), 'unauthorized') !== false) {
                            return ['success' => false, 'message' => 'PayTabs connection failed: Server Key or Profile ID unauthorized.'];
                        }
                        return ['success' => true, 'message' => 'PayTabs API connection successful! Profile ID ' . $profileId . ' authorized.'];
                    } else {
                        return ['success' => false, 'message' => 'PayTabs connection failed: HTTP ' . $code . '.'];
                    }

                case 'telr':
                    $storeId = !empty($overrideParams['telr_store_id']) ? $overrideParams['telr_store_id'] : self::getSetting($pdo, 'telr_store_id', '', $tid);
                    $apiKey = !empty($overrideParams['telr_api_key']) ? $overrideParams['telr_api_key'] : self::getSetting($pdo, 'telr_api_key', '', $tid);
                    if (empty($storeId) || empty($apiKey)) {
                        return ['success' => false, 'message' => 'Telr Store ID or Remote Auth API Key is missing.'];
                    }
                    $ch = curl_init('https://secure.telr.com/gateway/order.json');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                        'method' => 'check',
                        'store' => $storeId,
                        'auth' => $apiKey,
                        'order' => ['ref' => 'TST00000000']
                    ]));
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $json = json_decode($res, true);
                    if (isset($json['error'])) {
                        $msg = $json['error']['message'] ?? $json['error']['note'] ?? 'Authentication failed.';
                        if (strpos(strtolower($msg), 'not found') !== false) {
                            return ['success' => true, 'message' => 'Telr API connection successful! Store ID ' . $storeId . ' authorized.'];
                        }
                        return ['success' => false, 'message' => 'Telr connection failed: ' . $msg];
                    }
                    return ['success' => true, 'message' => 'Telr API connection successful! Store ID ' . $storeId . ' authorized.'];

                case 'checkout':
                    $secKey = !empty($overrideParams['checkout_secret_key']) ? $overrideParams['checkout_secret_key'] : self::getSetting($pdo, 'checkout_secret_key', '', $tid);
                    $env = !empty($overrideParams['checkout_environment']) ? $overrideParams['checkout_environment'] : self::getSetting($pdo, 'checkout_environment', 'sandbox', $tid);
                    if (empty($secKey)) {
                        return ['success' => false, 'message' => 'Checkout.com Secret Key is missing.'];
                    }
                    $host = ($env === 'live') ? 'https://api.checkout.com' : 'https://api.sandbox.checkout.com';
                    $ch = curl_init($host . '/events');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . trim($secKey),
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200 || $code === 400) {
                        return ['success' => true, 'message' => 'Checkout.com API connection successful! (' . ucfirst($env) . ' Mode).'];
                    } else {
                        return ['success' => false, 'message' => 'Checkout.com connection failed: Invalid Secret Key (HTTP ' . $code . ').'];
                    }

                case 'network':
                    $outletId = !empty($overrideParams['network_outlet_id']) ? $overrideParams['network_outlet_id'] : self::getSetting($pdo, 'network_outlet_id', '', $tid);
                    $apiKey = !empty($overrideParams['network_api_key']) ? $overrideParams['network_api_key'] : self::getSetting($pdo, 'network_api_key', '', $tid);
                    $env = !empty($overrideParams['network_environment']) ? $overrideParams['network_environment'] : self::getSetting($pdo, 'network_environment', 'sandbox', $tid);
                    if (empty($outletId) || empty($apiKey)) {
                        return ['success' => false, 'message' => 'Network Outlet ID or NGenius API Key is missing.'];
                    }
                    $host = ($env === 'live') ? 'https://identity.ngenius-payments.com' : 'https://identity-uat.ngenius-payments.com';
                    $ch = curl_init($host . '/auth/realms/ngenius/protocol/openid-connect/token');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['grant_type' => 'client_credentials']));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Basic ' . base64_encode($apiKey),
                        'Content-Type: application/vnd.ni-identity.v1+json',
                        'Accept: application/vnd.ni-identity.v1+json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200) {
                        $json = json_decode($res, true);
                        if (!empty($json['access_token'])) {
                            return ['success' => true, 'message' => 'Network International (NGenius) API connection successful! Access Token generated (' . ucfirst($env) . ' Mode).'];
                        }
                    }
                    return ['success' => false, 'message' => 'Network International connection failed: Invalid Outlet ID or NGenius API Key (HTTP ' . $code . ').'];

                case 'paypal':
                    $clientId = !empty($overrideParams['paypal_client_id']) ? $overrideParams['paypal_client_id'] : self::getSetting($pdo, 'paypal_client_id', '', $tid);
                    $secKey = !empty($overrideParams['paypal_secret_key']) ? $overrideParams['paypal_secret_key'] : self::getSetting($pdo, 'paypal_secret_key', '', $tid);
                    $mode = !empty($overrideParams['paypal_mode']) ? $overrideParams['paypal_mode'] : self::getSetting($pdo, 'paypal_mode', 'sandbox', $tid);
                    if (empty($clientId) || empty($secKey)) {
                        return ['success' => false, 'message' => 'PayPal Client ID or Secret Key is missing.'];
                    }
                    $host = ($mode === 'live') ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
                    $ch = curl_init($host . '/v1/oauth2/token');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
                    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $secKey);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200) {
                        $json = json_decode($res, true);
                        if (!empty($json['access_token'])) {
                            return ['success' => true, 'message' => 'PayPal Express connection successful! Client Credentials authorized (' . ucfirst($mode) . ' Mode).'];
                        }
                    }
                    return ['success' => false, 'message' => 'PayPal connection failed: Invalid Client ID or Secret Key (HTTP ' . $code . ').'];

                case 'bank':
                    $bankName = !empty($overrideParams['bank_name']) ? $overrideParams['bank_name'] : self::getSetting($pdo, 'bank_name', '', $tid);
                    $iban = !empty($overrideParams['bank_iban']) ? $overrideParams['bank_iban'] : self::getSetting($pdo, 'bank_iban', '', $tid);
                    if (empty($bankName) || empty($iban)) {
                        return ['success' => false, 'message' => 'Bank Name or IBAN is missing.'];
                    }
                    return ['success' => true, 'message' => 'Bank Transfer setup validated! Bank: ' . $bankName . ' (IBAN configured).'];

                default:
                    return ['success' => false, 'message' => 'Unknown gateway: ' . $gateway];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Connection test error: ' . $e->getMessage()];
        }
    }
}



