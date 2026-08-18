<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();

$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save_smtp';

    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpPort = (int)($_POST['smtp_port'] ?? 587);
    $smtpEncryption = strtolower(trim($_POST['smtp_encryption'] ?? 'tls'));
    $smtpUsername = trim($_POST['smtp_username'] ?? '');
    $smtpPassword = $_POST['smtp_password'] ?? '';
    $fromEmail = trim($_POST['from_email'] ?? '');
    $fromName = trim($_POST['from_name'] ?? '');

    $existingPass = $activeTenant['smtp_password'] ?? '';
    $encryptedPass = !empty($smtpPassword) ? \Core\Crypto::encrypt($smtpPassword) : $existingPass;

    if ($action === 'save_smtp') {
        $st = $pdo->prepare("UPDATE tenants SET smtp_host = ?, smtp_port = ?, smtp_encryption = ?, smtp_username = ?, smtp_password = ?, from_email = ?, from_name = ? WHERE id = ?");
        $st->execute([$smtpHost, $smtpPort, $smtpEncryption, $smtpUsername, $encryptedPass, $fromEmail, $fromName, $tid]);

        log_audit($pdo, 'update_smtp', 'tenants', $tid, "Updated SMTP server configuration for tenant #$tid");
        flash('success', 'Custom SMTP email settings saved successfully!');
        redirect('email_settings');
    }

    if ($action === 'test_smtp') {
        $testRecipient = trim($_POST['test_email'] ?? $_SESSION['user_email'] ?? $fromEmail);
        
        // Save transiently to test
        $st = $pdo->prepare("UPDATE tenants SET smtp_host = ?, smtp_port = ?, smtp_encryption = ?, smtp_username = ?, smtp_password = ?, from_email = ?, from_name = ? WHERE id = ?");
        $st->execute([$smtpHost, $smtpPort, $smtpEncryption, $smtpUsername, $encryptedPass, $fromEmail, $fromName, $tid]);

        $subject = "SMTP Test Connection - " . e($activeTenant['name']);
        $htmlBody = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                <h2 style='color: #0f172a;'>SMTP Connection Successful! 🎉</h2>
                <p style='color: #475569;'>This test email confirms that your custom SMTP server (<strong>" . e($smtpHost) . "</strong>) is connected and operating properly for <strong>" . e($activeTenant['name']) . "</strong>.</p>
                <div style='background: #f8fafc; padding: 12px; border-radius: 8px; font-size: 12px; color: #64748b; font-family: monospace;'>
                    Sent At: " . date('Y-m-d H:i:s T') . "<br>
                    From: " . e($fromName) . " &lt;" . e($fromEmail) . "&gt;<br>
                    Encryption: " . strtoupper($smtpEncryption) . " (Port $smtpPort)
                </div>
            </div>
        ";

        try {
            $sent = \Services\Mailer::send($pdo, $tid, $testRecipient, $subject, $htmlBody);
            if ($sent) {
                $testResult = ['success' => true, 'message' => "Test email delivered successfully to <strong>" . e($testRecipient) . "</strong> via $smtpHost:$smtpPort."];
            } else {
                $testResult = ['success' => false, 'message' => "Failed to deliver test email. Check your SMTP host, credentials, or firewall settings."];
            }
        } catch (Exception $e) {
            $testResult = ['success' => false, 'message' => "SMTP Connection Exception: " . $e->getMessage()];
        }

        // Refresh activeTenant array
        $stT = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $stT->execute([$tid]);
        $activeTenant = $stT->fetch();
    }
}

page_start('Tenant Custom SMTP Email Settings');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Tenant Custom SMTP Email Settings</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Connect your company's custom email server to send invoices, quotes, receipts, and 2FA security codes directly from your brand domain.</p>
    </div>
</div>

<?php if ($testResult): ?>
    <div class="<?=$testResult['success'] ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'?> border rounded-2xl p-4 mb-6 text-sm font-semibold flex items-center shadow-sm">
        <i class="fa-solid <?=$testResult['success'] ? 'fa-circle-check text-emerald-500' : 'fa-triangle-exclamation text-rose-500'?> text-xl mr-3"></i>
        <div><?=$testResult['message']?></div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">

    <!-- SMTP Configuration Form -->
    <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center">
            <i class="fa-solid fa-server text-amber-500 mr-2"></i>Outbound SMTP Mail Server Configuration
        </h2>

        <form method="post" class="space-y-5">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="save_smtp">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-extrabold text-slate-700 uppercase mb-1.5">SMTP Host Server *</label>
                    <input type="text" name="smtp_host" value="<?=e($activeTenant['smtp_host'] ?? '')?>" placeholder="e.g. smtp.gmail.com or mail.onesol.ae" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase mb-1.5">Port *</label>
                    <input type="number" name="smtp_port" value="<?=e((string)($activeTenant['smtp_port'] ?: 587))?>" placeholder="587" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase mb-1.5">Encryption *</label>
                    <select name="smtp_encryption" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900">
                        <option value="tls" <?=($activeTenant['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''?>>TLS (STARTTLS - Port 587)</option>
                        <option value="ssl" <?=($activeTenant['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''?>>SSL (Direct - Port 465)</option>
                        <option value="none" <?=($activeTenant['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''?>>None (Port 25)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase mb-1.5">SMTP Username *</label>
                    <input type="text" name="smtp_username" value="<?=e($activeTenant['smtp_username'] ?? '')?>" placeholder="billing@onesol.ae" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase mb-1.5">SMTP Password *</label>
                    <input type="password" name="smtp_password" value="<?=e($activeTenant['smtp_password'] ?? '')?>" placeholder="••••••••••••" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase mb-1.5">Sender Email Address (From)</label>
                    <input type="email" name="from_email" value="<?=e($activeTenant['from_email'] ?? '')?>" placeholder="billing@onesol.ae" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
                </div>
                <div>
                    <label class="block text-xs font-extrabold text-slate-700 uppercase mb-1.5">Sender Name (From Name)</label>
                    <input type="text" name="from_name" value="<?=e($activeTenant['from_name'] ?? $activeTenant['name'])?>" placeholder="<?=e($activeTenant['name'])?> Accounts" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-between">
                <button type="submit" name="action" value="save_smtp" class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-black rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md">
                    <i class="fa-solid fa-floppy-disk mr-2"></i>Save SMTP Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Test Email Tool Card -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm h-fit">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 mb-4 flex items-center">
            <i class="fa-solid fa-paper-plane text-emerald-500 mr-2"></i>Test SMTP Delivery
        </h2>
        <p class="text-xs text-slate-500 mb-4">Send a live test email using your configured SMTP settings to verify host connection & SSL certificates.</p>

        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="test_smtp">
            <input type="hidden" name="smtp_host" value="<?=e($activeTenant['smtp_host'] ?? '')?>">
            <input type="hidden" name="smtp_port" value="<?=e((string)($activeTenant['smtp_port'] ?: 587))?>">
            <input type="hidden" name="smtp_encryption" value="<?=e($activeTenant['smtp_encryption'] ?? 'tls')?>">
            <input type="hidden" name="smtp_username" value="<?=e($activeTenant['smtp_username'] ?? '')?>">
            <input type="hidden" name="smtp_password" value="<?=e($activeTenant['smtp_password'] ?? '')?>">
            <input type="hidden" name="from_email" value="<?=e($activeTenant['from_email'] ?? '')?>">
            <input type="hidden" name="from_name" value="<?=e($activeTenant['from_name'] ?? '')?>">

            <div>
                <label class="block text-xs font-extrabold text-slate-700 uppercase mb-1.5">Recipient Email</label>
                <input type="email" name="test_email" value="admin@onesol.ae" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
            </div>

            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-emerald-500 text-xs font-extrabold rounded-xl text-emerald-700 bg-emerald-50 hover:bg-emerald-100 shadow-xs">
                <i class="fa-solid fa-vial mr-2"></i>Send Test Email Now
            </button>
        </form>
    </div>

</div>

<?php page_end(); ?>
