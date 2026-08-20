<?php
$primaryColor = $brand['primary_color'] ?? '#1e293b';
$accentColor = $brand['accent_color'] ?? '#d97706';
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-corporate" style="font-family: 'Inter', sans-serif; border: 2px solid <?=e($primaryColor)?>; padding: 30px;">
    <div style="background: <?=e($primaryColor)?>; color: #fff; padding: 20px; margin: -30px -30px 20px -30px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" style="max-height: 50px; background: #fff; padding: 4px; border-radius: 4px;">
            <?php endif; ?>
            <h1 style="margin: 5px 0 0 0; font-size: 24px; letter-spacing: 1px;"><?=e(strtoupper($brand['company_name']))?></h1>
            <small><?=e($brand['company_tagline'])?></small>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; color: <?=e($accentColor)?>; font-size: 28px;">COMMERCIAL INVOICE</h2>
            <p style="margin: 5px 0 0 0;"># <?=e($inv['invoice_number'])?></p>
            <small>Date: <?=e(date('d M Y', strtotime($inv['invoice_date'])))?></small>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 25px;">
        <div style="width: 48%; border: 1px solid #cbd5e1; padding: 12px; border-radius: 4px;">
            <strong style="color: <?=e($primaryColor)?>;">FROM:</strong><br>
            <strong><?=e($brand['company_name'])?></strong><br>
            <?=nl2br(e($brand['address']))?><br>
            <?=e($brand['tax_number_label'])?>: <?=e($brand['tax_number'])?>
        </div>
        <div style="width: 48%; border: 1px solid #cbd5e1; padding: 12px; border-radius: 4px;">
            <strong style="color: <?=e($primaryColor)?>;">PREPARED FOR:</strong><br>
            <strong><?=e($inv['company_name'])?></strong><br>
            <?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?>
        </div>
    </div>

    <table class="inv-items-table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr style="border-bottom: 2px solid <?=e($primaryColor)?>; background: #f8fafc;">
                <th style="text-align: left; padding: 10px;">Item Details</th>
                <th style="text-align: center; padding: 10px;">Qty</th>
                <th style="text-align: right; padding: 10px;">Rate</th>
                <th style="text-align: right; padding: 10px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): 
                $iQty = (float)($it['qty'] ?? $it['quantity'] ?? 1);
                $iPrice = (float)($it['unit_price'] ?? 0);
                $iAmt = (float)($it['amount'] ?? $it['total'] ?? ($iQty * $iPrice));
            ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px;">
                        <strong><?=e($it['description'] ?? 'Line Item')?></strong>
                        <?php if (!empty($it['details'])): ?><br><small><?=e($it['details'])?></small><?php endif; ?>
                    </td>
                    <td style="text-align: center; padding: 10px;"><?=e(rtrim(rtrim(number_format($iQty, 2), '0'), '.'))?></td>
                    <td style="text-align: right; padding: 10px;"><?=\Core\Currency::format($iPrice, $currency)?></td>
                    <td style="text-align: right; padding: 10px; font-weight: bold;"><?=\Core\Currency::format($iAmt, $currency)?></td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>

    <div style="display: flex; justify-content: space-between;">
        <div style="width: 50%;">
            <?php if (!empty($brand['signature_url'])): ?>
                <div style="margin-top: 20px;">
                    <img src="<?=e($brand['signature_url'])?>" style="max-height: 60px;"><br>
                    <small>Authorized Corporate Signature</small>
                </div>
            <?php endif; ?>
        </div>
        <div style="width: 45%; text-align: right;">
            <p>Subtotal: <strong><?=\Core\Currency::format((float)($inv['subtotal'] ?? 0), $currency)?></strong></p>
            <?php if (!empty($inv['discount_amount']) && (float)$inv['discount_amount'] > 0): ?><p>Discount: <strong>- <?=\Core\Currency::format((float)$inv['discount_amount'], $currency)?></strong></p><?php endif; ?>
            <?php if (!empty($inv['tax_amount']) && (float)$inv['tax_amount'] > 0): ?><p>VAT: <strong>+ <?=\Core\Currency::format((float)$inv['tax_amount'], $currency)?></strong></p><?php endif; ?>
            <div style="background: <?=e($primaryColor)?>; color: #fff; padding: 12px; font-size: 18px; font-weight: bold; border-radius: 4px;">
                TOTAL: <?=\Core\Currency::format((float)($inv['total'] ?? 0), $currency)?>
            </div>

        </div>
    </div>
</div>
