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
        <a href="plugins_admin?action=create_sample" class="inline-flex items-center px-4 py-2.5 bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-700 font-extrabold text-xs rounded-xl shadow-xs transition-all">
            <i class="fa-solid fa-code mr-1.5 text-purple-600"></i>Generate Starter Sample Plugin
        </a>
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
