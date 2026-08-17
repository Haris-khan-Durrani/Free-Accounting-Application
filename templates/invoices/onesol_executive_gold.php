<?php
/**
 * Flagship Template: OneSol Executive Gold
 * Premium Corporate & Commercial Proposal / Tax Invoice Layout
 */
$brand = $brand ?? [];
$inv = $inv ?? [];
$items = $items ?? [];

$primaryColor = '#0f172a';
$goldColor = '#f59e0b';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?=e($inv['invoice_number'] ?? 'PROPOSAL')?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #ffffff; color: #0f172a; line-height: 1.5; font-size: 13px; }
        .invoice-box { max-width: 800px; margin: 0 auto; padding: 0; background: #ffffff; }
        
        /* Top Header Bar */
        .top-header { background: #0f172a; color: #ffffff; padding: 24px 32px; display: flex; justify-content: space-between; align-items: center; }
        .logo-box { display: flex; align-items: center; gap: 12px; }
        .logo-box img { max-height: 48px; width: auto; }
        .brand-text h1 { font-size: 20px; font-weight: 900; letter-spacing: -0.5px; color: #ffffff; text-transform: uppercase; }
        .brand-text p { font-size: 10px; color: #d97706; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        
        .header-meta { text-align: right; }
        .header-meta h2 { font-size: 22px; font-weight: 900; color: #f59e0b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .header-meta p { font-size: 11px; color: #cbd5e1; font-weight: 600; }

        .body-container { padding: 32px; }

        /* Party Cards */
        .parties-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .party-card { background: #f8fafc; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; }
        .party-label { font-size: 10px; font-weight: 800; color: #d97706; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .party-name { font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 2px; }
        .party-detail { font-size: 12px; color: #64748b; }

        /* Title & Scope */
        .doc-title-block { margin-bottom: 24px; }
        .doc-title-block h3 { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .doc-title-block p { font-size: 12px; color: #64748b; }

        /* Items Table */
        .table-wrap { margin-bottom: 24px; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #0f172a; color: #ffffff; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px; }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 12.5px; color: #1e293b; }
        tr:last-child td { border-bottom: none; }
        .item-desc { font-weight: 700; color: #0f172a; }
        .item-sub { font-size: 11px; color: #64748b; margin-top: 2px; }

        /* Discount & Totals Grid */
        .totals-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .discount-banner { background: #fef3c7; border-radius: 12px; padding: 18px; border: 1px solid #fde68a; }
        .discount-title { font-size: 10px; font-weight: 800; color: #b45309; text-transform: uppercase; letter-spacing: 0.5px; }
        .discount-amount { font-size: 22px; font-weight: 900; color: #92400e; margin: 4px 0; }
        .discount-sub { font-size: 11px; color: #b45309; }

        .summary-card { background: #f8fafc; border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0; }
        .summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12.5px; color: #475569; }
        .total-box { background: #f59e0b; color: #ffffff; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        .total-box span { font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .total-box strong { font-size: 18px; font-weight: 900; }

        /* Notes List */
        .notes-card { background: #ffffff; margin-bottom: 28px; }
        .notes-card h4 { font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .notes-card ul { list-style: none; padding: 0; }
        .notes-card li { font-size: 11.5px; color: #475569; padding: 4px 0; display: flex; align-items: flex-start; gap: 8px; }
        .notes-card li::before { content: '•'; color: #f59e0b; font-size: 16px; line-height: 1; }

        /* Dark Footer Bar */
        .footer-bar { background: #0f172a; color: #ffffff; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 600; }
        .footer-bar span { color: #f59e0b; font-weight: 800; }
    </style>
</head>
<body>
<div class="invoice-box">
    <!-- Top Header -->
    <div class="top-header">
        <div class="logo-box">
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" alt="Logo">
            <?php else: ?>
                <div style="background:#f59e0b; width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:900; color:#fff;">OS</div>
            <?php endif; ?>
            <div class="brand-text">
                <h1><?=e($brand['company_name'] ?? 'ONESOL')?></h1>
                <p><?=e($brand['company_tagline'] ?? 'DIGITAL & SOFTWARE SERVICES')?></p>
            </div>
        </div>
        <div class="header-meta">
            <h2>PROPOSAL INVOICE</h2>
            <p><?=e($brand['company_name'] ?? 'ONESOL')?> | <?=e($brand['company_tagline'] ?? 'SOFTWARE SERVICES')?></p>
            <p style="margin-top: 4px;">Proposal No: <strong><?=e($inv['invoice_number'] ?? 'OS-PI-20260807-001')?></strong></p>
            <p>Date: <strong><?=e(date('d M Y', strtotime($inv['invoice_date'] ?? date('Y-m-d'))))?></strong></p>
            <p>Valid Until: <strong><?=e(date('d M Y', strtotime($inv['valid_until'] ?? '+14 days')))?></strong></p>
        </div>
    </div>

    <div class="body-container">
        <!-- Parties Grid -->
        <div class="parties-grid">
            <div class="party-card">
                <div class="party-label">FROM</div>
                <div class="party-name"><?=e($brand['company_name'] ?? 'OneSol')?></div>
                <div class="party-detail"><?=e($brand['company_tagline'] ?? 'Technology & Digital Solutions')?></div>
                <div class="party-detail"><?=e($brand['company_website'] ?? 'www.onesol.ae')?></div>
                <?php if (!empty($brand['tax_number'])): ?>
                    <div class="party-detail" style="margin-top:4px; font-weight:700;"><?=e($brand['tax_number_label'] ?? 'TRN')?>: <?=e($brand['tax_number'])?></div>
                <?php endif; ?>
            </div>
            <div class="party-card">
                <div class="party-label">PREPARED FOR</div>
                <div class="party-name"><?=e($inv['company_name'] ?? '360 Business Consultants')?></div>
                <div class="party-detail"><?=e($inv['address'] ?? 'Dubai, United Arab Emirates')?></div>
                <div class="party-detail">Client / Project: Business Services</div>
                <?php if (!empty($inv['tax_number'])): ?>
                    <div class="party-detail" style="margin-top:4px; font-weight:700;">Client Tax ID: <?=e($inv['tax_number'])?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Title Block -->
        <div class="doc-title-block">
            <h3>Project Commercial Proposal</h3>
            <p>Professional software development, implementation, and configuration services, as per the mutually agreed project scope.</p>
        </div>

        <!-- Items Table -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 55%;">DESCRIPTION</th>
                        <th style="text-align: center; width: 10%;">QTY</th>
                        <th style="text-align: right; width: 17%;">UNIT PRICE</th>
                        <th style="text-align: right; width: 18%;">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td><div class="item-desc">Software Development & Implementation Services</div><div class="item-sub">Includes setup, configuration, customization and delivery as per agreed scope.</div></td>
                            <td style="text-align: center; font-weight: 700;">1</td>
                            <td style="text-align: right; font-weight: 700; font-family: monospace;">AED 4,000.00</td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace;">AED 4,000.00</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $qty = (float)($item['qty'] ?? $item['quantity'] ?? 1);
                        $unitPrice = (float)($item['unit_price'] ?? 0);
                        $amount = (float)($item['amount'] ?? $item['total'] ?? ($qty * $unitPrice));
                        ?>
                        <tr>
                            <td>
                                <div class="item-desc"><?=e($item['description'] ?? 'Line Item')?></div>
                                <?php if (!empty($item['details'])): ?>
                                    <div class="item-sub"><?=e($item['details'])?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; font-weight: 700;"><?=e((string)$qty)?></td>
                            <td style="text-align: right; font-weight: 700; font-family: monospace;"><?=money($unitPrice, $inv['currency'] ?? 'AED')?></td>
                            <td style="text-align: right; font-weight: 800; font-family: monospace;"><?=money($amount, $inv['currency'] ?? 'AED')?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $discountAmount = isset($inv['discount_amount']) ? (float)$inv['discount_amount'] : 1500.00;
        $subtotal = isset($inv['subtotal']) ? (float)$inv['subtotal'] : 4000.00;
        $total = isset($inv['total']) ? (float)$inv['total'] : 2500.00;
        ?>

        <!-- Totals Grid -->
        <div class="totals-grid">
            <div class="discount-banner">
                <div class="discount-title">SPECIAL COMMERCIAL DISCOUNT</div>
                <div class="discount-amount">You save <?=money($discountAmount, $inv['currency'] ?? 'AED')?></div>
                <div class="discount-sub">Final proposal value: <?=money($total, $inv['currency'] ?? 'AED')?></div>
            </div>

            <div class="summary-card">
                <div class="summary-row">
                    <span>Original Value</span>
                    <strong style="font-family: monospace;"><?=money($subtotal, $inv['currency'] ?? 'AED')?></strong>
                </div>
                <div class="summary-row" style="color: #b45309;">
                    <span>Special Discount</span>
                    <strong style="font-family: monospace;">- <?=money($discountAmount, $inv['currency'] ?? 'AED')?></strong>
                </div>
                <div class="total-box">
                    <span>TOTAL AFTER DISCOUNT</span>
                    <strong><?=money($total, $inv['currency'] ?? 'AED')?></strong>
                </div>
            </div>
        </div>

        <!-- Commercial Notes -->
        <div class="notes-card">
            <h4>Commercial Notes</h4>
            <ul>
                <li>This proposal is based on the mutually agreed project scope and deliverables.</li>
                <li>Any additional scope, integrations or major changes may be quoted separately.</li>
                <li>Payment schedule and project start date will follow the agreed confirmation.</li>
                <li>This document is a proposal / proforma invoice and is not a tax invoice.</li>
            </ul>
        </div>
    </div>

    <!-- Dark Footer -->
    <div class="footer-bar">
        <div><span><?=e(strtoupper($brand['company_name'] ?? 'ONESOL'))?></span></div>
        <div><?=e($brand['company_website'] ?? 'www.onesol.ae')?></div>
    </div>
</div>
</body>
</html>
