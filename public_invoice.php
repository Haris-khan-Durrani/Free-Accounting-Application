<?php
require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];
$id = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

$st = $pdo->prepare('SELECT i.*, c.company_name, c.contact_name, c.email, c.phone, c.address, c.tax_number FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ?');
$st->execute([$id]);
$inv = $st->fetch();

if (!$inv) {
    http_response_code(404);
    exit('Invoice unavailable or invalid token.');
}

// Token & Session Security Guard: Only allow if token matches OR logged in user belongs to this tenant
$expectedToken = get_invoice_token($inv);
$isAuthorizedUser = !empty($_SESSION['user_id']) && (int)($_SESSION['active_tenant_id'] ?? $_SESSION['tenant_id'] ?? 0) === (int)$inv['tenant_id'];
$isValidToken = !empty($token) && hash_equals($expectedToken, $token);

if (!$isAuthorizedUser && !$isValidToken) {
    http_response_code(403);
    exit('Access denied. Invalid or missing invoice access token.');
}

$itemsSt = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id');
$itemsSt->execute([$id]);
$items = $itemsSt->fetchAll();

// Get tenant branding & public link
$brand = \Core\Branding::get($pdo, (int)$inv['tenant_id']);
$templateId = $inv['template_id'] ?: $brand['default_invoice_template'];
if (!empty($brand['default_invoice_template']) && $brand['default_invoice_template'] === 'custom_drag_drop') {
    $templateId = 'custom_drag_drop';
}
$publicShareUrl = get_public_invoice_url($inv);

?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?=e($inv['invoice_number'])?> - <?=e($brand['company_name'])?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #0f172a; min-height: 100vh; padding: 40px 15px; color: #f8fafc; }
        .public-portal-container { max-width: 900px; margin: 0 auto; }
        .portal-header-bar { display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #334155; }
    </style>
</head>
<body>
<div class="public-portal-container">
    <div class="portal-header-bar">
        <div>
            <span style="color:#94a3b8; font-size:12px;">CLIENT PORTAL</span>
            <h2 style="margin:2px 0 0 0; font-size:18px; color:#fff;"><?=e($brand['company_name'])?></h2>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="btn" style="background:#25D366; color:#fff; font-weight:bold; border:none; padding:8px 14px; border-radius:8px; cursor:pointer;" onclick="window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent('Tax Invoice <?=e($inv['invoice_number'])?> from <?=e($brand['company_name'])?> - Total: <?=e($inv['currency'])?> <?=number_format((float)$inv['total'], 2)?>. View & Pay Online: ' + <?=json_encode($publicShareUrl)?>), '_blank')">💬 Share via WhatsApp</button>
            <button class="btn" style="background:#3b82f6; color:#fff; font-weight:bold; border:none; padding:8px 14px; border-radius:8px; cursor:pointer;" onclick="navigator.clipboard.writeText(<?=json_encode($publicShareUrl)?>); alert('Invoice payment link copied to clipboard!');">📋 Copy Link</button>
            <button class="btn btn-gold" onclick="window.print()">🖨️ Print / Download PDF</button>
        </div>
    </div>

    <div style="background:#fff; color:#0f172a; border-radius:12px; padding:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.5);">
        <?=\Services\InvoiceRenderer::render($inv, $items, $brand, $templateId)?>
    </div>
</div>
</body>
</html>

