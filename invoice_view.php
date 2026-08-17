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

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-3">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Invoice <?=e($invoice['invoice_number'])?></h1>
            <?php
            $statusClasses = [
                'paid' => 'bg-emerald-100 text-emerald-800',
                'partially_paid' => 'bg-amber-100 text-amber-900',
                'sent' => 'bg-blue-100 text-blue-800',
                'draft' => 'bg-sky-100 text-sky-800',
                'overdue' => 'bg-rose-100 text-rose-800',
                'void' => 'bg-slate-200 text-slate-700 line-through',
                'cancelled' => 'bg-slate-100 text-slate-800'
            ];
            $stClass = $statusClasses[$invoice['status']] ?? 'bg-slate-100 text-slate-800';
            ?>
            <span class="px-3 py-1 rounded-full text-xs font-extrabold <?=$stClass?>"><?=strtoupper(e(str_replace('_', ' ', $invoice['status'])))?></span>
        </div>
        <p class="mt-1 text-sm text-slate-500">Issued to <strong><?=e($invoice['company_name'])?></strong> on <?=e(date('d M Y', strtotime($invoice['invoice_date'])))?>.</p>
    </div>

    <div class="mt-4 flex flex-wrap md:mt-0 gap-2">
        <a href="invoice_send_email?id=<?=$invoice['id']?>" onclick="return confirm('Email this tax invoice directly to <?=e($invoice['email'])?>?')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-md text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 transition-all">
            <i class="fa-solid fa-paper-plane mr-2"></i>Send Email
        </a>

        <?php if ($invoice['status'] !== 'void' && $balanceDue > 0): ?>
            <button onclick="document.getElementById('record-payment-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-md text-sm font-extrabold rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700">
                <i class="fa-solid fa-money-bill-wave mr-2"></i>+ Record Payment
            </button>
        <?php endif; ?>

        <?php if ($invoice['status'] !== 'void'): ?>
            <a href="invoice_payment?action=void&id=<?=$invoice['id']?>&csrf=<?=e(csrf_token())?>" onclick="return confirm('Void this invoice? This will mark the balance due as zero without deleting the document history.')" class="inline-flex items-center px-3.5 py-2 border border-slate-300 shadow-sm text-sm font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
                <i class="fa-solid fa-ban mr-1.5 text-slate-500"></i>Void
            </a>
        <?php endif; ?>

        <a href="client_statement?client_id=<?=$invoice['client_id']?>" class="inline-flex items-center px-3.5 py-2 border border-slate-300 shadow-sm text-sm font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-file-invoice-dollar mr-1.5 text-amber-500"></i>Statement
        </a>

        <a href="invoice_print?id=<?=$invoice['id']?>" target="_blank" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-semibold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2"></i>Print / PDF
        </a>
        <a href="invoice_form?id=<?=$invoice['id']?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-semibold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700">
            <i class="fa-solid fa-pen-to-square mr-2"></i>Edit
        </a>
    </div>
</div>

<!-- Financial Summary Bar -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Invoice Value</span>
        <strong class="text-2xl font-black text-slate-900 font-mono"><?=money((float)$invoice['total'], $invoice['currency'])?></strong>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-emerald-200 shadow-sm">
        <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider block mb-1">Total Payments Received</span>
        <strong class="text-2xl font-black text-emerald-600 font-mono"><?=money($totalPaid, $invoice['currency'])?></strong>
    </div>
    <div class="bg-white rounded-2xl p-5 border border-amber-200 shadow-sm">
        <span class="text-xs font-bold text-amber-600 uppercase tracking-wider block mb-1">Remaining Balance Due</span>
        <strong class="text-2xl font-black text-amber-600 font-mono"><?=money($balanceDue, $invoice['currency'])?></strong>
    </div>
</div>

<!-- Render Document Canvas -->
<div class="bg-slate-200/80 rounded-2xl p-6 border border-slate-300 shadow-inner max-w-4xl mx-auto mb-8">
    <div class="bg-white rounded-xl shadow-xl border border-slate-200 p-8">
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
