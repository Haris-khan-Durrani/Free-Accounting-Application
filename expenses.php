<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Search & Pagination Logic for Expenses
$expSearch = trim($_GET['search'] ?? '');
$expPage = max(1, (int)($_GET['page'] ?? 1));
$expPerPage = 15;
$expOffset = ($expPage - 1) * $expPerPage;

$expWhere = "WHERE e.tenant_id = ?";
$expParams = [$tid];

if (!empty($expSearch)) {
    $expWhere .= " AND (e.vendor LIKE ? OR e.reference_number LIKE ? OR e.notes LIKE ? OR ec.name LIKE ?)";
    $sT = "%$expSearch%";
    $expParams = array_merge($expParams, [$sT, $sT, $sT, $sT]);
}

$stTotal = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id WHERE e.tenant_id = ?");
$stTotal->execute([$tid]);
$totalExpenses = (float)$stTotal->fetchColumn();

$stCountExp = $pdo->prepare("SELECT COUNT(*) FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id $expWhere");
$stCountExp->execute($expParams);
$totalExpRecords = (int)$stCountExp->fetchColumn();
$totalExpPages = max(1, (int)ceil($totalExpRecords / $expPerPage));

$st = $pdo->prepare("
    SELECT e.*, ec.name category_name 
    FROM expenses e 
    LEFT JOIN expense_categories ec ON ec.id = e.category_id 
    $expWhere 
    ORDER BY e.expense_date DESC, e.id DESC 
    LIMIT $expPerPage OFFSET $expOffset
");
$st->execute($expParams);
$expenses = $st->fetchAll();

page_start('Expense Management');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Expense Management</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Track vendor bills, corporate receipts, and input tax credits for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="mt-4 sm:mt-0">
        <a href="expense_form" class="inline-flex items-center px-4 py-2.5 border border-transparent text-xs font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
            <i class="fa-solid fa-plus mr-1.5"></i>+ Record New Expense
        </a>
    </div>
</div>

<!-- Total Expense Card -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Recorded Expenses</span>
            <div class="text-2xl font-black text-rose-600 mt-1"><?=money($totalExpenses)?></div>
            <span class="text-xs font-bold text-slate-500"><?=count($expenses)?> Receipts Logged</span>
        </div>
        <div class="p-3 bg-rose-50 rounded-xl text-rose-600 text-xl font-bold">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>
</div>

<!-- Expenses Log (Desktop Table + Mobile Touch Cards) -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 class="text-base sm:text-lg font-bold text-slate-900">Expenses Log <span class="text-xs font-semibold text-slate-400">(<?=$totalExpRecords?> total)</span></h2>
        
        <form method="get" class="flex items-center space-x-2">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?=e($expSearch)?>" placeholder="Search vendor, category, ref #..." class="pl-8 pr-4 py-1.5 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 w-48 sm:w-64">
            </div>
            <?php if ($expSearch): ?>
                <a href="expenses" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-all">Clear</a>
            <?php endif; ?>
            <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-xs">Search</button>
        </form>
    </div>

    <!-- Desktop Table View (Hidden on Mobile) -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Expense Date</th>
                    <th class="px-6 py-3.5">Vendor / Supplier</th>
                    <th class="px-6 py-3.5">Category</th>
                    <th class="px-6 py-3.5">Payment Method</th>
                    <th class="px-6 py-3.5 text-right">Subtotal</th>
                    <th class="px-6 py-3.5 text-right">Tax Amount</th>
                    <th class="px-6 py-3.5 text-right">Total</th>
                    <th class="px-6 py-3.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300 block"></i>
                            No expenses recorded yet. Click '+ Record New Expense' to start.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($expenses as $ex): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500"><?=e(date('d M Y', strtotime($ex['expense_date'])))?></td>
                        <td class="px-6 py-4 font-bold text-slate-900"><?=e($ex['vendor_name'])?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700"><?=e($ex['category_name'] ?: 'General')?></span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-600"><?=e($ex['payment_method'] ?: 'Bank Transfer')?></td>
                        <td class="px-6 py-4 text-right text-slate-600"><?=money((float)$ex['subtotal'], $ex['currency'])?></td>
                        <td class="px-6 py-4 text-right text-slate-600"><?=money((float)$ex['tax_amount'], $ex['currency'])?></td>
                        <td class="px-6 py-4 text-right font-extrabold text-rose-600"><?=money((float)$ex['total'], $ex['currency'])?></td>
                        <td class="px-6 py-4 text-right">
                            <a href="expense_form?id=<?=$ex['id']?>" class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Touch Cards View (Visible only on Mobile) -->
    <div class="sm:hidden divide-y divide-slate-100">
        <?php if (empty($expenses)): ?>
            <div class="p-6 text-center text-slate-400">
                <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300 block"></i>
                <span class="font-bold text-slate-700 block text-xs">No expenses recorded yet.</span>
            </div>
        <?php endif; ?>
        <?php foreach ($expenses as $ex): ?>
            <div class="p-4 hover:bg-slate-50 transition-colors">
                <div class="flex items-center justify-between mb-1.5">
                    <div class="font-black text-slate-900 text-sm"><?=e($ex['vendor_name'])?></div>
                    <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-slate-100 text-slate-700"><?=e($ex['category_name'] ?: 'General')?></span>
                </div>
                <div class="flex justify-between items-end">
                    <div>
                        <div class="text-2xs text-slate-400 font-semibold"><i class="fa-regular fa-clock mr-1"></i>Date: <?=e(date('d M Y', strtotime($ex['expense_date'])))?></div>
                        <div class="text-2xs text-slate-500 font-medium"><?=e($ex['payment_method'] ?: 'Bank Transfer')?></div>
                    </div>
                    <div class="text-right">
                        <div class="font-black text-rose-600 text-base font-mono"><?=money((float)$ex['total'], $ex['currency'])?></div>
                        <a href="expense_form?id=<?=$ex['id']?>" class="mt-1 inline-block px-3 py-1 bg-slate-100 text-slate-700 text-2xs font-extrabold rounded-lg">Edit Receipt</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Expense Pagination Controls -->
    <?php if ($totalExpPages > 1): ?>
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs font-bold text-slate-600">
            <div>
                Showing <strong><?=min($expOffset + 1, $totalExpRecords)?></strong> to <strong><?=min($expOffset + $expPerPage, $totalExpRecords)?></strong> of <strong><?=$totalExpRecords?></strong> expenses
            </div>
            
            <div class="flex items-center space-x-1.5">
                <?php if ($expPage > 1): ?>
                    <a href="expenses?page=<?=$expPage - 1?><?=!empty($expSearch) ? '&search=' . urlencode($expSearch) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                        <i class="fa-solid fa-chevron-left mr-1"></i>Prev
                    </a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalExpPages; $p++): ?>
                    <a href="expenses?page=<?=$p?><?=!empty($expSearch) ? '&search=' . urlencode($expSearch) : ''?>" class="px-3 py-1.5 rounded-lg border <?= $p === $expPage ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' ?>">
                        <?=$p?>
                    </a>
                <?php endfor; ?>

                <?php if ($expPage < $totalExpPages): ?>
                    <a href="expenses?page=<?=$expPage + 1?><?=!empty($expSearch) ? '&search=' . urlencode($expSearch) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                        Next<i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php page_end(); ?>
