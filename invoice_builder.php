<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$brand = branding();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_layout') {
    verify_csrf();
    $layoutJson = $_POST['layout_json'] ?? '[]';
    
    $st = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'custom_invoice_layout', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $st->execute([$tid, $layoutJson]);

    log_audit($pdo, 'save_custom_layout', 'settings', $tid, 'Saved custom drag-and-drop invoice layout');
    flash('success', 'Custom Drag & Drop Invoice Template layout saved successfully!');
    redirect('invoice_builder.php');
}

// Fetch existing saved layout
$stGet = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'custom_invoice_layout'");
$stGet->execute([$tid]);
$savedLayoutJson = $stGet->fetchColumn() ?: '';

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
        <p class="mt-1 text-sm text-slate-500">Design custom invoice layouts by dragging components, re-ordering blocks, and tweaking styles for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="flex space-x-3">
        <form method="post" id="save-layout-form">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="save_layout">
            <input type="hidden" name="layout_json" id="layout_json_input" value="<?=e($savedLayoutJson)?>">
            <button type="button" onclick="saveCustomLayout()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 shadow-md">
                <i class="fa-solid fa-floppy-disk mr-2"></i>Save Custom Template
            </button>
        </form>
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

        <div class="pt-4 border-t border-slate-100">
            <button onclick="resetBuilderCanvas()" class="w-full py-2 text-xs font-bold text-slate-500 hover:text-rose-600 border border-slate-200 rounded-xl hover:bg-rose-50 transition-all">
                <i class="fa-solid fa-rotate-left mr-1"></i>Reset Canvas to Default
            </button>
        </div>
    </div>

    <!-- Live Drag & Drop Builder Canvas -->
    <div class="canvas-wrapper lg:col-span-3 bg-slate-200/80 rounded-2xl p-6 border border-slate-300 shadow-inner min-h-[700px] flex flex-col justify-start">
        <div class="flex items-center justify-between mb-4 text-xs font-bold text-slate-500 uppercase tracking-wider no-print">
            <span>Interactive Invoice Canvas (Drag & Re-Order Blocks Below)</span>
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

function renderBlockHTML(block) {
    switch(block.type) {
        case 'header':
            return `
                <div class="flex justify-between items-start border-b border-slate-200 pb-5">
                    <div>
                        ${brandData.logo_url ? `<img src="${brandData.logo_url}" class="h-10 w-auto mb-2 rounded p-1 bg-slate-50 border">` : `<div class="h-10 w-10 bg-amber-500 text-white rounded-lg flex items-center justify-center font-bold text-lg mb-2"><i class="fa-solid fa-bolt"></i></div>`}
                        <h2 class="text-xl font-extrabold text-slate-900">${brandData.company_name}</h2>
                        <p class="text-xs text-slate-500">${brandData.tagline}</p>
                        <p class="text-xs text-slate-600 mt-1">${brandData.address}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-black text-slate-900 tracking-tight uppercase">TAX INVOICE</span>
                        <p class="text-xs font-mono text-slate-500 mt-1">${brandData.tax_label}: ${brandData.tax_number}</p>
                    </div>
                </div>`;
        case 'metadata':
            return `
                <div class="grid grid-cols-3 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
                    <div><span class="text-slate-400 font-bold uppercase block mb-0.5">Invoice Number</span><strong class="text-slate-900 font-mono text-sm">OS-INV-2026-0104</strong></div>
                    <div><span class="text-slate-400 font-bold uppercase block mb-0.5">Invoice Date</span><strong class="text-slate-900 text-sm">${new Date().toLocaleDateString('en-GB')}</strong></div>
                    <div><span class="text-slate-400 font-bold uppercase block mb-0.5">Payment Due Date</span><strong class="text-slate-900 text-sm">14 Days Net</strong></div>
                </div>`;
        case 'client':
            return `
                <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">Billed To (Client Details)</span>
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
                        <div class="border-t border-slate-200 pt-2 flex justify-between text-slate-900 font-extrabold text-sm"><span>Total Amount Due:</span><span class="text-emerald-600">${brandData.currency} 5,250.00</span></div>
                    </div>
                </div>`;
        case 'bank':
            return `
                <div class="p-4 bg-blue-50/60 rounded-xl border border-blue-100 text-xs">
                    <h4 class="font-bold text-blue-900 mb-1"><i class="fa-solid fa-landmark mr-1.5"></i>Remittance Bank Details</h4>
                    <p class="text-blue-800">Bank: Emirates NBD · Account: 10129384729 · IBAN: AE03033000010129384729</p>
                </div>`;
        case 'notes':
            return `
                <div class="text-xs text-slate-500 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <strong>Terms & Conditions:</strong> Payment is due within 14 days of invoice issuance. Late payments subject to interest charges.
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
                    <div class="h-12 w-12 bg-slate-900 text-white flex items-center justify-center font-bold rounded"><i class="fa-solid fa-qrcode text-xl"></i></div>
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
