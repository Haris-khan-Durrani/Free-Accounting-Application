<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Save Payment Gateway Settings (Stripe, Network International, PayPal, Wire Transfer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_gateways') {
    verify_csrf();

    $settingsToSave = [
        'stripe_enabled' => $_POST['stripe_enabled'] ?? '0',
        'stripe_publishable_key' => trim($_POST['stripe_publishable_key'] ?? ''),
        'stripe_secret_key' => trim($_POST['stripe_secret_key'] ?? ''),
        'stripe_webhook_secret' => trim($_POST['stripe_webhook_secret'] ?? ''),
        'stripe_currency' => $_POST['stripe_currency'] ?? 'USD',

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
        \Services\PaymentGatewayService::setSetting($pdo, $k, $v);
    }

    log_audit($pdo, 'update_gateway_settings', 'settings', $tid, 'Updated Super Admin Payment Gateway Credentials');
    flash('success', 'Super Admin Payment Gateway Credentials & Settings updated successfully!');
    redirect('super_admin_gateways.php');
}

// Fetch Existing Settings
$stripeEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_enabled', '1');
$stripePubKey = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_publishable_key', '');
$stripeSecKey = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_secret_key', '');
$stripeWebhookSec = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_webhook_secret', '');
$stripeCurrency = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_currency', 'USD');

$networkEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'network_enabled', '1');
$networkOutletId = \Services\PaymentGatewayService::getSetting($pdo, 'network_outlet_id', '');
$networkApiKey = \Services\PaymentGatewayService::getSetting($pdo, 'network_api_key', '');
$networkEnv = \Services\PaymentGatewayService::getSetting($pdo, 'network_environment', 'sandbox');

$paypalEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'paypal_enabled', '0');
$paypalClientId = \Services\PaymentGatewayService::getSetting($pdo, 'paypal_client_id', '');
$paypalSecKey = \Services\PaymentGatewayService::getSetting($pdo, 'paypal_secret_key', '');
$paypalMode = \Services\PaymentGatewayService::getSetting($pdo, 'paypal_mode', 'sandbox');

$bankEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'bank_transfer_enabled', '1');
$bankName = \Services\PaymentGatewayService::getSetting($pdo, 'bank_name', 'Emirates NBD');
$bankAccountName = \Services\PaymentGatewayService::getSetting($pdo, 'bank_account_name', 'OneSol Solutions FZ-LLC');
$bankIban = \Services\PaymentGatewayService::getSetting($pdo, 'bank_iban', 'AE03033000010129384729');
$bankSwift = \Services\PaymentGatewayService::getSetting($pdo, 'bank_swift', 'EBILAEADXXX');
$bankInstructions = \Services\PaymentGatewayService::getSetting($pdo, 'bank_instructions', 'Please include invoice number in wire transfer description.');

page_start('Payment Gateway Credentials');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Super Admin Payment Gateways</h1>
        <p class="mt-1 text-sm text-slate-500">Configure global API keys for Stripe Checkout, Network International NGenius, PayPal, and Wire Transfer for <strong><?=e(tenant()['name'])?></strong>.</p>
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
                    <h2 class="text-lg font-bold text-slate-900">Stripe Subscription Gateway</h2>
                    <p class="text-xs text-slate-500">Global credit card processing, Apple Pay, Google Pay & recurring billing</p>
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
                <input type="text" name="stripe_publishable_key" value="<?=e($stripePubKey)?>" placeholder="pk_live_51M..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Stripe Secret Key *</label>
                <input type="password" name="stripe_secret_key" value="<?=e($stripeSecKey)?>" placeholder="sk_live_51M..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Webhook Signing Secret</label>
                <input type="password" name="stripe_webhook_secret" value="<?=e($stripeWebhookSec)?>" placeholder="whsec_..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Base Settlement Currency</label>
                <select name="stripe_currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
                    <option value="USD" <?=$stripeCurrency === 'USD' ? 'selected' : ''?>>USD - US Dollar</option>
                    <option value="AED" <?=$stripeCurrency === 'AED' ? 'selected' : ''?>>AED - UAE Dirham</option>
                    <option value="EUR" <?=$stripeCurrency === 'EUR' ? 'selected' : ''?>>EUR - Euro</option>
                    <option value="GBP" <?=$stripeCurrency === 'GBP' ? 'selected' : ''?>>GBP - British Pound</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Card 2: Network International (NGenius UAE) -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-amber-100 text-amber-700 rounded-xl flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Network International (NGenius UAE Gateway)</h2>
                    <p class="text-xs text-slate-500">Official UAE & GCC regional card processing gateway</p>
                </div>
            </div>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="network_enabled" value="1" <?=$networkEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                <span class="ml-2 text-xs font-bold text-slate-700">Enable Network Intl</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Outlet Reference ID (UUID)</label>
                <input type="text" name="network_outlet_id" value="<?=e($networkOutletId)?>" placeholder="e.g. 5d92f1b4-..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">NGenius API Key</label>
                <input type="password" name="network_api_key" value="<?=e($networkApiKey)?>" placeholder="NGenius API Access Token..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Environment Mode</label>
                <select name="network_environment" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
                    <option value="sandbox" <?=$networkEnv === 'sandbox' ? 'selected' : ''?>>Sandbox Test Environment</option>
                    <option value="live" <?=$networkEnv === 'live' ? 'selected' : ''?>>Live Production Environment</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Card 3: Wire Transfer & Bank Details -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 bg-emerald-100 text-emerald-700 rounded-xl flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid fa-landmark"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Manual Wire Transfer & Bank Details</h2>
                    <p class="text-xs text-slate-500">Corporate bank account instructions for offline invoice settlements</p>
                </div>
            </div>
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="bank_transfer_enabled" value="1" <?=$bankEnabled === '1' ? 'checked' : ''?> class="sr-only peer">
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                <span class="ml-2 text-xs font-bold text-slate-700">Enable Wire Transfer</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Bank Name</label>
                <input type="text" name="bank_name" value="<?=e($bankName)?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Account Title</label>
                <input type="text" name="bank_account_name" value="<?=e($bankAccountName)?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">IBAN</label>
                <input type="text" name="bank_iban" value="<?=e($bankIban)?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">SWIFT / BIC Code</label>
                <input type="text" name="bank_swift" value="<?=e($bankSwift)?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-bold font-mono text-slate-900">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Wire Transfer Instructions</label>
                <textarea name="bank_instructions" rows="2" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-900"><?=e($bankInstructions)?></textarea>
            </div>
        </div>
    </div>

    <!-- Submit Action -->
    <div class="flex justify-end">
        <button type="submit" class="inline-flex items-center px-8 py-3 border border-transparent text-base font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
            <i class="fa-solid fa-floppy-disk mr-2"></i>Save Super Admin Gateway Settings
        </button>
    </div>
</form>

<?php page_end(); ?>
