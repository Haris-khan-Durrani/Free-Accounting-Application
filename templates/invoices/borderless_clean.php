<?php
$primaryColor = $brand['primary_color'] ?? '#0f172a';
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-borderless" style="font-family: 'Inter', sans-serif; background: #fff; color: #1e293b; padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 35px;">
        <div>
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" style="max-height: 50px; margin-bottom: 8px;">
            <?php endif; ?>
            <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: <?=e($primaryColor)?>;"><?=e($brand['company_name'])?></h2>
            <small style="color: #64748b;"><?=e($brand['tax_number_label'])?>: <?=e($brand['tax_number'])?></small>
        </div>
        <div style="text-align: right;">
            <span style="color: <?=e($primaryColor)?>; font-weight: 800; font-size: 13px; letter-spacing: 2px;">INVOICE</span>
            <h1 style="margin: 4px 0 0 0; font-size: 24px; font-weight: 800;"><?=e($inv['invoice_number'])?></h1>
            <small style="color: #64748b;"><?=e(date('d M Y', strtotime($inv['invoice_date'])))?></small>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
        <div style="width: 45%;">
            <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8;">Billed To</span>
            <h3 style="margin: 4px 0 2px 0; font-size: 16px;"><?=e($inv['company_name'])?></h3>
            <p style="margin: 0; color: #475569; font-size: 13px; line-height: 1.5;"><?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?></p>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
            <tr style="color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9;">
                <th style="padding: 12px 0; text-align: left;">Item</th>
                <th style="padding: 12px 0; text-align: center;">Qty</th>
                <th style="padding: 12px 0; text-align: right;">Rate</th>
                <th style="padding: 12px 0; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr style="border-bottom: 1px solid #f8fafc;">
                    <td style="padding: 14px 0; font-weight: 600;"><?=e($it['description'])?></td>
                    <td style="padding: 14px 0; text-align: center; color: #64748b;"><?=e(rtrim(rtrim(number_format((float)$it['qty'], 2), '0'), '.'))?></td>
                    <td style="padding: 14px 0; text-align: right; color: #64748b;"><?=\Core\Currency::format((float)$it['unit_price'], $currency)?></td>
                    <td style="padding: 14px 0; text-align: right; font-weight: 700;"><?=\Core\Currency::format((float)$it['amount'], $currency)?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="display: flex; justify-content: flex-end;">
        <div style="width: 250px; text-align: right;">
            <span style="font-size: 12px; color: #94a3b8; font-weight: 600;">TOTAL PAYABLE</span>
            <h1 style="margin: 4px 0 0 0; font-size: 26px; font-weight: 800; color: <?=e($primaryColor)?>;"><?=\Core\Currency::format((float)$inv['total'], $currency)?></h1>
        </div>
    </div>
</div>
