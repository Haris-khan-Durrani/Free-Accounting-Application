<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$id = (int)($_GET['id'] ?? 0);
$quote = null;
$quoteItems = [];

if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM quotes WHERE id = ? AND tenant_id = ?");
    $st->execute([$id, $tid]);
    $quote = $st->fetch();

    if ($quote) {
        $stItems = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order ASC");
        $stItems->execute([$id]);
        $quoteItems = $stItems->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $quoteNumber = trim($_POST['quote_number'] ?? '');
    $clientId = (int)($_POST['client_id'] ?? 0);
    $quoteDate = $_POST['quote_date'] ?? date('Y-m-d');
    $validUntil = $_POST['valid_until'] ?: date('Y-m-d', strtotime('+14 days'));
    $currency = $_POST['currency'] ?? 'AED';
    $discountType = $_POST['discount_type'] ?? 'fixed';
    $discountValue = (float)($_POST['discount_value'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $descriptions = $_POST['description'] ?? [];
    $detailsList = $_POST['details'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['unit_price'] ?? [];

    $subtotal = 0;
    $itemsData = [];

    foreach ($descriptions as $idx => $desc) {
        $desc = trim($desc);
        if (!$desc) continue;

        $qty = max(0.01, (float)($qtys[$idx] ?? 1));
        $unitPrice = (float)($prices[$idx] ?? 0);
        $amount = $qty * $unitPrice;
        $subtotal += $amount;

        $itemsData[] = [
            'description' => $desc,
            'details' => trim($detailsList[$idx] ?? ''),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'amount' => $amount
        ];
    }

    $discountAmount = 0;
    if ($discountType === 'percent') {
        $discountAmount = ($subtotal * $discountValue) / 100;
    } else {
        $discountAmount = $discountValue;
    }
    $total = max(0, $subtotal - $discountAmount);

    if ($id > 0 && $quote) {
        $st = $pdo->prepare("UPDATE quotes SET quote_number=?, client_id=?, quote_date=?, valid_until=?, currency=?, subtotal=?, discount_type=?, discount_value=?, discount_amount=?, total=?, notes=? WHERE id=? AND tenant_id=?");
        $st->execute([$quoteNumber, $clientId, $quoteDate, $validUntil, $currency, $subtotal, $discountType, $discountValue, $discountAmount, $total, $notes, $id, $tid]);
        
        $pdo->prepare("DELETE FROM quote_items WHERE quote_id = ?")->execute([$id]);
        $stItem = $pdo->prepare("INSERT INTO quote_items (quote_id, description, details, qty, unit_price, amount, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($itemsData as $sort => $it) {
            $stItem->execute([$id, $it['description'], $it['details'], $it['qty'], $it['unit_price'], $it['amount'], $sort]);
        }
        log_audit($pdo, 'update_quote', 'quotes', $id, "Updated quote $quoteNumber");
        flash('success', 'Commercial proposal updated.');
    } else {
        if (!$quoteNumber) $quoteNumber = quote_number($pdo);

        $st = $pdo->prepare("INSERT INTO quotes (tenant_id, quote_number, client_id, quote_date, valid_until, status, currency, subtotal, discount_type, discount_value, discount_amount, total, notes) VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$tid, $quoteNumber, $clientId, $quoteDate, $validUntil, $currency, $subtotal, $discountType, $discountValue, $discountAmount, $total, $notes]);
        $newId = (int)$pdo->lastInsertId();

        $stItem = $pdo->prepare("INSERT INTO quote_items (quote_id, description, details, qty, unit_price, amount, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($itemsData as $sort => $it) {
            $stItem->execute([$newId, $it['description'], $it['details'], $it['qty'], $it['unit_price'], $it['amount'], $sort]);
        }
        log_audit($pdo, 'create_quote', 'quotes', $newId, "Created quote $quoteNumber");
        flash('success', 'New commercial proposal created successfully.');
    }
    redirect('quotes.php');
}

$stClients = $pdo->prepare("SELECT * FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
$stClients->execute([$tid]);
$clients = $stClients->fetchAll();

$nextQuoteNo = $quote ? $quote['quote_number'] : quote_number($pdo);

page_start($quote ? 'Edit Commercial Proposal' : 'Create Commercial Proposal');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight"><?=$quote ? 'Edit Commercial Proposal' : 'Create Commercial Proposal'?></h1>
        <p class="mt-1 text-sm text-slate-500">Draft commercial quotes and client estimates for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <a href="quotes.php" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
        ← Back to Proposals
    </a>
</div>

<form method="post" class="space-y-8">
    <?=csrf_field()?>

    <!-- Section 1: Header Details -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-file-signature text-amber-500 mr-2.5"></i> Proposal Header & Client Details
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Proposal Number *</label>
                <input type="text" name="quote_number" value="<?=e($nextQuoteNo)?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Select Client Account *</label>
                <div class="flex items-center space-x-2">
                    <select name="client_id" id="client_id_select" onchange="handleClientSelectChange(this)" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                        <option value="">-- Choose Client --</option>
                        <option value="__add_new__" class="font-bold text-amber-600 bg-amber-50">+ Add New Client...</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?=$c['id']?>" <?=($quote && $quote['client_id'] == $c['id']) ? 'selected' : ''?>><?=e($c['company_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="openAddClientModal()" title="Add New Client" class="shrink-0 px-3.5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 active:scale-95 text-white font-bold text-xs shadow-xs transition-all flex items-center space-x-1.5">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span class="hidden sm:inline">Add</span>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Proposal Date</label>
                <input type="date" name="quote_date" value="<?=e($quote['quote_date'] ?? date('Y-m-d'))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Valid Until Date</label>
                <input type="date" name="valid_until" value="<?=e($quote['valid_until'] ?? date('Y-m-d', strtotime('+14 days')))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Currency</label>
                <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <?php foreach (['AED', 'USD', 'EUR', 'GBP', 'SAR', 'INR', 'CAD', 'AUD'] as $curr): ?>
                        <option value="<?=$curr?>" <?=($quote['currency'] ?? tenant()['currency'])===$curr?'selected':''?>><?=$curr?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Section 2: Itemized Deliverables Table -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center justify-between">
            <span><i class="fa-solid fa-list-check text-blue-500 mr-2.5"></i> Deliverables & Scope Pricing</span>
            <button type="button" onclick="addProposalRow()" class="text-xs font-bold text-amber-600 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg border border-amber-200 transition-all">
                <i class="fa-solid fa-plus mr-1"></i>Add Row
            </button>
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="proposal-items-table">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                        <th class="px-4 py-3" style="width: 45%;">Item / Service Name</th>
                        <th class="px-4 py-3" style="width: 25%;">Scope Details</th>
                        <th class="px-4 py-3 text-center" style="width: 10%;">Qty</th>
                        <th class="px-4 py-3 text-right" style="width: 15%;">Unit Price</th>
                        <th class="px-4 py-3 text-center" style="width: 5%;">Del</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($quoteItems)): ?>
                        <tr>
                            <td class="px-4 py-3"><input type="text" name="description[]" placeholder="e.g. Software Implementation Services" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold"></td>
                            <td class="px-4 py-3"><input type="text" name="details[]" placeholder="Scope description" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="qty[]" value="1" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-center font-bold"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="unit_price[]" value="0.00" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-right font-mono font-bold"></td>
                            <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-rose-500 hover:text-rose-700 font-bold">×</button></td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($quoteItems as $it): ?>
                        <tr>
                            <td class="px-4 py-3"><input type="text" name="description[]" value="<?=e($it['description'])?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold"></td>
                            <td class="px-4 py-3"><input type="text" name="details[]" value="<?=e($it['details'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="qty[]" value="<?=e((string)(float)$it['qty'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-center font-bold"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="unit_price[]" value="<?=e((string)(float)$it['unit_price'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-right font-mono font-bold"></td>
                            <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-rose-500 hover:text-rose-700 font-bold">×</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 3: Discounts & Notes -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-percent text-emerald-500 mr-2.5"></i> Commercial Discounts & Scope Notes
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Discount Type</label>
                <select name="discount_type" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900">
                    <option value="fixed" <?=($quote['discount_type'] ?? 'fixed')==='fixed'?'selected':''?>>Fixed Cash Discount</option>
                    <option value="percent" <?=($quote['discount_type'] ?? 'fixed')==='percent'?'selected':''?>>Percentage (%)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Discount Amount / Percentage</label>
                <input type="number" step="0.01" name="discount_value" value="<?=e((string)(float)($quote['discount_value'] ?? 0))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold font-mono text-slate-900">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Commercial Terms & Notes</label>
                <textarea name="notes" rows="3" placeholder="Enter terms, scope notes, or delivery timeline..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900"><?=e($quote['notes'] ?? '')?></textarea>
            </div>
        </div>
    </div>

    <!-- Live Summary Card -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-6 shadow-xl border border-slate-700/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <h3 class="text-base font-extrabold text-white flex items-center space-x-2">
                    <i class="fa-solid fa-coins text-amber-400"></i>
                    <span>Real-Time Proposal Summary</span>
                </h3>
                <p class="text-2xs text-slate-400 mt-1">Calculated automatically as line items and discounts are entered.</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-right">
                <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700/80">
                    <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Subtotal</span>
                    <span id="live_subtotal" class="text-sm font-mono font-bold text-slate-200">0.00 AED</span>
                </div>
                <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700/80">
                    <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Discount</span>
                    <span id="live_discount" class="text-sm font-mono font-bold text-amber-400">0.00 AED</span>
                </div>
                <div class="bg-amber-500/20 p-3 rounded-xl border border-amber-500/40">
                    <span class="text-3xs uppercase font-extrabold text-amber-300 block tracking-wider mb-1">Grand Total</span>
                    <span id="live_grand_total" class="text-base font-mono font-black text-amber-400">0.00 AED</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Action -->
    <div class="flex items-center justify-end space-x-4">
        <a href="quotes.php" class="px-6 py-3 border border-slate-300 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 transition-all">Cancel</a>
        <button type="submit" class="inline-flex items-center px-8 py-3 border border-transparent text-base font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
            <i class="fa-solid fa-floppy-disk mr-2"></i><?=$quote ? 'Update Commercial Proposal' : 'Save Commercial Proposal'?>
        </button>
    </div>
</form>

<script>
function addProposalRow() {
    const tbody = document.querySelector('#proposal-items-table tbody');
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50/50 transition-colors';
    tr.innerHTML = `
        <td class="px-4 py-3"><input type="text" name="description[]" placeholder="Item description..." required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
        <td class="px-4 py-3"><input type="text" name="details[]" placeholder="Scope details..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
        <td class="px-4 py-3"><input type="number" step="0.01" name="qty[]" value="1" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-center font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
        <td class="px-4 py-3"><input type="number" step="0.01" name="unit_price[]" value="0.00" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-right font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
        <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove(); calculateProposalTotals();" class="w-8 h-8 mx-auto rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all flex items-center justify-center font-bold" title="Delete Row"><i class="fa-solid fa-trash-can text-xs"></i></button></td>
    `;
    tbody.appendChild(tr);
    calculateProposalTotals();
}

function calculateProposalTotals() {
    let subtotal = 0;
    const qtyInputs = document.querySelectorAll('input[name="qty[]"]');
    const priceInputs = document.querySelectorAll('input[name="unit_price[]"]');

    qtyInputs.forEach((qInput, i) => {
        const qty = parseFloat(qInput.value) || 0;
        const price = parseFloat(priceInputs[i]?.value) || 0;
        subtotal += (qty * price);
    });

    const discType = document.querySelector('select[name="discount_type"]')?.value || 'fixed';
    const discVal = parseFloat(document.querySelector('input[name="discount_value"]')?.value) || 0;
    let discountAmount = 0;
    if (discType === 'percent') {
        discountAmount = (subtotal * discVal) / 100;
    } else {
        discountAmount = discVal;
    }

    const grandTotal = Math.max(0, subtotal - discountAmount);
    const curr = document.querySelector('select[name="currency"]')?.value || 'AED';

    const formatNum = (num) => num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    document.getElementById('live_subtotal').textContent = `${formatNum(subtotal)} ${curr}`;
    document.getElementById('live_discount').textContent = `${formatNum(discountAmount)} ${curr}`;
    document.getElementById('live_grand_total').textContent = `${formatNum(grandTotal)} ${curr}`;
}

document.addEventListener("DOMContentLoaded", () => {
    document.body.addEventListener("input", (e) => {
        if (e.target.matches('input[name="qty[]"], input[name="unit_price[]"], input[name="discount_value"]')) {
            calculateProposalTotals();
        }
    });
    document.body.addEventListener("change", (e) => {
        if (e.target.matches('select[name="discount_type"], select[name="currency"]')) {
            calculateProposalTotals();
        }
    });
    calculateProposalTotals();
});
</script>

<?php
require __DIR__ . '/add_client_modal.php';
page_end();
?>
