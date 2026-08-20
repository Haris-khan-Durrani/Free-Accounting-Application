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

$stCatalog = $pdo->prepare("SELECT * FROM items WHERE tenant_id = ? AND is_active = 1 ORDER BY name ASC");
$stCatalog->execute([$tid]);
$catalogItems = $stCatalog->fetchAll();

$stTax = $pdo->prepare("SELECT * FROM tax_rates WHERE tenant_id = ? AND is_active = 1 ORDER BY is_default DESC, name ASC");
$stTax->execute([$tid]);
$configuredTaxRates = $stTax->fetchAll();

$clientMap = [];
foreach ($clients as $c) {
    $clientMap[$c['id']] = [
        'country' => $c['country'] ?? 'United Arab Emirates',
        'tax_number' => $c['tax_number'] ?? '',
        'currency' => $c['currency'] ?? 'AED'
    ];
}

$nextInvNo = $invoice ? $invoice['invoice_number'] : invoice_number($pdo);

page_start($invoice ? 'Edit Tax Invoice' : 'Create Tax Invoice');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight"><?=$invoice ? 'Edit Tax Invoice' : 'Create Tax Invoice'?></h1>
        <p class="mt-1 text-sm text-slate-500">Issue official tax invoices with automatic country VAT detection & double-entry journal postings for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <a href="index.php" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
        ← Back to Dashboard
    </a>
</div>

<form action="invoice_save.php" method="post" class="space-y-8 pb-32 lg:pb-8">
    <?=csrf_field()?>
    <input type="hidden" name="id" value="<?=e((string)$id)?>">

    <!-- Section 1: Header Details -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center justify-between">
            <span class="flex items-center"><i class="fa-solid fa-file-invoice text-amber-500 mr-2.5"></i> Tax Invoice Header & Client Details</span>
            <span id="client_trn_badge" class="text-2xs font-extrabold px-3 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                <i class="fa-solid fa-id-card mr-1"></i>No Client Selected
            </span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Invoice Number *</label>
                <input type="text" name="invoice_number" value="<?=e($nextInvNo)?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Select Client Account *</label>
                <div class="flex items-center space-x-2">
                    <select name="client_id" id="client_id_select" onchange="updateClientCountryVat(); handleClientSelectChange(this);" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
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
                <?php
                $currentTpl = $invoice['template_id'] ?? $brand['default_invoice_template'] ?? 'onesol_executive_gold';
                $availableTpls = [
                    'custom_drag_drop' => '⭐ Custom Drag & Drop Builder Design',
                    'onesol_executive_gold' => '1. OneSol Executive Gold (Flagship)',
                    'modern_minimal' => '2. Modern Minimalist',
                    'corporate_executive' => '3. Corporate Executive',
                    'creative_vibrant' => '4. Creative Vibrant',
                    'sleek_dark' => '5. Sleek Dark Mode',
                    'elegant_serif' => '6. Elegant Serif',
                    'compact_thermal' => '7. Thermal POS Receipt',
                    'tech_glassmorphism' => '8. Tech Glassmorphism',
                    'swiss_grid' => '9. Swiss Grid Design',
                    'borderless_clean' => '10. Borderless Clean',
                    'twocolumn_split' => '11. Two-Column Split'
                ];
                ?>
                <select name="template_id" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <?php foreach ($availableTpls as $tKey => $tLabel): ?>
                        <option value="<?=$tKey?>" <?=$currentTpl === $tKey ? 'selected' : ''?>><?=e($tLabel)?></option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>
    </div>

    <!-- Section 2: Items Table -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center justify-between">
            <span class="flex items-center"><i class="fa-solid fa-list-check text-amber-500 mr-2.5"></i> Line Items & Pricing Breakdown</span>
            <div class="flex items-center space-x-2">
                <button type="button" onclick="openAddItemModal()" class="text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-xl border border-emerald-200 transition-all flex items-center gap-1.5 shadow-2xs">
                    <i class="fa-solid fa-box-open text-xs"></i> New Catalog Item
                </button>
                <button type="button" onclick="addInvoiceRow()" class="text-xs font-extrabold text-amber-600 bg-amber-50 hover:bg-amber-100 hover:text-amber-700 px-3.5 py-1.5 rounded-xl border border-amber-200/80 transition-all flex items-center gap-1.5 shadow-2xs">
                    <i class="fa-solid fa-plus text-2xs"></i> Add Row
                </button>
            </div>
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="invoice-items-table">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-2xs font-extrabold text-slate-400 uppercase tracking-wider">
                        <th class="px-4 py-3" style="width: 48%;">Item / Service Description</th>
                        <th class="px-4 py-3" style="width: 22%;">Scope Details</th>
                        <th class="px-4 py-3 text-center" style="width: 10%;">Qty</th>
                        <th class="px-4 py-3 text-right" style="width: 15%;">Unit Price</th>
                        <th class="px-4 py-3 text-center" style="width: 5%;">Del</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($invoiceItems)): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="space-y-1.5">
                                    <select onchange="handleCatalogItemChange(this)" class="catalog-item-select w-full rounded-xl border border-slate-300 bg-slate-50/90 px-3 py-1.5 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                                        <option value="">-- Choose Catalog Item or Type Below --</option>
                                        <option value="__add_new_item__" class="font-bold text-emerald-600 bg-emerald-50">+ Add New Catalog Item...</option>
                                        <?php foreach ($catalogItems as $cItem): ?>
                                            <option value="<?=$cItem['id']?>">
                                                <?=e(($cItem['sku'] ? '[' . $cItem['sku'] . '] ' : '') . $cItem['name'] . ' — ' . number_format((float)$cItem['unit_price'], 2))?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="description[]" placeholder="Item description..." required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                                </div>
                            </td>
                            <td class="px-4 py-3"><input type="text" name="details[]" placeholder="Additional details..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="qty[]" value="1" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-center font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="unit_price[]" value="0.00" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-right font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove(); calculateTotals();" class="w-8 h-8 mx-auto rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all flex items-center justify-center font-bold" title="Delete Row"><i class="fa-solid fa-trash-can text-xs"></i></button></td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($invoiceItems as $it): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="space-y-1.5">
                                    <select onchange="handleCatalogItemChange(this)" class="catalog-item-select w-full rounded-xl border border-slate-300 bg-slate-50/90 px-3 py-1.5 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                                        <option value="">-- Choose Catalog Item or Type Below --</option>
                                        <option value="__add_new_item__" class="font-bold text-emerald-600 bg-emerald-50">+ Add New Catalog Item...</option>
                                        <?php foreach ($catalogItems as $cItem): ?>
                                            <option value="<?=$cItem['id']?>">
                                                <?=e(($cItem['sku'] ? '[' . $cItem['sku'] . '] ' : '') . $cItem['name'] . ' — ' . number_format((float)$cItem['unit_price'], 2))?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="description[]" value="<?=e($it['description'])?>" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                                </div>
                            </td>
                            <td class="px-4 py-3"><input type="text" name="details[]" value="<?=e($it['details'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="qty[]" value="<?=e((string)(float)$it['qty'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-center font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3"><input type="number" step="0.01" name="unit_price[]" value="<?=e((string)(float)$it['unit_price'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-right font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></td>
                            <td class="px-4 py-3 text-center"><button type="button" onclick="this.closest('tr').remove(); calculateTotals();" class="w-8 h-8 mx-auto rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all flex items-center justify-center font-bold" title="Delete Row"><i class="fa-solid fa-trash-can text-xs"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 3: Tax & Discount Calculations -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-sm">
        <h2 class="text-lg font-extrabold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <span class="flex items-center">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center mr-3 text-sm">
                    <i class="fa-solid fa-calculator"></i>
                </div>
                <span>Tax & Discount Calculations</span>
            </span>
            <span id="country_vat_badge" class="inline-flex items-center text-xs font-black px-3.5 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200/80 shadow-3xs">
                <i class="fa-solid fa-earth-americas mr-1.5 text-emerald-600"></i>Auto Country VAT Enabled
            </span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Discount Type</label>
                <select name="discount_type" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="fixed" <?=($invoice['discount_type'] ?? 'fixed')==='fixed'?'selected':''?>>Fixed Cash Discount</option>
                    <option value="percent" <?=($invoice['discount_type'] ?? 'fixed')==='percent'?'selected':''?>>Percentage (%)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Discount Value</label>
                <input type="number" step="0.01" name="discount_value" value="<?=e((string)(float)($invoice['discount_value'] ?? 0))?>" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-4 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>
            <div>
                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">VAT / Tax Rate Preset</label>
                <select name="tax_rule" id="tax_rule_select" onchange="handleTaxRuleChange()" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-4 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="auto">🤖 Auto-Detect by Client Country</option>
                    <option value="5.0">🇦🇪 UAE Standard VAT (5%)</option>
                    <option value="15.0">🇸🇦 KSA Standard VAT (15%)</option>
                    <option value="20.0">🇬🇧 UK Standard VAT (20%)</option>
                    <option value="0.0_zero">🌐 Zero-Rated Export (0%)</option>
                    <option value="0.0_exempt">🛡️ Tax Exempt (0%)</option>
                    <?php foreach ($configuredTaxRates as $tr): ?>
                        <option value="<?=e((string)(float)$tr['rate_percent'])?>"><?=e($tr['name'])?> (<?=e((string)(float)$tr['rate_percent'])?>%)</option>
                    <?php endforeach; ?>
                    <option value="custom">✏️ Custom Tax Rate / Manual Amount</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Calculated VAT Tax Amount</label>
                <div class="relative">
                    <input type="number" step="0.01" name="tax_amount" id="tax_amount_input" value="<?=e((string)(float)($invoice['tax_amount'] ?? 0))?>" oninput="markTaxManuallyEdited()" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-4 py-2.5 text-sm font-bold font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <span id="tax_rate_percentage_tag" class="absolute right-3 top-2.5 text-2xs font-black px-2 py-0.5 rounded-md bg-slate-200 text-slate-800">5.0%</span>
                </div>
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider mb-2">Invoice Public Notes & Payment Terms</label>
                <textarea name="notes" rows="3" placeholder="Thank you for your business. Payment terms..." class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"><?=e($invoice['notes'] ?? '')?></textarea>
            </div>
        </div>
    </div>

    <!-- Section 4: Premium Executive Live Summary Panel -->
    <div class="bg-slate-950 text-white rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-800/80 relative overflow-hidden">
        <!-- Ambient Background Glows -->
        <div class="absolute -top-24 -right-24 w-72 h-72 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <!-- Left Info Header -->
            <div class="max-w-md">
                <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-3xs font-extrabold uppercase tracking-widest mb-3">
                    <i class="fa-solid fa-calculator"></i>
                    <span>Real-Time Calculation Engine</span>
                </div>
                <h3 class="text-xl font-black text-white tracking-tight flex items-center space-x-2">
                    <span>Executive Invoice Summary</span>
                </h3>
                <p class="text-xs text-slate-400 mt-1.5 leading-relaxed">
                    Financial ledger preview updating dynamically based on line items, client country tax compliance, and discounts.
                </p>
            </div>

            <!-- Right KPI Stat Cards Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 w-full xl:w-auto">
                <!-- Subtotal Card -->
                <div class="bg-slate-900/90 backdrop-blur-md p-4 rounded-2xl border border-slate-800 flex flex-col justify-between shadow-inner">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-3xs uppercase font-black text-slate-400 tracking-wider">Subtotal</span>
                        <i class="fa-solid fa-list-check text-slate-600 text-3xs"></i>
                    </div>
                    <span id="live_subtotal" class="text-base sm:text-lg font-mono font-bold text-slate-100 tracking-tight">0.00 AED</span>
                </div>

                <!-- Discount Card -->
                <div class="bg-slate-900/90 backdrop-blur-md p-4 rounded-2xl border border-slate-800 flex flex-col justify-between shadow-inner">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-3xs uppercase font-black text-amber-400/80 tracking-wider">Discount</span>
                        <i class="fa-solid fa-tags text-amber-500/40 text-3xs"></i>
                    </div>
                    <span id="live_discount" class="text-base sm:text-lg font-mono font-bold text-amber-400 tracking-tight">0.00 AED</span>
                </div>

                <!-- VAT Tax Card -->
                <div class="bg-slate-900/90 backdrop-blur-md p-4 rounded-2xl border border-slate-800 flex flex-col justify-between shadow-inner">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-3xs uppercase font-black text-sky-400/80 tracking-wider">VAT Tax</span>
                        <i class="fa-solid fa-percent text-sky-500/40 text-3xs"></i>
                    </div>
                    <span id="live_tax" class="text-base sm:text-lg font-mono font-bold text-sky-300 tracking-tight">0.00 AED</span>
                </div>

                <!-- Grand Total Hero Card -->
                <div class="bg-gradient-to-br from-amber-500 to-amber-600 text-slate-950 p-4 rounded-2xl border border-amber-400 shadow-xl flex flex-col justify-between transform hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-3xs uppercase font-black text-slate-950/80 tracking-widest">Grand Total</span>
                        <i class="fa-solid fa-bolt text-slate-950 text-xs"></i>
                    </div>
                    <span id="live_grand_total" class="text-lg sm:text-xl font-mono font-black text-slate-950 tracking-tight">0.00 AED</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions (Mobile Safe Responsive Action Bar) -->
    <div class="flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-4 gap-3">
        <a href="index.php" class="w-full sm:w-auto text-center px-6 py-3.5 border border-slate-300 rounded-xl text-sm font-bold text-slate-700 bg-white hover:bg-slate-50 transition-all">Cancel</a>
        <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-8 py-3.5 border border-transparent text-base font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-xl transition-all active:scale-95">
            <i class="fa-solid fa-paper-plane mr-2"></i><?=$invoice ? 'Update Tax Invoice' : 'Save & Issue Invoice'?>
        </button>
    </div>
</form>

<script>
const clientMap = <?=json_encode($clientMap)?>;
let catalogItems = <?=json_encode($catalogItems)?>;
let isTaxManuallyEdited = <?=($invoice && (float)$invoice['tax_amount'] > 0) ? 'true' : 'false'?>;

function markTaxManuallyEdited() {
    isTaxManuallyEdited = true;
    calculateTotals();
}

function handleTaxRuleChange() {
    isTaxManuallyEdited = false;
    calculateTotals();
}

function handleCatalogItemChange(select) {
    const val = select.value;
    if (val === '__add_new_item__') {
        select.value = '';
        openAddItemModal(select);
        return;
    }
    if (!val) return;
    const item = catalogItems.find(i => String(i.id) === String(val));
    if (!item) return;

    const row = select.closest('tr');
    if (!row) return;

    const descInput = row.querySelector('input[name="description[]"]');
    const detailsInput = row.querySelector('input[name="details[]"]');
    const priceInput = row.querySelector('input[name="unit_price[]"]');

    if (descInput) descInput.value = item.name;
    if (detailsInput) detailsInput.value = item.description || '';
    if (priceInput) priceInput.value = parseFloat(item.unit_price).toFixed(2);

    calculateTotals();
}

function detectCountryVatRate(countryName) {
    if (!countryName) return 5.0;
    const c = countryName.trim().toLowerCase();
    if (c.includes('united arab emirates') || c.includes('uae') || c === 'ae') {
        return 5.0;
    } else if (c.includes('saudi') || c.includes('ksa') || c === 'sa') {
        return 15.0;
    } else if (c.includes('united kingdom') || c.includes('uk') || c === 'gb') {
        return 20.0;
    } else if (c.includes('germany') || c.includes('france') || c.includes('italy') || c.includes('spain')) {
        return 19.0;
    } else if (c.includes('united states') || c.includes('usa') || c === 'us') {
        return 0.0;
    }
    return 0.0; // Export zero-rated fallback
}

function updateClientCountryVat() {
    const clientSelect = document.getElementById('client_id_select');
    const clientId = clientSelect ? clientSelect.value : '';
    const vatBadge = document.getElementById('country_vat_badge');
    const trnBadge = document.getElementById('client_trn_badge');

    if (clientId && clientMap[clientId]) {
        const client = clientMap[clientId];
        const country = client.country || 'United Arab Emirates';
        const trn = client.tax_number || '';
        const rate = detectCountryVatRate(country);

        if (vatBadge) {
            vatBadge.innerHTML = `<i class="fa-solid fa-earth-americas mr-1"></i>${country} — ${rate}% VAT Detected`;
        }
        if (trnBadge) {
            trnBadge.innerHTML = trn 
                ? `<i class="fa-solid fa-id-card text-emerald-500 mr-1"></i>TRN: <strong>${trn}</strong> (${country})`
                : `<i class="fa-solid fa-building text-slate-400 mr-1"></i>${country} (No TRN Registered)`;
        }
    }
    if (!isTaxManuallyEdited) {
        calculateTotals();
    }
}

function addInvoiceRow() {
    const tbody = document.querySelector('#invoice-items-table tbody');
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-slate-50/50 transition-colors';

    let catalogOptionsHtml = `<option value="">-- Choose Catalog Item or Type Below --</option>
        <option value="__add_new_item__" class="font-bold text-emerald-600 bg-emerald-50">+ Add New Catalog Item...</option>`;
    
    catalogItems.forEach(item => {
        const skuStr = item.sku ? `[${item.sku}] ` : '';
        catalogOptionsHtml += `<option value="${item.id}">${skuStr}${item.name} — ${parseFloat(item.unit_price).toFixed(2)}</option>`;
    });

    tr.innerHTML = `
        <td class="px-4 py-3">
            <div class="space-y-1.5">
                <select onchange="handleCatalogItemChange(this)" class="catalog-item-select w-full rounded-xl border border-slate-300 bg-slate-50/90 px-3 py-1.5 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    ${catalogOptionsHtml}
                </select>
                <input type="text" name="description[]" placeholder="Item description..." required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>
        </td>
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

    const netTaxable = Math.max(0, subtotal - discountAmount);

    // Resolve Tax Rate Percentage
    const taxRuleSelect = document.getElementById('tax_rule_select');
    const taxRuleVal = taxRuleSelect ? taxRuleSelect.value : 'auto';
    const taxAmountInput = document.getElementById('tax_amount_input');
    const taxTag = document.getElementById('tax_rate_percentage_tag');

    let effectiveTaxPercent = 5.0; // Default UAE 5%

    if (taxRuleVal === 'auto') {
        const clientSelect = document.getElementById('client_id_select');
        const clientId = clientSelect ? clientSelect.value : '';
        const clientCountry = (clientId && clientMap[clientId]) ? clientMap[clientId].country : 'United Arab Emirates';
        effectiveTaxPercent = detectCountryVatRate(clientCountry);
    } else if (taxRuleVal === 'custom') {
        effectiveTaxPercent = 0.0;
    } else {
        effectiveTaxPercent = parseFloat(taxRuleVal) || 0.0;
    }

    if (taxTag) {
        taxTag.textContent = taxRuleVal === 'custom' ? 'Custom' : `${effectiveTaxPercent.toFixed(1)}%`;
    }

    // Auto-calculate Tax Amount if not manually edited by user
    let taxAmount = 0;
    if (!isTaxManuallyEdited && taxRuleVal !== 'custom') {
        taxAmount = (netTaxable * effectiveTaxPercent) / 100;
        if (taxAmountInput) {
            taxAmountInput.value = taxAmount.toFixed(2);
        }
    } else {
        taxAmount = parseFloat(taxAmountInput?.value) || 0;
    }

    const grandTotal = Math.max(0, netTaxable + taxAmount);
    const curr = document.querySelector('select[name="currency"]')?.value || 'AED';

    const formatNum = (num) => num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    document.getElementById('live_subtotal').textContent = `${formatNum(subtotal)} ${curr}`;
    document.getElementById('live_discount').textContent = `${formatNum(discountAmount)} ${curr}`;
    document.getElementById('live_tax').textContent = `${formatNum(taxAmount)} ${curr}`;
    document.getElementById('live_grand_total').textContent = `${formatNum(grandTotal)} ${curr}`;
}

document.addEventListener("DOMContentLoaded", () => {
    document.body.addEventListener("input", (e) => {
        if (e.target.matches('input[name="qty[]"], input[name="unit_price[]"], input[name="discount_value"]')) {
            calculateTotals();
        }
    });
    document.body.addEventListener("change", (e) => {
        if (e.target.matches('select[name="discount_type"], select[name="currency"]')) {
            calculateTotals();
        }
    });
    updateClientCountryVat();
    calculateTotals();
});
</script>

<?php
require __DIR__ . '/add_client_modal.php';
require __DIR__ . '/add_item_modal.php';
page_end(); 
?>
