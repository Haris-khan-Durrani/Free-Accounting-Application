<?php
/** @var array $inv */
/** @var array $items */
/** @var array $brand */

$primaryColor = $brand['primary_color'] ?? '#0f172a';
$secondaryColor = $brand['secondary_color'] ?? '#2563eb';
$fontFamily = $brand['font_family'] ?? 'Inter';
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-modern-minimal" style="font-family: '<?=e($fontFamily)?>', sans-serif;">
    <div class="inv-header" style="border-bottom: 3px solid <?=e($primaryColor)?>;">
        <div class="inv-brand-box">
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" alt="Logo" class="inv-logo">
            <?php endif; ?>
            <div>
                <h1 style="color: <?=e($primaryColor)?>;"><?=e($brand['company_name'])?></h1>
                <p><?=e($brand['company_tagline'])?></p>
                <small><?=e($brand['tax_number_label'])?>: <strong><?=e($brand['tax_number'] ?: 'N/A')?></strong></small>
            </div>
        </div>
        <div class="inv-meta-box">
            <h2 style="color: <?=e($primaryColor)?>;">TAX INVOICE</h2>
            <p><strong>Invoice No:</strong> <?=e($inv['invoice_number'])?></p>
            <p><strong>Date:</strong> <?=e(date('d M Y', strtotime($inv['invoice_date'])))?></p>
            <?php if (!empty($inv['valid_until'])): ?>
                <p><strong>Due Date:</strong> <?=e(date('d M Y', strtotime($inv['valid_until'])))?></p>
            <?php endif; ?>
            <span class="inv-status-pill <?=e($inv['status'])?>"><?=e(strtoupper($inv['status']))?></span>
        </div>
    </div>

    <div class="inv-parties">
        <div class="inv-party">
            <small>ISSUED BY</small>
            <h3><?=e($brand['company_name'])?></h3>
            <p><?=nl2br(e($brand['address']))?><br>Email: <?=e($brand['company_email'])?><br>Phone: <?=e($brand['company_phone'])?></p>
        </div>
        <div class="inv-party">
            <small>BILLED TO</small>
            <h3><?=e($inv['company_name'])?></h3>
            <p><?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?><br>
            <?php if (!empty($inv['tax_number'])): ?>
                <small>Tax ID: <?=e($inv['tax_number'])?></small>
            <?php endif; ?></p>
        </div>
    </div>

    <table class="inv-items-table">
        <thead style="background: <?=e($primaryColor)?>; color: #fff;">
            <tr>
                <th>Item & Description</th>
                <th class="center">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): 
                $iQty = (float)($it['qty'] ?? $it['quantity'] ?? 1);
                $iPrice = (float)($it['unit_price'] ?? 0);
                $iAmt = (float)($it['amount'] ?? $it['total'] ?? ($iQty * $iPrice));
            ?>
                <tr>
                    <td>
                        <strong><?=e($it['description'] ?? 'Line Item')?></strong>
                        <?php if (!empty($it['details'])): ?><small><?=e($it['details'])?></small><?php endif; ?>
                    </td>
                    <td class="center"><?=e(rtrim(rtrim(number_format($iQty, 2), '0'), '.'))?></td>
                    <td class="right"><?=\Core\Currency::format($iPrice, $currency)?></td>
                    <td class="right strong"><?=\Core\Currency::format($iAmt, $currency)?></td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>

    <div class="inv-summary-wrap">
        <div class="inv-bank-info">
            <?php if (!empty($brand['bank_name'])): ?>
                <small>PAYMENT INSTRUCTIONS</small>
                <p><strong>Bank:</strong> <?=e($brand['bank_name'])?><br>
                <strong>Account Name:</strong> <?=e($brand['bank_account_name'])?><br>
                <strong>IBAN:</strong> <?=e($brand['bank_iban'])?><br>
                <strong>SWIFT:</strong> <?=e($brand['bank_swift'])?></p>
            <?php endif; ?>
        </div>
        <div class="inv-totals">
            <div><span>Subtotal:</span> <strong><?=\Core\Currency::format((float)($inv['subtotal'] ?? 0), $currency)?></strong></div>
            <?php if (!empty($inv['discount_amount']) && (float)$inv['discount_amount'] > 0): ?>
                <div><span>Discount:</span> <strong>- <?=\Core\Currency::format((float)$inv['discount_amount'], $currency)?></strong></div>
            <?php endif; ?>
            <?php if (!empty($inv['tax_amount']) && (float)$inv['tax_amount'] > 0): ?>
                <div><span>VAT / Tax:</span> <strong>+ <?=\Core\Currency::format((float)$inv['tax_amount'], $currency)?></strong></div>
            <?php endif; ?>
            <div class="grand-total" style="background: <?=e($primaryColor)?>; color: #fff;">
                <span>TOTAL DUE:</span> <strong><?=\Core\Currency::format((float)($inv['total'] ?? 0), $currency)?></strong>
            </div>
        </div>

    </div>

    <?php if ($brand['show_qr_code']): ?>
        <div class="inv-qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?=urlencode($brand['company_name'] . '|' . $inv['invoice_number'] . '|' . $inv['total'] . '|' . $brand['tax_number'])?>" alt="ZATCA / UAE Tax QR Code" class="inv-qr-code">
            <small>Scan QR code for instant digital validation</small>
        </div>
    <?php endif; ?>

    <div class="inv-footer" style="border-top: 1px solid #e2e8f0;">
        <p><?=e($brand['invoice_footer_notes'])?></p>
    </div>
</div>
