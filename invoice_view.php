<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT i.*, c.company_name, c.contact_name, c.email, c.phone, c.address, c.tax_number client_tax_number FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ? AND i.tenant_id = ?");
$st->execute([$id, $tid]);
$invoice = $st->fetch();

if (!$invoice) {
    flash('error', 'Invoice record not found.');
    redirect('index');
}

$stItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC");
$stItems->execute([$id]);
$items = $stItems->fetchAll();

// Fetch Payment History
$stPay = $pdo->prepare("SELECT p.*, u.name as recorder_name FROM payments p LEFT JOIN users u ON u.id = p.created_by WHERE p.invoice_id = ? ORDER BY p.id DESC");
$stPay->execute([$id]);
$payments = $stPay->fetchAll();

$totalPaid = (float)$invoice['paid_amount'];
$balanceDue = max(0, (float)$invoice['total'] - $totalPaid);

$brand = branding();
$templateId = $_GET['tpl'] ?? $invoice['template_id'] ?? $brand['default_invoice_template'] ?? 'onesol_executive_gold';
if (!empty($brand['default_invoice_template']) && $brand['default_invoice_template'] === 'custom_drag_drop' && !isset($_GET['tpl'])) {
    $templateId = 'custom_drag_drop';
}


page_start('View Invoice ' . $invoice['invoice_number']);
?>

<div class="xl:flex xl:items-center xl:justify-between mb-8 pb-6 border-b border-slate-200/80 gap-4">
    <div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Invoice <?=e($invoice['invoice_number'])?></h1>
            <?php
            $statusClasses = [
                'paid' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                'partially_paid' => 'bg-amber-100 text-amber-900 border-amber-300',
                'sent' => 'bg-blue-100 text-blue-800 border-blue-300',
                'draft' => 'bg-sky-100 text-sky-800 border-sky-300',
                'overdue' => 'bg-rose-100 text-rose-800 border-rose-300',
                'void' => 'bg-slate-200 text-slate-700 line-through border-slate-300',
                'cancelled' => 'bg-slate-100 text-slate-800 border-slate-300'
            ];
            $stClass = $statusClasses[$invoice['status']] ?? 'bg-slate-100 text-slate-800 border-slate-200';
            ?>
            <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border <?=$stClass?>"><?=str_replace('_', ' ', $invoice['status'])?></span>
        </div>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Issued to <strong><?=e($invoice['company_name'])?></strong> on <?=e(date('d M Y', strtotime($invoice['invoice_date'])))?>.</p>
    </div>

    <!-- Ultra-Clean Single Row Action Buttons Bar -->
    <div class="mt-4 xl:mt-0 flex flex-wrap items-center gap-2 no-print">
        <a href="invoice_form?id=<?=$invoice['id']?>" class="inline-flex items-center px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs rounded-xl shadow-xs transition-all space-x-1.5">
            <i class="fa-solid fa-pen-to-square"></i>
            <span>Edit</span>
        </a>

        <a href="invoice_send_email?id=<?=$invoice['id']?>" onclick="return confirm('Email this tax invoice directly to <?=e($invoice['email'])?>?')" class="inline-flex items-center px-3.5 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs rounded-xl shadow-xs transition-all space-x-1.5">
            <i class="fa-solid fa-paper-plane"></i>
            <span>Send Email</span>
        </a>

        <?php if ($invoice['status'] !== 'void' && $balanceDue > 0): ?>
            <button onclick="document.getElementById('record-payment-modal').classList.remove('hidden')" class="inline-flex items-center px-3.5 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-extrabold text-xs rounded-xl shadow-xs transition-all space-x-1.5">
                <i class="fa-solid fa-circle-plus"></i>
                <span>Record Payment</span>
            </button>
        <?php endif; ?>

        <a href="client_statement?client_id=<?=$invoice['client_id']?>" class="inline-flex items-center px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl shadow-2xs transition-all space-x-1">
            <i class="fa-solid fa-file-invoice-dollar text-amber-500"></i>
            <span>Statement</span>
        </a>

        <a href="invoice_print?id=<?=$invoice['id']?>" target="_blank" class="inline-flex items-center px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl shadow-2xs transition-all space-x-1">
            <i class="fa-solid fa-print text-slate-500"></i>
            <span>Print / PDF</span>
        </a>

        <?php if ($invoice['status'] !== 'void'): ?>
            <a href="invoice_payment?action=void&id=<?=$invoice['id']?>&csrf=<?=e(csrf_token())?>" onclick="return confirm('Void this invoice? This will mark the balance due as zero without deleting document history.')" class="inline-flex items-center px-3 py-2 bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-600 font-bold text-xs rounded-xl border border-slate-200 transition-all space-x-1" title="Void Invoice">
                <i class="fa-solid fa-ban text-2xs"></i>
                <span>Void</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8 max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-widest block mb-1">Total Invoice Value</span>
            <strong class="text-2xl font-black text-slate-900 font-mono"><?=money((float)$invoice['total'], $invoice['currency'])?></strong>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-lg">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-emerald-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs font-extrabold text-emerald-600 uppercase tracking-widest block mb-1">Total Payments Received</span>
            <strong class="text-2xl font-black text-emerald-600 font-mono"><?=money($totalPaid, $invoice['currency'])?></strong>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-5 border border-amber-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-3xs font-extrabold text-amber-600 uppercase tracking-widest block mb-1">Remaining Balance Due</span>
            <strong class="text-2xl font-black text-amber-600 font-mono"><?=money($balanceDue, $invoice['currency'])?></strong>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
    </div>
</div>

<!-- Render Document Canvas Desk Pad -->
<div class="bg-slate-100 p-6 sm:p-10 rounded-3xl border border-slate-200/90 shadow-lg max-w-4xl mx-auto mb-8">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-6 sm:p-10">
        <?php
        echo \Services\InvoiceRenderer::render($invoice, $items, $brand, $templateId);
        ?>
    </div>
</div>

<!-- Payment History Audit Trail Table -->
<?php if (!empty($payments)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm max-w-4xl mx-auto overflow-hidden mb-8">
        <div class="p-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-emerald-600"></i>
                    <span>Payment Audit Trail History</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Verified transaction ledger & gateway payment records</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded-full text-3xs font-extrabold bg-slate-100 text-slate-700 border border-slate-200 uppercase tracking-wider">
                    <?=count($payments)?> <?=count($payments) === 1 ? 'Transaction' : 'Transactions'?>
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200 font-mono">
                    Total Paid: <?=money((float)$invoice['paid_amount'], $invoice['currency'])?>
                </span>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100/70 text-3xs font-extrabold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                        <th class="px-5 py-3">Date & Time</th>
                        <th class="px-5 py-3">Gateway / Stripe ID</th>
                        <th class="px-5 py-3">Method & Source</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Amount Received</th>
                        <th class="px-5 py-3">Notes / Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($payments as $p): 
                        $rawTime = (!empty($p['created_at']) && $p['created_at'] !== '0000-00-00 00:00:00') ? $p['created_at'] : $p['payment_date'];
                        $ts = strtotime($rawTime);
                        $dateFormatted = date('d M Y', $ts);
                        $hasTime = (date('H:i:s', $ts) !== '00:00:00');
                        $timeFormatted = $hasTime ? date('h:i:s A', $ts) : date('h:i A', strtotime($p['created_at'] ?? $rawTime));

                        $txnId = trim($p['gateway_transaction_id'] ?: ($p['reference'] ?: ''));
                        $gwName = strtolower(trim($p['gateway'] ?: $p['payment_method']));
                        $isStripe = ($gwName === 'stripe' || str_contains($gwName, 'stripe') || str_starts_with($txnId, 'pi_') || str_starts_with($txnId, 'cs_') || str_starts_with($txnId, 'ch_'));
                    ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <!-- Date & Time -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="font-bold text-slate-800 text-xs"><?=e($dateFormatted)?></div>
                                <div class="text-3xs text-slate-500 flex items-center gap-1 mt-0.5">
                                    <i class="fa-regular fa-clock text-slate-400"></i>
                                    <span><?=e($timeFormatted)?></span>
                                </div>
                            </td>

                            <!-- Gateway / Stripe ID -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <?php if (!empty($txnId)): ?>
                                    <?php if ($isStripe): ?>
                                        <button type="button" onclick="navigator.clipboard.writeText('<?=e($txnId)?>'); alert('Stripe ID copied: <?=e($txnId)?>');" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/90 shadow-2xs hover:bg-indigo-100 transition-all cursor-pointer group" title="Click to copy Stripe Transaction ID">
                                            <i class="fa-brands fa-stripe text-base text-indigo-600"></i>
                                            <span><?=e($txnId)?></span>
                                            <i class="fa-regular fa-copy text-3xs text-indigo-400 group-hover:text-indigo-600"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" onclick="navigator.clipboard.writeText('<?=e($txnId)?>'); alert('Transaction ID copied: <?=e($txnId)?>');" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs hover:bg-slate-200 transition-all cursor-pointer group" title="Click to copy Transaction ID">
                                            <i class="fa-solid fa-hashtag text-3xs text-slate-400"></i>
                                            <span><?=e($txnId)?></span>
                                            <i class="fa-regular fa-copy text-3xs text-slate-400 group-hover:text-slate-600"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 font-mono italic">--</span>
                                <?php endif; ?>
                            </td>

                            <!-- Method & Source -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="font-bold text-slate-800 text-xs flex items-center gap-1.5">
                                    <?php if ($isStripe): ?>
                                        <i class="fa-brands fa-stripe text-indigo-600 text-sm"></i>
                                    <?php elseif (str_contains(strtolower($p['payment_method']), 'bank')): ?>
                                        <i class="fa-solid fa-building-columns text-slate-500 text-xs"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-credit-card text-slate-500 text-xs"></i>
                                    <?php endif; ?>
                                    <span><?=e(ucwords(str_replace('_', ' ', $p['payment_method'])))?></span>
                                </div>
                                <?php if (!empty($p['recorder_name'])): ?>
                                    <span class="text-3xs text-slate-500 font-medium flex items-center gap-1 mt-0.5">
                                        <i class="fa-solid fa-user-check text-emerald-600"></i>
                                        <span>Manual by <strong><?=e($p['recorder_name'])?></strong></span>
                                    </span>
                                <?php else: ?>
                                    <span class="text-3xs text-indigo-600 font-semibold flex items-center gap-1 mt-0.5">
                                        <i class="fa-solid fa-bolt text-indigo-500"></i>
                                        <span>Automated Gateway API</span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-3xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 uppercase tracking-wider">
                                    <i class="fa-solid fa-circle-check text-xs"></i> Verified & Paid
                                </span>
                            </td>

                            <!-- Amount -->
                            <td class="px-5 py-3.5 text-right font-mono font-black text-emerald-600 text-sm whitespace-nowrap">
                                <?=money((float)$p['amount'], $invoice['currency'])?>
                            </td>

                            <!-- Notes -->
                            <td class="px-5 py-3.5 text-xs text-slate-500 max-w-xs truncate" title="<?=e($p['notes'])?>">
                                <?=e($p['notes'] ?: '--')?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Record Payment -->
<div id="record-payment-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-lg font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-money-bill-wave text-emerald-600 mr-2"></i>Record Invoice Payment
            </h3>
            <button onclick="document.getElementById('record-payment-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">×</button>
        </div>
        <form action="invoice_payment" method="post" class="space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="invoice_id" value="<?=$invoice['id']?>">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Payment Amount * (Balance Due: <?=money($balanceDue, $invoice['currency'])?>)</label>
                <input type="number" step="0.01" name="amount" value="<?=$balanceDue?>" max="<?=$balanceDue?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold font-mono text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Payment Date & Time *</label>
                <input type="datetime-local" name="payment_date" value="<?=date('Y-m-d\TH:i')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Payment Method</label>
                <select name="payment_method" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-bold text-slate-900">
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Stripe Card">Stripe Online Card</option>
                    <option value="Network Intl NGenius">Network International NGenius</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Cash">Cash</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Stripe ID / Transaction Reference (Optional)</label>
                <input type="text" name="reference" placeholder="e.g. pi_3Nx98127391... or Bank Ref #91823" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-mono font-semibold text-slate-900">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Payment Notes</label>
                <textarea name="notes" rows="2" placeholder="e.g. Verified with bank statement" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900"></textarea>
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('record-payment-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 shadow-md">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>
