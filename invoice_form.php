<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$id = (int)($_GET['id'] ?? 0);
$invoice = null;
$invoiceItems = [];

if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?");
    $st->execute([$id, $tid]);
    $invoice = $st->fetch();

    if ($invoice) {
        $stItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC");
        $stItems->execute([$id]);
        $invoiceItems = $stItems->fetchAll();
    }
}

$stClients = $pdo->prepare("SELECT * FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
$stClients->execute([$tid]);
$clients = $stClients->fetchAll();

$nextInvNo = $invoice ? $invoice['invoice_number'] : invoice_number($pdo);

page_start($invoice ? 'Edit Tax Invoice' : 'Create Tax Invoice');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight"><?=$invoice ? 'Edit Tax Invoice' : 'Create Tax Invoice'?></h1>
        <p class="mt-1 text-sm text-slate-500">Issue official tax invoices with automatic double-entry journal postings for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <a href="index.php" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
        ← Back to Dashboard
    </a>
</div>

<form action="invoice_save.php" method="post" class="space-y-8">
    <?=csrf_field()?>
    <input type="hidden" name="id" value="<?=e((string)$id)?>">

    <!-- Section 1: Header Details -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-file-invoice text-amber-500 mr-2.5"></i> Tax Invoice Header & Client Details
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Invoice Number *</label>
                <input type="text" name="invoice_number" value="<?=e($nextInvNo)?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Select Client Account *</label>
                <div class="flex items-center space-x-2">
                    <select name="client_id" id="client_id_select" onchange="handleClientSelectChange(this)" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                        <option value="">-- Choose Client --</option>
                        <option value="__add_new__" class="font-bold text-amber-600 bg-amber-50">+ Add New Client...</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?=$c['id']?>" <?=($invoice && $invoice['client_id'] == $c['id']) ? 'selected' : ''?>><?=e($c['company_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="openAddClientModal()" title="Add New Client" class="shrink-0 px-3.5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 active:scale-95 text-white font-bold text-xs shadow-xs transition-all flex items-center space-x-1.5">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span class="hidden sm:inline">Add</span>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Invoice Date</label>
                <input type="date" name="invoice_date" value="<?=e($invoice['invoice_date'] ?? date('Y-m-d'))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Payment Due Date</label>
                <input type="date" name="valid_until" value="<?=e($invoice['valid_until'] ?? date('Y-m-d', strtotime('+14 days')))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Billing Currency</label>
                <select name="currency" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <?php foreach (['AED', 'USD', 'EUR', 'GBP', 'SAR', 'INR', 'CAD', 'AUD'] as $curr): ?>
                        <option value="<?=$curr?>" <?=($invoice['currency'] ?? tenant()['currency'])===$curr?'selected':''?>><?=$curr?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Invoice Status</label>
                <select name="status" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="draft" <?=($invoice['status'] ?? 'draft')==='draft'?'selected':''?>>Draft</option>
                    <option value="sent" <?=($invoice['status'] ?? 'draft')==='sent'?'selected':''?>>Sent (Unpaid)</option>
                    <option value="paid" <?=($invoice['status'] ?? 'draft')==='paid'?'selected':''?>>Paid (Settled)</option>
                    <option value="overdue" <?=($invoice['status'] ?? 'draft')==='overdue'?'selected':''?>>Overdue</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Design Template Style</label>
                <select name="template_id" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="onesol_executive_gold">1. OneSol Executive Gold (Flagship)</option>
                    <option value="modern_minimal">2. Modern Minimalist</option>
                    <option value="corporate_executive">3. Corporate Executive</option>
                    <option value="creative_vibrant">4. Creative Vibrant</option>
                    <option value="sleek_dark">5. Sleek Dark Mode</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Section 2: Items Table -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center justify-between">
            <span><i class="fa-solid fa-list-check text-amber-500 mr-2.5"></i> Line Items & Pricing Breakdown</span>
            <button type="button" onclick="addInvoiceRow()" class="text-xs font-extrabold text-amber-600 bg-amber-50 hover:bg-amber-100 hover:text-amber-700 px-3.5 py-1.5 rounded-xl border border-amber-200/80 transition-all flex items-center gap-1.5 shadow-2xs">
                <i class="fa-solid fa-plus text-2xs"></i> Add Row
            </button>
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="invoice-items-table">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                        <th class="px-4 py-3" style="width: 45%;">Item / Service Description</th>
                        <th class="px-4 py-3" style="width: 25%;">Details</th>
                        <th class="px-4 py-3 text-center" style="width: 10%;">Qty</th>
                        <th class="px-4 py-3 text-right" style="width: 15%;">Unit Price</th>
                        <th class="px-4 py-3 text-center" style="width: 5%;">Del</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($invoiceItems)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3"><input type="text" name="description[]" placeholder="Item description..." required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="text" name="details[]" placeholder="Additional details..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="qty[]" value="1" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-center font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="unit_price[]" value="0.00" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-right font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="w-8 h-8 mx-auto rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all flex items-center justify-center font-bold" title="Delete Row"><i class="fa-solid fa-trash-can text-xs"></i></button></td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($invoiceItems as $it): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3"><input type="text" name="description[]" value="<?=e($it['description'])?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="text" name="details[]" value="<?=e($it['details'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="qty[]" value="<?=e((string)(float)$it['qty'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-center font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="unit_price[]" value="<?=e((string)(float)$it['unit_price'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-right font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove()" class="w-8 h-8 mx-auto rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all flex items-center justify-center font-bold" title="Delete Row"><i class="fa-solid fa-trash-can text-xs"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 3: Discounts & VAT -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-calculator text-emerald-500 mr-2.5"></i> Tax & Discount Calculations
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Discount Type</label>
                <select name="discount_type" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="fixed" <?=($invoice['discount_type'] ?? 'fixed')==='fixed'?'selected':''?>>Fixed Cash Discount</option>
                    <option value="percent" <?=($invoice['discount_type'] ?? 'fixed')==='percent'?'selected':''?>>Percentage (%)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Discount Amount / Percentage</label>
                <input type="number" step="0.01" name="discount_value" value="<?=e((string)(float)($invoice['discount_value'] ?? 0))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">VAT Tax Amount</label>
                <input type="number" step="0.01" name="tax_amount" value="<?=e((string)(float)($invoice['tax_amount'] ?? 0))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Invoice Public Notes & Payment Terms</label>
                <textarea name="notes" rows="3" placeholder="Thank you for your business. Payment terms..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"><?=e($invoice['notes'] ?? '')?></textarea>
            </div>
        </div>
    </div>

    <!-- Section 4: Live Summary Card -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-6 shadow-xl border border-slate-700/80">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <h3 class="text-base font-extrabold text-white flex items-center space-x-2">
                    <i class="fa-solid fa-coins text-amber-400"></i>
                    <span>Real-Time Invoice Summary</span>
                </h3>
                <p class="text-2xs text-slate-400 mt-1">Calculated automatically as line items and discounts are entered.</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-right">
                <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700/80">
                    <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Subtotal</span>
                    <span id="live_subtotal" class="text-sm font-mono font-bold text-slate-200">0.00 AED</span>
                </div>
                <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700/80">
                    <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">Discount</span>
                    <span id="live_discount" class="text-sm font-mono font-bold text-amber-400">0.00 AED</span>
                </div>
                <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-700/80">
                    <span class="text-3xs uppercase font-extrabold text-slate-400 block tracking-wider mb-1">VAT Tax</span>
                    <span id="live_tax" class="text-sm font-mono font-bold text-slate-200">0.00 AED</span>
                </div>
                <div class="bg-amber-500/20 p-3 rounded-xl border border-amber-500/40">
                    <span class="text-3xs uppercase font-extrabold text-amber-300 block tracking-wider mb-1">Grand Total</span>
                    <span id="live_grand_total" class="text-base font-mono font-black text-amber-400">0.00 AED</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end space-x-4">
        <a href="index.php" class="px-6 py-3 border border-slate-300 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 transition-all">Cancel</a>
        <button type="submit" class="inline-flex items-center px-8 py-3 border border-transparent text-base font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
            <i class="fa-solid fa-floppy-disk mr-2"></i><?=$invoice ? 'Update Tax Invoice' : 'Save & Issue Invoice'?>
        </button>
    </div>
</form>

<script>
function addInvoiceRow() {
    const tbody = document.querySelector('#invoice-items-table tbody');
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50/50 transition-colors';
    tr.innerHTML = `
        <td class="px-4 py-3"><input type="text" name="description[]" placeholder="Item description..." required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
        <td class="px-4 py-3"><input type="text" name="details[]" placeholder="Additional details..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
        <td class="px-4 py-3"><input type="number" step="0.01" name="qty[]" value="1" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-center font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
        <td class="px-4 py-3"><input type="number" step="0.01" name="unit_price[]" value="0.00" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-right font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
        <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove(); calculateTotals();" class="w-8 h-8 mx-auto rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all flex items-center justify-center font-bold" title="Delete Row"><i class="fa-solid fa-trash-can text-xs"></i></button></td>
    `;
    tbody.appendChild(tr);
    calculateTotals();
}

function calculateTotals() {
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

    const taxAmount = parseFloat(document.querySelector('input[name="tax_amount"]')?.value) || 0;
    const grandTotal = Math.max(0, subtotal - discountAmount + taxAmount);
    const curr = document.querySelector('select[name="currency"]')?.value || 'AED';

    const formatNum = (num) => num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    document.getElementById('live_subtotal').textContent = `${formatNum(subtotal)} ${curr}`;
    document.getElementById('live_discount').textContent = `${formatNum(discountAmount)} ${curr}`;
    document.getElementById('live_tax').textContent = `${formatNum(taxAmount)} ${curr}`;
    document.getElementById('live_grand_total').textContent = `${formatNum(grandTotal)} ${curr}`;
}

document.addEventListener("DOMContentLoaded", () => {
    document.body.addEventListener("input", (e) => {
        if (e.target.matches('input[name="qty[]"], input[name="unit_price[]"], input[name="discount_value"], input[name="tax_amount"]')) {
            calculateTotals();
        }
    });
    document.body.addEventListener("change", (e) => {
        if (e.target.matches('select[name="discount_type"], select[name="currency"]')) {
            calculateTotals();
        }
    });
    calculateTotals();
});
</script>

<?php
require __DIR__ . '/add_client_modal.php';
page_end(); 
?>
