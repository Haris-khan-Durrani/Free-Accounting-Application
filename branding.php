<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

// Fetch all registered tenants for superadmin/owner workspace switcher
$stAllTenants = $pdo->query("SELECT id, name, code FROM tenants ORDER BY name ASC");
$allTenants = $stAllTenants->fetchAll();

$targetTenantId = (int)($_GET['tenant_id'] ?? $_POST['target_tenant_id'] ?? $tid);
if (!has_role(['owner', 'admin']) && $targetTenantId !== $tid) {
    $targetTenantId = $tid;
}

$brand = branding($targetTenantId);
$targetTenantObj = null;
foreach ($allTenants as $at) {
    if ($at['id'] == $targetTenantId) {
        $targetTenantObj = $at;
        break;
    }
}
$targetTenantName = $targetTenantObj ? $targetTenantObj['name'] : tenant()['name'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $companyName = trim($_POST['company_name'] ?? '');
    $companyTagline = trim($_POST['company_tagline'] ?? '');
    $companyWebsite = trim($_POST['company_website'] ?? '');
    $companyEmail = trim($_POST['company_email'] ?? '');
    $companyPhone = trim($_POST['company_phone'] ?? '');
    $taxLabel = trim($_POST['tax_number_label'] ?? 'TRN / Tax ID');
    $taxNumber = trim($_POST['tax_number'] ?? '');
    $regNumber = trim($_POST['registration_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? 'United Arab Emirates');

    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAccountName = trim($_POST['bank_account_name'] ?? '');
    $bankAccountNumber = trim($_POST['bank_account_number'] ?? '');
    $bankIban = trim($_POST['bank_iban'] ?? '');
    $bankSwift = trim($_POST['bank_swift'] ?? '');

    $primaryColor = trim($_POST['primary_color'] ?? '#0f172a');
    $secondaryColor = trim($_POST['secondary_color'] ?? '#2563eb');
    $accentColor = trim($_POST['accent_color'] ?? '#d97706');
    $fontFamily = trim($_POST['font_family'] ?? 'Inter');
    $defaultTemplate = trim($_POST['default_invoice_template'] ?? 'modern_minimal');
    $footerNotes = trim($_POST['invoice_footer_notes'] ?? '');
    $watermarkEnabled = isset($_POST['watermark_enabled']) ? 1 : 0;
    $showQrCode = isset($_POST['show_qr_code']) ? 1 : 0;

    // Handle File Uploads
    $uploadDir = __DIR__ . '/assets/img/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $logoUrl = $brand['logo_url'];
    $darkLogoUrl = $brand['dark_logo_url'];
    $signatureUrl = $brand['signature_url'];
    $stampUrl = $brand['stamp_url'];

    $handleUpload = function($fileKey, $prefix) use ($uploadDir, $targetTenantId) {
        if (!empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                $filename = $prefix . '_' . $targetTenantId . '_' . time() . '.' . $ext;
                $target = $uploadDir . $filename;
                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target)) {
                    return 'assets/img/uploads/' . $filename;
                }
            }
        }
        return null;
    };

    if ($upLogo = $handleUpload('logo_file', 'logo')) $logoUrl = $upLogo;
    if ($upDarkLogo = $handleUpload('dark_logo_file', 'dark_logo')) $darkLogoUrl = $upDarkLogo;
    if ($upSig = $handleUpload('signature_file', 'signature')) $signatureUrl = $upSig;
    if ($upStamp = $handleUpload('stamp_file', 'stamp')) $stampUrl = $upStamp;

    $st = $pdo->prepare("INSERT INTO branding_settings (
        tenant_id, company_name, company_tagline, company_website, company_email, company_phone,
        tax_number_label, tax_number, registration_number, address, city, country,
        bank_name, bank_account_name, bank_account_number, bank_iban, bank_swift,
        primary_color, secondary_color, accent_color, font_family, logo_url, dark_logo_url,
        signature_url, stamp_url, default_invoice_template, invoice_footer_notes, watermark_enabled, show_qr_code
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?
    ) ON DUPLICATE KEY UPDATE
        company_name=VALUES(company_name), company_tagline=VALUES(company_tagline), company_website=VALUES(company_website),
        company_email=VALUES(company_email), company_phone=VALUES(company_phone), tax_number_label=VALUES(tax_number_label),
        tax_number=VALUES(tax_number), registration_number=VALUES(registration_number), address=VALUES(address),
        city=VALUES(city), country=VALUES(country), bank_name=VALUES(bank_name), bank_account_name=VALUES(bank_account_name),
        bank_account_number=VALUES(bank_account_number), bank_iban=VALUES(bank_iban), bank_swift=VALUES(bank_swift),
        primary_color=VALUES(primary_color), secondary_color=VALUES(secondary_color), accent_color=VALUES(accent_color),
        font_family=VALUES(font_family), logo_url=VALUES(logo_url), dark_logo_url=VALUES(dark_logo_url),
        signature_url=VALUES(signature_url), stamp_url=VALUES(stamp_url), default_invoice_template=VALUES(default_invoice_template),
        invoice_footer_notes=VALUES(invoice_footer_notes), watermark_enabled=VALUES(watermark_enabled), show_qr_code=VALUES(show_qr_code)");

    $st->execute([
        $targetTenantId, $companyName, $companyTagline, $companyWebsite, $companyEmail, $companyPhone,
        $taxLabel, $taxNumber, $regNumber, $address, $city, $country,
        $bankName, $bankAccountName, $bankAccountNumber, $bankIban, $bankSwift,
        $primaryColor, $secondaryColor, $accentColor, $fontFamily, $logoUrl, $darkLogoUrl,
        $signatureUrl, $stampUrl, $defaultTemplate, $footerNotes, $watermarkEnabled, $showQrCode
    ]);

    \Core\Tenant::forgetCache($targetTenantId);

    log_audit($pdo, 'update', 'branding', $targetTenantId, "Updated dynamic branding profile and themes for workspace #$targetTenantId");
    flash('success', "Dynamic branding and company profile for '$targetTenantName' updated successfully!");
    redirect('branding.php?tenant_id=' . $targetTenantId);
}

page_start('Dynamic Branding & Company Profile');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Dynamic Branding & Identity</h1>
        <p class="mt-1 text-sm text-slate-500">Configure visual themes, logo media, bank details, and invoice parameters for <strong><?=e($targetTenantName)?></strong>.</p>
    </div>
    <div class="mt-4 md:mt-0 flex items-center space-x-3">
        <?php if (count($allTenants) > 1 && has_role(['owner', 'admin'])): ?>
            <div class="flex items-center space-x-2 bg-white px-3.5 py-2 rounded-xl border border-slate-200 shadow-sm">
                <label class="text-2xs font-extrabold text-slate-500 uppercase">Workspace:</label>
                <select onchange="location.href='branding.php?tenant_id=' + this.value" class="rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-extrabold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    <?php foreach ($allTenants as $at): ?>
                        <option value="<?=$at['id']?>" <?=$at['id'] == $targetTenantId ? 'selected' : ''?>>
                            🏢 <?=e($at['name'])?> (code: <?=e($at['code'])?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if (has_role(['owner', 'admin'])): ?>
        <a href="domain_settings?tenant_id=<?=$targetTenantId?>" class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all space-x-2">
            <i class="fa-solid fa-globe text-amber-300 text-sm"></i>
            <span>Whitelabel Domain Settings</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (has_role(['owner', 'admin'])): ?>
<!-- Whitelabel Custom Domain Promo Card -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-2xl p-5 text-white border border-slate-800 shadow-xl mb-8 flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="flex items-center space-x-4">
        <div class="w-12 h-12 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-xl font-bold border border-blue-500/30">
            <i class="fa-solid fa-globe"></i>
        </div>
        <div>
            <div class="flex items-center space-x-2">
                <h3 class="font-extrabold text-base text-white">Whitelabel Custom Domain &amp; SSL</h3>
                <span class="px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-300 text-3xs font-black uppercase">Enterprise Feature</span>
            </div>
            <p class="text-xs text-slate-300 mt-0.5">Host payment portals under your own domain (e.g., <code>billing.yourcompany.com</code>) with automated DNS verification.</p>
        </div>
    </div>
    <a href="domain_settings" class="whitespace-nowrap px-4 py-2 bg-white text-slate-900 hover:bg-slate-100 rounded-xl text-xs font-black transition-all flex items-center space-x-1.5 shadow-sm">
        <span>Configure Domain &amp; Test DNS</span>
        <i class="fa-solid fa-arrow-right text-2xs"></i>
    </a>
</div>
<?php endif; ?>


<form method="post" enctype="multipart/form-data" class="space-y-8">
    <?=csrf_field()?>
    <input type="hidden" name="target_tenant_id" value="<?=$targetTenantId?>">

    <!-- Section 1: Business Profile -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-building text-amber-500 mr-2.5"></i> Business & Tax Profile
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Company Name</label>
                <input type="text" name="company_name" value="<?=e($brand['company_name'])?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tagline / Motto</label>
                <input type="text" name="company_tagline" value="<?=e($brand['company_tagline'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Email Address</label>
                <input type="email" name="company_email" value="<?=e($brand['company_email'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone Number</label>
                <input type="text" name="company_phone" value="<?=e($brand['company_phone'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tax Label (e.g. TRN, VAT No, EIN)</label>
                <input type="text" name="tax_number_label" value="<?=e($brand['tax_number_label'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tax / TRN Registration No</label>
                <input type="text" name="tax_number" value="<?=e($brand['tax_number'])?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Official Address</label>
                <textarea name="address" rows="3" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition-all"><?=e($brand['address'])?></textarea>
            </div>
        </div>
    </div>

    <!-- Section 2: Colors & Media -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-4 mb-6 flex items-center">
            <i class="fa-solid fa-palette text-blue-500 mr-2.5"></i> Dynamic Theme Colors & Media
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Primary Brand Color</label>
                <div class="flex items-center space-x-3">
                    <input type="color" name="primary_color" value="<?=e($brand['primary_color'])?>" class="h-10 w-16 p-1 rounded-lg border border-slate-300 cursor-pointer">
                    <span class="text-sm font-bold text-slate-800"><?=e($brand['primary_color'])?></span>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Secondary Color</label>
                <div class="flex items-center space-x-3">
                    <input type="color" name="secondary_color" value="<?=e($brand['secondary_color'])?>" class="h-10 w-16 p-1 rounded-lg border border-slate-300 cursor-pointer">
                    <span class="text-sm font-bold text-slate-800"><?=e($brand['secondary_color'])?></span>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Accent Highlight Color</label>
                <div class="flex items-center space-x-3">
                    <input type="color" name="accent_color" value="<?=e($brand['accent_color'])?>" class="h-10 w-16 p-1 rounded-lg border border-slate-300 cursor-pointer">
                    <span class="text-sm font-bold text-slate-800"><?=e($brand['accent_color'])?></span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-slate-100 pt-6">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Company Logo (Light Mode)</label>
                <input type="file" name="logo_file" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 cursor-pointer">
                <?php if ($brand['logo_url']): ?>
                    <img src="<?=e($brand['logo_url'])?>" class="h-12 w-auto mt-3 rounded-lg bg-slate-100 p-1 border border-slate-200">
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Authorized Corporate Signature</label>
                <input type="file" name="signature_file" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                <?php if ($brand['signature_url']): ?>
                    <img src="<?=e($brand['signature_url'])?>" class="h-12 w-auto mt-3 rounded-lg bg-slate-100 p-1 border border-slate-200">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Submit Action -->
    <div class="flex justify-end">
        <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
            <i class="fa-solid fa-floppy-disk mr-2"></i> Save Dynamic Branding
        </button>
    </div>
</form>

<?php page_end(); ?>
