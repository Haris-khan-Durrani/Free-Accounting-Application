<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$brand = branding();

$templates = [
    'onesol_executive_gold' => '1. OneSol Executive Gold (Flagship Proposal Layout)',
    'modern_minimal' => '2. Modern Minimalist (Clean Corporate)',
    'corporate_executive' => '3. Corporate Executive (Formal & Navy Header)',
    'creative_vibrant' => '4. Creative Vibrant (Bold Accent Line)',
    'sleek_dark' => '5. Sleek Dark Mode (High Tech)',
    'elegant_serif' => '6. Elegant Serif (Luxury & Consulting)',
    'compact_thermal' => '7. Thermal POS Receipt (Compact)',
    'tech_glassmorphism' => '8. Tech Glassmorphism (SaaS Theme)',
    'swiss_grid' => '9. Swiss Grid Design (International Standard)',
    'borderless_clean' => '10. Borderless Clean (Minimalist Lines)',
    'twocolumn_split' => '11. Two-Column Split (Executive Modern)'
];

// Handle AJAX Auto-Save or Form Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_default_template') {
    verify_csrf();
    $selectedTemplate = $_POST['template'] ?? 'onesol_executive_gold';

    if (isset($templates[$selectedTemplate])) {
        $st = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'default_invoice_template', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $st->execute([$tid, $selectedTemplate, $selectedTemplate]);
        log_audit($pdo, 'update_template', 'settings', null, "Updated default invoice template to $selectedTemplate");
        flash('success', '✓ Default invoice template saved successfully! All new invoices will use this design.');
    }

    redirect('invoice_customize?template=' . urlencode($selectedTemplate));
}

$activeTemplate = $_GET['template'] ?? $brand['default_invoice_template'] ?? 'onesol_executive_gold';

// If template changed via GET, auto-save to database settings as well!
if (isset($_GET['template']) && isset($templates[$_GET['template']])) {
    $selectedTemplate = $_GET['template'];
    $st = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'default_invoice_template', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $st->execute([$tid, $selectedTemplate, $selectedTemplate]);
    $brand['default_invoice_template'] = $selectedTemplate;
}

page_start('10+ Live Invoice Customizer');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">10+ Live Invoice Customizer</h1>
        <p class="mt-1 text-sm text-slate-500">Tweak dynamic colors, typography, logos, and choose from 10+ professional invoice templates for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
    <div class="flex space-x-3">
        <a href="invoice_builder" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700">
            <i class="fa-solid fa-layer-group mr-2"></i>Open Drag & Drop Builder
        </a>
        <a href="branding" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-gear mr-2 text-amber-500"></i>Branding Settings
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Controls Sidebar -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm h-fit">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center justify-between">
            <span class="flex items-center"><i class="fa-solid fa-wand-magic-sparkles text-amber-500 mr-2"></i>Styling & Template</span>
            <span id="save-status-badge" class="text-2xs font-extrabold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full"><i class="fa-solid fa-circle-check mr-1"></i>Auto-Saved</span>
        </h2>

        <form method="post" id="template-form" class="space-y-5">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="save_default_template">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Select Active Invoice Template Style</label>
                <select name="template" id="template-select" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <?php foreach ($templates as $key => $label): ?>
                        <option value="<?=$key?>" <?=$activeTemplate === $key ? 'selected' : ''?>><?=$label?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-2xs text-slate-400 font-semibold mt-2 flex items-center">
                    <i class="fa-solid fa-floppy-disk text-emerald-500 mr-1"></i> Selection automatically saves to database as your default layout.
                </p>
            </div>
            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-transparent text-xs font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-sm transition-all">
                <i class="fa-solid fa-check mr-1.5"></i>Save as Default Template
            </button>
        </form>

        <div class="mt-6 border-t border-slate-100 pt-5 space-y-3">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Brand Parameters</h3>
            <div class="flex items-center justify-between text-xs text-slate-700">
                <span>Primary Color:</span>
                <span class="font-bold flex items-center"><span class="h-3 w-3 rounded-full mr-1.5 inline-block" style="background: <?=e($brand['primary_color'] ?: '#0f172a')?>;"></span><?=e($brand['primary_color'] ?: '#0f172a')?></span>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-700">
                <span>Font Family:</span>
                <span class="font-bold text-slate-900"><?=e($brand['font_family'] ?: 'Inter')?></span>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-700">
                <span>Multi-Currency:</span>
                <span class="font-bold text-emerald-600">Active (AED/USD/EUR)</span>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-700">
                <span>QR Code Verification:</span>
                <span class="font-bold text-blue-600"><?=!empty($brand['show_qr_code']) ? 'Enabled' : 'Disabled'?></span>
            </div>
        </div>

        <div class="mt-6 pt-5 border-t border-slate-100 space-y-2">
            <a href="invoice_builder" class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-amber-500 text-xs font-bold rounded-xl text-amber-700 bg-amber-50 hover:bg-amber-100">
                <i class="fa-solid fa-cubes mr-2"></i>Launch Drag & Drop Visual Builder
            </a>
            <button onclick="window.print()" class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-slate-300 text-xs font-bold rounded-xl text-slate-700 bg-slate-50 hover:bg-slate-100">
                <i class="fa-solid fa-print mr-2"></i>Test Print Layout
            </button>
        </div>
    </div>

    <!-- Live Interactive Preview Canvas -->
    <div class="lg:col-span-2 bg-slate-200/80 rounded-2xl p-6 border border-slate-300 shadow-inner min-h-[600px] flex flex-col justify-start">
        <div class="flex items-center justify-between mb-4 text-xs font-bold text-slate-500 uppercase tracking-wider">
            <span>Live Interactive Preview Canvas (<?=e($templates[$activeTemplate] ?? 'Custom Style')?>)</span>
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px]">Real-Time Render</span>
        </div>
        <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-8 overflow-x-auto min-h-[500px]">
            <?php
            // Sample Mock Data for Live Interactive Rendering
            $mockInvoice = [
                'id' => 9999,
                'invoice_number' => 'OS-PI-20260807-001',
                'invoice_date' => date('Y-m-d'),
                'valid_until' => date('Y-m-d', strtotime('+14 days')),
                'currency' => 'AED',
                'subtotal' => 2500.00,
                'tax_amount' => 125.00,
                'total' => 2625.00,
                'notes' => 'Commercial software development, implementation, and configuration services as per the mutually agreed project proposal terms.',
                'status' => 'sent',
                'company_name' => '360 Business Consultants',
                'contact_name' => 'Client / Project: Business Services',
                'email' => 'billing@360consultants.ae',
                'address' => 'Dubai, United Arab Emirates',
                'client_tax_number' => '100293847500003'
            ];

            $mockItems = [
                [
                    'description' => 'Custom SaaS Platform Architecture & Core Setup',
                    'quantity' => 1,
                    'unit_price' => 1500.00,
                    'total' => 1500.00
                ],
                [
                    'description' => 'Multi-Tenant Payment Gateway & Subscription Integration',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'total' => 1000.00
                ]
            ];

            echo \Services\InvoiceRenderer::render($mockInvoice, $mockItems, $brand, $activeTemplate);
            ?>
        </div>
    </div>
</div>

<?php page_end(); ?>
