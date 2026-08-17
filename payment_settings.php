<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();

// Save Payment Gateway Settings for Current Active Tenant/Subaccount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_gateways') {
    verify_csrf();

    $settingsToSave = [
        'stripe_enabled' => $_POST['stripe_enabled'] ?? '0',
        'stripe_publishable_key' => trim($_POST['stripe_publishable_key'] ?? ''),
        'stripe_secret_key' => trim($_POST['stripe_secret_key'] ?? ''),
        'stripe_webhook_secret' => trim($_POST['stripe_webhook_secret'] ?? ''),
        'stripe_currency' => $_POST['stripe_currency'] ?? 'AED',

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

    foreach ($settingsToSave as $k => $v) {
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

page_start('Workspace Payment Gateways');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800 font-black text-xs uppercase tracking-wider">Subaccount Checkout</span>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Workspace Payment Gateways</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Configure Stripe, PayPal, Network International, and Bank Wire Transfer for <strong><?=e($activeTenant['name'])?></strong>.</p>
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
                    <h2 class="text-lg font-bold text-slate-900">Stripe Online Credit Card Checkout</h2>
                    <p class="text-xs text-slate-500">Accept credit cards, Apple Pay, Google Pay & instant payment links for <?=e($activeTenant['name'])?></p>
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
                <input type="password" name="stripe_secret_key" value="<?=e($stripeSecKey)?>" placeholder="sk_live_..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Webhook Signing Secret</label>
                <input type="password" name="stripe_webhook_secret" value="<?=e($stripeWebhookSec)?>" placeholder="whsec_..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
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
            <div class="text-amber-400 font-bold font-sans">Stripe Fallback & Real-Time Sync URL for <?=e($activeTenant['name'])?>:</div>
            <div class="bg-slate-950 p-2 rounded-lg text-emerald-300 text-2xs border border-slate-800">
                <code><?=(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost')?>/invoice/stripe_return?invoice_id={INVOICE_ID}</code>
            </div>
        </div>
    </div>

    <!-- Card 2: PayPal Checkout -->
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
                <input type="password" name="paypal_secret_key" value="<?=e($paypalSecKey)?>" placeholder="E..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
        </div>
    </div>

    <!-- Card 3: Bank Wire Transfer Instructions -->
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
