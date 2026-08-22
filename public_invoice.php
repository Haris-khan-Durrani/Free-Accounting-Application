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
$paytabsEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'paytabs_enabled', '0', $tid);
$telrEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'telr_enabled', '0', $tid);
$checkoutComEnabled = \Services\PaymentGatewayService::getSetting($pdo, 'checkout_enabled', '0', $tid);
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
        .pay-btn-paytabs { background: #0284c7; color: #fff; font-weight: 900; }
        .pay-btn-paytabs:hover { background: #0369a1; }
        .pay-btn-telr { background: #d97706; color: #fff; font-weight: 900; }
        .pay-btn-telr:hover { background: #b45309; }
        .pay-btn-checkout { background: #0f172a; color: #fff; font-weight: 900; border: 1px solid #334155; }
        .pay-btn-checkout:hover { background: #1e293b; }
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

    <?php if (!empty($_GET['error'])): ?>
        <div style="background:#7f1d1d; border:1px solid #ef4444; padding:14px 20px; border-radius:12px; margin-bottom:20px; color:#fca5a5; font-weight:bold; font-size:13px; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
            <div><?=e($_GET['error'])?></div>
        </div>
    <?php endif; ?>

    <?php if (function_exists('render_flash')): ?>
        <?=render_flash()?>
    <?php endif; ?>

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

            <!-- Group 1: Credit Cards & Direct Wallets -->
            <?php 
            $hasCardGateways = ($stripeEnabled === '1' || $ziinaEnabled === '1' || $zbooniEnabled === '1' || $networkEnabled === '1' || $paytabsEnabled === '1' || $telrEnabled === '1' || $checkoutComEnabled === '1');
            $hasBnplGateways = ($tabbyEnabled === '1' || $tamaraEnabled === '1');
            $hasBankTransfer = ($bankEnabled === '1' && !empty($brand['bank_name']));
            ?>

            <?php if ($hasCardGateways): ?>
                <div style="margin-bottom: 18px;">
                    <span style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; display: block; margin-bottom: 8px;">💳 Credit & Debit Cards / Instant Wallets</span>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <?php if ($stripeEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=stripe" class="pay-btn pay-btn-stripe">
                                <svg style="height:18px; width:auto; vertical-align:middle;" viewBox="0 0 60 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M59.64 14.28c0-4.52-2.18-7.98-6.38-7.98-4.22 0-6.8 3.46-6.8 7.94 0 5.3 3.12 7.84 7.36 7.84 2.12 0 3.76-.48 4.96-1.16v-2.92c-1.2.6-2.52.92-4.14.92-1.74 0-3.26-.64-3.52-2.62h8.48c.02-.38.04-1.24.04-2.02zm-8.48-1.54c.14-1.84 1.34-2.68 2.58-2.68 1.22 0 2.44.84 2.54 2.68h-5.12zM38.8 6.3h-4.3v15.5h4.3V6.3zm-.12-3.8c0-1.28-1.04-2.32-2.32-2.32s-2.32 1.04-2.32 2.32c0 1.28 1.04 2.32 2.32 2.32s2.32-1.04 2.32-2.32zM45.54 9.18c0-1.12-.9-1.58-2.16-1.58-1.44 0-3.24.58-4.42 1.28V5.6c1.38-.6 3.18-1.04 4.88-1.04 3.48 0 5.86 1.76 5.86 5.28v11.96h-4.16v-1.88c-1.1 1.22-2.8 2.14-4.88 2.14-3.08 0-5.32-1.92-5.32-4.82 0-3.9 3.44-5.34 8.2-5.34v-.72zm-4.16 7.74c0 1.48 1.04 2.34 2.42 2.34 1.42 0 2.76-.84 3.44-2.06v-3.76c-3.14 0-5.86.6-5.86 3.48zM24.28 9.38v12.42h-4.3V9.38h4.3zm0-3.08h-4.3V2.5h4.3v3.8zM17.48 14.16c0-2.32-1.68-3.48-3.48-3.48-1.84 0-3.5 1.16-3.5 3.48v7.64H6.2V6.3h4.14v1.88c1.1-1.2 2.74-2.14 4.84-2.14 3.32 0 6.6 2.38 6.6 7.2v8.56h-4.3v-7.64zM4.46 9.8c0-.74-.62-1.06-1.64-1.06-1.48 0-3.32.58-4.48 1.26V6.74c1.36-.58 3.14-1.02 4.84-1.02 3.4 0 5.6 1.7 5.6 5.08v11H4.62v-1.74c-1.06 1.14-2.62 2-4.62 2C-2.88 22.06-5 20.2-5 17.38c0-3.66 3.22-5.02 7.68-5.02v-.72c.04-.64-.42-.98-1.4-1.04z" fill="#FFFFFF"/>
                                </svg>
                                <span>Pay via Card / Apple Pay</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($ziinaEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=ziina" class="pay-btn pay-btn-ziina" title="Pay via Ziina (Apple Pay / Credit Card)">
                                <svg style="height:20px; width:auto; vertical-align:middle;" viewBox="0 0 90 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="90" height="32" rx="6" fill="#8B5CF6"/>
                                    <path d="M22 8L12 18H18L14 24L24 14H18L22 8Z" fill="#F59E0B"/>
                                    <text x="60%" y="68%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="900" font-size="18" fill="#FFFFFF">Ziina</text>
                                </svg>
                                <span>Pay via Ziina</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($networkEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=network" class="pay-btn pay-btn-network">
                                <svg style="height:20px; width:auto; vertical-align:middle;" viewBox="0 0 120 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="120" height="32" rx="6" fill="#0284C7"/>
                                    <circle cx="20" cy="16" r="7" fill="#38BDF8"/>
                                    <text x="68%" y="68%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="900" font-size="15" fill="#FFFFFF">NGenius</text>
                                </svg>
                                <span>Pay via NGenius</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($paytabsEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=paytabs" class="pay-btn pay-btn-paytabs" title="Pay via PayTabs">
                                <svg style="height:20px; width:auto; vertical-align:middle;" viewBox="0 0 110 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="110" height="32" rx="6" fill="#0284C7"/>
                                    <path d="M16 10H26M16 16H24M16 22H21" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round"/>
                                    <text x="65%" y="68%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="900" font-size="16" fill="#FFFFFF">paytabs</text>
                                </svg>
                                <span>Pay via PayTabs</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($telrEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=telr" class="pay-btn pay-btn-telr" title="Pay via Telr">
                                <svg style="height:20px; width:auto; vertical-align:middle;" viewBox="0 0 90 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="90" height="32" rx="6" fill="#D97706"/>
                                    <text x="50%" y="68%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="900" font-size="19" fill="#FFFFFF">telr</text>
                                </svg>
                                <span>Pay via Telr</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($checkoutComEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=checkout" class="pay-btn pay-btn-checkout" title="Pay via Checkout.com">
                                <svg style="height:20px; width:auto; vertical-align:middle;" viewBox="0 0 140 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="140" height="32" rx="6" fill="#0F172A" stroke="#334155" stroke-width="1.5"/>
                                    <text x="50%" y="68%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="900" font-size="15" fill="#FFFFFF">checkout.com</text>
                                </svg>
                                <span>Pay via Checkout.com</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($zbooniEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=zbooni" class="pay-btn pay-btn-zbooni" title="Pay via Zbooni">
                                <svg style="height:20px; width:auto; vertical-align:middle;" viewBox="0 0 100 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="100" height="32" rx="6" fill="#10B981"/>
                                    <text x="50%" y="68%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="900" font-size="18" fill="#FFFFFF">zbooni</text>
                                </svg>
                                <span>Pay via Zbooni</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasBnplGateways): ?>
                <div style="margin-bottom: 18px;">
                    <span style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; display: block; margin-bottom: 8px;">🛍️ Buy Now Pay Later (Installments)</span>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <?php if ($tabbyEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=tabby" class="pay-btn pay-btn-tabby" title="Pay in 4 interest-free payments with Tabby" style="background:#3bffb6; color:#000; padding:10px 18px;">
                                <img src="assets/images/gateways/tabby.png" onerror="this.onerror=null; this.src='https://media.uaelogos.ae/1950/conversions/Tabby-thumb.png';" style="height:26px; width:auto; vertical-align:middle; display:inline-block;" alt="Tabby Official Logo">
                                <span style="font-weight:900;">Pay 4x <?=e($currency)?> <?=$tabbyInstallment?> / mo</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($tamaraEnabled === '1'): ?>
                            <a href="invoice_checkout.php?invoice_id=<?=$inv['id']?>&token=<?=e($token)?>&gateway=tamara" class="pay-btn pay-btn-tamara" title="Pay in 3 interest-free payments with Tamara" style="background:#fff0f5; border:1px solid #ff70a6; color:#222; padding:10px 18px;">
                                <img src="assets/images/gateways/tamara.png" onerror="this.onerror=null; this.src='https://media.uaelogos.ae/1958/conversions/Tamara-En-thumb.png';" style="height:26px; width:auto; vertical-align:middle; display:inline-block;" alt="Tamara Official Logo">
                                <span style="font-weight:900; color:#d81b60;">Pay 3x <?=e($currency)?> <?=$tamaraInstallment?> / mo</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasBankTransfer): ?>
                <div>
                    <span style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; display: block; margin-bottom: 8px;">🏛️ Direct Wire Transfer</span>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <button class="pay-btn" style="background:#334155; color:#fff;" onclick="alert('Wire Transfer Details:\n\nBank: <?=e($brand['bank_name'])?>\nAccount Name: <?=e($brand['bank_account_name'])?>\nIBAN: <?=e($brand['bank_iban'])?>\nSWIFT: <?=e($brand['bank_swift'])?>')">
                            <svg style="height:20px; width:auto; vertical-align:middle;" viewBox="0 0 130 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="130" height="32" rx="6" fill="#334155"/>
                                <path d="M18 11L24 7L30 11V13H18V11ZM19 14H21V21H19V14ZM23 14H25V21H23V14ZM27 14H29V21H27V14ZM17 22H31V24H17V22Z" fill="#F1F5F9"/>
                                <text x="68%" y="68%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="900" font-size="13" fill="#FFFFFF">Bank Wire</text>
                            </svg>
                            <span>Wire Transfer Details</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
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


