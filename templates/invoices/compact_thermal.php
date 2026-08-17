<?php
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-thermal" style="font-family: 'Courier New', monospace; max-width: 380px; margin: 0 auto; background: #fff; padding: 15px; border: 1px dashed #000; color: #000;">
    <div style="text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px;">
        <h2 style="margin: 0; font-size: 18px; text-transform: uppercase;"><?=e($brand['company_name'])?></h2>
        <p style="margin: 2px 0; font-size: 11px;"><?=e($brand['company_phone'])?></p>
        <p style="margin: 2px 0; font-size: 11px;"><?=e($brand['tax_number_label'])?>: <?=e($brand['tax_number'])?></p>
    </div>

    <div style="font-size: 12px; margin-bottom: 10px;">
        <p style="margin: 2px 0;">INV: <strong><?=e($inv['invoice_number'])?></strong></p>
        <p style="margin: 2px 0;">DATE: <?=e(date('Y-m-d H:i', strtotime($inv['created_at'])))?></p>
        <p style="margin: 2px 0;">CLIENT: <?=e($inv['company_name'])?></p>
    </div>

    <table style="width: 100%; font-size: 11px; border-collapse: collapse; border-bottom: 1px dashed #000; margin-bottom: 10px;">
        <thead>
            <tr style="border-bottom: 1px dashed #000; text-align: left;">
                <th>QTY</th>
                <th>ITEM</th>
                <th style="text-align: right;">AMT</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?=e(rtrim(rtrim(number_format((float)$it['qty'], 2), '0'), '.'))?></td>
                    <td><?=e($it['description'])?></td>
                    <td style="text-align: right;"><?=\Core\Currency::format((float)$it['amount'], $currency)?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="font-size: 13px; font-weight: bold; text-align: right; margin-bottom: 15px;">
        TOTAL: <?=\Core\Currency::format((float)$inv['total'], $currency)?>
    </div>

    <div style="text-align: center; font-size: 10px;">
        <p>*** THANK YOU FOR YOUR BUSINESS ***</p>
    </div>
</div>
