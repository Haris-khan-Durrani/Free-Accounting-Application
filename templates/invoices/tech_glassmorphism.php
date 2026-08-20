<?php
$primaryColor = $brand['primary_color'] ?? '#0f172a';
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-glass" style="font-family: 'Outfit', sans-serif; background: linear-gradient(135deg, #0f172a, #1e1b4b); color: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);">
    <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" style="max-height: 45px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));">
            <?php endif; ?>
            <h2 style="margin: 6px 0 0 0; font-size: 22px; font-weight: 700; color: #38bdf8;"><?=e($brand['company_name'])?></h2>
            <small style="opacity: 0.8;"><?=e($brand['company_tagline'])?></small>
        </div>
        <div style="text-align: right;">
            <span style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; border: 1px solid #38bdf8; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">E-INVOICE</span>
            <h1 style="margin: 8px 0 0 0; font-size: 24px; font-weight: 800;"><?=e($inv['invoice_number'])?></h1>
            <small style="opacity: 0.8;"><?=e(date('d M Y', strtotime($inv['invoice_date'])))?></small>
        </div>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
        <div style="flex: 1; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 10px;">
            <small style="color: #38bdf8; font-weight: 600;">FROM</small>
            <p style="margin: 4px 0 0 0; font-size: 13px;"><?=e($brand['company_name'])?><br><?=nl2br(e($brand['address']))?></p>
        </div>
        <div style="flex: 1; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 10px;">
            <small style="color: #38bdf8; font-weight: 600;">TO CLIENT</small>
            <p style="margin: 4px 0 0 0; font-size: 13px;"><?=e($inv['company_name'])?><br><?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?></p>
        </div>
    </div>

    <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: rgba(255, 255, 255, 0.1); color: #38bdf8;">
                    <th style="padding: 12px; text-align: left;">Item Description</th>
                    <th style="padding: 12px; text-align: center;">Qty</th>
                    <th style="padding: 12px; text-align: right;">Unit Price</th>
                    <th style="padding: 12px; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): 
                    $iQty = (float)($it['qty'] ?? $it['quantity'] ?? 1);
                    $iPrice = (float)($it['unit_price'] ?? 0);
                    $iAmt = (float)($it['amount'] ?? $it['total'] ?? ($iQty * $iPrice));
                ?>
                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                        <td style="padding: 12px;"><strong><?=e($it['description'] ?? 'Line Item')?></strong></td>
                        <td style="padding: 12px; text-align: center; opacity: 0.8;"><?=e(rtrim(rtrim(number_format($iQty, 2), '0'), '.'))?></td>
                        <td style="padding: 12px; text-align: right; opacity: 0.8;"><?=\Core\Currency::format($iPrice, $currency)?></td>
                        <td style="padding: 12px; text-align: right; font-weight: 700; color: #38bdf8;"><?=\Core\Currency::format($iAmt, $currency)?></td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>

    <div style="text-align: right; background: rgba(56, 189, 248, 0.15); border: 1px solid #38bdf8; padding: 15px; border-radius: 12px;">
        <span style="font-size: 12px; color: #38bdf8;">GRAND TOTAL DUE</span>
        <h1 style="margin: 0; font-size: 28px; font-weight: 800;"><?=\Core\Currency::format((float)$inv['total'], $currency)?></h1>
    </div>
</div>
