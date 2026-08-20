<?php
$primaryColor = $brand['primary_color'] ?? '#0f172a';
$currency = $inv['currency'] ?? 'AED';
?>
<div class="inv-template template-twocolumn" style="font-family: 'Inter', sans-serif; display: flex; min-height: 500px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
    <!-- Left Column: Provider Info & Meta -->
    <div style="width: 32%; background: <?=e($primaryColor)?>; color: #fff; padding: 25px; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" style="max-height: 45px; background: #fff; padding: 4px; border-radius: 6px; margin-bottom: 15px;">
            <?php endif; ?>
            <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #fff;"><?=e($brand['company_name'])?></h2>
            <p style="margin: 4px 0 15px 0; font-size: 12px; opacity: 0.8;"><?=e($brand['company_tagline'])?></p>

            <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px; margin-top: 15px; font-size: 12px;">
                <p><strong>Tax ID:</strong> <?=e($brand['tax_number'])?></p>
                <p><strong>Email:</strong> <?=e($brand['company_email'])?></p>
                <p><strong>Phone:</strong> <?=e($brand['company_phone'])?></p>
                <p><strong>Address:</strong><br><?=nl2br(e($brand['address']))?></p>
            </div>
        </div>

        <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px; font-size: 11px; opacity: 0.8;">
            <p><?=e($brand['invoice_footer_notes'])?></p>
        </div>
    </div>

    <!-- Right Column: Details & Items Table -->
    <div style="width: 68%; background: #fff; padding: 25px; color: #0f172a; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px;">
                <div>
                    <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #64748b;">Invoice To</span>
                    <h3 style="margin: 2px 0; font-size: 18px;"><?=e($inv['company_name'])?></h3>
                    <p style="margin: 0; font-size: 13px; color: #475569;"><?=nl2br(e($inv['address'] ?: trim(($inv['contact_name'] ?? '').' '.($inv['email'] ?? ''))))?></p>
                </div>
                <div style="text-align: right;">
                    <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: <?=e($primaryColor)?>;"><?=e($inv['invoice_number'])?></h1>
                    <small style="color: #64748b;">Date: <?=e(date('d M Y', strtotime($inv['invoice_date'])))?></small>
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569; font-size: 12px;">
                        <th style="padding: 10px; text-align: left;">Item Description</th>
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
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px;"><strong><?=e($it['description'] ?? 'Line Item')?></strong></td>
                            <td style="padding: 10px; text-align: center; color: #64748b;"><?=e(rtrim(rtrim(number_format($iQty, 2), '0'), '.'))?></td>
                            <td style="padding: 10px; text-align: right; color: #64748b;"><?=\Core\Currency::format($iPrice, $currency)?></td>
                            <td style="padding: 10px; text-align: right; font-weight: 700;"><?=\Core\Currency::format($iAmt, $currency)?></td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <div style="text-align: right; background: #f8fafc; padding: 15px; border-radius: 8px;">
            <span style="font-size: 12px; color: #64748b; font-weight: 600;">TOTAL PAYABLE</span>
            <h2 style="margin: 2px 0 0 0; font-size: 24px; font-weight: 800; color: <?=e($primaryColor)?>;"><?=\Core\Currency::format((float)$inv['total'], $currency)?></h2>
        </div>
    </div>
</div>
