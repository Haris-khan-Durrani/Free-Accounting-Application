<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Audit log is restricted to admins and owners
if (!has_role(['owner', 'admin'])) {
    flash('error', 'Access denied. The audit log requires admin or owner access.');
    redirect('index');
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="system_audit_log_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Log ID', 'Timestamp', 'User', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP Address']);

    $stCsv = $pdo->prepare("
        SELECT a.id, a.created_at, COALESCE(u.name, 'System/Client') as user_name, a.action, a.entity_type, a.entity_id, a.details, a.ip_address
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE a.tenant_id = ?
        ORDER BY a.id DESC
    ");
    $stCsv->execute([$tid]);


    while ($r = $stCsv->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// Pagination & Search Filters
$search = trim($_GET['q'] ?? '');
$actionFilter = trim($_GET['action_filter'] ?? 'all');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$whereClauses = ["a.tenant_id = ?"];
$params = [$tid];

if ($search !== '') {
    $whereClauses[] = "(a.action LIKE ? OR a.details LIKE ? OR a.entity_type LIKE ? OR a.ip_address LIKE ? OR u.name LIKE ?)";
    $term = "%{$search}%";
    array_push($params, $term, $term, $term, $term, $term);
}

if ($actionFilter !== 'all') {
    $whereClauses[] = "a.action LIKE ?";
    $params[] = "%{$actionFilter}%";
}

$whereSql = implode(' AND ', $whereClauses);

// Count Total Logs
$stCount = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE {$whereSql}");
$stCount->execute($params);
$totalLogs = (int)$stCount->fetchColumn();
$totalPages = max(1, ceil($totalLogs / $limit));

// Fetch Paginated Logs
$stLogs = $pdo->prepare("
    SELECT a.*, u.name as user_name, u.email as user_email, u.role as user_role
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE {$whereSql}
    ORDER BY a.id DESC
    LIMIT {$limit} OFFSET {$offset}
");
$stLogs->execute($params);
$logs = $stLogs->fetchAll();

require __DIR__ . '/layout.php';
page_start('System Audit Log');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-800 font-black text-xs uppercase tracking-wider">Security Console</span>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">System Audit Log</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Immutable security event history and operational audit trails for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <a href="audit_log?export=csv" class="inline-flex items-center px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all">
            <i class="fa-solid fa-file-arrow-down mr-1.5 text-amber-400"></i>Export Audit Log (CSV)
        </a>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-6">
    <form method="get" class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
            <input type="text" name="q" value="<?=e($search)?>" placeholder="Search actions, users, IPs, details..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:bg-white transition-all">
        </div>

        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <select name="action_filter" onchange="this.form.submit()" class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-900">
                <option value="all" <?=$actionFilter === 'all' ? 'selected' : ''?>>All Action Types</option>
                <option value="login" <?=$actionFilter === 'login' ? 'selected' : ''?>>Logins & Security</option>
                <option value="invoice" <?=$actionFilter === 'invoice' ? 'selected' : ''?>>Invoices & Receipts</option>
                <option value="payment" <?=$actionFilter === 'payment' ? 'selected' : ''?>>Payments & Gateways</option>
                <option value="recurring" <?=$actionFilter === 'recurring' ? 'selected' : ''?>>Subscription Billing</option>
                <option value="template" <?=$actionFilter === 'template' ? 'selected' : ''?>>Templates & Settings</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs rounded-xl shadow-xs">Filter</button>
            <?php if ($search || $actionFilter !== 'all'): ?>
                <a href="audit_log" class="text-xs font-bold text-slate-500 hover:text-slate-700">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Audit Logs Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Recorded System Audit Events (<?=$totalLogs?>)</h2>
        <span class="text-xs font-bold text-slate-400">Page <?=$page?> of <?=$totalPages?></span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-5 py-3">Timestamp</th>
                    <th class="px-5 py-3">User Account</th>
                    <th class="px-5 py-3">Action Event</th>
                    <th class="px-5 py-3">Details & Target</th>
                    <th class="px-5 py-3 text-right">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-clock-rotate-left text-4xl mb-3 text-slate-300 block"></i>
                            <span class="font-bold text-slate-700 block mb-1">No system audit logs found matching criteria.</span>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($logs as $l): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-5 py-3.5 text-xs font-mono font-bold text-slate-600 whitespace-nowrap">
                            <?=e(date('d M Y H:i:s', strtotime($l['created_at'])))?>
                        </td>
                        <td class="px-5 py-3.5 font-bold text-slate-900 whitespace-nowrap">
                            <?php if ($l['user_name']): ?>
                                <?=e($l['user_name'])?>
                                <span class="text-3xs font-extrabold uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 ml-1"><?=e($l['user_role'] ?: 'user')?></span>
                            <?php else: ?>
                                <span class="text-slate-400 italic">System / Client</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold uppercase tracking-wider bg-slate-900 text-amber-400 font-mono">
                                <?=e($l['action'])?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-slate-700 font-medium max-w-md">
                            <?=e($l['details'])?>
                            <?php if ($l['entity_type'] && $l['entity_id']): ?>
                                <span class="text-2xs text-slate-400 block font-mono">Entity: <?=e($l['entity_type'])?> #<?=e($l['entity_id'])?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-right font-mono text-xs font-bold text-slate-500 whitespace-nowrap">
                            <?=e($l['ip_address'] ?: '127.0.0.1')?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs font-bold">
            <div>
                Showing page <?=$page?> of <?=$totalPages?> (Total <?=$totalLogs?> audit logs)
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                    <a href="audit_log?page=<?=$page - 1?>&q=<?=urlencode($search)?>&action_filter=<?=urlencode($actionFilter)?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700">← Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="audit_log?page=<?=$page + 1?>&q=<?=urlencode($search)?>&action_filter=<?=urlencode($actionFilter)?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php page_end(); ?>
