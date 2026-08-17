<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Ensure all database columns exist
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN whatsapp_provider VARCHAR(30) NOT NULL DEFAULT 'meta'"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN whatsapp_phone_number_id VARCHAR(100) NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN whatsapp_access_token TEXT NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN twilio_account_sid VARCHAR(100) NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN twilio_auth_token VARCHAR(100) NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN twilio_from_number VARCHAR(50) NULL"); } catch (\Throwable $e) {}

$message = '';
$error = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_whatsapp') {
        $provider  = trim($_POST['whatsapp_provider'] ?? 'meta');
        $phoneId   = trim($_POST['whatsapp_phone_number_id'] ?? '');
        $token     = trim($_POST['whatsapp_access_token'] ?? '');
        $twilioSid = trim($_POST['twilio_account_sid'] ?? '');
        $twilioAuth= trim($_POST['twilio_auth_token'] ?? '');
        $twilioFrom= trim($_POST['twilio_from_number'] ?? '');

        $st = $pdo->prepare("
            UPDATE branding_settings 
            SET whatsapp_provider = ?, whatsapp_phone_number_id = ?, whatsapp_access_token = ?,
                twilio_account_sid = ?, twilio_auth_token = ?, twilio_from_number = ?
            WHERE tenant_id = ?
        ");
        $st->execute([$provider, $phoneId, $token, $twilioSid, $twilioAuth, $twilioFrom, $tid]);

        log_audit($pdo, 'update_whatsapp_settings', 'branding_settings', $tid, "Updated Messaging Provider settings ($provider)");
        $message = 'WhatsApp & Twilio SMS provider settings saved successfully.';
    } elseif ($action === 'test_whatsapp') {
        $testPhone = trim($_POST['test_phone'] ?? '');
        $testMsg   = trim($_POST['test_message'] ?? 'Hello! This is a test invoice message from OneSol Invoice Manager.');

        $testResult = \Services\WhatsAppService::send($pdo, $tid, $testPhone, $testMsg);
    }
}

$brand = \Core\Branding::get($pdo, $tid);
$provider  = $brand['whatsapp_provider'] ?? 'meta';
$phoneId   = $brand['whatsapp_phone_number_id'] ?? '';
$token     = $brand['whatsapp_access_token'] ?? '';
$twilioSid = $brand['twilio_account_sid'] ?? '';
$twilioAuth= $brand['twilio_auth_token'] ?? '';
$twilioFrom= $brand['twilio_from_number'] ?? '';

require __DIR__ . '/layout.php';
page_start('WhatsApp & Twilio Gateway Settings');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-black text-xs uppercase tracking-wider">SMS & WhatsApp Gateway</span>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">WhatsApp & Twilio API Settings</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Configure Meta WhatsApp Cloud API or Twilio SMS/WhatsApp for automated payment reminders & invoice links.</p>
    </div>
    
    <div class="mt-4 sm:mt-0 flex space-x-2">
        <a href="settings" class="px-4 py-2 bg-slate-900 text-white hover:bg-slate-800 text-xs font-bold rounded-xl transition-all shadow-sm">
            <i class="fa-solid fa-sliders mr-1.5 text-amber-400"></i>Master Settings Hub
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 text-xs font-bold flex items-center space-x-2">
        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
        <span><?=e($message)?></span>
    </div>
<?php endif; ?>

<?php if ($testResult): ?>
    <div class="mb-6 p-4 rounded-xl text-xs font-semibold <?= $testResult['success'] ? 'bg-emerald-50 border border-emerald-300 text-emerald-900' : 'bg-rose-50 border border-rose-300 text-rose-900' ?>">
        <div class="font-bold text-sm mb-1"><?= $testResult['success'] ? '✅ Message Dispatched Successfully!' : '❌ Dispatch Failed' ?></div>
        <div><?=e($testResult['message'])?></div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Config Form -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-6">
            <form method="post" class="space-y-6">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_whatsapp">

                <!-- Gateway Provider Selector -->
                <div>
                    <label class="block text-xs font-extrabold text-slate-900 uppercase tracking-wider mb-3">Active Messaging Gateway Provider</label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="border-2 rounded-2xl p-4 flex items-center space-x-3 cursor-pointer transition-all <?= $provider === 'meta' ? 'border-emerald-500 bg-emerald-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                            <input type="radio" name="whatsapp_provider" value="meta" <?= $provider === 'meta' ? 'checked' : '' ?> class="text-emerald-600 focus:ring-emerald-500" onchange="toggleProviders('meta')">
                            <div>
                                <div class="font-extrabold text-xs text-slate-900 flex items-center"><i class="fa-brands fa-whatsapp text-emerald-600 mr-1.5 text-base"></i> Meta WhatsApp Cloud API</div>
                                <div class="text-3xs text-slate-500">Direct Facebook/Meta Developer Gateway</div>
                            </div>
                        </label>

                        <label class="border-2 rounded-2xl p-4 flex items-center space-x-3 cursor-pointer transition-all <?= $provider === 'twilio' ? 'border-red-500 bg-red-50/40' : 'border-slate-200 hover:border-slate-300' ?>">
                            <input type="radio" name="whatsapp_provider" value="twilio" <?= $provider === 'twilio' ? 'checked' : '' ?> class="text-red-600 focus:ring-red-500" onchange="toggleProviders('twilio')">
                            <div>
                                <div class="font-extrabold text-xs text-slate-900 flex items-center"><i class="fa-solid fa-comment-sms text-red-600 mr-1.5 text-base"></i> Twilio SMS & WhatsApp</div>
                                <div class="text-3xs text-slate-500">Twilio Programmable Messaging API</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- SECTION 1: Meta WhatsApp Cloud API -->
                <div id="metaFields" class="space-y-4 pt-4 border-t border-slate-100 <?= $provider === 'twilio' ? 'hidden' : '' ?>">
                    <h3 class="text-xs font-black uppercase text-emerald-600 flex items-center">
                        <i class="fa-brands fa-whatsapp mr-1.5 text-base"></i> Meta Cloud API Credentials
                    </h3>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">WhatsApp Phone Number ID</label>
                        <input type="text" name="whatsapp_phone_number_id" value="<?=e($phoneId)?>" placeholder="e.g. 104857291048291" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Permanent Access Token / Bearer Token</label>
                        <textarea name="whatsapp_access_token" rows="2" placeholder="EAAG..." class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:ring-2 focus:ring-emerald-500"><?=e($token)?></textarea>
                    </div>
                </div>

                <!-- SECTION 2: Twilio Credentials -->
                <div id="twilioFields" class="space-y-4 pt-4 border-t border-slate-100 <?= $provider === 'meta' ? 'hidden' : '' ?>">
                    <h3 class="text-xs font-black uppercase text-red-600 flex items-center">
                        <i class="fa-solid fa-comment-sms mr-1.5 text-base"></i> Twilio Account Credentials
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Twilio Account SID</label>
                            <input type="text" name="twilio_account_sid" value="<?=e($twilioSid)?>" placeholder="ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:ring-2 focus:ring-red-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Twilio Auth Token</label>
                            <input type="password" name="twilio_auth_token" value="<?=e($twilioAuth)?>" placeholder="Your Twilio Auth Token" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Twilio From Phone Number (or WhatsApp Sender)</label>
                        <input type="text" name="twilio_from_number" value="<?=e($twilioFrom)?>" placeholder="+14155238886 or whatsapp:+14155238886" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:ring-2 focus:ring-red-500">
                        <p class="mt-1 text-3xs text-slate-500">For Twilio WhatsApp, prefix with <code>whatsapp:</code> (e.g. <code>whatsapp:+14155238886</code>). For SMS, use normal number <code>+14155238886</code>.</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        Save Gateway Configuration
                    </button>
                </div>
            </form>
        </div>

        <!-- Test Dispatch Form -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-4">
            <h3 class="text-sm font-extrabold text-slate-900 border-b border-slate-100 pb-3 flex items-center">
                <i class="fa-solid fa-paper-plane text-emerald-500 mr-2"></i> Test Live Message Dispatch
            </h3>
            
            <form method="post" class="space-y-4">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="test_whatsapp">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Recipient Phone Number (with Country Code)</label>
                    <input type="text" name="test_phone" placeholder="+971501234567" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm font-bold text-slate-900">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Test Message Text</label>
                    <input type="text" name="test_message" value="Hello! This is a test payment notification from OneSol Invoice Manager." required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-xs text-slate-900">
                </div>

                <button type="submit" class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-xl text-xs font-bold shadow-sm flex items-center space-x-1.5">
                    <i class="fa-solid fa-bolt text-amber-300"></i>
                    <span>Dispatch Test Message Now</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Right Side Help Column -->
    <div class="space-y-6">
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-2xl p-6 border border-slate-800 shadow-xl space-y-4">
            <div class="flex items-center space-x-3 text-emerald-400">
                <i class="fa-solid fa-sliders text-xl"></i>
                <h3 class="text-sm font-black">Supported Gateway Providers</h3>
            </div>
            
            <div class="space-y-3 text-xs text-slate-300">
                <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700/80">
                    <strong class="text-emerald-400 font-extrabold block mb-0.5"><i class="fa-brands fa-whatsapp"></i> 1. Meta WhatsApp Cloud API</strong>
                    <span>Official WhatsApp API from Facebook. Requires Phone Number ID & Permanent Access Token.</span>
                </div>
                <div class="p-3 rounded-xl bg-slate-800/80 border border-slate-700/80">
                    <strong class="text-red-400 font-extrabold block mb-0.5"><i class="fa-solid fa-comment-sms"></i> 2. Twilio SMS & WhatsApp API</strong>
                    <span>Supports both standard SMS text messages and Twilio Sandbox/Production WhatsApp messaging.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleProviders(p) {
    if (p === 'meta') {
        document.getElementById('metaFields').classList.remove('hidden');
        document.getElementById('twilioFields').classList.add('hidden');
    } else {
        document.getElementById('metaFields').classList.add('hidden');
        document.getElementById('twilioFields').classList.remove('hidden');
    }
}
</script>

<?php page_end(); ?>
