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

$tid = (int)$inv['tenant_id'];

// Check tenant payment gateway settings
$stripeEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'stripe_enabled', '1', $tid);
$networkEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'network_enabled', '0', $tid);
$tabbyEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'tabby_enabled', '0', $tid);
$tamaraEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'tamara_enabled', '0', $tid);
$ziinaEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'ziina_enabled', '0', $tid);
$zbooniEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'zbooni_enabled', '0', $tid);
$bankEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'bank_transfer_enabled', '1', $tid);

$isPaid = ($inv['status'] === 'paid');
$isVoid = ($inv['status'] === 'void');

$currency = strtoupper($inv['currency'] ?? 'AED');
$totalAmount = (float)$inv['total'];
$remainingBalance = max(0, $totalAmount - (float)$inv['paid_amount']);
$tabbyInstallment = number_format($remainingBalance / 4, 2);
$tamaraInstallment = number_format($remainingBalance / 3, 2);

?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?=e($inv['invoice_number'])?> - <?=e($brand['company_name'])?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0f172a; min-height: 100vh; padding: 40px 15px; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
        .public-portal-container { max-width: 950px; margin: 0 auto; }
        .portal-header-bar { display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 15px 25px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #334155; }
        .pay-card { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 1px solid #3b82f6; border-radius: 16px; padding: 24px; margin-bottom: 25px; box-shadow: 0 10px 25px -5px rgba(59,130,246,0.2); }
        .pay-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 800; font-size: 13px; padding: 12px 20px; border-radius: 12px; cursor: pointer; text-decoration: none; transition: all 0.2s ease; border: none; }
        .pay-btn-stripe { background: #6366f1; color: #fff; }
        .pay-btn-stripe:hover { background: #4f46e5; }
        .pay-btn-tabby { background: #3bffb6; color: #000; font-weight: 900; }
        .pay-btn-tabby:hover { background: #00e595; }
        .pay-btn-tamara { background: #ff70a6; color: #fff; font-weight: 900; }
        .pay-btn-tamara:hover { background: #ff4785; }
        .pay-btn-network { background: #0284c7; color: #fff; }
        .pay-btn-network:hover { background: #0369a1; }
        .pay-btn-ziina { background: #8b5cf6; color: #fff; font-weight: 900; }
        .pay-btn-ziina:hover { background: #7c3aed; }
        .pay-btn-zbooni { background: #10b981; color: #fff; font-weight: 900; }
        .pay-btn-zbooni:hover { background: #059669; }
    </style>
</head>
<body>
<div class="public-portal-container">
    <div class="portal-header-bar">
        <div>
            <span style="color:#94a3b8; font-size:11px; text-transform:uppercase; font-weight:bold; letter-spacing:1px;">SECURE CLIENT PORTAL</span>
            <h2 style="margin:2px 0 0 0; font-size:18px; color:#fff; font-weight:800;"><?=e($brand['company_name'])?></h2>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="btn" style="background:#25D366; color:#fff; font-weight:bold; border:none; padding:8px 14px; border-radius:8px; cursor:pointer;" onclick="window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent('Tax Invoice <?=e($inv['invoice_number'])?> from <?=e($brand['company_name'])?> - Total: <?=e($inv['currency'])?> <?=number_format((float)$inv['total'], 2)?>. View & Pay Online: ' + <?=json_encode($publicShareUrl)?>), '_blank')">💬 WhatsApp</button>
            <button class="btn" style="background:#3b82f6; color:#fff; font-weight:bold; border:none; padding:8px 14px; border-radius:8px; cursor:pointer;" onclick="navigator.clipboard.writeText(<?=json_encode($publicShareUrl)?>); alert('Invoice link copied!');">📋 Copy Link</button>
            <button class="btn btn-gold" onclick="window.print()">🖨️ Print / PDF</button>
        </div>
    </div>

    <?php if (!$isPaid && !$isVoid): ?>
        <div class="pay-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px; border-b:1px solid #334155; padding-bottom:15px;">
                <div>
                    <span style="background:#3b82f620; color:#60a5fa; font-size:10px; font-weight:800; padding:3px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:1px;">Online Payment Options</span>
                    <h3 style="margin:6px 0 0 0; color:#fff; font-size:20px; font-weight:900;">Pay Invoice Online Directly</h3>
                </div>
                <div style="text-align:right;">
                    <span style="color:#94a3b8; font-size:12px; display:block;">Amount Due:</span>
                    <span style="color:#fbbf24; font-size:24px; font-weight:900; font-family:monospace;"><?=e($currency)?> <?=number_format($remainingBalance, 2)?></span>
                </div>
            </div>

            <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                <?php if ($stripeEnabled === '1'): ?>
                    <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=stripe" class="pay-btn pay-btn-stripe">
                        <i class="fa-solid fa-credit-card"></i> Pay via Credit Card / Apple Pay
                    </a>
                <?php endif; ?>

                <?php if ($tabbyEnabled === '1'): ?>
                    <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=tabby" class="pay-btn pay-btn-tabby" title="Pay in 4 interest-free payments with Tabby">
                        <span>tabby</span> Pay 4x <?=e($currency)?> <?=$tabbyInstallment?> / mo
                    </a>
                <?php endif; ?>

                <?php if ($tamaraEnabled === '1'): ?>
                    <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=tamara" class="pay-btn pay-btn-tamara" title="Pay in 3 interest-free payments with Tamara">
                        <span>tamara</span> Pay 3x <?=e($currency)?> <?=$tamaraInstallment?> / mo
                    </a>
                <?php endif; ?>

                <?php if ($networkEnabled === '1'): ?>
                    <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=network" class="pay-btn pay-btn-network">
                        <i class="fa-solid fa-lock"></i> Pay via NGenius Card
                    </a>
                <?php endif; ?>

                <?php if ($ziinaEnabled === '1'): ?>
                    <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=ziina" class="pay-btn pay-btn-ziina" title="Pay via Ziina (Apple Pay / Credit Card)">
                        <i class="fa-solid fa-bolt"></i> Pay via Ziina
                    </a>
                <?php endif; ?>

                <?php if ($zbooniEnabled === '1'): ?>
                    <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=zbooni" class="pay-btn pay-btn-zbooni" title="Pay via Zbooni">
                        <i class="fa-solid fa-bag-shopping"></i> Pay via Zbooni
                    </a>
                <?php endif; ?>

                <?php if ($bankEnabled === '1' && !empty($brand['bank_name'])): ?>
                    <button class="pay-btn" style="background:#334155; color:#fff;" onclick="alert('Wire Transfer Details:\n\nBank: <?=e($brand['bank_name'])?>\nAccount Name: <?=e($brand['bank_account_name'])?>\nIBAN: <?=e($brand['bank_iban'])?>\nSWIFT: <?=e($brand['bank_swift'])?>')">
                        <i class="fa-solid fa-building-columns"></i> Wire Transfer Details
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($isPaid): ?>
        <div style="background:#064e3b; border:1px solid #10b981; padding:16px; border-radius:12px; margin-bottom:20px; text-align:center; color:#a7f3d0; font-weight:bold;">
            <i class="fa-solid fa-circle-check" style="font-size:20px; margin-right:8px;"></i>
            THIS INVOICE HAS BEEN FULLY PAID AND SETTLED. THANK YOU FOR YOUR BUSINESS!
        </div>
    <?php endif; ?>

    <div style="background:#fff; color:#0f172a; border-radius:12px; padding:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.5);">
        <?=\Services\InvoiceRenderer::render($inv, $items, $brand, $templateId)?>
    </div>
</div>
</body>
</html>


