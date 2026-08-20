<?php
/**
 * Dynamic Drag & Drop Custom Invoice Template (Tenant-Scoped)
 * Renders blocks in the exact order saved by the tenant in invoice_builder.php
 */

$layoutBlocks = [];
if (!empty($customLayoutJson)) {
    $parsed = json_decode($customLayoutJson, true);
    if (is_array($parsed) && count($parsed) > 0) {
        $layoutBlocks = $parsed;
    }
}

if (empty($layoutBlocks)) {
    $layoutBlocks = [
        ['id' => 'b1', 'type' => 'header'],
        ['id' => 'b2', 'type' => 'metadata'],
        ['id' => 'b3', 'type' => 'client'],
        ['id' => 'b4', 'type' => 'table'],
        ['id' => 'b5', 'type' => 'summary'],
        ['id' => 'b6', 'type' => 'bank'],
        ['id' => 'b7', 'type' => 'notes'],
        ['id' => 'b8', 'type' => 'signature']
    ];
}

$w = array_merge([
    'title'        => 'TAX INVOICE',
    'invoice_no'   => 'Invoice Number',
    'invoice_date' => 'Invoice Date',
    'due_date'     => 'Payment Due Date',
    'billed_to'    => 'Billed To (Client Details)',
    'tax_label'    => 'TRN / Tax ID',
    'subtotal'     => 'Subtotal',
    'discount'     => 'Discount',
    'tax_amount'   => 'VAT (5%)',
    'total'        => 'Total Amount Due',
    'paid_amount'  => 'Amount Paid',
    'balance_due'  => 'Balance Due',
    'terms_label'  => 'Terms & Conditions',
    'bank_label'   => 'Remittance Bank Details',
    'sign_label'   => 'Authorized Signatory',
], $wording ?? []);

$currency = $inv['currency'] ?? 'AED';
$subtotal = (float)($inv['subtotal'] ?? 0);
$taxAmount = (float)($inv['tax_amount'] ?? 0);
$discountAmount = (float)($inv['discount_amount'] ?? 0);
$total = (float)($inv['total'] ?? 0);
$paidAmount = (float)($inv['paid_amount'] ?? 0);
$balanceDue = max(0, $total - $paidAmount);

$accentColor = $w['accent_color'] ?? '#d97706';
$headerColor = $w['header_color'] ?? '#0f172a';
?>
<div class="custom-builder-invoice font-sans color-slate-900 text-xs leading-relaxed max-w-[850px] mx-auto p-8 bg-white border border-slate-200 rounded-2xl shadow-xl space-y-6">

<?php foreach ($layoutBlocks as $block): ?>
    <?php $bType = $block['type'] ?? ''; ?>

    <?php if ($bType === 'header'): ?>
        <!-- Block: Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-5">
            <div>
                <?php if (!empty($brand['logo_url'])): ?>
                    <img src="<?=e($brand['logo_url'])?>" alt="Company Logo" class="h-12 w-auto mb-3 max-w-[200px] object-contain">
                <?php else: ?>
                    <div class="h-12 w-12 text-white rounded-xl flex items-center justify-center font-black text-xl mb-3 shadow-sm" style="background-color: <?=e($accentColor)?>;">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                <?php endif; ?>
                <h2 class="text-xl font-extrabold tracking-tight" style="color: <?=e($headerColor)?>;"><?=e($brand['company_name'])?></h2>
                <?php if (!empty($brand['company_tagline'])): ?>
                    <p class="text-xs text-slate-500 font-medium"><?=e($brand['company_tagline'])?></p>
                <?php endif; ?>
                <p class="text-xs text-slate-600 mt-1"><?=nl2br(e($brand['address']))?></p>
            </div>
            <div class="text-right">
                <h1 class="text-2xl font-black tracking-tight uppercase" style="color: <?=e($accentColor)?>;"><?=e($w['title'])?></h1>
                <?php if (!empty($brand['tax_number'])): ?>
                    <p class="text-xs font-mono text-slate-500 mt-1.5 bg-slate-50 px-3 py-1 rounded-md border border-slate-200 inline-block">
                        <strong><?=e($w['tax_label'])?>:</strong> <?=e($brand['tax_number'])?>
                    </p>
                <?php endif; ?>
            </div>
        </div>


    <?php elseif ($bType === 'metadata'): ?>
        <!-- Block: Metadata -->
        <div class="grid grid-cols-3 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
            <div>
                <span class="text-slate-400 font-extrabold uppercase text-[10px] tracking-wider block mb-0.5"><?=e($w['invoice_no'])?></span>
                <strong class="text-slate-900 font-mono text-sm"><?=e($inv['invoice_number'])?></strong>
            </div>
            <div>
                <span class="text-slate-400 font-extrabold uppercase text-[10px] tracking-wider block mb-0.5"><?=e($w['invoice_date'])?></span>
                <strong class="text-slate-900 text-sm"><?=date('d M Y', strtotime($inv['invoice_date']))?></strong>
            </div>
            <div>
                <span class="text-slate-400 font-extrabold uppercase text-[10px] tracking-wider block mb-0.5"><?=e($w['due_date'])?></span>
                <strong class="text-slate-900 text-sm"><?=!empty($inv['valid_until']) ? date('d M Y', strtotime($inv['valid_until'])) : 'On Receipt'?></strong>
            </div>
        </div>

    <?php elseif ($bType === 'client'): ?>
        <!-- Block: Client Info -->
        <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block mb-1"><?=e($w['billed_to'])?></span>
            <h3 class="text-base font-bold text-slate-900"><?=e($inv['company_name'])?></h3>
            <?php if (!empty($inv['contact_name']) || !empty($inv['email'])): ?>
                <p class="text-xs text-slate-600">Attn: <?=e($inv['contact_name'])?> &bull; <?=e($inv['email'])?></p>
            <?php endif; ?>
            <?php if (!empty($inv['tax_number'])): ?>
                <p class="text-xs text-slate-500 mt-0.5">TRN / Tax ID: <?=e($inv['tax_number'])?></p>
            <?php endif; ?>
            <?php if (!empty($inv['address'])): ?>
                <p class="text-xs text-slate-500 mt-0.5"><?=nl2br(e($inv['address']))?></p>
            <?php endif; ?>
        </div>

    <?php elseif ($bType === 'table'): ?>
        <!-- Block: Line Items Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase text-[10px] border-b border-slate-200 tracking-wider">
                        <th class="py-3 px-3">Item Description</th>
                        <th class="py-3 px-3 text-center">Qty</th>
                        <th class="py-3 px-3 text-right">Unit Price</th>
                        <th class="py-3 px-3 text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $it): 
                            $iQty = (float)($it['qty'] ?? $it['quantity'] ?? 1);
                            $iPrice = (float)($it['unit_price'] ?? 0);
                            $iAmt = (float)($it['amount'] ?? $it['total'] ?? ($iQty * $iPrice));
                        ?>
                            <tr>
                                <td class="py-3.5 px-3">
                                    <div class="font-bold text-slate-900"><?=e($it['description'] ?? 'Line Item')?></div>
                                    <?php if (!empty($it['details'])): ?>
                                        <div class="text-2xs text-slate-500 font-normal mt-0.5"><?=nl2br(e($it['details']))?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-3 text-center font-bold"><?=$iQty?></td>
                                <td class="py-3.5 px-3 text-right font-mono text-slate-700"><?=e($currency)?> <?=number_format($iPrice, 2)?></td>
                                <td class="py-3.5 px-3 text-right font-mono font-bold text-slate-900"><?=e($currency)?> <?=number_format($iAmt, 2)?></td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr><td colspan="4" class="py-4 text-center text-slate-400">No line items recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($bType === 'summary'): ?>
        <!-- Block: Financial Totals -->
        <div class="flex justify-end">
            <div class="w-80 bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600">
                    <span><?=e($w['subtotal'])?>:</span>
                    <span class="font-mono font-bold"><?=e($currency)?> <?=number_format($subtotal, 2)?></span>
                </div>
                <?php if ($discountAmount > 0): ?>
                    <div class="flex justify-between text-rose-600">
                        <span><?=e($w['discount'])?>:</span>
                        <span class="font-mono font-bold">- <?=e($currency)?> <?=number_format($discountAmount, 2)?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-slate-600">
                    <span><?=e($w['tax_amount'])?>:</span>
                    <span class="font-mono font-bold"><?=e($currency)?> <?=number_format($taxAmount, 2)?></span>
                </div>
                <div class="border-t border-slate-200 pt-2 flex justify-between text-slate-900 font-black text-sm">
                    <span><?=e($w['total'])?>:</span>
                    <span class="text-amber-600"><?=e($currency)?> <?=number_format($total, 2)?></span>
                </div>
                <?php if ($paidAmount > 0): ?>
                    <div class="flex justify-between text-emerald-600 pt-1">
                        <span><?=e($w['paid_amount'])?>:</span>
                        <span class="font-mono font-bold"><?=e($currency)?> <?=number_format($paidAmount, 2)?></span>
                    </div>
                    <div class="flex justify-between text-amber-700 font-bold border-t border-dashed border-slate-300 pt-1">
                        <span><?=e($w['balance_due'])?>:</span>
                        <span class="font-mono"><?=e($currency)?> <?=number_format($balanceDue, 2)?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($bType === 'bank'): ?>
        <!-- Block: Bank Details -->
        <?php if (!empty($brand['bank_name']) || !empty($brand['bank_account_number'])): ?>
            <div class="p-4 bg-blue-50/70 rounded-xl border border-blue-100 text-xs">
                <h4 class="font-bold text-blue-900 mb-1 flex items-center">
                    <i class="fa-solid fa-landmark mr-1.5 text-blue-600"></i><?=e($w['bank_label'])?>
                </h4>
                <p class="text-blue-800">
                    <strong>Bank:</strong> <?=e($brand['bank_name'])?> &bull; 
                    <strong>Account #:</strong> <?=e($brand['bank_account_number'])?> 
                    <?php if (!empty($brand['bank_iban'])): ?> &bull; <strong>IBAN:</strong> <?=e($brand['bank_iban'])?><?php endif; ?>
                    <?php if (!empty($brand['bank_swift'])): ?> &bull; <strong>SWIFT/BIC:</strong> <?=e($brand['bank_swift'])?><?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

    <?php elseif ($bType === 'notes'): ?>
        <!-- Block: Terms & Notes -->
        <?php if (!empty($inv['notes']) || !empty($brand['terms_conditions'])): ?>
            <div class="text-xs text-slate-600 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <strong class="text-slate-800 block mb-1"><?=e($w['terms_label'])?>:</strong>
                <?=nl2br(e($inv['notes'] ?: $brand['terms_conditions']))?>
            </div>
        <?php endif; ?>

    <?php elseif ($bType === 'signature'): ?>
        <!-- Block: Corporate Signature & Stamp -->
        <div class="flex justify-between items-end pt-4 border-t border-slate-100 text-xs">
            <div>
                <p class="font-bold text-slate-800"><?=e($w['sign_label'])?></p>
                <p class="text-slate-400 text-2xs"><?=e($brand['company_name'])?></p>
            </div>
            <div>
                <?php if (!empty($brand['signature_url'])): ?>
                    <img src="<?=e($brand['signature_url'])?>" alt="Authorized Stamp Signature" class="h-16 w-auto object-contain">
                <?php else: ?>
                    <div class="h-12 w-32 bg-slate-50 rounded-lg border border-dashed border-slate-300 flex items-center justify-center text-slate-400 font-serif italic text-2xs">
                        [ Stamp & Signature ]
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($bType === 'qrcode'): ?>
        <!-- Block: Verification QR Code -->
        <div class="flex items-center space-x-3 p-3 bg-slate-50 rounded-xl border border-slate-200 w-fit text-xs">
            <div class="h-10 w-10 bg-slate-950 text-amber-400 flex items-center justify-center font-bold rounded-lg shadow-sm">
                <i class="fa-solid fa-qrcode text-lg"></i>
            </div>
            <div>
                <span class="font-bold text-slate-900 block text-xs">FTA Tax Compliant Invoice</span>
                <span class="text-slate-500 text-[10px]">Verified e-Invoice &bull; <?=e($brand['company_name'])?></span>
            </div>
        </div>

    <?php endif; ?>
<?php endforeach; ?>

</div>
