<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo  = $GLOBALS['pdo'];
$tid  = tenant_id();
$uid  = (int)($_SESSION['user_id'] ?? 0);

// ── Scope Definitions ──────────────────────────────────────────────
$SCOPE_DEFS = [
    'invoices:read'   => ['label' => 'Read Invoices',      'icon' => 'fa-file-invoice',    'color' => 'amber',   'desc' => 'List and view all invoices.'],
    'invoices:write'  => ['label' => 'Create Invoices',    'icon' => 'fa-file-circle-plus', 'color' => 'amber',   'desc' => 'Create and update invoices.'],
    'clients:read'    => ['label' => 'Read Clients',       'icon' => 'fa-users',            'color' => 'blue',    'desc' => 'List and view client records.'],
    'clients:write'   => ['label' => 'Create Clients',     'icon' => 'fa-user-plus',        'color' => 'blue',    'desc' => 'Create and update client profiles.'],
    'payments:write'  => ['label' => 'Record Payments',    'icon' => 'fa-money-bill-wave',  'color' => 'emerald', 'desc' => 'Post payment records against invoices.'],
    'reports:read'    => ['label' => 'Read Reports',       'icon' => 'fa-chart-line',       'color' => 'rose',    'desc' => 'Access financial reports and ledger data.'],
    'tenants:write'   => ['label' => 'Manage Sub-Accounts','icon' => 'fa-sitemap',          'color' => 'purple',  'desc' => 'Create and configure workspace tenants.'],
];

// ── POST Actions ────────────────────────────────────────────────────
$newKeyPlain = null; // Shown once after creation
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // CREATE KEY
    if ($action === 'create_key') {
        $name      = trim($_POST['key_name'] ?? '');
        $scopesRaw = $_POST['scopes'] ?? [];
        $expiresAt = $_POST['expires_at'] ?? null;

        if (!$name) {
            $flash_error = 'Key name is required.';
        } else {
            // Validate scopes
            $validScopes = array_filter($scopesRaw, fn($s) => array_key_exists($s, $SCOPE_DEFS));
            if (empty($validScopes)) {
                $flash_error = 'Select at least one permission scope.';
            } else {
                // Generate key: os_live_ + 40 hex chars
                $rawKey    = 'os_live_' . bin2hex(random_bytes(20));
                $keyHash   = hash('sha256', $rawKey);
                $keyPrefix = substr($rawKey, 0, 16) . '...';
                $expiry    = (!empty($expiresAt)) ? $expiresAt : null;

                $st = $pdo->prepare("INSERT INTO api_keys
                    (tenant_id, created_by_user_id, name, key_hash, key_prefix, scopes, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $st->execute([
                    $tid, $uid, $name, $keyHash, $keyPrefix,
                    json_encode(array_values($validScopes)),
                    $expiry
                ]);
                $newKeyPlain = $rawKey;
                flash('success', "API key '<strong>" . htmlspecialchars($name) . "</strong>' created. Copy it now — it won't be shown again.");
            }
        }
    }

    // REVOKE KEY
    if ($action === 'revoke_key') {
        $keyId = (int)($_POST['key_id'] ?? 0);
        $st = $pdo->prepare("UPDATE api_keys SET is_active = 0, revoked_at = NOW() WHERE id = ? AND tenant_id = ?");
        $st->execute([$keyId, $tid]);
        flash('success', 'API key revoked successfully.');
        redirect('api_keys');
    }

    // DELETE KEY
    if ($action === 'delete_key') {
        $keyId = (int)($_POST['key_id'] ?? 0);
        $st = $pdo->prepare("DELETE FROM api_keys WHERE id = ? AND tenant_id = ? AND is_active = 0");
        $st->execute([$keyId, $tid]);
        flash('success', 'Revoked key deleted permanently.');
        redirect('api_keys');
    }
}

// Load all keys for this tenant
$keys = $pdo->prepare("SELECT * FROM api_keys WHERE tenant_id = ? ORDER BY created_at DESC");
$keys->execute([$tid]);
$apiKeys = $keys->fetchAll();

$activeCount  = count(array_filter($apiKeys, fn($k) => $k['is_active']));
$revokedCount = count(array_filter($apiKeys, fn($k) => !$k['is_active']));

page_start('API Key Manager — Scoped Access & Expiry');
?>

<style>
.scope-toggle:checked + label { background: var(--scope-bg); border-color: var(--scope-border); }
.scope-toggle { display: none; }
.key-badge-active  { background:#dcfce7; color:#166534; }
.key-badge-expired { background:#fef9c3; color:#854d0e; }
.key-badge-revoked { background:#fee2e2; color:#991b1b; }
</style>

<!-- Page Header -->
<div class="sm:flex sm:items-start sm:justify-between mb-6 gap-4">
    <div>
        <div class="flex items-center space-x-2 mb-1">
            <a href="api_playground" class="text-xs text-slate-400 hover:text-slate-600 font-semibold">← API Playground</a>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">🔑 API Key Manager</h1>
        <p class="mt-1 text-xs text-slate-500">Create scoped access keys, set expiry dates, and revoke keys instantly. Keys are stored as secure SHA-256 hashes and shown only once.</p>
    </div>
    <button onclick="document.getElementById('create-key-modal').classList.remove('hidden')"
        class="mt-4 sm:mt-0 flex-shrink-0 inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-extrabold text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg transition-all">
        <i class="fa-solid fa-plus mr-2"></i>+ Generate New API Key
    </button>
</div>

<?php
$flash = get_flash();
if ($flash): ?>
<div class="rounded-xl px-4 py-3 mb-5 text-sm font-semibold <?= $flash['type']==='success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
    <i class="fa-solid fa-<?= $flash['type']==='success' ? 'circle-check text-emerald-500' : 'triangle-exclamation text-rose-500' ?> mr-2"></i>
    <?= $flash['message'] ?>
</div>
<?php endif;
if ($flash_error): ?>
<div class="rounded-xl px-4 py-3 mb-5 text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200">
    <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-2"></i><?= e($flash_error) ?>
</div>
<?php endif; ?>

<!-- NEW KEY REVEAL BANNER (shown once) -->
<?php if ($newKeyPlain): ?>
<div class="bg-slate-950 border-2 border-amber-500/50 rounded-2xl p-6 mb-6 shadow-xl shadow-amber-500/10">
    <div class="flex items-start space-x-4">
        <div class="h-10 w-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-key text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-amber-400 font-extrabold text-sm uppercase tracking-wider mb-1">
                ⚠️ Copy Your New API Key Now — It Will Not Be Shown Again
            </div>
            <div class="flex items-center space-x-2 mt-2">
                <code id="new-api-key" class="bg-slate-800 text-emerald-300 font-mono text-sm px-4 py-2.5 rounded-xl flex-1 min-w-0 overflow-x-auto whitespace-nowrap border border-slate-700">
                    <?= e($newKeyPlain) ?>
                </code>
                <button onclick="copyNewKey()" id="copy-new-btn"
                    class="flex-shrink-0 inline-flex items-center px-4 py-2.5 rounded-xl text-xs font-extrabold text-slate-950 bg-amber-400 hover:bg-amber-300 transition-all">
                    <i class="fa-solid fa-copy mr-1.5"></i>Copy
                </button>
            </div>
            <p class="text-slate-500 text-xs mt-2">Use this key in your API requests: <code class="text-slate-400">X-API-Key: <?= e(substr($newKeyPlain,0,20)) ?>...</code></p>
        </div>
    </div>
</div>
<script>
function copyNewKey() {
    const key = document.getElementById('new-api-key').innerText.trim();
    navigator.clipboard.writeText(key).then(() => {
        const btn = document.getElementById('copy-new-btn');
        btn.innerHTML = '<i class="fa-solid fa-check mr-1.5"></i>Copied!';
        btn.classList.replace('bg-amber-400','bg-emerald-400');
        setTimeout(() => { btn.innerHTML='<i class="fa-solid fa-copy mr-1.5"></i>Copy'; btn.classList.replace('bg-emerald-400','bg-amber-400'); }, 2500);
    });
}
</script>
<?php endif; ?>

<!-- Stats Bar -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm text-center">
        <div class="text-2xl font-black text-slate-900"><?= count($apiKeys) ?></div>
        <div class="text-xs font-semibold text-slate-500 mt-0.5">Total Keys</div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-emerald-200 shadow-sm text-center">
        <div class="text-2xl font-black text-emerald-700"><?= $activeCount ?></div>
        <div class="text-xs font-semibold text-emerald-600 mt-0.5">Active</div>
    </div>
    <div class="bg-white rounded-2xl p-4 border border-rose-200 shadow-sm text-center">
        <div class="text-2xl font-black text-rose-700"><?= $revokedCount ?></div>
        <div class="text-xs font-semibold text-rose-600 mt-0.5">Revoked</div>
    </div>
</div>

<!-- API Keys Table -->
<?php if (empty($apiKeys)): ?>
<div class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center shadow-sm">
    <div class="h-14 w-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-key text-slate-400 text-2xl"></i>
    </div>
    <h3 class="font-extrabold text-slate-800 text-lg mb-1">No API Keys Yet</h3>
    <p class="text-slate-500 text-sm mb-5">Create your first scoped API key to integrate external systems with this workspace.</p>
    <button onclick="document.getElementById('create-key-modal').classList.remove('hidden')"
        class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-extrabold text-white bg-gradient-to-r from-amber-500 to-amber-600 shadow-md">
        <i class="fa-solid fa-plus mr-2"></i>Generate First API Key
    </button>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <span class="font-extrabold text-slate-800 text-sm flex items-center">
            <i class="fa-solid fa-key text-amber-500 mr-2"></i>Your API Keys
        </span>
        <span class="text-xs text-slate-400"><?= count($apiKeys) ?> key<?= count($apiKeys)!==1?'s':'' ?></span>
    </div>
    <div class="divide-y divide-slate-100">
        <?php foreach ($apiKeys as $k):
            $scopes     = json_decode($k['scopes'], true) ?: [];
            $isExpired  = $k['expires_at'] && strtotime($k['expires_at']) < time();
            $isRevoked  = !$k['is_active'];
            $statusClass = $isRevoked ? 'key-badge-revoked' : ($isExpired ? 'key-badge-expired' : 'key-badge-active');
            $statusLabel = $isRevoked ? 'Revoked' : ($isExpired ? 'Expired' : 'Active');
        ?>
        <div class="px-6 py-5 <?= $isRevoked ? 'bg-slate-50/50 opacity-75' : '' ?>">
            <div class="sm:flex sm:items-start sm:justify-between gap-4">
                <!-- Key Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-2.5 mb-2 flex-wrap gap-y-1">
                        <span class="font-extrabold text-slate-900 text-sm"><?= e($k['name']) ?></span>
                        <span class="px-2 py-0.5 rounded-full text-2xs font-extrabold <?= $statusClass ?>">
                            <?= $statusLabel ?>
                        </span>
                        <?php if ($k['expires_at'] && !$isRevoked): ?>
                        <span class="px-2 py-0.5 rounded-full text-2xs font-semibold bg-slate-100 text-slate-600">
                            <i class="fa-solid fa-calendar-alt mr-1"></i>
                            Expires <?= date('d M Y', strtotime($k['expires_at'])) ?>
                        </span>
                        <?php elseif (!$k['expires_at'] && !$isRevoked): ?>
                        <span class="px-2 py-0.5 rounded-full text-2xs font-semibold bg-blue-50 text-blue-700">
                            <i class="fa-solid fa-infinity mr-1"></i>No Expiry
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- Key Prefix -->
                    <div class="flex items-center space-x-2 mb-3">
                        <code class="bg-slate-100 text-slate-600 font-mono text-xs px-3 py-1 rounded-lg border border-slate-200">
                            <?= e($k['key_prefix']) ?>••••••••••••••••••••••••••
                        </code>
                        <span class="text-2xs text-slate-400">Full key stored as SHA-256 hash</span>
                    </div>

                    <!-- Scopes -->
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <?php
                        $badgeStyles = [
                            'amber'   => 'bg-amber-50 text-amber-800 border-amber-200',
                            'blue'    => 'bg-blue-50 text-blue-800 border-blue-200',
                            'emerald' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            'rose'    => 'bg-rose-50 text-rose-800 border-rose-200',
                            'purple'  => 'bg-purple-50 text-purple-800 border-purple-200',
                        ];
                        foreach ($scopes as $scope):
                            $def = $SCOPE_DEFS[$scope] ?? null;
                            if (!$def) continue;
                            $badge = $badgeStyles[$def['color']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                        ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-2xs font-extrabold border <?= $badge ?>">
                            <i class="fa-solid <?= $def['icon'] ?> mr-1 text-[9px]"></i><?= $def['label'] ?>
                        </span>
                        <?php endforeach; ?>
                    </div>

                    <!-- Meta -->
                    <div class="flex items-center space-x-4 text-[10px] text-slate-400 font-semibold">
                        <span><i class="fa-solid fa-clock mr-1"></i>Created <?= date('d M Y', strtotime($k['created_at'])) ?></span>
                        <?php if ($k['last_used_at']): ?>
                        <span><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>Last used <?= date('d M Y H:i', strtotime($k['last_used_at'])) ?></span>
                        <?php else: ?>
                        <span class="text-slate-300"><i class="fa-solid fa-minus mr-1"></i>Never used</span>
                        <?php endif; ?>
                        <?php if ($k['revoked_at']): ?>
                        <span class="text-rose-400"><i class="fa-solid fa-ban mr-1"></i>Revoked <?= date('d M Y', strtotime($k['revoked_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center space-x-2 mt-4 sm:mt-0 flex-shrink-0">
                    <?php if ($k['is_active'] && !$isExpired): ?>
                    <form method="post" onsubmit="return confirm('Revoke this API key? All applications using it will lose access immediately.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="revoke_key">
                        <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                        <button type="submit" class="inline-flex items-center px-3.5 py-2 rounded-xl text-xs font-extrabold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition-all">
                            <i class="fa-solid fa-ban mr-1.5"></i>Revoke
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="post" onsubmit="return confirm('Permanently delete this revoked key record?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_key">
                        <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
                        <button type="submit" class="inline-flex items-center px-3.5 py-2 rounded-xl text-xs font-extrabold text-slate-600 bg-slate-100 border border-slate-200 hover:bg-slate-200 transition-all">
                            <i class="fa-solid fa-trash mr-1.5"></i>Delete
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Security Note -->
<div class="mt-6 bg-slate-900 rounded-2xl p-5 border border-slate-800">
    <h4 class="text-white font-extrabold text-xs uppercase tracking-wider mb-3 flex items-center">
        <i class="fa-solid fa-shield-halved text-amber-400 mr-2"></i>API Key Security Best Practices
    </h4>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-400">
        <div class="flex items-start space-x-2"><i class="fa-solid fa-lock text-emerald-400 mt-0.5 flex-shrink-0"></i><span>Never expose API keys in client-side JavaScript or public repositories. Use environment variables in production.</span></div>
        <div class="flex items-start space-x-2"><i class="fa-solid fa-clock text-amber-400 mt-0.5 flex-shrink-0"></i><span>Set an expiry date on all keys. Short-lived keys (30–90 days) reduce risk from compromised credentials.</span></div>
        <div class="flex items-start space-x-2"><i class="fa-solid fa-list-check text-blue-400 mt-0.5 flex-shrink-0"></i><span>Use the minimum scopes required for each integration. A reporting tool needs only <code class="text-slate-300">reports:read</code>.</span></div>
    </div>
</div>

<!-- ══════════════════════════════════════════ -->
<!-- MODAL: Create API Key -->
<!-- ══════════════════════════════════════════ -->
<div id="create-key-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center z-50 hidden p-3 sm:p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh] my-auto">

        <!-- Modal Header (Fixed Top) -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/80 flex-shrink-0">
            <div class="flex items-center space-x-3">
                <div class="h-9 w-9 rounded-2xl bg-amber-500/15 text-amber-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-key text-base"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-slate-900 text-base leading-tight">Generate New API Key</h3>
                    <p class="text-2xs text-slate-500">Configure access scopes and expiration policy</p>
                </div>
            </div>
            <button onclick="document.getElementById('create-key-modal').classList.add('hidden')" class="h-8 w-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-700 flex items-center justify-center text-lg font-bold transition-all">×</button>
        </div>

        <!-- Modal Form (Scrollable Body) -->
        <form method="post" class="flex flex-col flex-1 overflow-hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_key">

            <div class="px-6 py-5 overflow-y-auto space-y-5 flex-1" style="max-height: calc(90vh - 130px);">
                <!-- Key Name -->
                <div>
                    <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1.5">
                        Key Name / Application Label <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="key_name" required maxlength="100"
                        placeholder="e.g. Zapier Automated Invoicing, n8n CRM Sync, Mobile App..."
                        class="w-full rounded-2xl border border-slate-300 bg-slate-50/70 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <p class="text-2xs text-slate-400 mt-1">Identifies which external service or application is using this key.</p>
                </div>

                <!-- Permission Scopes (2-Column Grid) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider">
                            Permission Scopes <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-2xs text-slate-400">Select at least one scope</span>
                    </div>
                    <p class="text-2xs text-slate-500 mb-3">Enforce least privilege access by granting only required permissions.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <?php
                        $colorClasses = [
                            'amber'   => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'border' => 'hover:border-amber-400 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/80'],
                            'blue'    => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'border' => 'hover:border-blue-400 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/80'],
                            'emerald' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'border' => 'hover:border-emerald-400 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/80'],
                            'rose'    => ['bg' => 'bg-rose-100',    'text' => 'text-rose-700',    'border' => 'hover:border-rose-400 has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/80'],
                            'purple'  => ['bg' => 'bg-purple-100',  'text' => 'text-purple-700',  'border' => 'hover:border-purple-400 has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50/80'],
                        ];
                        foreach ($SCOPE_DEFS as $scope => $def):
                            $c = $def['color'];
                            $theme = $colorClasses[$c] ?? $colorClasses['amber'];
                        ?>
                        <label class="flex items-start space-x-3 p-3 rounded-2xl border-2 border-slate-200/80 bg-slate-50/50 cursor-pointer <?= $theme['border'] ?> transition-all select-none">
                            <input type="checkbox" name="scopes[]" value="<?= $scope ?>" class="mt-0.5 rounded border-slate-300 text-amber-500 focus:ring-amber-500/20 w-4 h-4 flex-shrink-0">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center space-x-1.5 mb-0.5">
                                    <div class="h-5 w-5 rounded-md <?= $theme['bg'] ?> flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid <?= $def['icon'] ?> <?= $theme['text'] ?> text-[10px]"></i>
                                    </div>
                                    <span class="font-extrabold text-slate-900 text-xs truncate"><?= $def['label'] ?></span>
                                </div>
                                <p class="text-[11px] text-slate-500 leading-tight"><?= $def['desc'] ?></p>
                                <code class="text-[9px] font-mono text-slate-400 mt-1 block"><?= $scope ?></code>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Expiry Date -->
                <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-200/80">
                    <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1.5">
                        Expiration Policy
                        <span class="text-slate-400 normal-case font-normal">(Optional)</span>
                    </label>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <input type="date" name="expires_at" id="expires_at_input" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                            class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                        <div class="flex items-center space-x-1.5 text-xs">
                            <span class="text-2xs font-extrabold text-slate-400 uppercase mr-1">Presets:</span>
                            <button type="button" onclick="setExpiry(30)"  class="px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 hover:border-amber-400 hover:bg-amber-50 font-extrabold text-slate-700 text-2xs transition-all">30 Days</button>
                            <button type="button" onclick="setExpiry(90)"  class="px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 hover:border-amber-400 hover:bg-amber-50 font-extrabold text-slate-700 text-2xs transition-all">90 Days</button>
                            <button type="button" onclick="setExpiry(365)" class="px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 hover:border-amber-400 hover:bg-amber-50 font-extrabold text-slate-700 text-2xs transition-all">1 Year</button>
                            <button type="button" onclick="setExpiry(0)"   class="px-2.5 py-1.5 rounded-xl bg-white border border-slate-200 hover:border-rose-400 hover:bg-rose-50 font-extrabold text-slate-500 text-2xs transition-all">Never</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer (Fixed Bottom) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between px-6 py-4 bg-slate-50 border-t border-slate-100 flex-shrink-0 gap-3">
                <div class="flex items-center space-x-2 text-xs font-semibold text-amber-800 bg-amber-100/70 border border-amber-200 px-3 py-1.5 rounded-xl">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                    <span>Shown <strong>once only</strong> after generation.</span>
                </div>
                <div class="flex items-center space-x-3 justify-end">
                    <button type="button" onclick="document.getElementById('create-key-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200/60 transition-all">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md shadow-amber-500/20 transition-all flex items-center">
                        <i class="fa-solid fa-key mr-2"></i>Generate API Key
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function setExpiry(days) {
    const input = document.getElementById('expires_at_input');
    if (!input) return;
    if (days === 0) {
        input.value = '';
        return;
    }
    const d = new Date();
    d.setDate(d.getDate() + days);
    input.value = d.toISOString().split('T')[0];
}
// Close modal on backdrop click
document.getElementById('create-key-modal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>


<?php page_end(); ?>
