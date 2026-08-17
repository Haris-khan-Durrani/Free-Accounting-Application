<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$st = $pdo->prepare("SELECT e.*, ec.name category_name FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id WHERE e.tenant_id = ? ORDER BY e.expense_date DESC, e.id DESC");
$st->execute([$tid]);
$expenses = $st->fetchAll();

$totalExpenses = 0;
foreach ($expenses as $ex) $totalExpenses += (float)$ex['total'];

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
    <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base sm:text-lg font-bold text-slate-900">Expenses Log (<?=count($expenses)?>)</h2>
        <a href="expense_form" class="text-xs font-extrabold text-amber-600 hover:underline">+ Record Expense</a>
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
</div>

<?php page_end(); ?>
