<?php
$primaryColor = '#0f172a';
$accentColor = '#38bdf8';
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-dark" style="font-family: 'Inter', sans-serif; background: #0f172a; color: #f8fafc; padding: 30px; border-radius: 12px;">
    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #334155; padding-bottom: 20px; margin-bottom: 20px;">
        <div>
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" style="max-height: 50px; filter: brightness(1.2);">
            <?php endif; ?>
            <h2 style="color: <?=e($accentColor)?>; margin: 10px 0 0 0;"><?=e($brand['company_name'])?></h2>
            <small style="color: #94a3b8;"><?=e($brand['tax_number_label'])?>: <?=e($brand['tax_number'])?></small>
        </div>
        <div style="text-align: right;">
            <h1 style="margin: 0; color: #f8fafc; font-size: 28px;">INVOICE</h1>
            <p style="color: <?=e($accentColor)?>; font-weight: bold; margin: 4px 0;"><?=e($inv['invoice_number'])?></p>
            <small style="color: #94a3b8;">Issued: <?=e(date('d M Y', strtotime($inv['invoice_date'])))?></small>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 25px;">
        <div style="background: #1e293b; padding: 14px; border-radius: 8px; width: 48%;">
            <small style="color: <?=e($accentColor)?>; font-weight: bold;">BILLED FROM</small>
            <p style="margin: 4px 0 0 0; color: #e2e8f0; font-size: 13px;"><?=e($brand['company_name'])?><br><?=nl2br(e($brand['address']))?></p>
        </div>
        <div style="background: #1e293b; padding: 14px; border-radius: 8px; width: 48%;">
            <small style="color: <?=e($accentColor)?>; font-weight: bold;">BILLED TO</small>
            <p style="margin: 4px 0 0 0; color: #e2e8f0; font-size: 13px;"><?=e($inv['company_name'])?><br><?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?></p>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr style="background: #1e293b; color: <?=e($accentColor)?>;">
                <th style="padding: 10px; text-align: left;">Item</th>
                <th style="padding: 10px; text-align: center;">Qty</th>
                <th style="padding: 10px; text-align: right;">Rate</th>
                <th style="padding: 10px; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr style="border-bottom: 1px solid #334155;">
                    <td style="padding: 10px; color: #e2e8f0;"><strong><?=e($it['description'])?></strong></td>
                    <td style="padding: 10px; text-align: center; color: #94a3b8;"><?=e(rtrim(rtrim(number_format((float)$it['qty'], 2), '0'), '.'))?></td>
                    <td style="padding: 10px; text-align: right; color: #94a3b8;"><?=\Core\Currency::format((float)$it['unit_price'], $currency)?></td>
                    <td style="padding: 10px; text-align: right; font-weight: bold; color: #f8fafc;"><?=\Core\Currency::format((float)$it['amount'], $currency)?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align: right; background: #1e293b; padding: 15px; border-radius: 8px;">
        <span style="color: #94a3b8; font-size: 13px;">TOTAL AMOUNT DUE</span>
        <h2 style="margin: 0; color: <?=e($accentColor)?>; font-size: 26px;"><?=\Core\Currency::format((float)$inv['total'], $currency)?></h2>
    </div>
</div>
