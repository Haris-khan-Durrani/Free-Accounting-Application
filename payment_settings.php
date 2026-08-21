<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();

// Payment gateway settings — owner or admin only
if (!has_role(['owner', 'admin'])) {
    flash('error', 'Access denied. Payment settings require admin or owner access.');
    redirect('index');
}

// Save Payment Gateway Settings for Current Active Tenant/Subaccount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_gateways') {
    verify_csrf();

    $settingsToSave = [
        'stripe_enabled' => $_POST['stripe_enabled'] ?? '0',
        'stripe_publishable_key' => trim($_POST['stripe_publishable_key'] ?? ''),
        'stripe_secret_key' => trim($_POST['stripe_secret_key'] ?? ''),
        'stripe_webhook_secret' => trim($_POST['stripe_webhook_secret'] ?? ''),
        'stripe_currency' => $_POST['stripe_currency'] ?? 'AED',

        'tabby_enabled' => $_POST['tabby_enabled'] ?? '0',
        'tabby_public_key' => trim($_POST['tabby_public_key'] ?? ''),
        'tabby_secret_key' => trim($_POST['tabby_secret_key'] ?? ''),
        'tabby_merchant_code' => trim($_POST['tabby_merchant_code'] ?? ''),

        'tamara_enabled' => $_POST['tamara_enabled'] ?? '0',
        'tamara_api_url' => trim($_POST['tamara_api_url'] ?? 'https://api-sandbox.tamara.co'),
        'tamara_api_token' => trim($_POST['tamara_api_token'] ?? ''),
        'tamara_notification_token' => trim($_POST['tamara_notification_token'] ?? ''),

        'network_enabled' => $_POST['network_enabled'] ?? '0',
        'network_outlet_id' => trim($_POST['network_outlet_id'] ?? ''),
        'network_api_key' => trim($_POST['network_api_key'] ?? ''),
        'network_environment' => $_POST['network_environment'] ?? 'sandbox',

        'paypal_enabled' => $_POST['paypal_enabled'] ?? '0',
        'paypal_client_id' => trim($_POST['paypal_client_id'] ?? ''),
        'paypal_secret_key' => trim($_POST['paypal_secret_key'] ?? ''),
        'paypal_mode' => $_POST['paypal_mode'] ?? 'sandbox',

        'bank_transfer_enabled' => $_POST['bank_transfer_enabled'] ?? '1',
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'bank_account_name' => trim($_POST['bank_account_name'] ?? ''),
        'bank_iban' => trim($_POST['bank_iban'] ?? ''),
        'bank_swift' => trim($_POST['bank_swift'] ?? ''),
        'bank_instructions' => trim($_POST['bank_instructions'] ?? '')
    ];

    $secretFields = [
        'stripe_secret_key', 
        'stripe_webhook_secret', 
        'network_api_key', 
        'paypal_secret_key',
        'tabby_secret_key',
        'tamara_api_token',
        'tamara_notification_token'
    ];

    foreach ($settingsToSave as $k => $v) {
        if (in_array($k, $secretFields, true)) {
            // Do not overwrite existing secret if submitted value is empty or masked placeholder
            if ($v === '' || str_starts_with($v, '••••••••')) {
                continue;
            }
        }
        \Services\PaymentGatewayService::setSetting($pdo, $k, $v, $tid);
    }

    log_audit($pdo, 'update_tenant_gateway_settings', 'settings', $tid, "Updated Workspace Payment Gateway Credentials for {$activeTenant['name']}");
    flash('success', 'Workspace Payment Gateway Credentials & Checkout Settings updated successfully!');
    redirect('payment_settings.php');
}

// Fetch Existing Tenant Settings
$stripeEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_enabled', '1', $tid);
$stripePubKey = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_publishable_key', '', $tid);
$stripeSecKey = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_secret_key', '', $tid);
$stripeWebhookSec = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_webhook_secret', '', $tid);
$stripeCurrency = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_currency', 'AED', $tid);

$tabbyEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'tabby_enabled', '0', $tid);
$tabbyPubKey = \Services\PaymentGatewayService::getSetting($pdo, 'tabby_public_key', '', $tid);
$tabbySecKey = \Services\PaymentGatewayService::getSetting($pdo, 'tabby_secret_key', '', $tid);
$tabbyMerchantCode = \Services\PaymentGatewayService::getSetting($pdo, 'tabby_merchant_code', '', $tid);

$tamaraEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'tamara_enabled', '0', $tid);
$tamaraApiUrl = \Services\PaymentGatewayService::getSetting($pdo, 'tamara_api_url', 'https://api-sandbox.tamara.co', $tid);
$tamaraApiToken = \Services\PaymentGatewayService::getSetting($pdo, 'tamara_api_token', '', $tid);
$tamaraNotificationToken = \Services\PaymentGatewayService::getSetting($pdo, 'tamara_notification_token', '', $tid);

$networkEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'network_enabled', '0', $tid);
$networkOutletId = \Services\PaymentGatewayService::getSetting($pdo, 'network_outlet_id', '', $tid);
$networkApiKey = \Services\PaymentGatewayService::getSetting($pdo, 'network_api_key', '', $tid);
$networkEnv = \Services\PaymentGatewayService::getSetting($pdo, 'network_environment', 'sandbox', $tid);

$paypalEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'paypal_enabled', '0', $tid);
$paypalClientId = \Services\PaymentGatewayService::getSetting($pdo, 'paypal_client_id', '', $tid);
$paypalSecKey = \Services\PaymentGatewayService::getSetting($pdo, 'paypal_secret_key', '', $tid);
$paypalMode = \Services\PaymentGatewayService::getSetting($pdo, 'paypal_mode', 'sandbox', $tid);

$bankTransferEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'bank_transfer_enabled', '1', $tid);
$bankName = \Services\PaymentGatewayService::getSetting($pdo, 'bank_name', '', $tid);
$bankAccountName = \Services\PaymentGatewayService::getSetting($pdo, 'bank_account_name', '', $tid);
$bankIban = \Services\PaymentGatewayService::getSetting($pdo, 'bank_iban', '', $tid);
$bankSwift = \Services\PaymentGatewayService::getSetting($pdo, 'bank_swift', '', $tid);
$bankInstructions = \Services\PaymentGatewayService::getSetting($pdo, 'bank_instructions', 'Please include invoice number in wire transfer description.', $tid);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$baseUrl = $protocol . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

page_start('Workspace Payment Gateways');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800 font-black text-xs uppercase tracking-wider">Subaccount Checkout</span>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Workspace Payment Gateways</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Configure Stripe, Tabby, Tamara, Network International, PayPal, and Bank Wire Transfer for <strong><?=e($activeTenant['name'])?></strong>.</p>
    </div>
</div>

<form method="post" class="space-y-8 max-w-5xl mx-auto">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="save_gateways">

    <!-- Card 1: Stripe Integration -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-purple-100 text-purple-700 rounded-xl flex items-center justify-center text-xl font-bold">
                    <i class="fa-brands fa-stripe-s"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Stripe Credit Card & Apple Pay</h2>
                    <p class="text-xs text-slate-500">Accept credit cards, Apple Pay, Google Pay & instant payment links</p>
                </div>
            </div>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="stripe_enabled" value="1" <?=$stripeEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                <span class="ml-2 text-xs font-bold text-slate-700">Enable Stripe</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Stripe Publishable Key *</label>
                <input type="text" name="stripe_publishable_key" value="<?=e($stripePubKey)?>" placeholder="pk_live_..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Stripe Secret Key *</label>
                <input type="password" name="stripe_secret_key" value="" placeholder="<?=!empty($stripeSecKey) ? '•••••••••••• (Configured - leave blank to keep)' : 'sk_live_...'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Webhook Signing Secret</label>
                <input type="password" name="stripe_webhook_secret" value="" placeholder="<?=!empty($stripeWebhookSec) ? '•••••••••••• (Configured - leave blank to keep)' : 'whsec_...'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Settlement Currency</label>
                <select name="stripe_currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
                    <option value="AED" <?=$stripeCurrency === 'AED' ? 'selected' : ''?>>AED - UAE Dirham</option>
                    <option value="USD" <?=$stripeCurrency === 'USD' ? 'selected' : ''?>>USD - US Dollar</option>
                    <option value="EUR" <?=$stripeCurrency === 'EUR' ? 'selected' : ''?>>EUR - Euro</option>
                    <option value="GBP" <?=$stripeCurrency === 'GBP' ? 'selected' : ''?>>GBP - British Pound</option>
                    <option value="SAR" <?=$stripeCurrency === 'SAR' ? 'selected' : ''?>>SAR - Saudi Riyal</option>
                </select>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 bg-slate-900 text-white rounded-xl p-4 text-xs font-mono space-y-2">
            <div class="text-amber-400 font-bold font-sans flex items-center justify-between">
                <span>📘 Stripe Webhook Setup Instructions:</span>
                <span class="text-2xs bg-purple-950 text-purple-300 px-2 py-0.5 rounded border border-purple-800">Step-by-Step</span>
            </div>
            <p class="text-slate-300 text-2xs font-sans">Log into Stripe Dashboard $\rightarrow$ Developers $\rightarrow$ Webhooks $\rightarrow$ Add Endpoint and enter this exact URL:</p>
            <div class="bg-slate-950 p-2.5 rounded-lg text-emerald-300 text-2xs border border-slate-800 flex justify-between items-center">
                <code><?=$baseUrl?>/api/v1/webhooks/stripe.php</code>
                <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/stripe.php'); alert('Stripe Webhook URL copied!');" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded text-2xs font-sans font-bold">Copy URL</button>
            </div>
        </div>
    </div>

    <!-- Card 2: Tabby BNPL Integration -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-emerald-100 text-emerald-800 rounded-xl flex items-center justify-center text-lg font-black">
                    tabby
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Tabby (Pay in 4 Installments)</h2>
                    <p class="text-xs text-slate-500">Allow customers in UAE & KSA to split payments into 4 interest-free monthly installments</p>
                </div>
            </div>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="tabby_enabled" value="1" <?=$tabbyEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                <span class="ml-2 text-xs font-bold text-slate-700">Enable Tabby</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tabby Public Key *</label>
                <input type="text" name="tabby_public_key" value="<?=e($tabbyPubKey)?>" placeholder="pk_test_... or pk_live_..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tabby Secret Key *</label>
                <input type="password" name="tabby_secret_key" value="" placeholder="<?=!empty($tabbySecKey) ? '•••••••••••• (Configured - leave blank to keep)' : 'sk_test_... or sk_live_...'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tabby Merchant Code</label>
                <input type="text" name="tabby_merchant_code" value="<?=e($tabbyMerchantCode)?>" placeholder="AE or SA Merchant Code" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 bg-slate-900 text-white rounded-xl p-4 text-xs font-mono space-y-2">
            <div class="text-emerald-400 font-bold font-sans flex items-center justify-between">
                <span>📘 Tabby Webhook Integration Guide:</span>
                <span class="text-2xs bg-emerald-950 text-emerald-300 px-2 py-0.5 rounded border border-emerald-800">Tabby Portal Setup</span>
            </div>
            <p class="text-slate-300 text-2xs font-sans">In Tabby Merchant Portal $\rightarrow$ Developers $\rightarrow$ Webhook Notifications, set this Webhook URL:</p>
            <div class="bg-slate-950 p-2.5 rounded-lg text-emerald-300 text-2xs border border-slate-800 flex justify-between items-center">
                <code><?=$baseUrl?>/api/v1/webhooks/tabby.php</code>
                <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/tabby.php'); alert('Tabby Webhook URL copied!');" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded text-2xs font-sans font-bold">Copy URL</button>
            </div>
        </div>
    </div>

    <!-- Card 3: Tamara BNPL Integration -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-pink-100 text-pink-700 rounded-xl flex items-center justify-center text-lg font-black">
                    tamara
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Tamara (Pay in 3/4 Installments)</h2>
                    <p class="text-xs text-slate-500">GCC BNPL leader in UAE, Saudi Arabia, Kuwait, Bahrain & Qatar</p>
                </div>
            </div>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="tamara_enabled" value="1" <?=$tamaraEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-600"></div>
                <span class="ml-2 text-xs font-bold text-slate-700">Enable Tamara</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tamara Environment API URL *</label>
                <select name="tamara_api_url" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
                    <option value="https://api-sandbox.tamara.co" <?=$tamaraApiUrl === 'https://api-sandbox.tamara.co' ? 'selected' : ''?>>Sandbox (Testing Mode)</option>
                    <option value="https://api.tamara.co" <?=$tamaraApiUrl === 'https://api.tamara.co' ? 'selected' : ''?>>Production (Live Mode)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tamara API Token *</label>
                <input type="password" name="tamara_api_token" value="" placeholder="<?=!empty($tamaraApiToken) ? '•••••••••••• (Configured - leave blank to keep)' : 'Tamara Bearer Token'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Notification Webhook Token</label>
                <input type="password" name="tamara_notification_token" value="" placeholder="<?=!empty($tamaraNotificationToken) ? '•••••••••••• (Configured - leave blank to keep)' : 'Notification Token'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 bg-slate-900 text-white rounded-xl p-4 text-xs font-mono space-y-2">
            <div class="text-pink-400 font-bold font-sans flex items-center justify-between">
                <span>📘 Tamara Webhook Setup Guide:</span>
                <span class="text-2xs bg-pink-950 text-pink-300 px-2 py-0.5 rounded border border-pink-800">Tamara Partner Portal</span>
            </div>
            <p class="text-slate-300 text-2xs font-sans">In Tamara Partner Portal $\rightarrow$ Settings $\rightarrow$ Webhooks / Notifications, enter this URL:</p>
            <div class="bg-slate-950 p-2.5 rounded-lg text-emerald-300 text-2xs border border-slate-800 flex justify-between items-center">
                <code><?=$baseUrl?>/api/v1/webhooks/tamara.php</code>
                <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/tamara.php'); alert('Tamara Webhook URL copied!');" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded text-2xs font-sans font-bold">Copy URL</button>
            </div>
        </div>
    </div>

    <!-- Card 4: Network International Integration -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-sky-100 text-sky-700 rounded-xl flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Network International (NGenius)</h2>
                    <p class="text-xs text-slate-500">Leading payment solution provider in the Middle East & Africa</p>
                </div>
            </div>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="network_enabled" value="1" <?=$networkEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                <span class="ml-2 text-xs font-bold text-slate-700">Enable Network</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Outlet ID *</label>
                <input type="text" name="network_outlet_id" value="<?=e($networkOutletId)?>" placeholder="Outlet Reference ID" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">NGenius API Key *</label>
                <input type="password" name="network_api_key" value="" placeholder="<?=!empty($networkApiKey) ? '•••••••••••• (Configured - leave blank to keep)' : 'API Key'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Environment</label>
                <select name="network_environment" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
                    <option value="sandbox" <?=$networkEnv === 'sandbox' ? 'selected' : ''?>>Sandbox (Testing)</option>
                    <option value="live" <?=$networkEnv === 'live' ? 'selected' : ''?>>Live / Production</option>
                </select>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 bg-slate-900 text-white rounded-xl p-4 text-xs font-mono space-y-2">
            <div class="text-sky-400 font-bold font-sans flex items-center justify-between">
                <span>📘 Network International Webhook Setup:</span>
                <span class="text-2xs bg-sky-950 text-sky-300 px-2 py-0.5 rounded border border-sky-800">NGenius Portal</span>
            </div>
            <p class="text-slate-300 text-2xs font-sans">In NGenius Merchant Portal $\rightarrow$ Outlet Settings $\rightarrow$ Webhooks, configure this URL:</p>
            <div class="bg-slate-950 p-2.5 rounded-lg text-emerald-300 text-2xs border border-slate-800 flex justify-between items-center">
                <code><?=$baseUrl?>/api/v1/webhooks/network.php</code>
                <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/network.php'); alert('Network Webhook URL copied!');" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded text-2xs font-sans font-bold">Copy URL</button>
            </div>
        </div>
    </div>

    <!-- Card 5: PayPal Checkout -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center text-xl font-bold">
                    <i class="fa-brands fa-paypal"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">PayPal Express Checkout</h2>
                    <p class="text-xs text-slate-500">Accept international PayPal account & debit card payments</p>
                </div>
            </div>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="paypal_enabled" value="1" <?=$paypalEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                <span class="ml-2 text-xs font-bold text-slate-700">Enable PayPal</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">PayPal Client ID</label>
                <input type="text" name="paypal_client_id" value="<?=e($paypalClientId)?>" placeholder="A..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">PayPal Secret Key</label>
                <input type="password" name="paypal_secret_key" value="" placeholder="<?=!empty($paypalSecKey) ? '•••••••••••• (Configured - leave blank to keep)' : 'E...'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
        </div>
    </div>

    <!-- Card 6: Bank Wire Transfer Instructions -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-emerald-100 text-emerald-700 rounded-xl flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Bank Wire Transfer & IBAN Details</h2>
                    <p class="text-xs text-slate-500">Display direct bank transfer instructions on client invoice views</p>
                </div>
            </div>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="bank_transfer_enabled" value="1" <?=$bankTransferEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                <span class="ml-2 text-xs font-bold text-slate-700">Enable Bank Wire</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Bank Name</label>
                <input type="text" name="bank_name" value="<?=e($bankName)?>" placeholder="Emirates NBD / Mashreq / FAB" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Account Holder Name</label>
                <input type="text" name="bank_account_name" value="<?=e($bankAccountName)?>" placeholder="<?=e($activeTenant['name'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">IBAN Number</label>
                <input type="text" name="bank_iban" value="<?=e($bankIban)?>" placeholder="AE03033000010129384729" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">SWIFT / BIC Code</label>
                <input type="text" name="bank_swift" value="<?=e($bankSwift)?>" placeholder="EBILAEADXXX" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="px-8 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-extrabold text-sm rounded-xl shadow-xl transition-all">
            Save Workspace Gateway Credentials
        </button>
    </div>
</form>

<?php page_end(); ?>
