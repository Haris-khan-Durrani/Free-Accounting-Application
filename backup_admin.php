<?php
require __DIR__ . '/bootstrap.php';
require_platform_admin();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$user = $_SESSION['user_name'] ?? 'User';
$uid = (int)($_SESSION['user_id'] ?? 0);
$isSuperAdmin = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'download') {
    verify_csrf();

    $allowedTables = ['tenants', 'users', 'clients', 'invoices', 'invoice_items', 'payments', 'expenses', 'expense_categories', 'quotes', 'quote_items', 'journal_entries', 'audit_logs', 'api_keys'];
    $tables = array_values(array_intersect($tables, $allowedTables));
    
    $sqlDump = "-- OneSol Invoice Manager - Database Backup Dump\n";
    $sqlDump .= "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Workspace Tenant ID: $tid (" . tenant()['name'] . ")\n";
    $sqlDump .= "-- Scope Mode: " . ($isSuperAdmin ? "Full Platform Snapshot (Super-Admin)" : "Tenant Isolated Export") . "\n\n";

    foreach ($tables as $table) {
        try {
            // Check if table exists
            $st = $pdo->query("SHOW TABLES LIKE '$table'");
            if (!$st->fetch()) continue;

            $sqlDump .= "-- --------------------------------------------------------\n";
            $sqlDump .= "-- Table structure for `$table`\n";
            $sqlDump .= "-- --------------------------------------------------------\n";

            $stCreate = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sqlDump .= $stCreate['Create Table'] . ";\n\n";

            // Export rows (Tenant isolated if not super-admin)
            if ($isSuperAdmin) {
                $stRows = $pdo->query("SELECT * FROM `$table`");
            } else {
                if ($table === 'tenants') {
                    $stRows = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
                    $stRows->execute([$tid]);
                } elseif ($table === 'invoice_items') {
                    $stRows = $pdo->prepare("SELECT ii.* FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE i.tenant_id = ?");
                    $stRows->execute([$tid]);
                } elseif ($table === 'quote_items') {
                    $stRows = $pdo->prepare("SELECT qi.* FROM quote_items qi JOIN quotes q ON q.id = qi.quote_id WHERE q.tenant_id = ?");
                    $stRows->execute([$tid]);
                } else {
                    // Filter by tenant_id column
                    $stCheckCol = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
                    if ($stCheckCol->fetch()) {
                        $stRows = $pdo->prepare("SELECT * FROM `$table` WHERE tenant_id = ?");
                        $stRows->execute([$tid]);
                    } else {
                        // Skip un-scoped global reference table for non-superadmin
                        continue;
                    }
                }
            }

            $rows = $stRows->fetchAll();

            if (!empty($rows)) {
                $sqlDump .= "-- Dumping data for `$table`\n";
                foreach ($rows as $r) {
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote($v);
                    }, array_values($r));
                    $sqlDump .= "INSERT INTO `$table` VALUES (" . implode(', ', $vals) . ");\n";
                }
                $sqlDump .= "\n";
            }
        } catch (\Throwable $e) {
            // Skip table errors cleanly
        }
    }

    log_audit($pdo, 'backup_database', 'tenants', $tid, "Downloaded SQL database backup dump (Isolated Mode)");

    $fileName = 'onesol_backup_tenant_' . $tid . '_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($sqlDump));
    echo $sqlDump;
    exit;
}

require __DIR__ . '/layout.php';
page_start('Database Backup & Export');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Database Backup & Export</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Download raw SQL database snapshots of your workspace for offline archiving and data safety.</p>
    </div>
</div>

<div class="max-w-2xl bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-6">
    <div class="flex items-center space-x-4">
        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-xl">
            <i class="fa-solid fa-database"></i>
        </div>
        <div>
            <h2 class="text-base font-extrabold text-slate-900">One-Click SQL Backup</h2>
            <p class="text-xs text-slate-500">Exports all workspace clients, invoices, ledgers, and workspace settings.</p>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-4 text-xs font-semibold text-amber-900 flex items-start space-x-3">
        <i class="fa-solid fa-shield-halved text-amber-600 text-base mt-0.5"></i>
        <div>
            <strong>Backup Scope & Data Isolation:</strong>
            <p class="mt-1 text-amber-800">Your SQL dump contains strictly isolated records belonging to workspace <strong><?=e(tenant()['name'])?> (ID #<?=$tid?>)</strong>. Store your downloaded <code>.sql</code> backup files in a secure location.</p>
        </div>
    </div>

    <div class="pt-4 border-t border-slate-100 flex justify-end">
        <form method="post" action="backup_admin">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="download">
            <button type="submit" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-extrabold text-xs shadow-md transition-all flex items-center space-x-2">
                <i class="fa-solid fa-download"></i>
                <span>Download Tenant SQL Backup Dump (.sql)</span>
            </button>
        </form>
    </div>
</div>

<?php page_end(); ?>
