<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();

// Search & Pagination Logic for Tax Invoices
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereClause = "WHERE i.tenant_id = ?";
$queryParams = [$tid];

if (!empty($search)) {
    $whereClause .= " AND (i.invoice_number LIKE ? OR c.company_name LIKE ? OR i.notes LIKE ?)";
    $sT = "%$search%";
    $queryParams = array_merge($queryParams, [$sT, $sT, $sT]);
}

if (!empty($statusFilter)) {
    $whereClause .= " AND i.status = ?";
    $queryParams[] = $statusFilter;
}

// Count Total Matching Invoices
$stCount = $pdo->prepare("SELECT COUNT(*) FROM invoices i JOIN clients c ON c.id = i.client_id $whereClause");
$stCount->execute($queryParams);
$totalRecords = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Fetch Paginated Tax Invoices
$st = $pdo->prepare("
    SELECT i.*, c.company_name, c.email as client_email 
    FROM invoices i 
    JOIN clients c ON c.id = i.client_id 
    $whereClause 
    ORDER BY i.id DESC 
    LIMIT $perPage OFFSET $offset
");
$st->execute($queryParams);
$invoices = $st->fetchAll();

// Total Summary Metrics
$stTotalVal = $pdo->prepare("SELECT COALESCE(SUM(total), 0), COALESCE(SUM(paid_amount), 0) FROM invoices WHERE tenant_id = ?");
$stTotalVal->execute([$tid]);
list($sumTotal, $sumPaid) = $stTotalVal->fetch(PDO::FETCH_NUM);
$sumDue = max(0, (float)$sumTotal - (float)$sumPaid);

page_start('Tax Invoices Directory');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Tax Invoices Directory</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Manage client billing, payment status, and PDF dispatches for <strong><?=e($activeTenant['name'])?></strong>.</p>
    </div>
    <div class="mt-4 sm:mt-0 flex items-center space-x-3">
        <a href="invoice_form" class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-1.5">
            <i class="fa-solid fa-plus text-amber-200"></i>
            <span>+ Create New Invoice</span>
        </a>
    </div>
</div>

<!-- Summary Metric Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
    <div class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-2xl p-5 border border-slate-800 shadow-xl">
        <span class="text-3xs font-extrabold text-amber-400 uppercase tracking-widest block mb-1">Total Invoiced Volume</span>
        <strong class="text-2xl font-black text-white font-mono"><?=money((float)$sumTotal, $activeTenant['currency'])?></strong>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-emerald-200 shadow-md">
        <span class="text-3xs font-extrabold text-emerald-600 uppercase tracking-widest block mb-1">Collected Cash</span>
        <strong class="text-2xl font-black text-emerald-600 font-mono"><?=money((float)$sumPaid, $activeTenant['currency'])?></strong>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-amber-200 shadow-md">
        <span class="text-3xs font-extrabold text-amber-600 uppercase tracking-widest block mb-1">Outstanding Balance</span>
        <strong class="text-2xl font-black text-amber-600 font-mono"><?=money((float)$sumDue, $activeTenant['currency'])?></strong>
    </div>
</div>

<!-- Invoices Log Table & Search Bar -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center space-x-3">
            <h2 class="text-base sm:text-lg font-bold text-slate-900">Invoices Log <span class="text-xs font-semibold text-slate-400">(<?=$totalRecords?> total)</span></h2>
        </div>

        <!-- Filter & Search Controls -->
        <form method="get" class="flex flex-wrap items-center gap-2">
            <!-- Status Filter -->
            <select name="status" onchange="this.form.submit()" class="py-1.5 px-3 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 focus:ring-2 focus:ring-amber-500">
                <option value="">All Statuses</option>
                <option value="paid" <?=$statusFilter==='paid'?'selected':''?>>Paid</option>
                <option value="partially_paid" <?=$statusFilter==='partially_paid'?'selected':''?>>Partially Paid</option>
                <option value="sent" <?=$statusFilter==='sent'?'selected':''?>>Sent</option>
                <option value="draft" <?=$statusFilter==='draft'?'selected':''?>>Draft</option>
                <option value="overdue" <?=$statusFilter==='overdue'?'selected':''?>>Overdue</option>
                <option value="void" <?=$statusFilter==='void'?'selected':''?>>Void</option>
            </select>

            <!-- Search Input -->
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?=e($search)?>" placeholder="Search invoice #, client..." class="pl-8 pr-4 py-1.5 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 w-48 sm:w-64">
            </div>

            <?php if ($search || $statusFilter): ?>
                <a href="invoices" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-all">Clear</a>
            <?php endif; ?>

            <button type="submit" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-xs">Search</button>
        </form>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Invoice #</th>
                    <th class="px-6 py-3.5">Client Company</th>
                    <th class="px-6 py-3.5">Issue Date</th>
                    <th class="px-6 py-3.5">Due Date</th>
                    <th class="px-6 py-3.5 text-right">Total Amount</th>
                    <th class="px-6 py-3.5 text-center">Status</th>
                    <th class="px-6 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-file-invoice text-4xl mb-3 text-slate-300 block"></i>
                            <span class="font-bold text-slate-700 block mb-1">No tax invoices found.</span>
                            <a href="invoice_form" class="text-xs font-bold text-amber-600 hover:underline">Create a new invoice →</a>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($invoices as $inv): ?>
                    <tr class="hover:bg-slate-50/80 transition-all group">
                        <td class="px-6 py-4 font-mono font-extrabold text-blue-600">
                            <a href="invoice_view?id=<?=$inv['id']?>" class="hover:underline"><?=e($inv['invoice_number'])?></a>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-900">
                            <?=e($inv['company_name'])?>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500">
                            <?=e(date('d M Y', strtotime($inv['invoice_date'])))?>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500">
                            <?=e(date('d M Y', strtotime($inv['valid_until'])))?>
                        </td>
                        <td class="px-6 py-4 text-right font-extrabold text-slate-900 font-mono">
                            <?=money((float)$inv['total'], $inv['currency'] ?: $activeTenant['currency'])?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php
                            $statusClasses = [
                                'paid' => 'bg-emerald-100 text-emerald-800',
                                'partially_paid' => 'bg-amber-100 text-amber-900',
                                'sent' => 'bg-blue-100 text-blue-800',
                                'draft' => 'bg-sky-100 text-sky-800',
                                'overdue' => 'bg-rose-100 text-rose-800',
                                'void' => 'bg-slate-200 text-slate-700 line-through',
                                'cancelled' => 'bg-slate-100 text-slate-800'
                            ];
                            $sClass = $statusClasses[$inv['status']] ?? 'bg-slate-100 text-slate-800';
                            ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold <?=$sClass?>">
                                <?=strtoupper(e(str_replace('_', ' ', $inv['status'])))?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-1.5">
                            <a href="invoice_view?id=<?=$inv['id']?>" class="text-xs font-bold text-slate-600 hover:text-blue-600 bg-slate-100 hover:bg-blue-50 px-2.5 py-1.5 rounded-lg transition-all">View</a>
                            <a href="invoice_print?id=<?=$inv['id']?>" target="_blank" class="text-xs font-bold text-slate-600 hover:text-emerald-600 bg-slate-100 hover:bg-emerald-50 px-2.5 py-1.5 rounded-lg transition-all">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Touch Cards View -->
    <div class="sm:hidden divide-y divide-slate-100">
        <?php foreach ($invoices as $inv): ?>
            <div class="p-4 hover:bg-slate-50 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <a href="invoice_view?id=<?=$inv['id']?>" class="font-mono font-black text-blue-600 text-sm"><?=e($inv['invoice_number'])?></a>
                    <span class="px-2.5 py-0.5 rounded-full text-2xs font-black bg-slate-100 text-slate-800"><?=strtoupper(e($inv['status']))?></span>
                </div>
                <div class="flex justify-between items-end">
                    <div>
                        <div class="font-extrabold text-slate-900 text-sm"><?=e($inv['company_name'])?></div>
                        <div class="text-2xs text-slate-400 font-semibold mt-0.5"><i class="fa-regular fa-clock mr-1"></i>Due: <?=e(date('d M Y', strtotime($inv['valid_until'])))?></div>
                    </div>
                    <div class="text-right">
                        <div class="font-black text-slate-900 text-base font-mono"><?=money((float)$inv['total'], $inv['currency'] ?: $activeTenant['currency'])?></div>
                        <div class="mt-1 flex justify-end space-x-1.5">
                            <a href="invoice_view?id=<?=$inv['id']?>" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-50 text-slate-700 text-2xs font-extrabold rounded-lg">View</a>
                            <a href="invoice_print?id=<?=$inv['id']?>" target="_blank" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-50 text-slate-700 text-2xs font-extrabold rounded-lg">PDF</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs font-bold text-slate-600">
            <div>
                Showing <strong><?=min($offset + 1, $totalRecords)?></strong> to <strong><?=min($offset + $perPage, $totalRecords)?></strong> of <strong><?=$totalRecords?></strong> invoices
            </div>
            
            <div class="flex items-center space-x-1.5">
                <?php if ($page > 1): ?>
                    <a href="invoices?page=<?=$page - 1?><?=!empty($search) ? '&search=' . urlencode($search) : ''?><?=!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                        <i class="fa-solid fa-chevron-left mr-1"></i>Prev
                    </a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="invoices?page=<?=$p?><?=!empty($search) ? '&search=' . urlencode($search) : ''?><?=!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''?>" class="px-3 py-1.5 rounded-lg border <?= $p === $page ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' ?>">
                        <?=$p?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="invoices?page=<?=$page + 1?><?=!empty($search) ? '&search=' . urlencode($search) : ''?><?=!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                        Next<i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php page_end(); ?>
