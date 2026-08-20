<?php
$primaryColor = $brand['primary_color'] ?? '#0284c7';
$secondaryColor = $brand['secondary_color'] ?? '#6366f1';
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-vibrant" style="font-family: 'Poppins', sans-serif;">
    <div style="background: linear-gradient(135deg, <?=e($primaryColor)?>, <?=e($secondaryColor)?>); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" style="max-height: 55px; background: rgba(255,255,255,0.9); padding: 6px; border-radius: 8px; margin-bottom: 10px;">
            <?php endif; ?>
            <h1 style="margin: 0; font-size: 26px; font-weight: 700;"><?=e($brand['company_name'])?></h1>
            <p style="margin: 2px 0 0 0; opacity: 0.9;"><?=e($brand['company_tagline'])?></p>
        </div>
        <div style="text-align: right;">
            <span style="background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 13px; text-transform: uppercase;">Invoice</span>
            <h2 style="margin: 10px 0 0 0; font-size: 24px; font-weight: 700;"><?=e($inv['invoice_number'])?></h2>
            <small style="opacity: 0.9;">Date: <?=e(date('d M Y', strtotime($inv['invoice_date'])))?></small>
        </div>
    </div>

    <div style="display: flex; gap: 20px; margin-bottom: 25px;">
        <div style="flex: 1; background: #f8fafc; padding: 16px; border-radius: 10px; border-left: 4px solid <?=e($primaryColor)?>;">
            <small style="color: #64748b; font-weight: 600;">FROM PROVIDER</small>
            <h3 style="margin: 4px 0; color: #0f172a;"><?=e($brand['company_name'])?></h3>
            <p style="margin: 0; color: #475569; font-size: 13px;"><?=nl2br(e($brand['address']))?></p>
        </div>
        <div style="flex: 1; background: #f8fafc; padding: 16px; border-radius: 10px; border-left: 4px solid <?=e($secondaryColor)?>;">
            <small style="color: #64748b; font-weight: 600;">CLIENT RECIPIENT</small>
            <h3 style="margin: 4px 0; color: #0f172a;"><?=e($inv['company_name'])?></h3>
            <p style="margin: 0; color: #475569; font-size: 13px;"><?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?></p>
        </div>
    </div>

    <table class="inv-items-table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr style="background: #f1f5f9; color: #334155;">
                <th style="padding: 12px; text-align: left; border-top-left-radius: 8px;">Description</th>
                <th style="padding: 12px; text-align: center;">Qty</th>
                <th style="padding: 12px; text-align: right;">Rate</th>
                <th style="padding: 12px; text-align: right; border-top-right-radius: 8px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): 
                $iQty = (float)($it['qty'] ?? $it['quantity'] ?? 1);
                $iPrice = (float)($it['unit_price'] ?? 0);
                $iAmt = (float)($it['amount'] ?? $it['total'] ?? ($iQty * $iPrice));
            ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px;">
                        <strong><?=e($it['description'] ?? 'Line Item')?></strong>
                        <?php if (!empty($it['details'])): ?><br><small style="color:#64748b;"><?=e($it['details'])?></small><?php endif; ?>
                    </td>
                    <td style="padding: 12px; text-align: center;"><?=e(rtrim(rtrim(number_format($iQty, 2), '0'), '.'))?></td>
                    <td style="padding: 12px; text-align: right;"><?=\Core\Currency::format($iPrice, $currency)?></td>
                    <td style="padding: 12px; text-align: right; font-weight: 700; color: <?=e($primaryColor)?>;"><?=\Core\Currency::format($iAmt, $currency)?></td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>

    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div style="max-width: 50%;">
            <p style="font-size: 12px; color: #64748b; margin: 0;"><?=e($brand['invoice_footer_notes'])?></p>
        </div>
        <div style="background: linear-gradient(135deg, <?=e($primaryColor)?>, <?=e($secondaryColor)?>); color: #fff; padding: 16px 24px; border-radius: 12px; text-align: right;">
            <span style="font-size: 12px; opacity: 0.9;">FINAL AMOUNT DUE</span>
            <h2 style="margin: 0; font-size: 26px; font-weight: 800;"><?=\Core\Currency::format((float)$inv['total'], $currency)?></h2>
        </div>
    </div>
</div>
