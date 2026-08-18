<?php
require __DIR__ . '/bootstrap.php';
require_login();

use Services\PluginEngine;

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$msg = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_plugin'])) {
    if (!empty($_FILES['plugin_zip']['name'])) {
        $res = PluginEngine::uploadPluginZip($_FILES['plugin_zip']);
        if ($res['success']) {
            $msg = $res['message'];
            log_audit($pdo, 'upload_plugin', 'plugins', null, "Uploaded plugin zip: {$res['slug']}");
        } else {
            $error = $res['error'];
        }
    } else {
        $error = 'Please select a valid .zip plugin file to upload.';
    }
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $slug = preg_replace('/[^a-z0-9_]/i', '', $_GET['slug'] ?? '');

    if ($action === 'activate' && $slug) {
        PluginEngine::activatePlugin($pdo, $tid, $slug);
        $msg = "Plugin '$slug' has been activated for " . tenant()['name'] . ".";
        log_audit($pdo, 'activate_plugin', 'plugins', null, "Activated plugin '$slug'");
    } elseif ($action === 'deactivate' && $slug) {
        PluginEngine::deactivatePlugin($pdo, $tid, $slug);
        $msg = "Plugin '$slug' has been deactivated for " . tenant()['name'] . ".";
        log_audit($pdo, 'deactivate_plugin', 'plugins', null, "Deactivated plugin '$slug'");
    } elseif ($action === 'delete' && $slug) {
        PluginEngine::deactivatePlugin($pdo, $tid, $slug);
        $dir = __DIR__ . "/plugins/{$slug}";
        if (is_dir($dir)) {
            // Recursive directory deletion
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir($dir);
        }
        $msg = "Plugin '$slug' deleted successfully.";
        log_audit($pdo, 'delete_plugin', 'plugins', null, "Deleted plugin '$slug'");
    } elseif ($action === 'create_sample') {
        $sampleSlug = PluginEngine::createSamplePlugin();
        $msg = "Developer sample plugin '$sampleSlug' generated in plugins/$sampleSlug!";
        log_audit($pdo, 'create_sample_plugin', 'plugins', null, "Generated starter sample plugin '$sampleSlug'");
    }
}

$availablePlugins = PluginEngine::getAvailablePlugins();
$activeSlugs = PluginEngine::getActivePluginSlugs($pdo, $tid);
$isSafeMode = isset($_GET['plugin_safe_mode']) && $_GET['plugin_safe_mode'] == '1';

require __DIR__ . '/layout.php';
page_start('Plug & Play Modular Plugins');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-purple-100 text-purple-800 font-black text-xs uppercase tracking-wider">Modular Extensions</span>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Plug & Play Extensions</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Upload and manage custom modular feature plugins for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>

    <div class="mt-4 sm:mt-0 flex items-center space-x-3">
        <button onclick="document.getElementById('devGuidePanel').classList.toggle('hidden')" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all">
            <i class="fa-solid fa-book-open mr-1.5 text-amber-400"></i>Plugin Developer Guide
        </button>
        <a href="plugins_admin?action=create_sample" class="inline-flex items-center px-4 py-2.5 bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-700 font-extrabold text-xs rounded-xl shadow-xs transition-all">
            <i class="fa-solid fa-code mr-1.5 text-purple-600"></i>Generate Starter Sample Plugin
        </a>
    </div>
</div>

<!-- Expandable Plugin Developer Guide -->
<div id="devGuidePanel" class="hidden mb-8 bg-slate-950 text-white rounded-2xl p-6 shadow-2xl border border-slate-800">
    <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-4">
        <div class="flex items-center space-x-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-black">
                <i class="fa-solid fa-book-bookmark"></i>
            </div>
            <div>
                <h2 class="text-base font-extrabold text-white">Modular Plugin Development & API Specification</h2>
                <p class="text-xs text-slate-400">Complete developer manual for extending OneSol Invoice Manager with custom plug-and-play features.</p>
            </div>
        </div>
        <button onclick="document.getElementById('devGuidePanel').classList.add('hidden')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
    </div>

    <!-- Tab Buttons -->
    <div class="flex space-x-2 border-b border-slate-800 pb-3 mb-6 overflow-x-auto">
        <button onclick="switchDevTab('tab-blueprint')" id="btn-tab-blueprint" class="dev-tab-btn px-4 py-2 rounded-xl text-xs font-bold bg-amber-500 text-slate-950 transition-all">🚀 4-Step Blueprint</button>
        <button onclick="switchDevTab('tab-hooks')" id="btn-tab-hooks" class="dev-tab-btn px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 text-slate-300 hover:text-white transition-all">🪝 System Hooks API</button>
        <button onclick="switchDevTab('tab-database')" id="btn-tab-database" class="dev-tab-btn px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 text-slate-300 hover:text-white transition-all">🗄️ Database & Schema</button>
        <button onclick="switchDevTab('tab-security')" id="btn-tab-security" class="dev-tab-btn px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 text-slate-300 hover:text-white transition-all">🛡️ Anti-Hacking Protection</button>
        <button onclick="switchDevTab('tab-examples')" id="btn-tab-examples" class="dev-tab-btn px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 text-slate-300 hover:text-white transition-all">💡 Code Snippets</button>
    </div>

    <!-- Tab 1: 4-Step Blueprint -->
    <div id="tab-blueprint" class="dev-tab-content space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
            <div class="bg-slate-900/90 p-4 rounded-xl border border-slate-800">
                <span class="w-6 h-6 rounded-full bg-amber-500 text-slate-950 font-black flex items-center justify-center text-3xs mb-2">1</span>
                <h4 class="font-extrabold text-white mb-1">Create Folder</h4>
                <p class="text-slate-400 text-3xs">Create a folder for your plugin (e.g. <code>my_custom_discount</code>).</p>
            </div>
            <div class="bg-slate-900/90 p-4 rounded-xl border border-slate-800">
                <span class="w-6 h-6 rounded-full bg-amber-500 text-slate-950 font-black flex items-center justify-center text-3xs mb-2">2</span>
                <h4 class="font-extrabold text-white mb-1">Create Manifest</h4>
                <p class="text-slate-400 text-3xs">Create <code>plugin.json</code> file with title, slug, author, version, and main file.</p>
            </div>
            <div class="bg-slate-900/90 p-4 rounded-xl border border-slate-800">
                <span class="w-6 h-6 rounded-full bg-amber-500 text-slate-950 font-black flex items-center justify-center text-3xs mb-2">3</span>
                <h4 class="font-extrabold text-white mb-1">Write PHP Code</h4>
                <p class="text-slate-400 text-3xs">Create <code>plugin.php</code> and use <code>PluginEngine::add_action()</code> or <code>add_filter()</code>.</p>
            </div>
            <div class="bg-slate-900/90 p-4 rounded-xl border border-slate-800">
                <span class="w-6 h-6 rounded-full bg-amber-500 text-slate-950 font-black flex items-center justify-center text-3xs mb-2">4</span>
                <h4 class="font-extrabold text-white mb-1">Zip & Upload</h4>
                <p class="text-slate-400 text-3xs">Compress into a <code>.zip</code> file and upload via the form on the left!</p>
            </div>
        </div>

        <div class="bg-slate-900 p-4 rounded-xl border border-slate-800 mt-4">
            <h4 class="text-xs font-bold text-amber-400 mb-2">Example `plugin.json` Manifest File:</h4>
            <pre class="text-3xs font-mono text-amber-300">{
  "name": "Custom Royalty Fee Calculator",
  "slug": "custom_royalty_fee",
  "version": "1.0.0",
  "author": "Acme Partner Corp",
  "description": "Calculates automatic custom percentage royalty fees on invoice save.",
  "main": "plugin.php"
}</pre>
        </div>
    </div>

    <!-- Tab 2: System Hooks API -->
    <div id="tab-hooks" class="dev-tab-content hidden space-y-4 text-xs">
        <p class="text-slate-300">The plugin engine provides Action and Filter hooks to intercept app execution cleanly:</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-3xs">
                <thead>
                    <tr class="bg-slate-900 text-slate-400 font-mono uppercase">
                        <th class="p-2.5 border-b border-slate-800">Hook Name</th>
                        <th class="p-2.5 border-b border-slate-800">Type</th>
                        <th class="p-2.5 border-b border-slate-800">Arguments</th>
                        <th class="p-2.5 border-b border-slate-800">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono">
                    <tr>
                        <td class="p-2.5 text-amber-400 font-bold">management_menu_items</td>
                        <td class="p-2.5 text-purple-400">Action</td>
                        <td class="p-2.5 text-slate-400">None</td>
                        <td class="p-2.5 text-slate-300">Injects custom links into Topbar Management mega menu.</td>
                    </tr>
                    <tr>
                        <td class="p-2.5 text-amber-400 font-bold">invoice_before_save</td>
                        <td class="p-2.5 text-emerald-400">Filter</td>
                        <td class="p-2.5 text-slate-400">$invoiceData (array)</td>
                        <td class="p-2.5 text-slate-300">Alters subtotal, discount, or tax rates before saving.</td>
                    </tr>
                    <tr>
                        <td class="p-2.5 text-amber-400 font-bold">invoice_after_save</td>
                        <td class="p-2.5 text-purple-400">Action</td>
                        <td class="p-2.5 text-slate-400">$pdo, $invoiceId, $tenantId</td>
                        <td class="p-2.5 text-slate-300">Triggers after invoice creation (webhooks/external API sync).</td>
                    </tr>
                    <tr>
                        <td class="p-2.5 text-amber-400 font-bold">payment_gateways_register</td>
                        <td class="p-2.5 text-emerald-400">Filter</td>
                        <td class="p-2.5 text-slate-400">$gateways (array)</td>
                        <td class="p-2.5 text-slate-300">Registers custom payment gateway classes.</td>
                    </tr>
                    <tr>
                        <td class="p-2.5 text-amber-400 font-bold">dashboard_widgets_top</td>
                        <td class="p-2.5 text-purple-400">Action</td>
                        <td class="p-2.5 text-slate-400">$pdo, $tenantId</td>
                        <td class="p-2.5 text-slate-300">Renders custom analytical metric cards on Dashboard.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 3: Database & Schema -->
    <div id="tab-database" class="dev-tab-content hidden space-y-4 text-xs">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-2">
                <h4 class="font-extrabold text-cyan-400">Mandatory Database Rules</h4>
                <ul class="list-disc list-inside space-y-1 text-3xs text-slate-300">
                    <li><b>Table Prefixing:</b> Custom tables MUST use prefix <code>plugin_{slug}_...</code> (e.g. <code>plugin_royalties_log</code>).</li>
                    <li><b>Multi-Tenant Scoping:</b> All queries MUST filter by <code>tenant_id = tenant_id()</code> to isolate subaccount data.</li>
                    <li><b>Mutation Guards:</b> Plugins CANNOT run <code>DROP TABLE</code> or <code>TRUNCATE</code> on core tables (<code>invoices</code>, <code>clients</code>, <code>users</code>).</li>
                </ul>
            </div>

            <div class="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-2">
                <h4 class="font-extrabold text-cyan-400">Creating Custom SQL Table Snippet</h4>
                <pre class="text-3xs font-mono text-emerald-300 overflow-x-auto">$pdo = $GLOBALS['pdo'];
$pdo->exec("
    CREATE TABLE IF NOT EXISTS plugin_royalty_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        invoice_id INT NOT NULL,
        fee DECIMAL(15,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");</pre>
            </div>
        </div>
    </div>

    <!-- Tab 4: Anti-Hacking & Core Protection -->
    <div id="tab-security" class="dev-tab-content hidden space-y-4 text-xs">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-2">
                <h4 class="font-extrabold text-rose-400">7 Active Defense Protection Layers</h4>
                <ul class="list-disc list-inside space-y-1 text-3xs text-slate-300">
                    <li><b class="text-white">Static Malware Scanner:</b> Scans PHP files in uploaded .zip packages for malicious constructs (<code>eval()</code>, <code>base64_decode()</code>, <code>system()</code>, <code>exec()</code>, <code>shell_exec()</code>, <code>passthru()</code>).</li>
                    <li><b class="text-white">Extension Whitelist:</b> Restricts uploads strictly to safe extensions (<code>.php</code>, <code>.json</code>, <code>.css</code>, <code>.js</code>, <code>.png</code>, <code>.jpg</code>, <code>.svg</code>). Blocks <code>.phtml</code>, <code>.exe</code>, <code>.bat</code>, <code>.sh</code>.</li>
                    <li><b class="text-white">Path Traversal Block:</b> Rejects zip archives with relative path overrides (<code>../</code>, <code>..\</code>).</li>
                    <li><b class="text-white">Filesystem Lockdown:</b> Auto-generates protective <code>.htaccess</code> inside <code>plugins/</code> blocking direct standalone web script execution.</li>
                </ul>
            </div>

            <div class="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-2">
                <h4 class="font-extrabold text-rose-400">Multi-Tenant Sandbox & Circuit Breaker</h4>
                <ul class="list-disc list-inside space-y-1 text-3xs text-slate-300">
                    <li><b class="text-white">Throwable Sandbox:</b> Traps syntax/runtime exceptions silently inside <code>try-catch \Throwable</code> so plugins never crash the core app UI.</li>
                    <li><b class="text-white">Auto Circuit Breaker:</b> Automatically deactivates buggy plugins upon detecting a runtime error and logs tracebacks to <code>audit_logs</code>.</li>
                    <li><b class="text-white">Tenant Isolation:</b> Parameterized PDO queries with <code>tenant_id</code> ensure Subaccount A cannot inspect Subaccount B data.</li>
                    <li><b class="text-white">Emergency Safe Mode:</b> Append <code>?plugin_safe_mode=1</code> to bypass all plugins instantly for admin recovery.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Tab 5: Code Examples -->
    <div id="tab-examples" class="dev-tab-content hidden space-y-4 text-xs">
        <div class="bg-slate-900 p-4 rounded-xl border border-slate-800 space-y-2">
            <h4 class="font-extrabold text-emerald-400">Production Example: `plugin.php`</h4>
            <pre class="text-3xs font-mono text-emerald-300 overflow-x-auto">&lt;?php
use Services\PluginEngine;

// 1. Inject Topbar Menu Link
PluginEngine::add_action('management_menu_items', function() {
    echo '&lt;a href="#" onclick="alert(\'Royalty Plugin Active!\')" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"&gt;';
    echo '&lt;i class="fa-solid fa-calculator w-5 text-indigo-600 text-center"&gt;&lt;/i&gt;&lt;span&gt;Royalty Calculator&lt;/span&gt;';
    echo '&lt;/a&gt;';
});

// 2. Intercept Invoice Save (Apply 5% Discount for Orders > 10,000 AED)
PluginEngine::add_filter('invoice_before_save', function($invoice) {
    if (isset($invoice['subtotal']) && $invoice['subtotal'] > 10000) {
        $invoice['discount_type'] = 'percent';
        $invoice['discount_value'] = 5.0; // 5% discount
    }
    return $invoice;
});</pre>
        </div>
    </div>

    <script>
    function switchDevTab(tabId) {
        document.querySelectorAll('.dev-tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.dev-tab-btn').forEach(el => {
            el.classList.remove('bg-amber-500', 'text-slate-950');
            el.classList.add('bg-slate-900', 'text-slate-300');
        });
        document.getElementById(tabId).classList.remove('hidden');
        const btn = document.getElementById('btn-' + tabId);
        btn.classList.remove('bg-slate-900', 'text-slate-300');
        btn.classList.add('bg-amber-500', 'text-slate-950');
    }
    </script>

    <div class="mt-6 pt-3 border-t border-slate-800 flex items-center justify-between text-2xs text-slate-400">
        <span>Complete File Documentation: See <code>PLUGIN_DEVELOPMENT_GUIDE.md</code> in root directory.</span>
        <a href="plugins_admin?action=create_sample" class="text-amber-400 hover:underline font-bold">Generate Working Starter Sample Plugin →</a>
    </div>
</div>

<?php if ($isSafeMode): ?>
    <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl">
        <div class="flex">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg mr-3"></i>
            <div>
                <h3 class="text-sm font-bold text-amber-800">Emergency Safe Mode Active</h3>
                <p class="text-xs text-amber-700 mt-0.5">All plugins are temporarily bypassed for this session. <a href="plugins_admin" class="font-extrabold underline">Exit Safe Mode</a>.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($msg): ?>
    <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl text-emerald-800 font-bold text-sm flex items-center justify-between">
        <span><i class="fa-solid fa-circle-check mr-2 text-emerald-500"></i><?=e($msg)?></span>
        <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl text-rose-800 font-bold text-sm flex items-center justify-between">
        <span><i class="fa-solid fa-circle-exclamation mr-2 text-rose-500"></i><?=e($error)?></span>
        <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <!-- Left Column: Upload .zip Plugin Form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm sticky top-24">
            <div class="flex items-center space-x-2.5 mb-4 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-black">
                    <i class="fa-solid fa-file-arrow-up"></i>
                </div>
                <h2 class="text-base font-bold text-slate-900">Upload .ZIP Plugin</h2>
            </div>
            
            <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                Bring your custom extensions or third-party features into OneSol Invoice Manager. Simply upload a compressed <strong>.zip archive</strong> containing a valid <code>plugin.json</code> manifest.
            </p>

            <form method="post" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-2xs font-extrabold uppercase text-slate-400 mb-1">Select Plugin Archive (.zip)</label>
                    <input type="file" name="plugin_zip" accept=".zip" required class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-extrabold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 border border-slate-200 rounded-xl p-1 bg-slate-50">
                </div>

                <button type="submit" name="upload_plugin" class="w-full py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all">
                    <i class="fa-solid fa-upload mr-1.5"></i>Upload & Install Plugin
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-100 text-3xs text-slate-400 space-y-1">
                <div class="flex items-center space-x-1"><i class="fa-solid fa-shield-halved text-emerald-500"></i><span>5-Layer Throwable Sandbox Protection</span></div>
                <div class="flex items-center space-x-1"><i class="fa-solid fa-building text-amber-500"></i><span>Tenant-Isolated Scoping</span></div>
            </div>
        </div>
    </div>

    <!-- Right Column: Installed Plugins Table -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Installed Feature Plugins (<?=count($availablePlugins)?>)</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Toggle plugin availability per subaccount workspace.</p>
                </div>
                <a href="plugins_admin?plugin_safe_mode=1" class="text-2xs font-bold text-amber-600 hover:underline">Emergency Safe Mode</a>
            </div>

            <div class="divide-y divide-slate-100">
                <?php if (empty($availablePlugins)): ?>
                    <div class="p-12 text-center text-slate-400">
                        <i class="fa-solid fa-puzzle-piece text-5xl mb-3 text-slate-300 block"></i>
                        <span class="font-bold text-slate-700 block mb-1">No feature plugins installed yet.</span>
                        <p class="text-xs text-slate-500 mb-4">Click "Generate Starter Sample Plugin" above or upload a custom .zip plugin archive.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($availablePlugins as $slug => $plugin): 
                    $isActive = in_array($slug, $activeSlugs);
                ?>
                    <div class="p-5 hover:bg-slate-50/80 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-md">
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm font-extrabold text-slate-900"><?=e($plugin['name'])?></h3>
                                <span class="text-3xs font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">v<?=e($plugin['version'])?></span>
                                <?php if ($isActive): ?>
                                    <span class="px-2 py-0.5 rounded-full text-3xs font-black uppercase bg-emerald-100 text-emerald-800">Active</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-3xs font-black uppercase bg-slate-100 text-slate-500">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed"><?=e($plugin['description'])?></p>
                            <div class="text-3xs font-mono text-slate-400">
                                Author: <?=e($plugin['author'])?> | Directory: <code>plugins/<?=e($slug)?></code>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <?php if ($isActive): ?>
                                <a href="plugins_admin?action=deactivate&slug=<?=urlencode($slug)?>" class="px-3 py-1.5 bg-slate-100 hover:bg-amber-100 text-slate-700 hover:text-amber-800 font-bold text-xs rounded-xl transition-all">
                                    Deactivate
                                </a>
                            <?php else: ?>
                                <a href="plugins_admin?action=activate&slug=<?=urlencode($slug)?>" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl shadow-xs transition-all">
                                    Activate
                                </a>
                            <?php endif; ?>

                            <a href="plugins_admin?action=delete&slug=<?=urlencode($slug)?>" onclick="return confirm('Are you sure you want to delete this plugin directory?')" class="p-1.5 text-slate-400 hover:text-rose-600 text-xs font-bold transition-all" title="Delete Plugin">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php page_end(); ?>
