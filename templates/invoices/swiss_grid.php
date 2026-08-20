<?php
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-swiss" style="font-family: 'Helvetica Neue', Arial, sans-serif; padding: 30px; background: #fff; color: #000; border: 4px solid #000;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; border-bottom: 3px solid #000; padding-bottom: 20px; margin-bottom: 20px;">
        <div>
            <h1 style="margin: 0; font-size: 32px; font-weight: 900; letter-spacing: -1px; text-transform: uppercase;"><?=e($brand['company_name'])?></h1>
            <p style="margin: 4px 0 0 0; font-size: 13px; font-weight: 500;"><?=e($brand['company_tagline'])?></p>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; font-size: 36px; font-weight: 900;">INVOICE</h2>
            <p style="margin: 0; font-size: 16px; font-weight: 700;"># <?=e($inv['invoice_number'])?></p>
            <p style="margin: 0; font-size: 12px;"><?=e(date('d.m.Y', strtotime($inv['invoice_date'])))?></p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px;">
        <div>
            <span style="font-size: 10px; font-weight: 900; text-transform: uppercase;">01 / SENDER</span>
            <p style="margin: 4px 0 0 0; font-size: 13px; line-height: 1.4;"><?=e($brand['company_name'])?><br><?=nl2br(e($brand['address']))?></p>
        </div>
        <div>
            <span style="font-size: 10px; font-weight: 900; text-transform: uppercase;">02 / RECIPIENT</span>
            <p style="margin: 4px 0 0 0; font-size: 13px; line-height: 1.4;"><?=e($inv['company_name'])?><br><?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?></p>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr style="border-bottom: 2px solid #000; font-size: 11px; text-transform: uppercase; font-weight: 900;">
                <th style="padding: 8px; text-align: left;">POS</th>
                <th style="padding: 8px; text-align: left;">DESCRIPTION</th>
                <th style="padding: 8px; text-align: center;">QTY</th>
                <th style="padding: 8px; text-align: right;">PRICE</th>
                <th style="padding: 8px; text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $idx => $it): 
                $iQty = (float)($it['qty'] ?? $it['quantity'] ?? 1);
                $iPrice = (float)($it['unit_price'] ?? 0);
                $iAmt = (float)($it['amount'] ?? $it['total'] ?? ($iQty * $iPrice));
            ?>
                <tr style="border-bottom: 1px solid #000;">
                    <td style="padding: 8px; font-weight: bold;"><?=sprintf('%02d', $idx + 1)?></td>
                    <td style="padding: 8px; font-weight: 700;"><?=e($it['description'] ?? 'Line Item')?></td>
                    <td style="padding: 8px; text-align: center;"><?=e(rtrim(rtrim(number_format($iQty, 2), '0'), '.'))?></td>
                    <td style="padding: 8px; text-align: right;"><?=\Core\Currency::format($iPrice, $currency)?></td>
                    <td style="padding: 8px; text-align: right; font-weight: 900;"><?=\Core\Currency::format($iAmt, $currency)?></td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>

    <div style="border-top: 3px solid #000; padding-top: 15px; text-align: right;">
        <span style="font-size: 12px; font-weight: 900; text-transform: uppercase;">TOTAL AMOUNT DUE</span>
        <h1 style="margin: 0; font-size: 32px; font-weight: 900;"><?=\Core\Currency::format((float)$inv['total'], $currency)?></h1>
    </div>
</div>
