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
$stPay = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY id DESC");
$stPay->execute([$id]);
$payments = $stPay->fetchAll();

$totalPaid = (float)$invoice['paid_amount'];
$balanceDue = max(0, (float)$invoice['total'] - $totalPaid);

$brand = branding();
$templateId = $invoice['template_id'] ?: $brand['default_invoice_template'] ?: 'onesol_executive_gold';

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
    <div class="mt-4 xl:mt-0 flex flex-wrap items-center gap-2">
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
        <div class="p-5 border-b border-slate-100">
            <h3 class="text-base font-bold text-slate-900">Payment Audit Trail History</h3>
        </div>
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-slate-50 text-xs font-bold text-slate-400 uppercase border-b border-slate-200">
                    <th class="px-6 py-3">Payment Date</th>
                    <th class="px-6 py-3">Payment Method</th>
                    <th class="px-6 py-3 text-right">Amount Received</th>
                    <th class="px-6 py-3">Notes / Reference</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="px-6 py-3.5 text-xs font-semibold text-slate-600"><?=e(date('d M Y', strtotime($p['payment_date'])))?></td>
                        <td class="px-6 py-3.5 font-bold text-slate-800"><?=e($p['payment_method'])?></td>
                        <td class="px-6 py-3.5 text-right font-mono font-extrabold text-emerald-600"><?=money((float)$p['amount'], $invoice['currency'])?></td>
                        <td class="px-6 py-3.5 text-xs text-slate-500"><?=e($p['notes'] ?: '--')?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal: Record Payment -->
<div id="record-payment-modal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
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
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Payment Date *</label>
                <input type="date" name="payment_date" value="<?=date('Y-m-d')?>" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900">
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
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Payment Notes / Transaction Ref</label>
                <textarea name="notes" rows="2" placeholder="e.g. Bank Ref #1029384" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm font-semibold text-slate-900"></textarea>
            </div>

            <div class="pt-3 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('record-payment-modal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 shadow-md">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<?php page_end(); ?>
