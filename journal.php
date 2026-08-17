<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$st = $pdo->prepare("SELECT j.*, ji.debit, ji.credit, ji.memo, coa.account_code, coa.account_name FROM journal_entries j JOIN journal_items ji ON ji.journal_id = j.id LEFT JOIN chart_of_accounts coa ON coa.id = ji.account_id WHERE j.tenant_id = ? ORDER BY j.entry_date DESC, j.id DESC LIMIT 100");
$st->execute([$tid]);
$entries = $st->fetchAll();

page_start('General Ledger & Double-Entry Audit');
?>

<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">General Ledger & Double-Entry Audit</h1>
        <p class="mt-1 text-sm text-slate-500">Automated debit and credit transaction audit trail for <strong><?=e(tenant()['name'])?></strong>.</p>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900">Double-Entry Journal Postings (Latest 100)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Entry Date</th>
                    <th class="px-6 py-3.5">Entry #</th>
                    <th class="px-6 py-3.5">Ledger Account</th>
                    <th class="px-6 py-3.5">Memo / Description</th>
                    <th class="px-6 py-3.5 text-right">Debit</th>
                    <th class="px-6 py-3.5 text-right">Credit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($entries)): ?>
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No journal postings recorded yet. Double-entry postings are generated automatically when invoices are created or payments are received.</td></tr>
                <?php endif; ?>
                <?php foreach ($entries as $e): ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500"><?=e(date('d M Y', strtotime($e['entry_date'])))?></td>
                        <td class="px-6 py-4 font-mono font-bold text-blue-600"><?=e($e['entry_number'])?></td>
                        <td class="px-6 py-4 font-bold text-slate-900">
                            <span class="font-mono text-slate-500 mr-1.5"><?=e($e['account_code'])?></span>
                            <?=e($e['account_name'] ?: 'General Account')?>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-600"><?=e($e['memo'] ?: $e['description'])?></td>
                        <td class="px-6 py-4 text-right font-extrabold text-emerald-600"><?=(float)$e['debit'] > 0 ? money((float)$e['debit']) : '-'?></td>
                        <td class="px-6 py-4 text-right font-extrabold text-blue-600"><?=(float)$e['credit'] > 0 ? money((float)$e['credit']) : '-'?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php page_end(); ?>
