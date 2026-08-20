<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$error = '';
$success = '';

// Run migration to ensure items table exists
require_once __DIR__ . '/migrate.php';
run_migrations($pdo);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item' || $action === 'edit_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['product', 'service'], true) ? $_POST['type'] : 'service';
        $description = trim($_POST['description'] ?? '');
        $unitPrice = max(0, (float)($_POST['unit_price'] ?? 0));
        $unit = trim($_POST['unit'] ?? 'unit');

        if (!$name) {
            $error = 'Product / Service name is required.';
        } else {
            if ($action === 'add_item') {
                $st = $pdo->prepare("INSERT INTO items (tenant_id, name, sku, type, description, unit_price, unit) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $st->execute([$tid, $name, $sku, $type, $description, $unitPrice, $unit]);
                log_audit($pdo, 'create_item', 'items', (int)$pdo->lastInsertId(), "Created catalog item '$name' ($type)");
                flash('success', "Catalog item '$name' created successfully!");
            } else {
                $st = $pdo->prepare("UPDATE items SET name = ?, sku = ?, type = ?, description = ?, unit_price = ?, unit = ? WHERE id = ? AND tenant_id = ?");
                $st->execute([$name, $sku, $type, $description, $unitPrice, $unit, $itemId, $tid]);
                log_audit($pdo, 'update_item', 'items', $itemId, "Updated catalog item '$name'");
                flash('success', "Catalog item '$name' updated successfully!");
            }
            redirect('items.php');
        }
    } elseif ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $st = $pdo->prepare("DELETE FROM items WHERE id = ? AND tenant_id = ?");
        $st->execute([$itemId, $tid]);
        log_audit($pdo, 'delete_item', 'items', $itemId, "Deleted item #$itemId");
        flash('success', 'Catalog item deleted successfully.');
        redirect('items.php');
    }
}

// Fetch Catalog Data
$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';

$where = ["tenant_id = ?"];
$params = [$tid];

if ($search !== '') {
    $where[] = "(name LIKE ? OR sku LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($typeFilter !== '' && in_array($typeFilter, ['product', 'service'], true)) {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}

$whereClause = implode(' AND ', $where);
$stList = $pdo->prepare("SELECT * FROM items WHERE $whereClause ORDER BY id DESC");
$stList->execute($params);
$items = $stList->fetchAll();

// KPI Stats
$stTotal = $pdo->prepare("SELECT COUNT(*), SUM(CASE WHEN type='service' THEN 1 ELSE 0 END), SUM(CASE WHEN type='product' THEN 1 ELSE 0 END), AVG(unit_price) FROM items WHERE tenant_id = ?");
$stTotal->execute([$tid]);
list($countTotal, $countServices, $countProducts, $avgPrice) = $stTotal->fetch(PDO::FETCH_NUM);
$countTotal = (int)$countTotal;
$countServices = (int)$countServices;
$countProducts = (int)$countProducts;
$avgPrice = (float)$avgPrice;

page_start('Product & Service Catalog');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Product & Service Catalog</h1>
        <p class="mt-1 text-sm text-slate-500">Manage re-usable items, standard pricing, and services for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <button onclick="openItemModal()" class="inline-flex items-center px-4 py-2.5 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 shadow-md transition-all">
        <i class="fa-solid fa-plus mr-2"></i>Add Product / Service
    </button>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Total Catalog Items</span>
            <span class="text-2xl font-extrabold text-slate-900"><?=$countTotal?></span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Services</span>
            <span class="text-2xl font-extrabold text-blue-600"><?=$countServices?></span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-concierge-bell"></i>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Physical Products</span>
            <span class="text-2xl font-extrabold text-purple-600"><?=$countProducts?></span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-box"></i>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs flex items-center justify-between">
        <div>
            <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Average Unit Price</span>
            <span class="text-xl font-mono font-extrabold text-slate-900"><?=number_format($avgPrice, 2)?> <?=e(tenant()['currency'])?></span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-lg">
            <i class="fa-solid fa-tag"></i>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-xs mb-8 no-print">
    <form method="get" class="flex flex-wrap items-center gap-4">
        <div class="flex-grow min-w-[240px]">
            <input type="text" name="search" value="<?=e($search)?>" placeholder="Search by SKU, item name, or description..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
        </div>
        <div>
            <select name="type" class="rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                <option value="">All Item Types</option>
                <option value="service" <?=$typeFilter==='service'?'selected':''?>>Services</option>
                <option value="product" <?=$typeFilter==='product'?'selected':''?>>Products</option>
            </select>
        </div>
        <div>
            <button type="submit" class="px-5 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition-all">Filter Catalog</button>
            <?php if ($search !== '' || $typeFilter !== ''): ?>
                <a href="items.php" class="ml-2 text-xs font-semibold text-slate-500 hover:text-slate-800">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Data Table -->
<div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">SKU / Code</th>
                    <th class="px-6 py-3.5">Product / Service Name</th>
                    <th class="px-6 py-3.5">Type</th>
                    <th class="px-6 py-3.5 text-right">Standard Unit Price</th>
                    <th class="px-6 py-3.5 text-center">Unit</th>
                    <th class="px-6 py-3.5 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 text-sm font-semibold">
                            <i class="fa-solid fa-boxes-stacked text-3xl text-slate-300 block mb-2"></i>
                            No products or services found in your catalog.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-4 font-mono font-bold text-xs text-slate-500"><?=e($item['sku'] ?: '—')?></td>
                        <td class="px-6 py-4">
                            <div class="font-extrabold text-slate-900"><?=e($item['name'])?></div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="text-xs text-slate-400 line-clamp-1 mt-0.5"><?=e($item['description'])?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($item['type'] === 'service'): ?>
                                <span class="px-2.5 py-1 rounded-lg text-3xs font-extrabold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200/80"><i class="fa-solid fa-concierge-bell mr-1"></i>Service</span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-lg text-3xs font-extrabold uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200/80"><i class="fa-solid fa-box mr-1"></i>Product</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-extrabold text-slate-900">
                            <?=number_format((float)$item['unit_price'], 2)?> <?=e(tenant()['currency'])?>
                        </td>
                        <td class="px-6 py-4 text-center text-xs font-semibold text-slate-500"><?=e($item['unit'] ?: 'unit')?></td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <button type="button" onclick='editItem(<?=json_encode($item)?>)' class="p-2 rounded-lg text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 transition-colors" title="Edit Item">
                                    <i class="fa-solid fa-pen-to-square text-sm"></i>
                                </button>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this catalog item?');" class="inline">
                                    <?=csrf_field()?>
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="item_id" value="<?=$item['id']?>">
                                    <button type="submit" class="p-2 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Delete Item">
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

<!-- Modal: Add / Edit Item -->
<div id="item-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 id="modal-title" class="text-lg font-bold text-slate-900">Add Catalog Item</h3>
            <button onclick="closeItemModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" id="item-action" value="add_item">
            <input type="hidden" name="item_id" id="item-id" value="0">

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Item Classification *</label>
                    <select name="type" id="item-type" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900">
                        <option value="service">Service</option>
                        <option value="product">Physical Product</option>
                    </select>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">SKU / Item Code</label>
                    <input type="text" name="sku" id="item-sku" placeholder="e.g. PRD-001" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Product / Service Name *</label>
                <input type="text" name="name" id="item-name" placeholder="e.g. Web Development Services" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Standard Price (<?=e(tenant()['currency'])?>) *</label>
                    <input type="number" step="0.01" name="unit_price" id="item-price" placeholder="0.00" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Unit of Measure</label>
                    <input type="text" name="unit" id="item-unit" value="unit" placeholder="e.g. hrs, pcs, unit" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Description & Scope Details</label>
                <textarea name="description" id="item-description" rows="3" placeholder="Item description for default invoice rows..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900"></textarea>
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="closeItemModal()" class="px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-extrabold text-white bg-gradient-to-r from-emerald-500 to-emerald-600 shadow-md">Save Catalog Item</button>
            </div>
        </form>
    </div>
</div>

<script>
function openItemModal() {
    document.getElementById('modal-title').textContent = 'Add Catalog Item';
    document.getElementById('item-action').value = 'add_item';
    document.getElementById('item-id').value = '0';
    document.getElementById('item-name').value = '';
    document.getElementById('item-sku').value = '';
    document.getElementById('item-type').value = 'service';
    document.getElementById('item-price').value = '0.00';
    document.getElementById('item-unit').value = 'unit';
    document.getElementById('item-description').value = '';
    document.getElementById('item-modal').classList.remove('hidden');
}

function editItem(item) {
    document.getElementById('modal-title').textContent = 'Edit Catalog Item';
    document.getElementById('item-action').value = 'edit_item';
    document.getElementById('item-id').value = item.id;
    document.getElementById('item-name').value = item.name;
    document.getElementById('item-sku').value = item.sku || '';
    document.getElementById('item-type').value = item.type;
    document.getElementById('item-price').value = item.unit_price;
    document.getElementById('item-unit').value = item.unit || 'unit';
    document.getElementById('item-description').value = item.description || '';
    document.getElementById('item-modal').classList.remove('hidden');
}

function closeItemModal() {
    document.getElementById('item-modal').classList.add('hidden');
}
</script>

<?php page_end(); ?>
