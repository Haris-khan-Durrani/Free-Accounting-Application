<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$brand = branding();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_layout') {
        $layoutJson = $_POST['layout_json'] ?? '[]';
        $wordingArray = [
            'title'        => trim($_POST['wording_title'] ?? 'TAX INVOICE'),
            'invoice_no'   => trim($_POST['wording_invoice_no'] ?? 'Invoice Number'),
            'invoice_date' => trim($_POST['wording_invoice_date'] ?? 'Invoice Date'),
            'due_date'     => trim($_POST['wording_due_date'] ?? 'Payment Due Date'),
            'billed_to'    => trim($_POST['wording_billed_to'] ?? 'Billed To (Client Details)'),
            'tax_label'    => trim($_POST['wording_tax_label'] ?? 'TRN / Tax ID'),
            'subtotal'     => trim($_POST['wording_subtotal'] ?? 'Subtotal'),
            'tax_amount'   => trim($_POST['wording_tax_amount'] ?? 'VAT (5%)'),
            'total'        => trim($_POST['wording_total'] ?? 'Total Amount Due'),
            'terms_label'  => trim($_POST['wording_terms_label'] ?? 'Terms & Conditions'),
            'bank_label'   => trim($_POST['wording_bank_label'] ?? 'Remittance Bank Details'),
            'sign_label'   => trim($_POST['wording_sign_label'] ?? 'Authorized Signatory'),
            'accent_color' => trim($_POST['wording_accent_color'] ?? '#d97706'),
            'header_color' => trim($_POST['wording_header_color'] ?? '#0f172a'),
        ];
        $wordingJson = json_encode($wordingArray);
        
        $st1 = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'custom_invoice_layout', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $st1->execute([$tid, $layoutJson]);

        $st2 = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'custom_invoice_wording', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $st2->execute([$tid, $wordingJson]);

        // Auto-set as default template
        $st3 = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'default_invoice_template', 'custom_drag_drop') ON DUPLICATE KEY UPDATE setting_value = 'custom_drag_drop'");
        $st3->execute([$tid]);

        try {
            $st4 = $pdo->prepare("UPDATE branding_settings SET default_invoice_template = 'custom_drag_drop' WHERE tenant_id = ?");
            $st4->execute([$tid]);
        } catch (\Throwable $t) {}

        log_audit($pdo, 'save_custom_layout', 'settings', $tid, 'Saved custom drag-and-drop layout and wording overrides');
        flash('success', '✓ Custom Drag & Drop Layout saved and set as DEFAULT invoice template!');
        redirect('invoice_builder.php');
    }



    if ($action === 'set_as_default') {
        $st = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'default_invoice_template', 'custom_drag_drop') ON DUPLICATE KEY UPDATE setting_value = 'custom_drag_drop'");
        $st->execute([$tid]);

        try {
            $stBr = $pdo->prepare("UPDATE branding_settings SET default_invoice_template = 'custom_drag_drop' WHERE tenant_id = ?");
            $stBr->execute([$tid]);
        } catch (\Throwable $t) {}

        log_audit($pdo, 'set_default_template', 'settings', $tid, 'Set custom_drag_drop as default invoice template');
        flash('success', '⭐ Custom Drag & Drop Template is now the DEFAULT invoice template for all new invoices in your workspace!');
        redirect('invoice_builder.php');
    }

}

// Fetch existing saved layout and custom wording
$stGet = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'custom_invoice_layout'");
$stGet->execute([$tid]);
$savedLayoutJson = $stGet->fetchColumn() ?: '';

$customWording = get_custom_wording($pdo, $tid);

$stTpl = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'default_invoice_template'");
$stTpl->execute([$tid]);
$isCustomDefault = ($stTpl->fetchColumn() === 'custom_drag_drop');

page_start('Visual Drag & Drop Invoice Builder');
?>

<!-- Print-Only Isolated Canvas CSS -->
<style>
@media print {
    header, footer, nav, .no-print, .palette-sidebar, .page-header-bar {
        display: none !important;
    }
    body, main, .main-container {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .builder-layout-grid {
        display: block !important;
    }
    .canvas-wrapper {
        background: #ffffff !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    #builder-canvas {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .builder-block {
        border: none !important;
        padding: 0 !important;
        margin-bottom: 16px !important;
    }
}
</style>

<div class="page-header-bar md:flex md:items-center md:justify-between mb-8 no-print">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Visual Drag & Drop Invoice Builder</h1>
        <p class="mt-1 text-sm text-slate-500">Design custom invoice layouts, re-order blocks, and customize wordings/labels for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="flex flex-wrap gap-3 mt-4 md:mt-0">
        <?php if (!$isCustomDefault): ?>
            <form method="post">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="set_as_default">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-amber-300 text-sm font-extrabold rounded-xl text-amber-900 bg-amber-50 hover:bg-amber-100 shadow-sm transition-all">
                    <i class="fa-solid fa-star text-amber-500 mr-2"></i>Set as Default Template
                </button>
            </form>
        <?php else: ?>
            <span class="inline-flex items-center px-3.5 py-2 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-black uppercase tracking-wider">
                <i class="fa-solid fa-circle-check text-emerald-500 mr-1.5 text-sm"></i>Active Default Template
            </span>
        <?php endif; ?>

        <button type="button" onclick="saveCustomLayout()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 shadow-md transition-all">
            <i class="fa-solid fa-floppy-disk mr-2"></i>Save Design & Labels
        </button>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print Canvas
        </button>
    </div>
</div>


<div class="builder-layout-grid grid grid-cols-1 lg:grid-cols-4 gap-8">
    <!-- Component Palette Sidebar -->
    <div class="palette-sidebar bg-white rounded-2xl p-6 border border-slate-200 shadow-sm h-fit space-y-4 no-print">
        <div class="border-b border-slate-100 pb-3">
            <h2 class="text-base font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-cubes text-amber-500 mr-2"></i>Component Palette
            </h2>
            <p class="text-xs text-slate-500">Drag components onto the invoice canvas on the right.</p>
        </div>

        <div class="space-y-2.5" id="palette">
            <!-- Palette Blocks -->
            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="header">
                <span class="flex items-center"><i class="fa-solid fa-building text-blue-500 w-5"></i>1. Corporate Header & Logo</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>

            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="metadata">
                <span class="flex items-center"><i class="fa-solid fa-file-invoice text-amber-500 w-5"></i>2. Invoice Metadata Box</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>

            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="client">
                <span class="flex items-center"><i class="fa-solid fa-user-tag text-emerald-500 w-5"></i>3. Client Billing Info Card</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>

            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="table">
                <span class="flex items-center"><i class="fa-solid fa-table-list text-purple-500 w-5"></i>4. Line Items Table</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>

            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="summary">
                <span class="flex items-center"><i class="fa-solid fa-calculator text-rose-500 w-5"></i>5. Financial Totals & Tax</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>

            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="bank">
                <span class="flex items-center"><i class="fa-solid fa-landmark text-indigo-500 w-5"></i>6. Bank & Payment Info</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>

            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="notes">
                <span class="flex items-center"><i class="fa-solid fa-note-sticky text-cyan-500 w-5"></i>7. Terms & Notes Box</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>

            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="signature">
                <span class="flex items-center"><i class="fa-solid fa-signature text-pink-500 w-5"></i>8. Corporate Signature & Stamp</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>

            <div class="palette-item bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl cursor-grab flex items-center justify-between text-xs font-bold text-slate-700 shadow-2xs transition-all" draggable="true" data-block-type="qrcode">
                <span class="flex items-center"><i class="fa-solid fa-qrcode text-slate-700 w-5"></i>9. Verification QR Code</span>
                <i class="fa-solid fa-grip-vertical text-slate-400"></i>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 space-y-3">
            <button type="button" onclick="resetBuilderCanvas()" class="w-full py-2 text-xs font-bold text-slate-500 hover:text-rose-600 border border-slate-200 rounded-xl hover:bg-rose-50 transition-all">
                <i class="fa-solid fa-rotate-left mr-1"></i>Reset Canvas to Default
            </button>
        </div>

        <!-- Custom Wording & Label Controls Card -->
        <div class="border-t border-slate-100 pt-4 mt-4 space-y-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center">
                <i class="fa-solid fa-pen-to-square text-amber-500 mr-1.5"></i>Custom Wording &amp; Labels
            </h3>
            <p class="text-[11px] text-slate-500">Change labels on your invoice design:</p>

            <form method="post" id="save-layout-form" class="space-y-3 text-xs">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_layout">
                <input type="hidden" name="layout_json" id="layout_json_input" value="<?=e($savedLayoutJson)?>">

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Document Title</label>
                    <input type="text" name="wording_title" id="w_title" value="<?=e($customWording['title'])?>" oninput="renderCanvas()" class="w-full text-xs font-bold px-2.5 py-1.5 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Invoice No. Label</label>
                    <input type="text" name="wording_invoice_no" id="w_invoice_no" value="<?=e($customWording['invoice_no'])?>" oninput="renderCanvas()" class="w-full text-xs font-bold px-2.5 py-1.5 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Billed To Label</label>
                    <input type="text" name="wording_billed_to" id="w_billed_to" value="<?=e($customWording['billed_to'])?>" oninput="renderCanvas()" class="w-full text-xs font-bold px-2.5 py-1.5 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Tax ID / TRN Label</label>
                    <input type="text" name="wording_tax_label" id="w_tax_label" value="<?=e($customWording['tax_label'])?>" oninput="renderCanvas()" class="w-full text-xs font-bold px-2.5 py-1.5 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Total Amount Label</label>
                    <input type="text" name="wording_total" id="w_total" value="<?=e($customWording['total'])?>" oninput="renderCanvas()" class="w-full text-xs font-bold px-2.5 py-1.5 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Bank Section Title</label>
                    <input type="text" name="wording_bank_label" id="w_bank_label" value="<?=e($customWording['bank_label'])?>" oninput="renderCanvas()" class="w-full text-xs font-bold px-2.5 py-1.5 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Terms Section Title</label>
                    <input type="text" name="wording_terms_label" id="w_terms_label" value="<?=e($customWording['terms_label'])?>" oninput="renderCanvas()" class="w-full text-xs font-bold px-2.5 py-1.5 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-amber-500">
                </div>

                <div class="pt-2 border-t border-slate-100 space-y-2">
                    <label class="block text-[10px] font-black uppercase text-slate-700"><i class="fa-solid fa-palette text-amber-500 mr-1"></i>Template Color Theme</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="block text-[9px] font-bold text-slate-500 uppercase">Accent Color</span>
                            <input type="color" name="wording_accent_color" id="w_accent_color" value="<?=e($customWording['accent_color'] ?? '#d97706')?>" onchange="renderCanvas()" class="w-full h-8 p-0 rounded-md cursor-pointer border border-slate-300">
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-500 uppercase">Header Text Color</span>
                            <input type="color" name="wording_header_color" id="w_header_color" value="<?=e($customWording['header_color'] ?? '#0f172a')?>" onchange="renderCanvas()" class="w-full h-8 p-0 rounded-md cursor-pointer border border-slate-300">
                        </div>
                    </div>
                </div>


                <button type="button" onclick="saveCustomLayout()" class="w-full mt-2 py-2 px-3 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow transition-all">
                    <i class="fa-solid fa-floppy-disk mr-1"></i>Save Layout &amp; Labels
                </button>
            </form>
        </div>
    </div>

    <!-- Live Drag & Drop Builder Canvas -->
    <div class="canvas-wrapper lg:col-span-3 bg-slate-200/80 rounded-2xl p-6 border border-slate-300 shadow-inner min-h-[700px] flex flex-col justify-start">
        <div class="flex items-center justify-between mb-4 text-xs font-bold text-slate-500 uppercase tracking-wider no-print">
            <span>Interactive Invoice Canvas (Drag &amp; Re-Order Blocks Below)</span>
            <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px]">WYSIWYG Builder</span>
        </div>

        <!-- Droppable Canvas Container -->
        <div id="builder-canvas" class="bg-white rounded-xl shadow-xl border border-slate-200 p-8 min-h-[600px] space-y-6">
            <!-- Canvas blocks rendered via JS -->
        </div>
    </div>
</div>


<script>
// Mock Brand & Invoice Data for Builder Canvas
const brandData = {
    company_name: "<?=e(addslashes($brand['company_name']))?>",
    tagline: "<?=e(addslashes($brand['company_tagline']))?>",
    tax_label: "<?=e(addslashes($brand['tax_number_label'] ?: 'TRN / Tax ID'))?>",
    tax_number: "<?=e(addslashes($brand['tax_number'] ?: '100293847500003'))?>",
    address: "<?=e(addslashes($brand['address'] ?: 'Suite 402, Business Tower, Dubai, UAE'))?>",
    email: "<?=e(addslashes($brand['company_email'] ?: 'info@company.com'))?>",
    logo_url: "<?=e(addslashes($brand['logo_url'] ?: ''))?>",
    currency: "<?=e(tenant()['currency'])?>"
};

// Default block order
let canvasBlocks = [
    { id: 'b1', type: 'header' },
    { id: 'b2', type: 'metadata' },
    { id: 'b3', type: 'client' },
    { id: 'b4', type: 'table' },
    { id: 'b5', type: 'summary' },
    { id: 'b6', type: 'bank' },
    { id: 'b7', type: 'notes' },
    { id: 'b8', type: 'signature' }
];

// Saved layout check
const savedJson = document.getElementById('layout_json_input').value;
if (savedJson && savedJson.trim().length > 5) {
    try {
        const parsed = JSON.parse(savedJson);
        if (Array.isArray(parsed) && parsed.length > 0) {
            canvasBlocks = parsed;
        }
    } catch(e){}
}

function getWording(key, fallback) {
    const el = document.getElementById('w_' + key);
    if (el && el.value && el.value.trim().length > 0) {
        return el.value.trim();
    }
    return fallback;
}

function renderBlockHTML(block) {


    const titleVal = getWording('title', 'TAX INVOICE');
    const invoiceNoVal = getWording('invoice_no', 'Invoice Number');
    const billedToVal = getWording('billed_to', 'Billed To (Client Details)');
    const taxLabelVal = getWording('tax_label', 'TRN / Tax ID');
    const totalVal = getWording('total', 'Total Amount Due');
    const bankLabelVal = getWording('bank_label', 'Remittance Bank Details');
    const termsLabelVal = getWording('terms_label', 'Terms & Conditions');

    const accentColor = document.getElementById('w_accent_color')?.value || '#d97706';
    const headerColor = document.getElementById('w_header_color')?.value || '#0f172a';

    switch(block.type) {
        case 'header':
            return `
                <div class="flex justify-between items-start border-b border-slate-200 pb-5">
                    <div>
                        ${brandData.logo_url ? `<img src="${brandData.logo_url}" class="h-10 w-auto mb-2 rounded p-1 bg-slate-50 border">` : `<div class="h-10 w-10 text-white rounded-lg flex items-center justify-center font-bold text-lg mb-2" style="background-color: ${accentColor};"><i class="fa-solid fa-bolt"></i></div>`}
                        <h2 class="text-xl font-extrabold" style="color: ${headerColor};">${brandData.company_name}</h2>
                        <p class="text-xs text-slate-500">${brandData.tagline}</p>
                        <p class="text-xs text-slate-600 mt-1">${brandData.address}</p>
                    </div>
                    <div class="text-right">
                        <span contenteditable="true" data-wording-target="title" class="text-2xl font-black tracking-tight uppercase border-b border-dashed border-amber-300 hover:bg-amber-50 px-1 outline-none" style="color: ${accentColor};">${titleVal}</span>
                        <p class="text-xs font-mono text-slate-500 mt-1"><span contenteditable="true" data-wording-target="tax_label" class="outline-none hover:bg-amber-50 px-0.5 border-b border-dashed border-slate-300">${taxLabelVal}</span>: ${brandData.tax_number}</p>
                    </div>
                </div>`;
        case 'metadata':
            return `
                <div class="grid grid-cols-3 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
                    <div><span contenteditable="true" data-wording-target="invoice_no" class="text-slate-400 font-bold uppercase block mb-0.5 outline-none hover:bg-amber-50 border-b border-dashed border-slate-300">${invoiceNoVal}</span><strong class="text-slate-900 font-mono text-sm">OS-INV-2026-0104</strong></div>
                    <div><span class="text-slate-400 font-bold uppercase block mb-0.5">Invoice Date</span><strong class="text-slate-900 text-sm">${new Date().toLocaleDateString('en-GB')}</strong></div>
                    <div><span class="text-slate-400 font-bold uppercase block mb-0.5">Payment Due Date</span><strong class="text-slate-900 text-sm">14 Days Net</strong></div>
                </div>`;
        case 'client':
            return `
                <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200">
                    <span contenteditable="true" data-wording-target="billed_to" class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1 outline-none hover:bg-amber-50 border-b border-dashed border-slate-300">${billedToVal}</span>
                    <h3 class="text-base font-bold text-slate-900">360 Business Consultants FZ-LLC</h3>
                    <p class="text-xs text-slate-600">Attn: Finance Manager · billing@360consultants.ae</p>
                    <p class="text-xs text-slate-500 mt-0.5">TRN / Tax ID: 100392019200003 · Dubai Internet City, UAE</p>
                </div>`;
        case 'table':
            return `
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                                <th class="py-2.5 px-3">Item Description</th>
                                <th class="py-2.5 px-3 text-center">Qty</th>
                                <th class="py-2.5 px-3 text-right">Unit Price</th>
                                <th class="py-2.5 px-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr>
                                <td class="py-3 px-3 font-semibold text-slate-800">Enterprise Accounting Software Implementation & Setup</td>
                                <td class="py-3 px-3 text-center">1</td>
                                <td class="py-3 px-3 text-right font-mono">${brandData.currency} 3,500.00</td>
                                <td class="py-3 px-3 text-right font-bold text-slate-900">${brandData.currency} 3,500.00</td>
                            </tr>
                            <tr>
                                <td class="py-3 px-3 font-semibold text-slate-800">WooCommerce & Shopify Webhooks Integration</td>
                                <td class="py-3 px-3 text-center">1</td>
                                <td class="py-3 px-3 text-right font-mono">${brandData.currency} 1,500.00</td>
                                <td class="py-3 px-3 text-right font-bold text-slate-900">${brandData.currency} 1,500.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>`;
        case 'summary':
            return `
                <div class="flex justify-end">
                    <div class="w-72 bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600"><span>Subtotal:</span><span class="font-mono font-bold">${brandData.currency} 5,000.00</span></div>
                        <div class="flex justify-between text-slate-600"><span>VAT (5%):</span><span class="font-mono font-bold">${brandData.currency} 250.00</span></div>
                        <div class="border-t border-slate-200 pt-2 flex justify-between text-slate-900 font-extrabold text-sm"><span contenteditable="true" data-wording-target="total" class="outline-none hover:bg-amber-50 border-b border-dashed border-slate-300">${totalVal}</span>:<span class="font-bold" style="color: ${accentColor};">${brandData.currency} 5,250.00</span></div>
                    </div>
                </div>`;
        case 'bank':
            return `
                <div class="p-4 bg-blue-50/60 rounded-xl border border-blue-100 text-xs">
                    <h4 class="font-bold text-blue-900 mb-1"><i class="fa-solid fa-landmark mr-1.5" style="color: ${accentColor};"></i><span contenteditable="true" data-wording-target="bank_label" class="outline-none hover:bg-amber-50 border-b border-dashed border-blue-300">${bankLabelVal}</span></h4>
                    <p class="text-blue-800">Bank: Emirates NBD · Account: 10129384729 · IBAN: AE03033000010129384729</p>
                </div>`;
        case 'notes':
            return `
                <div class="text-xs text-slate-500 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <strong contenteditable="true" data-wording-target="terms_label" class="outline-none hover:bg-amber-50 border-b border-dashed border-slate-300">${termsLabelVal}</strong>: Payment is due within 14 days of invoice issuance. Late payments subject to interest charges.
                </div>`;
        case 'signature':
            return `
                <div class="flex justify-between items-center pt-4 border-t border-slate-100 text-xs">
                    <div><p class="font-bold text-slate-700">Authorized Signatory</p><p class="text-slate-400">OneSol Finance Dept.</p></div>
                    <div class="h-12 w-28 bg-slate-100 rounded border border-dashed border-slate-300 flex items-center justify-center text-slate-400 font-serif italic text-xs">[ Stamp & Signature ]</div>
                </div>`;
        case 'qrcode':
            return `
                <div class="flex items-center space-x-3 p-3 bg-slate-50 rounded-xl border border-slate-200 w-fit text-xs">
                    <div class="h-12 w-12 text-white flex items-center justify-center font-bold rounded" style="background-color: ${headerColor};"><i class="fa-solid fa-qrcode text-xl"></i></div>
                    <div><span class="font-bold text-slate-800 block">FTA Tax Verified</span><span class="text-slate-500 text-[10px]">Scan to verify authenticity</span></div>
                </div>`;
        default:
            return `<div class="p-3 bg-slate-100 rounded">Generic Component Block</div>`;
    }
}




function renderCanvas() {
    const canvas = document.getElementById('builder-canvas');
    canvas.innerHTML = '';

    if (canvasBlocks.length === 0) {
        canvas.innerHTML = `<div class="text-center py-16 text-slate-400 text-sm"><i class="fa-solid fa-hand-pointer text-3xl mb-2 block"></i>Drag components from the left palette to build your custom layout!</div>`;
        return;
    }

    canvasBlocks.forEach((block, index) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'builder-block relative group border border-dashed border-transparent hover:border-amber-400 p-2 rounded-xl transition-all';
        wrapper.setAttribute('data-id', block.id);
        
        wrapper.innerHTML = `
            <div class="no-print absolute -top-3 right-3 hidden group-hover:flex items-center space-x-1 bg-slate-900 text-white px-2 py-0.5 rounded-full text-2xs z-20 shadow-md">
                ${index > 0 ? `<button onclick="moveBlock(${index}, -1)" class="hover:text-amber-400 px-1" title="Move Up"><i class="fa-solid fa-arrow-up"></i></button>` : ''}
                ${index < canvasBlocks.length - 1 ? `<button onclick="moveBlock(${index}, 1)" class="hover:text-amber-400 px-1" title="Move Down"><i class="fa-solid fa-arrow-down"></i></button>` : ''}
                <button onclick="removeBlock(${index})" class="hover:text-rose-400 px-1" title="Delete Block"><i class="fa-solid fa-trash"></i></button>
            </div>
            ${renderBlockHTML(block)}
        `;
        canvas.appendChild(wrapper);
    });

    document.getElementById('layout_json_input').value = JSON.stringify(canvasBlocks);

    // Sync contenteditable text changes on canvas back to sidebar inputs
    canvas.querySelectorAll('[contenteditable="true"]').forEach(el => {
        el.addEventListener('input', () => {
            const targetKey = el.getAttribute('data-wording-target');
            if (targetKey) {
                const inputEl = document.getElementById('w_' + targetKey);
                if (inputEl) {
                    inputEl.value = el.innerText.trim();
                }
            }
        });
    });
}


function moveBlock(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= canvasBlocks.length) return;
    const temp = canvasBlocks[index];
    canvasBlocks[index] = canvasBlocks[target];
    canvasBlocks[target] = temp;
    renderCanvas();
}

function removeBlock(index) {
    canvasBlocks.splice(index, 1);
    renderCanvas();
}

function resetBuilderCanvas() {
    canvasBlocks = [
        { id: 'b1', type: 'header' },
        { id: 'b2', type: 'metadata' },
        { id: 'b3', type: 'client' },
        { id: 'b4', type: 'table' },
        { id: 'b5', type: 'summary' },
        { id: 'b6', type: 'bank' },
        { id: 'b7', type: 'notes' },
        { id: 'b8', type: 'signature' }
    ];
    renderCanvas();
}

function saveCustomLayout() {
    document.getElementById('save-layout-form').submit();
}

// Setup HTML5 Drag & Drop
document.addEventListener('DOMContentLoaded', () => {
    renderCanvas();

    const paletteItems = document.querySelectorAll('.palette-item');
    paletteItems.forEach(item => {
        item.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', item.getAttribute('data-block-type'));
        });
        item.addEventListener('click', () => {
            const blockType = item.getAttribute('data-block-type');
            if (blockType) {
                canvasBlocks.push({
                    id: 'b_' + Date.now(),
                    type: blockType
                });
                renderCanvas();
            }
        });
    });


    const canvas = document.getElementById('builder-canvas');
    canvas.addEventListener('dragover', (e) => {
        e.preventDefault();
        canvas.classList.add('bg-amber-50/50');
    });

    canvas.addEventListener('dragleave', () => {
        canvas.classList.remove('bg-amber-50/50');
    });

    canvas.addEventListener('drop', (e) => {
        e.preventDefault();
        canvas.classList.remove('bg-amber-50/50');
        const blockType = e.dataTransfer.getData('text/plain');
        if (blockType) {
            canvasBlocks.push({
                id: 'b_' + Date.now(),
                type: blockType
            });
            renderCanvas();
        }
    });
});
</script>

<?php page_end(); ?>
