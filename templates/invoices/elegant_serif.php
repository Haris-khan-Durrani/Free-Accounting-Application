<?php
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-serif" style="font-family: 'Playfair Display', serif; padding: 25px; color: #1c1917; border: 1px solid #d6d3d1;">
    <div style="text-align: center; border-bottom: 2px solid #78716c; padding-bottom: 15px; margin-bottom: 25px;">
        <h1 style="margin: 0; font-size: 32px; letter-spacing: 2px; text-transform: uppercase;"><?=e($brand['company_name'])?></h1>
        <p style="margin: 4px 0 0 0; font-style: italic; color: #57534e;"><?=e($brand['company_tagline'])?></p>
        <small style="letter-spacing: 1px;"><?=e($brand['tax_number_label'])?>: <?=e($brand['tax_number'])?></small>
    </div>

    <div style="display: flex; justify-content: space-between; font-family: 'Inter', sans-serif; margin-bottom: 25px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #78716c;">Client Name</span>
            <h3 style="margin: 2px 0;"><?=e($inv['company_name'])?></h3>
            <p style="margin: 0; font-size: 13px; color: #44403c;"><?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?></p>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-family: 'Playfair Display', serif;">INVOICE</h2>
            <p style="margin: 2px 0; font-weight: bold;"><?=e($inv['invoice_number'])?></p>
            <small style="color: #78716c;">Date: <?=e(date('d M Y', strtotime($inv['invoice_date'])))?></small>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-family: 'Inter', sans-serif;">
        <thead>
            <tr style="border-bottom: 2px solid #292524; text-transform: uppercase; font-size: 12px; letter-spacing: 1px;">
                <th style="padding: 10px; text-align: left;">Description</th>
                <th style="padding: 10px; text-align: center;">Qty</th>
                <th style="padding: 10px; text-align: right;">Price</th>
                <th style="padding: 10px; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): 
                $iQty = (float)($it['qty'] ?? $it['quantity'] ?? 1);
                $iPrice = (float)($it['unit_price'] ?? 0);
                $iAmt = (float)($it['amount'] ?? $it['total'] ?? ($iQty * $iPrice));
            ?>
                <tr style="border-bottom: 1px solid #e7e5e4;">
                    <td style="padding: 10px;"><strong><?=e($it['description'] ?? 'Line Item')?></strong></td>
                    <td style="padding: 10px; text-align: center;"><?=e(rtrim(rtrim(number_format($iQty, 2), '0'), '.'))?></td>
                    <td style="padding: 10px; text-align: right;"><?=\Core\Currency::format($iPrice, $currency)?></td>
                    <td style="padding: 10px; text-align: right; font-weight: bold;"><?=\Core\Currency::format($iAmt, $currency)?></td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>

    <div style="text-align: right; font-family: 'Playfair Display', serif;">
        <h2 style="margin: 0; font-size: 24px;">Total Payable: <?=\Core\Currency::format((float)$inv['total'], $currency)?></h2>
    </div>
</div>
