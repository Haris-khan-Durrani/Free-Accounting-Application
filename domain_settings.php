<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Fetch all registered tenants for superadmin/owner domain workspace switcher
$stAllTenants = $pdo->query("SELECT id, name, code FROM tenants ORDER BY name ASC");
$allTenants = $stAllTenants->fetchAll();

$targetTenantId = (int)($_GET['tenant_id'] ?? $_POST['target_tenant_id'] ?? $tid);
if (!has_role(['owner', 'admin']) && $targetTenantId !== $tid) {
    $targetTenantId = $tid;
}

$targetTenantObj = null;
foreach ($allTenants as $at) {
    if ($at['id'] == $targetTenantId) {
        $targetTenantObj = $at;
        break;
    }
}
$targetTenantName = $targetTenantObj ? $targetTenantObj['name'] : tenant()['name'];

// Ensure columns exist in database
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN custom_domain VARCHAR(190) NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN domain_verified TINYINT(1) NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE branding_settings ADD COLUMN remove_powered_by TINYINT(1) NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_domain') {
    verify_csrf();
    $customDomain = trim($_POST['custom_domain'] ?? '');
    $customDomain = preg_replace('#^https?://#i', '', $customDomain);
    $customDomain = preg_replace('#/.*$#', '', $customDomain);
    $customDomain = strtolower($customDomain);
    
    $removePoweredBy = isset($_POST['remove_powered_by']) ? 1 : 0;

    $st = $pdo->prepare("UPDATE branding_settings SET custom_domain = ?, remove_powered_by = ? WHERE tenant_id = ?");
    $st->execute([$customDomain, $removePoweredBy, $targetTenantId]);

    \Core\Tenant::forgetCache($targetTenantId);

    log_audit($pdo, 'update_domain_settings', 'branding_settings', $targetTenantId, "Updated whitelabel custom domain: $customDomain for workspace #$targetTenantId");
    $message = "Whitelabel domain configuration for '$targetTenantName' updated successfully.";
}

$brand = \Core\Branding::get($pdo, $targetTenantId);
$customDomain = $brand['custom_domain'] ?? '';
$isVerified = !empty($brand['domain_verified']);
$removePoweredBy = !empty($brand['remove_powered_by']);
$serverHost = $_SERVER['HTTP_HOST'] ?? 'app.onesol.ae';

require __DIR__ . '/layout.php';
page_start('Whitelabel Domain Settings');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 font-black text-xs uppercase tracking-wider">Enterprise Whitelabel</span>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Custom Domain & Whitelabel Branding</h1>
        </div>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Bind your custom domain or subdomain for workspace <strong><?=e($targetTenantName)?></strong>.</p>
    </div>

    <?php if (count($allTenants) > 1 && has_role(['owner', 'admin'])): ?>
        <div class="mt-4 sm:mt-0 flex items-center space-x-2 bg-white px-3.5 py-2 rounded-xl border border-slate-200 shadow-sm">
            <label class="text-2xs font-extrabold text-slate-500 uppercase">Target Workspace:</label>
            <select onchange="location.href='domain_settings.php?tenant_id=' + this.value" class="rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-extrabold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                <?php foreach ($allTenants as $at): ?>
                    <option value="<?=$at['id']?>" <?=$at['id'] == $targetTenantId ? 'selected' : ''?>>
                        🏢 <?=e($at['name'])?> (code: <?=e($at['code'])?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 text-xs font-bold flex items-center space-x-2">
        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
        <span><?=e($message)?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Domain Configuration Form -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-6">
            <form method="post" id="domainForm" class="space-y-6">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_domain">
                <input type="hidden" name="target_tenant_id" value="<?=$targetTenantId?>">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Whitelabel Custom Domain / Subdomain <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative rounded-xl shadow-xs">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 font-semibold text-xs">
                            https://
                        </div>
                        <input type="text" name="custom_domain" id="custom_domain_input" value="<?=e($customDomain)?>" placeholder="billing.yourcompany.com" class="w-full pl-16 pr-36 py-3 border border-slate-300 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        
                        <div class="absolute inset-y-1.5 right-1.5 flex items-center space-x-2">
                            <button type="button" id="btnTestDns" onclick="testDomainDns()" class="px-3.5 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg text-xs font-black shadow-xs transition-all flex items-center space-x-1.5">
                                <i class="fa-solid fa-bolt text-amber-300"></i>
                                <span>Test & Verify DNS</span>
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Enter your custom domain without <code>http://</code> or <code>https://</code>.</p>
                </div>

                <!-- Real-time Test Result Diagnosis Box -->
                <div id="dnsResultBox" class="hidden p-4 rounded-xl text-xs font-semibold space-y-2 transition-all">
                    <!-- Populated via JavaScript -->
                </div>

                <!-- Verification Status Card -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div id="statusDot" class="w-3.5 h-3.5 rounded-full <?= $isVerified ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300' ?>"></div>
                        <div>
                            <div class="text-xs font-bold text-slate-900" id="statusTitle"><?= $isVerified ? 'Domain Connected & Verified' : 'DNS Verification Required' ?></div>
                            <div class="text-2xs text-slate-500" id="statusSubtext"><?= $isVerified ? 'SSL Certificate Active (TLS 1.3)' : 'Click Test & Verify DNS above to validate CNAME' ?></div>
                        </div>
                    </div>
                    <span id="statusBadge" class="px-2.5 py-1 rounded-full text-3xs font-black uppercase tracking-wider <?= $isVerified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                        <?= $isVerified ? 'VERIFIED & ACTIVE' : 'UNVERIFIED' ?>
                    </span>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="remove_powered_by" value="1" <?= $removePoweredBy ? 'checked' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4">
                        <div>
                            <span class="text-xs font-extrabold text-slate-900">Remove "Powered by OneSol" Watermark</span>
                            <p class="text-2xs text-slate-500">Hides SaaS vendor branding on client invoices and payment portals.</p>
                        </div>
                    </label>

                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-md transition-all">
                        Save Domain Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DNS Setup Guide Column -->
    <div class="space-y-6">
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-2xl p-6 border border-slate-800 shadow-xl space-y-4">
            <div class="flex items-center space-x-3 text-amber-400">
                <i class="fa-solid fa-network-wired text-xl"></i>
                <h3 class="text-sm font-extrabold tracking-tight">DNS CNAME Setup Instructions</h3>
            </div>
            
            <p class="text-xs text-slate-300 leading-relaxed">
                Log into your domain registrar (GoDaddy, Cloudflare, Namecheap) and add the following <strong>CNAME Record</strong>:
            </p>

            <div class="bg-slate-950/80 rounded-xl p-3.5 border border-slate-800/80 font-mono text-2xs space-y-2">
                <div class="flex justify-between items-center text-slate-400">
                    <span>RECORD TYPE:</span>
                    <strong class="text-amber-400">CNAME</strong>
                </div>
                <div class="flex justify-between items-center text-slate-400">
                    <span>HOST / NAME:</span>
                    <strong class="text-white">billing</strong> (or subdomain)
                </div>
                <div class="flex justify-between items-center text-slate-400">
                    <span>TARGET / POINTS TO:</span>
                    <strong class="text-emerald-400"><?=e($serverHost)?></strong>
                </div>
                <div class="flex justify-between items-center text-slate-400">
                    <span>TTL:</span>
                    <strong class="text-slate-300">Auto / 3600</strong>
                </div>
            </div>

            <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-3 text-2xs text-blue-200 flex items-start space-x-2">
                <i class="fa-solid fa-lightbulb text-amber-400 text-xs mt-0.5"></i>
                <span>After saving your DNS record, click the <strong>Test & Verify DNS</strong> button to validate instant propagation.</span>
            </div>
        </div>
    </div>
</div>

<script>
function testDomainDns() {
    const domainInput = document.getElementById('custom_domain_input').value.trim();
    const btn = document.getElementById('btnTestDns');
    const resultBox = document.getElementById('dnsResultBox');

    if (!domainInput) {
        alert('Please enter a domain or subdomain to test.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-amber-300"></i> <span>Testing DNS...</span>';

    resultBox.classList.remove('hidden');
    resultBox.className = 'p-4 rounded-xl text-xs font-semibold bg-blue-50 border border-blue-200 text-blue-900 space-y-1';
    resultBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Querying global DNS servers for ' + domainInput + '...';

    const formData = new FormData();
    formData.append('domain', domainInput);

    fetch('domain_test_ajax', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt text-amber-300"></i> <span>Test & Verify DNS</span>';

        if (data.status === 'verified') {
            resultBox.className = 'p-4 rounded-xl text-xs font-semibold bg-emerald-50 border border-emerald-300 text-emerald-900 space-y-1';
            resultBox.innerHTML = `
                <div class="flex items-center space-x-2 text-emerald-700 font-extrabold text-sm mb-1">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                    <span>DNS VERIFICATION SUCCESSFUL!</span>
                </div>
                <div>${data.message}</div>
                <div class="text-2xs text-emerald-600 font-mono mt-1">Resolved IP: ${data.resolved_ip} | CNAME Target: ${data.cname_target}</div>
            `;
            
            document.getElementById('statusDot').className = 'w-3.5 h-3.5 rounded-full bg-emerald-500 animate-pulse';
            document.getElementById('statusTitle').innerText = 'Domain Connected & Verified';
            document.getElementById('statusSubtext').innerText = 'SSL Certificate Active (TLS 1.3)';
            document.getElementById('statusBadge').className = 'px-2.5 py-1 rounded-full text-3xs font-black uppercase tracking-wider bg-emerald-100 text-emerald-800';
            document.getElementById('statusBadge').innerText = 'VERIFIED & ACTIVE';
        } else {
            resultBox.className = 'p-4 rounded-xl text-xs font-semibold bg-rose-50 border border-rose-300 text-rose-900 space-y-1';
            resultBox.innerHTML = `
                <div class="flex items-center space-x-2 text-rose-700 font-extrabold text-sm mb-1">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                    <span>DNS VERIFICATION PENDING</span>
                </div>
                <div>${data.message}</div>
                <div class="text-2xs text-rose-600 font-mono mt-1">Point CNAME host to: ${data.server_ip || 'app.onesol.ae'}</div>
            `;

            document.getElementById('statusDot').className = 'w-3.5 h-3.5 rounded-full bg-amber-500';
            document.getElementById('statusTitle').innerText = 'DNS Propagation Pending';
            document.getElementById('statusSubtext').innerText = 'CNAME record has not resolved yet.';
            document.getElementById('statusBadge').className = 'px-2.5 py-1 rounded-full text-3xs font-black uppercase tracking-wider bg-amber-100 text-amber-800';
            document.getElementById('statusBadge').innerText = 'PENDING DNS';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt text-amber-300"></i> <span>Test & Verify DNS</span>';
        resultBox.className = 'p-4 rounded-xl text-xs font-semibold bg-rose-50 border border-rose-300 text-rose-900';
        resultBox.innerText = 'Server request failed: ' + err.message;
    });
}
</script>

<?php page_end(); ?>
