<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category' || $action === 'edit_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if (!$name) {
            $error = 'Expense category name is required.';
        } else {
            if ($action === 'add_category') {
                $st = $pdo->prepare("INSERT INTO expense_categories (tenant_id, name) VALUES (?, ?)");
                $st->execute([$tid, $name]);
                log_audit($pdo, 'create_expense_category', 'expense_categories', (int)$pdo->lastInsertId(), "Created expense category '$name'");
                flash('success', "Expense category '$name' created successfully!");
            } else {
                $st = $pdo->prepare("UPDATE expense_categories SET name = ? WHERE id = ? AND tenant_id = ?");
                $st->execute([$name, $catId, $tid]);
                log_audit($pdo, 'update_expense_category', 'expense_categories', $catId, "Updated expense category '$name'");
                flash('success', "Expense category '$name' updated successfully!");
            }
            redirect('expense_categories.php');
        }
    } elseif ($action === 'delete_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $st = $pdo->prepare("DELETE FROM expense_categories WHERE id = ? AND tenant_id = ?");
        $st->execute([$catId, $tid]);
        log_audit($pdo, 'delete_expense_category', 'expense_categories', $catId, "Deleted expense category #$catId");
        flash('success', 'Expense category deleted successfully.');
        redirect('expense_categories.php');
    }
}

// Fetch categories with total expenses & sum
$stCats = $pdo->prepare("
    SELECT c.*, 
           COUNT(e.id) AS exp_count, 
           COALESCE(SUM(e.total), 0) AS exp_total 
    FROM expense_categories c 
    LEFT JOIN expenses e ON e.category_id = c.id 
    WHERE c.tenant_id = ? 
    GROUP BY c.id 
    ORDER BY c.name ASC
");
$stCats->execute([$tid]);
$categories = $stCats->fetchAll();

// Overall KPI stats
$stOverall = $pdo->prepare("
    SELECT COUNT(DISTINCT c.id), 
           COUNT(e.id), 
           COALESCE(SUM(e.total), 0) 
    FROM expense_categories c 
    LEFT JOIN expenses e ON e.category_id = c.id 
    WHERE c.tenant_id = ?
");
$stOverall->execute([$tid]);
list($catCount, $totalExpenses, $totalAmount) = $stOverall->fetch(PDO::FETCH_NUM);
$catCount = (int)$catCount;
$totalExpenses = (int)$totalExpenses;
$totalAmount = (float)$totalAmount;

page_start('Expense Categories');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Expense Categories</h1>
        <p class="mt-1 text-sm text-slate-500">Organize operational overheads, tax deductions, and vendor expenses for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <button onclick="openCategoryModal()" class="inline-flex items-center px-4 py-2.5 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 shadow-md transition-all">
        <i class="fa-solid fa-plus mr-2"></i>Add Expense Category
    </button>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Configured Categories</span>
            <span class="text-2xl font-extrabold text-slate-900"><?=$catCount?></span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-tags"></i>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Categorized Expenses</span>
            <span class="text-2xl font-extrabold text-blue-600"><?=$totalExpenses?></span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-receipt"></i>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Total Category Spend</span>
            <span class="text-xl font-mono font-extrabold text-rose-600"><?=number_format($totalAmount, 2)?> <?=e(tenant()['currency'])?></span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-coins"></i>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Expense Categories List (<?=count($categories)?>)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Category Name</th>
                    <th class="px-6 py-3.5 text-center">Expenses Count</th>
                    <th class="px-6 py-3.5 text-right">Total Recorded Spend</th>
                    <th class="px-6 py-3.5 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-slate-400 text-sm font-semibold">
                            <i class="fa-solid fa-tags text-3xl text-slate-300 block mb-2"></i>
                            No expense categories created yet. Click "Add Expense Category" to create one.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($categories as $cat): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-900">
                            <i class="fa-solid fa-folder-closed text-amber-500 mr-2"></i><?=e($cat['name'])?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700">
                                <?=(int)$cat['exp_count']?> transactions
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-extrabold text-slate-900">
                            <?=number_format((float)$cat['exp_total'], 2)?> <?=e(tenant()['currency'])?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <button type="button" onclick='editCategory(<?=json_encode($cat)?>)' class="p-2 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Edit Category">
                                    <i class="fa-solid fa-pen-to-square text-sm"></i>
                                </button>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this expense category? Existing expenses in this category will become Uncategorized.');" class="inline">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?=$cat['id']?>">
                                    <button type="submit" class="p-2 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Delete Category">
                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Category -->
<div id="cat-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 id="cat-modal-title" class="text-lg font-bold text-slate-900">Add Expense Category</h3>
            <button onclick="closeCategoryModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" id="cat-action" value="add_category">
            <input type="hidden" name="category_id" id="cat-id" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Category Name *</label>
                <input type="text" name="name" id="cat-name" placeholder="e.g. Software & Cloud Services" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all">
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="closeCategoryModal()" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-extrabold text-white bg-gradient-to-r from-rose-500 to-rose-600 shadow-md">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('cat-modal-title').textContent = 'Add Expense Category';
    document.getElementById('cat-action').value = 'add_category';
    document.getElementById('cat-id').value = '0';
    document.getElementById('cat-name').value = '';
    document.getElementById('cat-modal').classList.remove('hidden');
}

function editCategory(cat) {
    document.getElementById('cat-modal-title').textContent = 'Edit Expense Category';
    document.getElementById('cat-action').value = 'edit_category';
    document.getElementById('cat-id').value = cat.id;
    document.getElementById('cat-name').value = cat.name;
    document.getElementById('cat-modal').classList.remove('hidden');
}

function closeCategoryModal() {
    document.getElementById('cat-modal').classList.add('hidden');
}
</script>

<?php page_end(); ?>
