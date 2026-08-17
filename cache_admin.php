<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Handle Flush Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'flush_tenant') {
        $count = \Core\Cache::flushTenant($tid);
        flash('success', "Workspace cache flushed successfully! ($count keys cleared).");
        redirect('cache_admin');
    }

    if ($action === 'flush_all') {
        \Core\Cache::flushAll();
        flash('success', 'System-wide cache flushed successfully across all tenants!');
        redirect('cache_admin');
    }

    if ($action === 'test_cache') {
        $testVal = 'cache_test_' . time();
        \Core\Cache::set('test_ping', $testVal, 60);
        $readVal = \Core\Cache::get('test_ping');
        if ($readVal === $testVal) {
            flash('success', 'Cache Read/Write test passed cleanly! Value: ' . $readVal);
        } else {
            flash('error', 'Cache Test Failed! Read value mismatch.');
        }
        redirect('cache_admin');
    }
}

$stats = \Core\Cache::getStats();

page_start('Redis & TTL Cache Engine Diagnostics');
?>

<!-- Page Header -->
<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2 mb-1">
            <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-emerald-100 text-emerald-800 uppercase tracking-wider">
                <i class="fa-solid fa-bolt mr-1"></i>High-Performance Subsystem
            </span>
        </div>
        <h1 class="text-3xl font-black text-slate-900 tracking-tight">⚡ Cache Engine & TTL Jitter Diagnostics</h1>
        <p class="mt-1 text-sm text-slate-500">Real-time status of Redis cache, file storage fallback, cache stampede jitter variance, and key invalidation.</p>
    </div>
    <div class="mt-4 sm:mt-0 flex space-x-3">
        <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_cache">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-xs font-extrabold rounded-xl text-slate-700 bg-white hover:bg-slate-50 transition-all">
                <i class="fa-solid fa-vial-circle-check mr-2 text-blue-500"></i>Run Cache Test
            </button>
        </form>
        <form method="post" class="inline" onsubmit="return confirm('Flush cache for active workspace?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="flush_tenant">
            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-extrabold text-white bg-amber-500 hover:bg-amber-600 shadow-md transition-all">
                <i class="fa-solid fa-rotate-right mr-2"></i>Flush Workspace Cache
            </button>
        </form>
    </div>
</div>

<!-- Driver Status Banner -->
<div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 rounded-3xl p-6 text-white shadow-xl mb-8 border border-slate-700/80">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div class="flex items-start space-x-4">
            <div class="h-12 w-12 rounded-2xl bg-amber-500/20 border border-amber-500/40 text-amber-400 flex items-center justify-center text-2xl font-bold flex-shrink-0">
                <i class="fa-solid <?= $stats['is_redis'] ? 'fa-database' : 'fa-hard-drive' ?>"></i>
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-extrabold text-amber-400 uppercase tracking-widest">Active Cache Driver</span>
                    <span class="px-2 py-0.5 rounded-full text-2xs font-extrabold <?= $stats['is_redis'] ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-blue-500/20 text-blue-300 border border-blue-500/40' ?>">
                        <?= $stats['is_redis'] ? 'ONLINE (Redis)' : 'ACTIVE (File + Memory Fallback)' ?>
                    </span>
                </div>
                <h3 class="text-xl font-extrabold text-white mt-1"><?= e($stats['driver']) ?></h3>
                <p class="text-xs text-slate-400 mt-1 max-w-2xl">
                    All queries for workspace branding, active tenant configs, SaaS subscription plans, and reports are cached with <strong>TTL Jitter variance</strong> to eliminate MySQL thundering herd stampedes.
                </p>
            </div>
        </div>
        <div class="flex flex-col space-y-2 text-right font-mono text-xs text-slate-300 border-l border-slate-700/80 pl-6 flex-shrink-0">
            <div>Host: <strong class="text-amber-300"><?= e($stats['redis_host']) ?></strong></div>
            <div>Redis Memory: <strong class="text-emerald-300"><?= e($stats['redis_memory']) ?></strong></div>
            <div>File Backup Keys: <strong class="text-blue-300"><?= $stats['file_keys'] ?></strong></div>
        </div>
    </div>
</div>

<!-- Metrics Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-2xs font-extrabold uppercase tracking-wider">Hit Ratio</span>
            <i class="fa-solid fa-chart-pie text-blue-500 text-base"></i>
        </div>
        <div class="text-3xl font-black text-slate-900"><?= $stats['hit_ratio'] ?></div>
        <div class="text-2xs text-slate-500 mt-1">Hits: <strong><?= $stats['hits'] ?></strong> / Misses: <strong><?= $stats['misses'] ?></strong></div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-2xs font-extrabold uppercase tracking-wider">Redis Keys</span>
            <i class="fa-solid fa-key text-emerald-500 text-base"></i>
        </div>
        <div class="text-3xl font-black text-slate-900"><?= $stats['redis_keys'] ?></div>
        <div class="text-2xs text-slate-500 mt-1">Active Redis database keys</div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-2xs font-extrabold uppercase tracking-wider">Stampede Protection</span>
            <i class="fa-solid fa-shield-halved text-amber-500 text-base"></i>
        </div>
        <div class="text-base font-extrabold text-slate-900 mt-1">TTL Jitter Variance</div>
        <div class="text-2xs text-amber-600 font-bold mt-1">0% – 15% Random Stagger</div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-2xs font-extrabold uppercase tracking-wider">Tenant Namespace</span>
            <i class="fa-solid fa-sitemap text-purple-500 text-base"></i>
        </div>
        <div class="text-lg font-mono font-bold text-purple-700 mt-1">onesol:t<?= $tid ?>:*</div>
        <div class="text-2xs text-slate-500 mt-1">100% Isolated Data Boundaries</div>
    </div>
</div>

<!-- Architecture Details & Emergency Flush -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Architecture Feature Grid -->
    <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-4">
        <h3 class="text-base font-extrabold text-slate-900 flex items-center">
            <i class="fa-solid fa-microchip text-amber-500 mr-2"></i>Cache Subsystem Specifications
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <strong class="font-extrabold text-slate-900 block mb-1">
                    <i class="fa-solid fa-clock text-amber-500 mr-1.5"></i>TTL Jitter Stampede Prevention
                </strong>
                <p class="text-slate-600 text-2xs leading-relaxed">
                    When caching objects (e.g. 600s TTL for branding), the system adds a random $0 - 15\%$ variance (e.g. $600s - 690s$). This guarantees that cached items never expire at the exact same millisecond under heavy load.
                </p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <strong class="font-extrabold text-slate-900 block mb-1">
                    <i class="fa-solid fa-layer-group text-blue-500 mr-1.5"></i>Multi-Driver Graceful Fallback
                </strong>
                <p class="text-slate-600 text-2xs leading-relaxed">
                    If Redis is running, requests hit in-memory Redis keys. If Redis is uninstalled or offline, requests seamlessly drop back to high-speed file storage in <code class="text-slate-700 font-mono">storage/cache/</code> without breaking execution.
                </p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <strong class="font-extrabold text-slate-900 block mb-1">
                    <i class="fa-solid fa-lock text-emerald-500 mr-1.5"></i>Strict Multi-Tenant Prefixing
                </strong>
                <p class="text-slate-600 text-2xs leading-relaxed">
                    Keys are namespaced as <code class="text-emerald-700 font-mono">onesol:t{tenant_id}:{key}</code>. Flushing workspace #2 cache will never affect workspace #1 or superadmin keys.
                </p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <strong class="font-extrabold text-slate-900 block mb-1">
                    <i class="fa-solid fa-bolt text-rose-500 mr-1.5"></i>Event-Driven Cache Invalidation
                </strong>
                <p class="text-slate-600 text-2xs leading-relaxed">
                    When tenant branding settings or user roles are updated, invalidation hooks automatically call <code class="text-slate-700 font-mono">Cache::forget()</code> to purge stale data instantly.
                </p>
            </div>
        </div>
    </div>

    <!-- Emergency Actions Panel -->
    <div class="bg-slate-900 text-white rounded-3xl p-6 border border-slate-800 flex flex-col justify-between shadow-xl">
        <div>
            <h4 class="font-extrabold text-sm text-amber-400 uppercase tracking-wider mb-2 flex items-center">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>Cache Control Center
            </h4>
            <p class="text-xs text-slate-400 leading-relaxed mb-6">
                Use these tools to manually flush cached entries if you've directly edited MySQL database tables or modified core code.
            </p>

            <div class="space-y-3">
                <form method="post" onsubmit="return confirm('Flush cache for active workspace?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="flush_tenant">
                    <button type="submit" class="w-full py-3 px-4 rounded-2xl text-xs font-extrabold text-slate-950 bg-amber-400 hover:bg-amber-300 transition-all shadow-md flex items-center justify-center">
                        <i class="fa-solid fa-rotate-right mr-2"></i>Flush Workspace #<?= $tid ?> Cache
                    </button>
                </form>

                <form method="post" onsubmit="return confirm('WARNING: This will flush ALL cached data across ALL tenant workspaces on the platform! Proceed?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="flush_all">
                    <button type="submit" class="w-full py-3 px-4 rounded-2xl text-xs font-extrabold text-rose-300 bg-rose-500/20 border border-rose-500/40 hover:bg-rose-500/30 transition-all flex items-center justify-center">
                        <i class="fa-solid fa-dumpster-fire mr-2"></i>Flush Global System Cache (All Tenants)
                    </button>
                </form>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-800 text-[10px] text-slate-500 flex items-center justify-between">
            <span>Cache Engine v2.4</span>
            <span class="text-emerald-400 font-bold"><i class="fa-solid fa-circle text-[6px] mr-1"></i>Healthy</span>
        </div>
    </div>
</div>

<?php page_end(); ?>
