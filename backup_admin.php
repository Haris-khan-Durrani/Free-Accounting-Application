<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$user = $_SESSION['user_name'] ?? 'User';

if (isset($_GET['action']) && $_GET['action'] === 'download') {
    verify_csrf();

    $tables = ['tenants', 'users', 'clients', 'invoices', 'invoice_items', 'payments', 'expenses', 'expense_categories', 'quotes', 'quote_items', 'journal_entries', 'audit_logs', 'api_keys'];
    
    $sqlDump = "-- OneSol Invoice Manager - Database Backup Dump\n";
    $sqlDump .= "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Workspace Tenant ID: $tid (" . tenant()['name'] . ")\n\n";

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

            // Export rows
            $stRows = $pdo->query("SELECT * FROM `$table`");
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
            // Skip unreadable table
        }
    }

    log_audit($pdo, 'backup_database', 'tenants', $tid, "Downloaded SQL database backup dump");

    $fileName = 'onesol_backup_' . date('Ymd_His') . '.sql';
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
            <p class="text-xs text-slate-500">Exports all workspace tables, clients, invoices, ledgers, and configuration settings.</p>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-4 text-xs font-semibold text-amber-900 flex items-start space-x-3">
        <i class="fa-solid fa-shield-halved text-amber-600 text-base mt-0.5"></i>
        <div>
            <strong>Backup Recommendation:</strong>
            <p class="mt-1 text-amber-800">Store your downloaded <code>.sql</code> backup files in a secure location. You can restore this backup at any time using phpMyAdmin or standard MySQL CLI.</p>
        </div>
    </div>

    <div class="pt-4 border-t border-slate-100 flex justify-end">
        <a href="backup_admin?action=download&csrf=<?=e(csrf_token())?>" class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-extrabold text-xs shadow-md transition-all flex items-center space-x-2">
            <i class="fa-solid fa-download"></i>
            <span>Download SQL Backup Dump (.sql)</span>
        </a>
    </div>
</div>

<?php page_end(); ?>
