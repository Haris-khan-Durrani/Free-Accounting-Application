<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Handle API Key Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_key') {
    verify_csrf();
    $name = trim($_POST['name'] ?? 'Production API Key');
    $rawKey = 'onesol_live_' . bin2hex(random_bytes(16));
    $keyHash = hash('sha256', $rawKey);

    $st = $pdo->prepare("INSERT INTO api_keys (tenant_id, name, key_hash) VALUES (?, ?, ?)");
    $st->execute([$tid, $name, $keyHash]);

    log_audit($pdo, 'create_api_key', 'api_keys', (int)$pdo->lastInsertId(), "Created API key $name");
    flash('success', "New API Key generated successfully! Secret Key: $rawKey (Copy now, will not be shown again).");
    redirect('integrations.php');
}

$stKeys = $pdo->prepare("SELECT * FROM api_keys WHERE tenant_id = ? ORDER BY id DESC");
$stKeys->execute([$tid]);
$apiKeys = $stKeys->fetchAll();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$wooWebhookUrl = $baseUrl . '/api/v1/webhooks/woocommerce.php?tenant_id=' . $tid;
$shopifyWebhookUrl = $baseUrl . '/api/v1/webhooks/shopify.php?tenant_id=' . $tid;

page_start('E-Commerce & REST API Integrations');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Integrations & REST API Settings</h1>
        <p class="mt-1 text-sm text-slate-500">Sync WooCommerce orders, Shopify webhooks, and custom REST API endpoints for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
</div>

<!-- Webhooks Endpoints -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center space-x-3 mb-3">
            <div class="h-10 w-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-brands fa-wordpress font-bold"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-900">WooCommerce Webhook URL</h3>
                <p class="text-xs text-slate-500">Auto-create invoices when WooCommerce orders complete</p>
            </div>
        </div>
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 font-mono text-xs text-slate-700 break-all select-all">
            <?=e($wooWebhookUrl)?>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center space-x-3 mb-3">
            <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-brands fa-shopify"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-900">Shopify Webhook URL</h3>
                <p class="text-xs text-slate-500">Auto-sync Shopify sales & tax receipts</p>
            </div>
        </div>
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 font-mono text-xs text-slate-700 break-all select-all">
            <?=e($shopifyWebhookUrl)?>
        </div>
    </div>
</div>

<!-- API Keys Table Card -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">REST API v1 Access Keys</h2>
        <form method="post" class="inline flex items-center space-x-2">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="create_key">
            <input type="text" name="name" placeholder="Key Label (e.g. Mobile App)" required class="rounded-xl border-slate-300 text-xs px-3 py-1.5">
            <button type="submit" class="px-3.5 py-1.5 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800">+ Generate Key</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Key Label</th>
                    <th class="px-6 py-3.5">Status</th>
                    <th class="px-6 py-3.5">Created Date</th>
                    <th class="px-6 py-3.5">Last Used</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($apiKeys)): ?>
                    <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No REST API keys created yet. Generate a key to connect external apps.</td></tr>
                <?php endif; ?>
                <?php foreach ($apiKeys as $k): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 font-bold text-slate-900"><?=e($k['name'])?></td>
                        <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">ACTIVE</span></td>
                        <td class="px-6 py-4 text-xs text-slate-500"><?=e(date('d M Y', strtotime($k['created_at'])))?></td>
                        <td class="px-6 py-4 text-xs text-slate-500"><?=$k['last_used_at'] ? e(date('d M Y H:i', strtotime($k['last_used_at']))) : 'Never'?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php page_end(); ?>
