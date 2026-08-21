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

        'ziina_enabled' => $_POST['ziina_enabled'] ?? '0',
        'ziina_api_token' => trim($_POST['ziina_api_token'] ?? ''),
        'ziina_webhook_secret' => trim($_POST['ziina_webhook_secret'] ?? ''),

        'zbooni_enabled' => $_POST['zbooni_enabled'] ?? '0',
        'zbooni_api_key' => trim($_POST['zbooni_api_key'] ?? ''),
        'zbooni_secret_key' => trim($_POST['zbooni_secret_key'] ?? ''),

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
        'tamara_notification_token',
        'ziina_api_token',
        'ziina_webhook_secret',
        'zbooni_api_key',
        'zbooni_secret_key'
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
    flash('success', 'Workspace Payment Gateway Credentials & Settings updated successfully!');
    redirect('payment_settings.php' . (!empty($_POST['active_tab']) ? '#' . urlencode($_POST['active_tab']) : ''));
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

$ziinaEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'ziina_enabled', '0', $tid);
$ziinaApiToken = \Services\PaymentGatewayService::getSetting($pdo, 'ziina_api_token', '', $tid);
$ziinaWebhookSec = \Services\PaymentGatewayService::getSetting($pdo, 'ziina_webhook_secret', '', $tid);

$zbooniEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'zbooni_enabled', '0', $tid);
$zbooniApiKey = \Services\PaymentGatewayService::getSetting($pdo, 'zbooni_api_key', '', $tid);
$zbooniSecKey = \Services\PaymentGatewayService::getSetting($pdo, 'zbooni_secret_key', '', $tid);

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

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header Title -->
    <div class="md:flex md:items-center md:justify-between border-b border-slate-200 pb-5">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800 font-black text-xs uppercase tracking-wider">Checkout Configuration</span>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Workspace Payment Gateways</h1>
            </div>
            <p class="mt-1 text-sm text-slate-500">Configure online payment gateways, BNPL installment providers, and webhook real-time sync for <strong><?=e($activeTenant['name'])?></strong>.</p>
        </div>
    </div>

    <form method="post" id="gatewaySettingsForm" class="space-y-6">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="save_gateways">
        <input type="hidden" name="active_tab" id="activeTabInput" value="stripe">

        <!-- Modern Pill Tabs Navigation Header -->
        <div class="bg-slate-900 p-2 rounded-2xl border border-slate-800 shadow-lg overflow-x-auto scrollbar-none">
            <div class="flex space-x-2 min-w-max">
                <button type="button" onclick="switchGatewayTab('stripe')" id="tabBtn-stripe" class="gateway-tab-btn flex items-center space-x-2 px-4 py-2.5 rounded-xl font-bold text-xs transition-all bg-purple-600 text-white shadow-lg">
                    <i class="fa-brands fa-stripe-s text-sm"></i>
                    <span>Stripe</span>
                    <span class="ml-1 w-2 h-2 rounded-full <?=$stripeEnabled === '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'?>"></span>
                </button>

                <button type="button" onclick="switchGatewayTab('tabby')" id="tabBtn-tabby" class="gateway-tab-btn flex items-center space-x-2 px-4 py-2.5 rounded-xl font-bold text-xs transition-all text-slate-400 hover:text-white hover:bg-slate-800">
                    <span class="font-black text-emerald-400">tabby</span>
                    <span>Tabby BNPL</span>
                    <span class="ml-1 w-2 h-2 rounded-full <?=$tabbyEnabled === '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'?>"></span>
                </button>

                <button type="button" onclick="switchGatewayTab('tamara')" id="tabBtn-tamara" class="gateway-tab-btn flex items-center space-x-2 px-4 py-2.5 rounded-xl font-bold text-xs transition-all text-slate-400 hover:text-white hover:bg-slate-800">
                    <span class="font-black text-pink-400">tamara</span>
                    <span>Tamara BNPL</span>
                    <span class="ml-1 w-2 h-2 rounded-full <?=$tamaraEnabled === '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'?>"></span>
                </button>

                <button type="button" onclick="switchGatewayTab('ziina')" id="tabBtn-ziina" class="gateway-tab-btn flex items-center space-x-2 px-4 py-2.5 rounded-xl font-bold text-xs transition-all text-slate-400 hover:text-white hover:bg-slate-800">
                    <i class="fa-solid fa-bolt text-purple-400"></i>
                    <span>Ziina</span>
                    <span class="ml-1 w-2 h-2 rounded-full <?=$ziinaEnabled === '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'?>"></span>
                </button>

                <button type="button" onclick="switchGatewayTab('zbooni')" id="tabBtn-zbooni" class="gateway-tab-btn flex items-center space-x-2 px-4 py-2.5 rounded-xl font-bold text-xs transition-all text-slate-400 hover:text-white hover:bg-slate-800">
                    <i class="fa-solid fa-bag-shopping text-emerald-400"></i>
                    <span>Zbooni</span>
                    <span class="ml-1 w-2 h-2 rounded-full <?=$zbooniEnabled === '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'?>"></span>
                </button>

                <button type="button" onclick="switchGatewayTab('network')" id="tabBtn-network" class="gateway-tab-btn flex items-center space-x-2 px-4 py-2.5 rounded-xl font-bold text-xs transition-all text-slate-400 hover:text-white hover:bg-slate-800">
                    <i class="fa-solid fa-building-columns text-sky-400"></i>
                    <span>Network Int.</span>
                    <span class="ml-1 w-2 h-2 rounded-full <?=$networkEnabled === '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'?>"></span>
                </button>

                <button type="button" onclick="switchGatewayTab('paypal')" id="tabBtn-paypal" class="gateway-tab-btn flex items-center space-x-2 px-4 py-2.5 rounded-xl font-bold text-xs transition-all text-slate-400 hover:text-white hover:bg-slate-800">
                    <i class="fa-brands fa-paypal text-blue-400"></i>
                    <span>PayPal</span>
                    <span class="ml-1 w-2 h-2 rounded-full <?=$paypalEnabled === '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'?>"></span>
                </button>

                <button type="button" onclick="switchGatewayTab('bank')" id="tabBtn-bank" class="gateway-tab-btn flex items-center space-x-2 px-4 py-2.5 rounded-xl font-bold text-xs transition-all text-slate-400 hover:text-white hover:bg-slate-800">
                    <i class="fa-solid fa-building-columns text-amber-400"></i>
                    <span>Bank Transfer</span>
                    <span class="ml-1 w-2 h-2 rounded-full <?=$bankTransferEnabled === '1' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'?>"></span>
                </button>
            </div>
        </div>

        <!-- ================= TAB 1: STRIPE ================= -->
        <div id="tabPanel-stripe" class="gateway-tab-panel space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-purple-100 text-purple-700 rounded-2xl flex items-center justify-center text-2xl font-bold">
                            <i class="fa-brands fa-stripe-s"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Stripe Online Payments</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Credit/Debit Cards, Apple Pay, Google Pay & Payment Links</p>
                        </div>
                    </div>
                    <label class="flex items-center cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                        <input type="checkbox" name="stripe_enabled" value="1" <?=$stripeEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        <span class="ml-3 text-xs font-extrabold text-slate-800">Enable Stripe Gateway</span>
                    </label>
                </div>

                <!-- Detailed Gateway Overview Box -->
                <div class="bg-purple-50/60 border border-purple-100 rounded-2xl p-5 mb-6 space-y-3">
                    <h3 class="text-xs font-extrabold text-purple-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> About Stripe Gateway
                    </h3>
                    <p class="text-xs text-purple-950 leading-relaxed">
                        Stripe is a world-class payment gateway allowing your clients to pay invoices instantly using Credit Cards (Visa, MasterCard, Amex), Apple Pay, and Google Pay. It features automatic 3D-Secure fraud protection and instant email payment receipts.
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1 text-2xs">
                        <span class="px-2.5 py-1 bg-white text-purple-800 rounded-lg font-bold border border-purple-200">🌍 Global & GCC (UAE, KSA)</span>
                        <span class="px-2.5 py-1 bg-white text-purple-800 rounded-lg font-bold border border-purple-200">💳 Visa, MasterCard, Amex, Apple Pay</span>
                        <span class="px-2.5 py-1 bg-white text-purple-800 rounded-lg font-bold border border-purple-200">⚡ Real-Time Webhook Sync</span>
                    </div>
                </div>

                <!-- Credentials Grid -->
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

                <!-- Webhook Step-by-Step Guide -->
                <div class="mt-8 border-t border-slate-100 bg-slate-900 text-white rounded-2xl p-6 text-xs space-y-4 shadow-xl">
                    <div class="flex items-center justify-between">
                        <span class="text-amber-400 font-extrabold text-sm flex items-center gap-2">
                            <i class="fa-solid fa-list-check"></i> Stripe Webhook Setup Instructions
                        </span>
                        <span class="bg-purple-900 text-purple-200 text-2xs px-3 py-1 rounded-full font-bold">Step-by-Step Guide</span>
                    </div>
                    <ol class="text-slate-300 space-y-2 text-xs font-sans list-decimal list-inside leading-relaxed">
                        <li>Log into your <strong>Stripe Dashboard</strong> (<a href="https://dashboard.stripe.com" target="_blank" class="text-purple-400 underline font-bold">dashboard.stripe.com</a>).</li>
                        <li>Navigate to <strong>Developers</strong> &rarr; <strong>Webhooks</strong> &rarr; Click <strong>Add Endpoint</strong>.</li>
                        <li>Copy the Webhook URL below and paste it into the <em>Endpoint URL</em> field.</li>
                        <li>Select events: <code class="bg-slate-800 text-emerald-300 px-1.5 py-0.5 rounded text-2xs">checkout.session.completed</code> and <code class="bg-slate-800 text-emerald-300 px-1.5 py-0.5 rounded text-2xs">payment_intent.succeeded</code>.</li>
                        <li>Copy the generated <strong>Signing Secret</strong> (<code class="text-amber-300">whsec_...</code>) and paste it into the field above.</li>
                    </ol>
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center font-mono">
                        <code class="text-emerald-400 text-xs"><?=$baseUrl?>/api/v1/webhooks/stripe.php</code>
                        <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/stripe.php'); alert('Stripe Webhook URL copied!');" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-sans font-bold shadow">Copy URL</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 2: TABBY BNPL ================= -->
        <div id="tabPanel-tabby" class="gateway-tab-panel hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-emerald-100 text-emerald-800 rounded-2xl flex items-center justify-center text-xl font-black">
                            tabby
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Tabby (Pay in 4 Installments)</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Split payments into 4 interest-free monthly payments for GCC clients</p>
                        </div>
                    </div>
                    <label class="flex items-center cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                        <input type="checkbox" name="tabby_enabled" value="1" <?=$tabbyEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        <span class="ml-3 text-xs font-extrabold text-slate-800">Enable Tabby BNPL</span>
                    </label>
                </div>

                <!-- Detailed Gateway Overview Box -->
                <div class="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-5 mb-6 space-y-3">
                    <h3 class="text-xs font-extrabold text-emerald-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> About Tabby Buy Now Pay Later
                    </h3>
                    <p class="text-xs text-emerald-950 leading-relaxed">
                        Tabby is the MENA region's largest BNPL provider. It allows your buyers to split their invoice into 4 interest-free monthly payments. <strong>You as the merchant get paid the full invoice total upfront</strong> directly into your bank account, while Tabby manages customer installment collections.
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1 text-2xs">
                        <span class="px-2.5 py-1 bg-white text-emerald-800 rounded-lg font-bold border border-emerald-200">🇦🇪 UAE & 🇸🇦 Saudi Arabia</span>
                        <span class="px-2.5 py-1 bg-white text-emerald-800 rounded-lg font-bold border border-emerald-200">4x Interest-Free Installments</span>
                        <span class="px-2.5 py-1 bg-white text-emerald-800 rounded-lg font-bold border border-emerald-200">💰 Upfront Merchant Settlement</span>
                    </div>
                </div>

                <!-- Credentials Grid -->
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

                <!-- Webhook Step-by-Step Guide -->
                <div class="mt-8 border-t border-slate-100 bg-slate-900 text-white rounded-2xl p-6 text-xs space-y-4 shadow-xl">
                    <div class="flex items-center justify-between">
                        <span class="text-emerald-400 font-extrabold text-sm flex items-center gap-2">
                            <i class="fa-solid fa-list-check"></i> Tabby Webhook Setup Guide
                        </span>
                        <span class="bg-emerald-900 text-emerald-200 text-2xs px-3 py-1 rounded-full font-bold">Tabby Merchant Portal</span>
                    </div>
                    <ol class="text-slate-300 space-y-2 text-xs font-sans list-decimal list-inside leading-relaxed">
                        <li>Log into your <strong>Tabby Merchant Portal</strong> (<a href="https://merchant.tabby.ai" target="_blank" class="text-emerald-400 underline font-bold">merchant.tabby.ai</a>).</li>
                        <li>Go to <strong>Integration</strong> &rarr; <strong>Webhook Notifications</strong>.</li>
                        <li>Add a new Webhook URL pointing to the endpoint below for events <code class="bg-slate-800 text-emerald-300 px-1.5 py-0.5 rounded text-2xs">payment.authorized</code> and <code class="bg-slate-800 text-emerald-300 px-1.5 py-0.5 rounded text-2xs">payment.captured</code>.</li>
                    </ol>
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center font-mono">
                        <code class="text-emerald-400 text-xs"><?=$baseUrl?>/api/v1/webhooks/tabby.php</code>
                        <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/tabby.php'); alert('Tabby Webhook URL copied!');" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-sans font-bold shadow">Copy URL</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 3: TAMARA BNPL ================= -->
        <div id="tabPanel-tamara" class="gateway-tab-panel hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-pink-100 text-pink-700 rounded-2xl flex items-center justify-center text-xl font-black">
                            tamara
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Tamara (Pay in 3/4 Installments)</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Top GCC Buy Now Pay Later solution for Saudi Arabia, UAE & Kuwait</p>
                        </div>
                    </div>
                    <label class="flex items-center cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                        <input type="checkbox" name="tamara_enabled" value="1" <?=$tamaraEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-600"></div>
                        <span class="ml-3 text-xs font-extrabold text-slate-800">Enable Tamara BNPL</span>
                    </label>
                </div>

                <!-- Detailed Gateway Overview Box -->
                <div class="bg-pink-50/60 border border-pink-100 rounded-2xl p-5 mb-6 space-y-3">
                    <h3 class="text-xs font-extrabold text-pink-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> About Tamara Buy Now Pay Later
                    </h3>
                    <p class="text-xs text-pink-950 leading-relaxed">
                        Tamara is the dominant BNPL payment solution across Saudi Arabia and the UAE. It offers flexible 3x or 4x interest-free installment plans. Your business receives guaranteed upfront settlement while Tamara handles buyer billing.
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1 text-2xs">
                        <span class="px-2.5 py-1 bg-white text-pink-800 rounded-lg font-bold border border-pink-200">🇸🇦 KSA, 🇦🇪 UAE, 🇰🇼 Kuwait, 🇧🇭 Bahrain</span>
                        <span class="px-2.5 py-1 bg-white text-pink-800 rounded-lg font-bold border border-pink-200">3x / 4x Monthly Installments</span>
                        <span class="px-2.5 py-1 bg-white text-pink-800 rounded-lg font-bold border border-pink-200">🔒 Guaranteed Merchant Payout</span>
                    </div>
                </div>

                <!-- Credentials Grid -->
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

                <!-- Webhook Step-by-Step Guide -->
                <div class="mt-8 border-t border-slate-100 bg-slate-900 text-white rounded-2xl p-6 text-xs space-y-4 shadow-xl">
                    <div class="flex items-center justify-between">
                        <span class="text-pink-400 font-extrabold text-sm flex items-center gap-2">
                            <i class="fa-solid fa-list-check"></i> Tamara Webhook Setup Guide
                        </span>
                        <span class="bg-pink-900 text-pink-200 text-2xs px-3 py-1 rounded-full font-bold">Tamara Partner Portal</span>
                    </div>
                    <ol class="text-slate-300 space-y-2 text-xs font-sans list-decimal list-inside leading-relaxed">
                        <li>Log into your <strong>Tamara Partner Portal</strong> (<a href="https://partners.tamara.co" target="_blank" class="text-pink-400 underline font-bold">partners.tamara.co</a>).</li>
                        <li>Go to <strong>Settings</strong> &rarr; <strong>Webhooks & Notifications</strong>.</li>
                        <li>Enter the Webhook URL below and copy your <strong>Notification Token</strong> into the field above.</li>
                    </ol>
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center font-mono">
                        <code class="text-emerald-400 text-xs"><?=$baseUrl?>/api/v1/webhooks/tamara.php</code>
                        <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/tamara.php'); alert('Tamara Webhook URL copied!');" class="px-3 py-1.5 bg-pink-600 hover:bg-pink-700 text-white rounded-lg text-xs font-sans font-bold shadow">Copy URL</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 4: ZIINA ================= -->
        <div id="tabPanel-ziina" class="gateway-tab-panel hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-purple-100 text-purple-700 rounded-2xl flex items-center justify-center text-2xl font-black">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Ziina Payment Gateway</h2>
                            <p class="text-xs text-slate-500 mt-0.5">UAE Cards, Apple Pay & Instant Payment Links</p>
                        </div>
                    </div>
                    <label class="flex items-center cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                        <input type="checkbox" name="ziina_enabled" value="1" <?=$ziinaEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        <span class="ml-3 text-xs font-extrabold text-slate-800">Enable Ziina Gateway</span>
                    </label>
                </div>

                <!-- Detailed Gateway Overview Box -->
                <div class="bg-purple-50/60 border border-purple-100 rounded-2xl p-5 mb-6 space-y-3">
                    <h3 class="text-xs font-extrabold text-purple-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> About Ziina Payments
                    </h3>
                    <p class="text-xs text-purple-950 leading-relaxed">
                        Ziina is the UAE's premier fintech payment platform. It enables fast customer checkout via Credit/Debit Cards, Apple Pay, and digital wallet links with competitive transaction rates and rapid bank settlement in UAE Dirhams (AED).
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1 text-2xs">
                        <span class="px-2.5 py-1 bg-white text-purple-800 rounded-lg font-bold border border-purple-200">🇦🇪 UAE Dirham (AED) Settlement</span>
                        <span class="px-2.5 py-1 bg-white text-purple-800 rounded-lg font-bold border border-purple-200">🍏 Native Apple Pay & Cards</span>
                        <span class="px-2.5 py-1 bg-white text-purple-800 rounded-lg font-bold border border-purple-200">⚡ Instant Digital Payment Links</span>
                    </div>
                </div>

                <!-- Credentials Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Ziina API Secret Token *</label>
                        <input type="password" name="ziina_api_token" value="" placeholder="<?=!empty($ziinaApiToken) ? '•••••••••••• (Configured - leave blank to keep)' : 'zi_sec_...'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Webhook Signing Secret</label>
                        <input type="password" name="ziina_webhook_secret" value="" placeholder="<?=!empty($ziinaWebhookSec) ? '•••••••••••• (Configured - leave blank to keep)' : 'zi_whsec_...'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
                    </div>
                </div>

                <!-- Webhook Step-by-Step Guide -->
                <div class="mt-8 border-t border-slate-100 bg-slate-900 text-white rounded-2xl p-6 text-xs space-y-4 shadow-xl">
                    <div class="flex items-center justify-between">
                        <span class="text-purple-400 font-extrabold text-sm flex items-center gap-2">
                            <i class="fa-solid fa-list-check"></i> Ziina Webhook Setup Guide
                        </span>
                        <span class="bg-purple-900 text-purple-200 text-2xs px-3 py-1 rounded-full font-bold">Ziina Business Dashboard</span>
                    </div>
                    <ol class="text-slate-300 space-y-2 text-xs font-sans list-decimal list-inside leading-relaxed">
                        <li>Log into your <strong>Ziina Business Dashboard</strong> (<a href="https://business.ziina.com" target="_blank" class="text-purple-400 underline font-bold">business.ziina.com</a>).</li>
                        <li>Go to <strong>Settings</strong> &rarr; <strong>Webhooks</strong>.</li>
                        <li>Add the Endpoint URL below for event <code class="bg-slate-800 text-emerald-300 px-1.5 py-0.5 rounded text-2xs">payment_intent.completed</code>.</li>
                    </ol>
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center font-mono">
                        <code class="text-emerald-400 text-xs"><?=$baseUrl?>/api/v1/webhooks/ziina.php</code>
                        <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/ziina.php'); alert('Ziina Webhook URL copied!');" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-sans font-bold shadow">Copy URL</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 5: ZBOONI ================= -->
        <div id="tabPanel-zbooni" class="gateway-tab-panel hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-emerald-100 text-emerald-700 rounded-2xl flex items-center justify-center text-2xl font-black">
                            <i class="fa-solid fa-bag-shopping"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Zbooni Conversational Commerce</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Payment links and digital checkout across UAE, Saudi Arabia & Jordan</p>
                        </div>
                    </div>
                    <label class="flex items-center cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                        <input type="checkbox" name="zbooni_enabled" value="1" <?=$zbooniEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        <span class="ml-3 text-xs font-extrabold text-slate-800">Enable Zbooni Gateway</span>
                    </label>
                </div>

                <!-- Detailed Gateway Overview Box -->
                <div class="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-5 mb-6 space-y-3">
                    <h3 class="text-xs font-extrabold text-emerald-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> About Zbooni Payments
                    </h3>
                    <p class="text-xs text-emerald-950 leading-relaxed">
                        Zbooni is a popular MENA payment platform that turns invoices into trackable, shareable payment links. It allows customers in UAE, Saudi Arabia, and Jordan to pay securely via credit cards and mobile wallets.
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1 text-2xs">
                        <span class="px-2.5 py-1 bg-white text-emerald-800 rounded-lg font-bold border border-emerald-200">🇦🇪 UAE, 🇸🇦 KSA, 🇯🇴 Jordan</span>
                        <span class="px-2.5 py-1 bg-white text-emerald-800 rounded-lg font-bold border border-emerald-200">💳 Card Checkout & Payment Links</span>
                        <span class="px-2.5 py-1 bg-white text-emerald-800 rounded-lg font-bold border border-emerald-200">⚡ Auto-Status Callback Sync</span>
                    </div>
                </div>

                <!-- Credentials Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Zbooni API Token *</label>
                        <input type="password" name="zbooni_api_key" value="" placeholder="<?=!empty($zbooniApiKey) ? '•••••••••••• (Configured - leave blank to keep)' : 'Zbooni API Token'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Zbooni Secret / Webhook Key</label>
                        <input type="password" name="zbooni_secret_key" value="" placeholder="<?=!empty($zbooniSecKey) ? '•••••••••••• (Configured - leave blank to keep)' : 'Webhook Secret'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
                    </div>
                </div>

                <!-- Webhook Step-by-Step Guide -->
                <div class="mt-8 border-t border-slate-100 bg-slate-900 text-white rounded-2xl p-6 text-xs space-y-4 shadow-xl">
                    <div class="flex items-center justify-between">
                        <span class="text-emerald-400 font-extrabold text-sm flex items-center gap-2">
                            <i class="fa-solid fa-list-check"></i> Zbooni Webhook Setup Guide
                        </span>
                        <span class="bg-emerald-900 text-emerald-200 text-2xs px-3 py-1 rounded-full font-bold">Zbooni Merchant Portal</span>
                    </div>
                    <ol class="text-slate-300 space-y-2 text-xs font-sans list-decimal list-inside leading-relaxed">
                        <li>Log into your <strong>Zbooni Merchant Dashboard</strong> (<a href="https://dashboard.zbooni.com" target="_blank" class="text-emerald-400 underline font-bold">dashboard.zbooni.com</a>).</li>
                        <li>Go to <strong>Developer Settings</strong> &rarr; <strong>Webhooks</strong>.</li>
                        <li>Register the Webhook URL below for <code class="bg-slate-800 text-emerald-300 px-1.5 py-0.5 rounded text-2xs">order.paid</code> events.</li>
                    </ol>
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center font-mono">
                        <code class="text-emerald-400 text-xs"><?=$baseUrl?>/api/v1/webhooks/zbooni.php</code>
                        <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/zbooni.php'); alert('Zbooni Webhook URL copied!');" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-sans font-bold shadow">Copy URL</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 6: NETWORK INTERNATIONAL ================= -->
        <div id="tabPanel-network" class="gateway-tab-panel hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-sky-100 text-sky-700 rounded-2xl flex items-center justify-center text-2xl font-black">
                            <i class="fa-solid fa-building-columns"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Network International (NGenius)</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Direct merchant acquiring & card payments across Middle East & Africa</p>
                        </div>
                    </div>
                    <label class="flex items-center cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                        <input type="checkbox" name="network_enabled" value="1" <?=$networkEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                        <span class="ml-3 text-xs font-extrabold text-slate-800">Enable Network Int.</span>
                    </label>
                </div>

                <!-- Detailed Gateway Overview Box -->
                <div class="bg-sky-50/60 border border-sky-100 rounded-2xl p-5 mb-6 space-y-3">
                    <h3 class="text-xs font-extrabold text-sky-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> About Network International (NGenius)
                    </h3>
                    <p class="text-xs text-sky-950 leading-relaxed">
                        Network International is the largest bank payment acquirer in the Middle East. The NGenius portal enables direct merchant account processing with maximum local card approval rates, Jaywan debit cards, and corporate cards.
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1 text-2xs">
                        <span class="px-2.5 py-1 bg-white text-sky-800 rounded-lg font-bold border border-sky-200">🏛️ Direct Bank Acquiring</span>
                        <span class="px-2.5 py-1 bg-white text-sky-800 rounded-lg font-bold border border-sky-200">💳 UAE & MEA Card Approval Rates</span>
                        <span class="px-2.5 py-1 bg-white text-sky-800 rounded-lg font-bold border border-sky-200">🛡️ Bank 3DS Fraud Prevention</span>
                    </div>
                </div>

                <!-- Credentials Grid -->
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
                            <option value="sandbox" <?=$networkEnv === 'sandbox' ? 'selected' : ''?>>Sandbox (Testing Mode)</option>
                            <option value="live" <?=$networkEnv === 'live' ? 'selected' : ''?>>Production (Live Mode)</option>
                        </select>
                    </div>
                </div>

                <!-- Webhook Step-by-Step Guide -->
                <div class="mt-8 border-t border-slate-100 bg-slate-900 text-white rounded-2xl p-6 text-xs space-y-4 shadow-xl">
                    <div class="flex items-center justify-between">
                        <span class="text-sky-400 font-extrabold text-sm flex items-center gap-2">
                            <i class="fa-solid fa-list-check"></i> Network Webhook Setup Guide
                        </span>
                        <span class="bg-sky-900 text-sky-200 text-2xs px-3 py-1 rounded-full font-bold">NGenius Merchant Portal</span>
                    </div>
                    <ol class="text-slate-300 space-y-2 text-xs font-sans list-decimal list-inside leading-relaxed">
                        <li>Log into your <strong>NGenius Portal</strong> (<a href="https://portal.ngenius-payments.com" target="_blank" class="text-sky-400 underline font-bold">portal.ngenius-payments.com</a>).</li>
                        <li>Go to <strong>Outlet Settings</strong> &rarr; <strong>Webhooks</strong>.</li>
                        <li>Configure the URL below for events <code class="bg-slate-800 text-emerald-300 px-1.5 py-0.5 rounded text-2xs">ORDER_CLOSED</code> and <code class="bg-slate-800 text-emerald-300 px-1.5 py-0.5 rounded text-2xs">CAPTURED</code>.</li>
                    </ol>
                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex justify-between items-center font-mono">
                        <code class="text-emerald-400 text-xs"><?=$baseUrl?>/api/v1/webhooks/network.php</code>
                        <button type="button" onclick="navigator.clipboard.writeText('<?=$baseUrl?>/api/v1/webhooks/network.php'); alert('Network Webhook URL copied!');" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-sans font-bold shadow">Copy URL</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 7: PAYPAL ================= -->
        <div id="tabPanel-paypal" class="gateway-tab-panel hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-blue-100 text-blue-700 rounded-2xl flex items-center justify-center text-2xl font-bold">
                            <i class="fa-brands fa-paypal"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">PayPal Express Checkout</h2>
                            <p class="text-xs text-slate-500 mt-0.5">International PayPal account & card payments across 200+ countries</p>
                        </div>
                    </div>
                    <label class="flex items-center cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                        <input type="checkbox" name="paypal_enabled" value="1" <?=$paypalEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-xs font-extrabold text-slate-800">Enable PayPal</span>
                    </label>
                </div>

                <!-- Detailed Gateway Overview Box -->
                <div class="bg-blue-50/60 border border-blue-100 rounded-2xl p-5 mb-6 space-y-3">
                    <h3 class="text-xs font-extrabold text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> About PayPal Express Checkout
                    </h3>
                    <p class="text-xs text-blue-950 leading-relaxed">
                        PayPal allows international buyers to pay securely using their PayPal balance, linked bank accounts, or credit cards without sharing financial details.
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1 text-2xs">
                        <span class="px-2.5 py-1 bg-white text-blue-800 rounded-lg font-bold border border-blue-200">🌐 200+ Countries & 25+ Currencies</span>
                        <span class="px-2.5 py-1 bg-white text-blue-800 rounded-lg font-bold border border-blue-200">💙 PayPal Balance & Debit Cards</span>
                    </div>
                </div>

                <!-- Credentials Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">PayPal Client ID</label>
                        <input type="text" name="paypal_client_id" value="<?=e($paypalClientId)?>" placeholder="Client ID from developer.paypal.com" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">PayPal Secret Key</label>
                        <input type="password" name="paypal_secret_key" value="" placeholder="<?=!empty($paypalSecKey) ? '•••••••••••• (Configured - leave blank to keep)' : 'Secret Key'?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Environment Mode</label>
                        <select name="paypal_mode" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
                            <option value="sandbox" <?=$paypalMode === 'sandbox' ? 'selected' : ''?>>Sandbox (Testing)</option>
                            <option value="live" <?=$paypalMode === 'live' ? 'selected' : ''?>>Live / Production</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= TAB 8: BANK WIRE TRANSFER ================= -->
        <div id="tabPanel-bank" class="gateway-tab-panel hidden space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-5 mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 bg-amber-100 text-amber-700 rounded-2xl flex items-center justify-center text-2xl font-bold">
                            <i class="fa-solid fa-building-columns"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-900">Bank Wire Transfer & IBAN Details</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Display direct wire transfer instructions on invoice client portals</p>
                        </div>
                    </div>
                    <label class="flex items-center cursor-pointer bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                        <input type="checkbox" name="bank_transfer_enabled" value="1" <?=$bankTransferEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        <span class="ml-3 text-xs font-extrabold text-slate-800">Enable Bank Wire</span>
                    </label>
                </div>

                <!-- Detailed Overview Box -->
                <div class="bg-amber-50/60 border border-amber-100 rounded-2xl p-5 mb-6 space-y-3">
                    <h3 class="text-xs font-extrabold text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> About Bank Wire Transfers
                    </h3>
                    <p class="text-xs text-amber-950 leading-relaxed">
                        When enabled, clients viewing their invoice online can view your official bank account details, IBAN, and SWIFT code to perform direct electronic bank transfers.
                    </p>
                </div>

                <!-- Inputs Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Bank Name</label>
                        <input type="text" name="bank_name" value="<?=e($bankName)?>" placeholder="Emirates NBD / Mashreq / FAB / Al Rajhi" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
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
        </div>

        <!-- Global Save Action Bar -->
        <div class="bg-slate-900 rounded-2xl p-4 border border-slate-800 shadow-xl flex items-center justify-between">
            <div class="text-xs text-slate-400 font-sans flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-emerald-400 text-sm"></i>
                <span>Credentials are encrypted & saved securely for <strong><?=e($activeTenant['name'])?></strong></span>
            </div>
            <button type="submit" class="px-8 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-xl transition-all flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Save Workspace Gateway Settings</span>
            </button>
        </div>
    </form>
</div>

<!-- Inline JavaScript for Tab Switching & Hash Persistence -->
<script>
function switchGatewayTab(tabId) {
    // Hide all panels
    const panels = document.querySelectorAll('.gateway-tab-panel');
    panels.forEach(p => p.classList.add('hidden'));

    // Reset all tab button styles
    const tabBtns = document.querySelectorAll('.gateway-tab-btn');
    tabBtns.forEach(btn => {
        btn.classList.remove('bg-purple-600', 'text-white', 'shadow-lg');
        btn.classList.add('text-slate-400', 'hover:text-white', 'hover:bg-slate-800');
    });

    // Show target panel
    const targetPanel = document.getElementById('tabPanel-' + tabId);
    if (targetPanel) {
        targetPanel.classList.remove('hidden');
    }

    // Highlight target button
    const targetBtn = document.getElementById('tabBtn-' + tabId);
    if (targetBtn) {
        targetBtn.classList.remove('text-slate-400', 'hover:text-white', 'hover:bg-slate-800');
        targetBtn.classList.add('bg-purple-600', 'text-white', 'shadow-lg');
    }

    // Update hidden active tab input
    const tabInput = document.getElementById('activeTabInput');
    if (tabInput) {
        tabInput.value = tabId;
    }

    // Update URL hash without scrolling
    if (history.replaceState) {
        history.replaceState(null, null, '#' + tabId);
    } else {
        location.hash = '#' + tabId;
    }
}

// Restore active tab from URL hash on load
document.addEventListener('DOMContentLoaded', function() {
    let hash = window.location.hash.replace('#', '').toLowerCase();
    const validTabs = ['stripe', 'tabby', 'tamara', 'ziina', 'zbooni', 'network', 'paypal', 'bank'];
    if (hash && validTabs.includes(hash)) {
        switchGatewayTab(hash);
    }
});
</script>

<?php page_end(); ?>
