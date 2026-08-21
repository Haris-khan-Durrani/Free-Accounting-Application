<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$id = (int)($_GET['id'] ?? 0);
$expense = null;

if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND tenant_id = ?");
    $st->execute([$id, $tid]);
    $expense = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $vendorName = trim($_POST['vendor_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $currency = $_POST['currency'] ?? 'AED';
    $paymentMethod = $_POST['payment_method'] ?? 'Bank Transfer';
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $taxAmount = (float)($_POST['tax_amount'] ?? 0);
    $total = $subtotal + $taxAmount;
    $notes = trim($_POST['notes'] ?? '');

    if (!$vendorName || $total <= 0) {
        flash('error', 'Vendor name and positive amount are required.');
        redirect('expense_form.php' . ($id ? '?id='.$id : ''));
    }

    if ($id > 0 && $expense) {
        $st = $pdo->prepare("UPDATE expenses SET category_id=?, vendor_name=?, expense_date=?, subtotal=?, tax_amount=?, total=?, currency=?, payment_method=?, notes=? WHERE id=? AND tenant_id=?");
        $st->execute([$categoryId, $vendorName, $expenseDate, $subtotal, $taxAmount, $total, $currency, $paymentMethod, $notes, $id, $tid]);
        log_audit($pdo, 'update_expense', 'expenses', $id, "Updated expense for $vendorName");
        flash('success', 'Expense record updated.');
    } else {
        $st = $pdo->prepare("INSERT INTO expenses (tenant_id, category_id, vendor_name, expense_date, subtotal, tax_amount, total, currency, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$tid, $categoryId, $vendorName, $expenseDate, $subtotal, $taxAmount, $total, $currency, $paymentMethod, $notes]);
        $newId = (int)$pdo->lastInsertId();
        log_audit($pdo, 'create_expense', 'expenses', $newId, "Recorded expense for $vendorName ($total $currency)");
        flash('success', 'New business expense recorded successfully.');
    }
    redirect('expenses.php');
}

$stCats = $pdo->prepare("SELECT * FROM expense_categories WHERE tenant_id = ? ORDER BY name ASC");
$stCats->execute([$tid]);
$categories = $stCats->fetchAll();

page_start($expense ? 'Edit Expense Record' : 'Record New Expense');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight"><?=$expense ? 'Edit Expense Record' : 'Record New Expense'?></h1>
        <p class="mt-1 text-sm text-slate-500">Record corporate expenses, vendor receipts, and tax credits for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <a href="expenses.php" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
        ← Back to Expenses
    </a>
</div>

<form method="post" class="space-y-8 max-w-4xl mx-auto pb-28 lg:pb-8">
    <?=csrf_field()?>

    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-receipt text-rose-500 mr-2.5"></i> Vendor & Expense Details
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Vendor / Supplier Name *</label>
                <input type="text" name="vendor_name" value="<?=e($expense['vendor_name'] ?? '')?>" placeholder="e.g. AWS Amazon, Dewa, Du Telecom" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Expense Category</label>
                    <button type="button" onclick="openQuickCategoryModal()" class="text-xs font-extrabold text-amber-600 hover:text-amber-700 hover:underline flex items-center cursor-pointer">
                        <i class="fa-solid fa-plus-circle mr-1 text-2xs"></i>+ New Category
                    </button>
                </div>
                <select id="expense_category_select" name="category_id" onchange="if(this.value==='__add_new_cat__'){openQuickCategoryModal(); this.value='';}" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <option value="">-- General Expense --</option>
                    <option value="__add_new_cat__" class="font-extrabold text-amber-600 bg-amber-50">+ Add New Category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?=$cat['id']?>" <?=($expense && $expense['category_id'] == $cat['id']) ? 'selected' : ''?>><?=e($cat['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Expense Date *</label>
                <input type="date" name="expense_date" value="<?=e($expense['expense_date'] ?? date('Y-m-d'))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Currency & Payment Method</label>
                <div class="grid grid-cols-2 gap-3">
                    <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-base sm:text-sm font-bold text-slate-900">
                        <?php foreach (['AED', 'USD', 'EUR', 'GBP', 'SAR', 'INR', 'CAD', 'AUD'] as $curr): ?>
                            <option value="<?=$curr?>" <?=($expense['currency'] ?? tenant()['currency'])===$curr?'selected':''?>><?=$curr?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="payment_method" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-base sm:text-sm font-bold text-slate-900">
                        <option value="Bank Transfer" <?=($expense['payment_method'] ?? '')==='Bank Transfer'?'selected':''?>>Bank Transfer</option>
                        <option value="Corporate Credit Card" <?=($expense['payment_method'] ?? '')==='Corporate Credit Card'?'selected':''?>>Credit Card</option>
                        <option value="Petty Cash" <?=($expense['payment_method'] ?? '')==='Petty Cash'?'selected':''?>>Petty Cash</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Subtotal Amount (Excl. Tax) *</label>
                <input type="number" step="0.01" name="subtotal" value="<?=e((string)(float)($expense['subtotal'] ?? 0))?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Recoverable Tax / VAT Amount</label>
                <input type="number" step="0.01" name="tax_amount" value="<?=e((string)(float)($expense['tax_amount'] ?? 0))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Expense Notes / Memo</label>
                <textarea name="notes" rows="3" placeholder="Reference invoice or receipt description..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-base sm:text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"><?=e($expense['notes'] ?? '')?></textarea>
            </div>
        </div>
    </div>

    <!-- Submit Action (Mobile Safe Layout) -->
    <div class="flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-4 gap-3">
        <a href="expenses.php" class="w-full sm:w-auto text-center px-6 py-3.5 rounded-xl text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-all">Cancel</a>
        <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-8 py-3.5 border border-transparent text-base font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg transition-all">
            <i class="fa-solid fa-floppy-disk mr-2"></i><?=$expense ? 'Update Expense Record' : 'Save Expense Record'?>
        </button>
    </div>
</form>

<!-- Quick Add Category Modal -->
<div id="quick-category-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-[200] hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-md overflow-hidden transform transition-all">
        <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
            <h3 class="font-extrabold text-sm flex items-center"><i class="fa-solid fa-tags text-amber-400 mr-2"></i>Add New Expense Category</h3>
            <button type="button" onclick="closeQuickCategoryModal()" class="text-slate-400 hover:text-white font-bold text-lg">×</button>
        </div>
        <div class="p-6">
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Category Name *</label>
            <input type="text" id="quick_cat_name_input" placeholder="e.g. Subscriptions & Software, Logistics" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            <p id="quick_cat_error" class="text-xs text-rose-600 font-bold mt-2 hidden"></p>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeQuickCategoryModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl">Cancel</button>
                <button type="button" id="save_quick_cat_btn" onclick="saveQuickCategory()" class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs rounded-xl shadow-md flex items-center">
                    <i class="fa-solid fa-check mr-1.5"></i>Save Category
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openQuickCategoryModal() {
    document.getElementById('quick-category-modal').classList.remove('hidden');
    const input = document.getElementById('quick_cat_name_input');
    input.value = '';
    document.getElementById('quick_cat_error').classList.add('hidden');
    setTimeout(() => input.focus(), 100);
}
function closeQuickCategoryModal() {
    document.getElementById('quick-category-modal').classList.add('hidden');
}
async function saveQuickCategory() {
    const input = document.getElementById('quick_cat_name_input');
    const errEl = document.getElementById('quick_cat_error');
    const btn = document.getElementById('save_quick_cat_btn');
    const name = input.value.trim();

    if (!name) {
        errEl.textContent = 'Please enter a category name.';
        errEl.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Saving...';

    try {
        const res = await fetch('api_create_category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name })
        });
        const data = await res.json();
        
        if (data.success) {
            const select = document.getElementById('expense_category_select');
            
            // Check if option already exists
            let existingOpt = select.querySelector(`option[value="${data.id}"]`);
            if (!existingOpt) {
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = data.name;
                select.appendChild(opt);
            }
            select.value = data.id;
            closeQuickCategoryModal();
        } else {
            errEl.textContent = data.error || 'Failed to save category.';
            errEl.classList.remove('hidden');
        }
    } catch (e) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check mr-1.5"></i>Save Category';
    }
}
</script>

<?php page_end(); ?>
