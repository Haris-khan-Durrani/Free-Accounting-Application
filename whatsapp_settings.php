<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Ensure columns exist
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN whatsapp_phone_number_id VARCHAR(100) NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN whatsapp_access_token TEXT NULL"); } catch (\Throwable $e) {}

$message = '';
$error = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_whatsapp') {
        $phoneId = trim($_POST['whatsapp_phone_number_id'] ?? '');
        $token   = trim($_POST['whatsapp_access_token'] ?? '');

        $st = $pdo->prepare("UPDATE branding_settings SET whatsapp_phone_number_id = ?, whatsapp_access_token = ? WHERE tenant_id = ?");
        $st->execute([$phoneId, $token, $tid]);

        log_audit($pdo, 'update_whatsapp_settings', 'branding_settings', $tid, 'Updated WhatsApp Cloud API credentials');
        $message = 'WhatsApp Cloud API settings saved successfully.';
    } elseif ($action === 'test_whatsapp') {
        $testPhone = trim($_POST['test_phone'] ?? '');
        $testMsg   = trim($_POST['test_message'] ?? 'Hello! This is a test invoice message from OneSol Invoice Manager.');

        $testResult = \Services\WhatsAppService::send($pdo, $tid, $testPhone, $testMsg);
    }
}

$brand = \Core\Branding::get($pdo, $tid);
$phoneId = $brand['whatsapp_phone_number_id'] ?? '';
$token   = $brand['whatsapp_access_token'] ?? '';

require __DIR__ . '/layout.php';
page_start('WhatsApp Business API Integration');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-black text-xs uppercase tracking-wider">Automated Messaging</span>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">WhatsApp Cloud API Gateway</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Configure Meta WhatsApp Cloud API credentials for automated invoice PDF dispatches & payment reminders.</p>
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
        <div class="font-bold text-sm mb-1"><?= $testResult['success'] ? '✅ WhatsApp Message Dispatched!' : '❌ Dispatch Failed' ?></div>
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

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">WhatsApp Phone Number ID <span class="text-rose-500">*</span></label>
                    <input type="text" name="whatsapp_phone_number_id" value="<?=e($phoneId)?>" placeholder="e.g. 104857291048291" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm font-mono text-slate-900 focus:ring-2 focus:ring-emerald-500">
                    <p class="mt-1 text-2xs text-slate-500">Obtained from Meta Business Suite / WhatsApp Developer Console.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Permanent Access Token / Bearer Token <span class="text-rose-500">*</span></label>
                    <textarea name="whatsapp_access_token" rows="3" placeholder="EAAG..." class="w-full px-4 py-3 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:ring-2 focus:ring-emerald-500"><?=e($token)?></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        Save WhatsApp Credentials
                    </button>
                </div>
            </form>
        </div>

        <!-- Test Dispatch Form -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-4">
            <h3 class="text-sm font-extrabold text-slate-900 border-b border-slate-100 pb-3 flex items-center">
                <i class="fa-solid fa-paper-plane text-emerald-500 mr-2"></i> Test Live WhatsApp Message Dispatch
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

                <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-sm">
                    ⚡ Dispatch Test WhatsApp Message
                </button>
            </form>
        </div>
    </div>

    <!-- Instruction Panel -->
    <div class="space-y-6">
        <div class="bg-gradient-to-br from-emerald-950 to-slate-900 text-white rounded-2xl p-6 border border-emerald-800 shadow-xl space-y-4">
            <div class="flex items-center space-x-3 text-emerald-400">
                <i class="fa-brands fa-whatsapp text-2xl"></i>
                <h3 class="text-sm font-black">Meta Cloud API Setup</h3>
            </div>
            <p class="text-xs text-slate-300 leading-relaxed">
                Connect your official Meta Business Account to enable automated WhatsApp invoice link dispatches with 1-click payment buttons.
            </p>
            <div class="bg-slate-950/80 rounded-xl p-3 text-2xs font-mono space-y-2 text-emerald-300 border border-emerald-800/50">
                <div>1. Create app at developers.facebook.com</div>
                <div>2. Add WhatsApp Product</div>
                <div>3. Copy Phone Number ID & Token</div>
            </div>
        </div>
    </div>
</div>

<?php page_end(); ?>
