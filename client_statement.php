<?php
require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];

// Multi-Auth Context: Staff Admin vs Client Portal
$isClientPortal = false;

if (!empty($_SESSION['client_id'])) {
    $isClientPortal = true;
    $clientId = (int)$_SESSION['client_id'];
    $tid      = (int)$_SESSION['client_tenant_id'];
} elseif (!empty($_SESSION['user_id'])) {
    $isClientPortal = false;
    require_login();
    require __DIR__ . '/layout.php';
    $tid      = tenant_id();
    $clientId = (int)($_GET['client_id'] ?? 0);
} else {
    redirect('client_login.php');
}

$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

// Fetch clients list for selector (only needed for staff admins)
$allClients = [];
if (!$isClientPortal) {
    $stAllClients = $pdo->prepare('SELECT id, company_name FROM clients WHERE tenant_id = ? ORDER BY company_name ASC');
    $stAllClients->execute([$tid]);
    $allClients = $stAllClients->fetchAll();
}

$client = null;
$statementRows = [];
$totalInvoiced = 0;
$totalPaid = 0;
$closingBalance = 0;

if ($clientId > 0) {
    $stClient = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND tenant_id = ?');
    $stClient->execute([$clientId, $tid]);
    $client = $stClient->fetch();

    if ($client) {
        // Fetch all invoices for this client within date range
        $stInv = $pdo->prepare('
            SELECT 
                "invoice" as type,
                id,
                invoice_number as reference,
                invoice_date as trans_date,
                valid_until as due_date,
                status,
                currency,
                total as amount,
                notes
            FROM invoices
            WHERE tenant_id = ? AND client_id = ? AND status != "cancelled" AND invoice_date BETWEEN ? AND ?
            ORDER BY invoice_date ASC, id ASC
        ');
        $stInv->execute([$tid, $clientId, $startDate, $endDate]);
        $invoices = $stInv->fetchAll();

        // Fetch payments for this client's invoices
        $stPay = $pdo->prepare('
            SELECT 
                "payment" as type,
                p.id,
                CONCAT("PAY-", p.id, " (Inv #", i.invoice_number, ")") as reference,
                p.payment_date as trans_date,
                "" as due_date,
                "completed" as status,
                i.currency,
                p.amount as amount,
                p.notes
            FROM payments p
            JOIN invoices i ON i.id = p.invoice_id
            WHERE i.tenant_id = ? AND i.client_id = ? AND p.payment_date BETWEEN ? AND ?
            ORDER BY p.payment_date ASC, p.id ASC
        ');
        $stPay->execute([$tid, $clientId, $startDate, $endDate]);
        $payments = $stPay->fetchAll();

        // Merge and sort transactions by date
        $statementRows = array_merge($invoices, $payments);
        usort($statementRows, fn($a, $b) => strcmp($a['trans_date'], $b['trans_date']));

        // Calculate totals & running balance
        $runningBal = 0;
        foreach ($statementRows as &$r) {
            if ($r['type'] === 'invoice') {
                $totalInvoiced += (float)$r['amount'];
                $runningBal += (float)$r['amount'];
            } else {
                $totalPaid += (float)$r['amount'];
                $runningBal -= (float)$r['amount'];
            }
            $r['running_balance'] = $runningBal;
        }
        unset($r);
        $closingBalance = $runningBal;
    }
}

if (!$isClientPortal) {
    page_start('Statement of Account');
} else {
    $brand = \Core\Branding::get($pdo, $tid);
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement of Account - <?=e($client['company_name'] ?? '')?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-full bg-slate-100 text-slate-900 p-4 sm:p-8">
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between print:hidden">
        <a href="client_portal.php" class="px-4 py-2 bg-slate-800 text-white hover:bg-slate-900 rounded-xl text-xs font-extrabold transition-all inline-flex items-center space-x-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Portal</span>
        </a>
        <button onclick="window.print()" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-extrabold shadow-sm transition-all inline-flex items-center space-x-2">
            <i class="fa-solid fa-print"></i>
            <span>Print / Save PDF</span>
        </button>
    </div>
<?php } ?>

<div class="sm:flex sm:items-center sm:justify-between mb-8 print:hidden">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Statement of Account</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Financial ledger summary of invoices and payments.</p>
    </div>
    <?php if ($client): ?>
        <div class="mt-4 sm:mt-0 flex space-x-3">
            <a href="report_pdf.php?type=client_statement&<?=http_build_query($_GET)?>" target="_blank" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 shadow-sm transition-all flex items-center gap-2">
                <i class="fa-solid fa-file-pdf text-amber-400"></i> Download Official Statement (PDF)
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$isClientPortal): ?>
<!-- Filter Box (Admin Only) -->
<div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm mb-8 print:hidden">
    <form method="get" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Select Client Account</label>
            <select name="client_id" required class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-bold text-slate-900">
                <option value="">-- Select Client --</option>
                <?php foreach ($allClients as $c): ?>
                    <option value="<?=$c['id']?>" <?=$clientId === (int)$c['id'] ? 'selected' : ''?>><?=e($c['company_name'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">From Date</label>
            <input type="date" name="start_date" value="<?=e($startDate)?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">To Date</label>
            <input type="date" name="end_date" value="<?=e($endDate)?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-900">
        </div>
        <div>
            <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs shadow-sm transition-all flex items-center justify-center space-x-2">
                <i class="fa-solid fa-filter"></i>
                <span>Generate Statement</span>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (!$client): ?>
    <div class="bg-white rounded-2xl p-12 text-center border border-slate-200 shadow-sm">
        <div class="w-16 h-16 rounded-full bg-amber-50 text-amber-500 mx-auto flex items-center justify-center text-2xl mb-4">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
        <h3 class="text-base font-bold text-slate-900">No Client Selected</h3>
        <p class="text-xs text-slate-500 mt-1">Please select a client account from the dropdown above to view their financial statement of account.</p>
    </div>
<?php else: ?>
    <!-- Statement Sheet Printable Box -->
    <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm space-y-8">
        <!-- Sheet Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start border-b border-slate-100 pb-6 gap-6">
            <div>
                <h2 class="text-2xl font-black text-slate-900 uppercase tracking-tight"><?=e($brand['company_name'] ?? tenant()['name'])?></h2>
                <p class="text-xs text-slate-500 mt-1"><?=e($brand['address'] ?? tenant()['company_address'] ?? 'Official Financial Statement')?></p>
            </div>
            <div class="sm:text-right">
                <span class="inline-block px-3 py-1 bg-amber-50 text-amber-700 text-xs font-bold rounded-lg border border-amber-200/80 mb-2">Statement of Account</span>
                <p class="text-xs text-slate-600 font-semibold">Period: <strong><?=date('d M Y', strtotime($startDate))?></strong> to <strong><?=date('d M Y', strtotime($endDate))?></strong></p>
                <p class="text-2xs text-slate-400">Generated: <?=date('d M Y H:i')?></p>
            </div>
        </div>

        <!-- Client Info & Summary KPI Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-slate-50 p-5 rounded-xl border border-slate-200/80">
                <span class="text-3xs uppercase font-extrabold text-slate-400 tracking-wider block mb-1">Statement Prepared For:</span>
                <h3 class="text-base font-extrabold text-slate-900"><?=e($client['company_name'])?></h3>
                <p class="text-xs text-slate-600 mt-1 font-medium">Attn: <?=e($client['contact_name'] ?: 'Accounts Department')?></p>
                <p class="text-xs text-slate-500"><?=e($client['email'])?> <?=e($client['phone'] ? '| '.$client['phone'] : '')?></p>
                <?php if ($client['tax_number']): ?>
                    <p class="text-xs font-mono text-slate-600 mt-1">TRN / Tax ID: <strong><?=e($client['tax_number'])?></strong></p>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-3 gap-3 text-right">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80 flex flex-col justify-center">
                    <span class="text-3xs uppercase font-extrabold text-slate-400 tracking-wider mb-1">Total Invoiced</span>
                    <span class="text-sm font-mono font-bold text-slate-900"><?=money($totalInvoiced, $client['currency'])?></span>
                </div>
                <div class="bg-emerald-50 p-4 rounded-xl border border-emerald-200/80 flex flex-col justify-center">
                    <span class="text-3xs uppercase font-extrabold text-emerald-600 tracking-wider mb-1">Total Paid</span>
                    <span class="text-sm font-mono font-bold text-emerald-700"><?=money($totalPaid, $client['currency'])?></span>
                </div>
                <div class="bg-amber-50 p-4 rounded-xl border border-amber-200/80 flex flex-col justify-center">
                    <span class="text-3xs uppercase font-extrabold text-amber-700 tracking-wider mb-1">Balance Due</span>
                    <span class="text-sm font-mono font-black text-amber-700"><?=money($closingBalance, $client['currency'])?></span>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-100/80 border-y border-slate-200 text-2xs font-extrabold text-slate-500 uppercase tracking-wider">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Reference / Description</th>
                        <th class="px-4 py-3 text-right">Invoiced (+)</th>
                        <th class="px-4 py-3 text-right">Paid (-)</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($statementRows)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400 font-semibold">No transactions recorded for this client in the selected date range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($statementRows as $r): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-4 py-3 font-semibold text-slate-700"><?=date('d M Y', strtotime($r['trans_date']))?></td>
                                <td class="px-4 py-3">
                                    <?php if ($r['type'] === 'invoice'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-3xs font-extrabold bg-blue-50 text-blue-700 border border-blue-200">INVOICE</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-3xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">PAYMENT</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    <?=e($r['reference'])?>
                                    <?php if ($r['notes']): ?>
                                        <span class="block text-2xs font-normal text-slate-400 mt-0.5"><?=e($r['notes'])?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                    <?=$r['type'] === 'invoice' ? money((float)$r['amount'], $client['currency']) : '-'?>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600">
                                    <?=$r['type'] === 'payment' ? money((float)$r['amount'], $client['currency']) : '-'?>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                    <?=money((float)$r['running_balance'], $client['currency'])?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 border-t-2 border-slate-200 text-xs font-bold text-slate-900">
                        <td colspan="3" class="px-4 py-3.5 uppercase tracking-wider text-slate-500">Closing Balance Summary</td>
                        <td class="px-4 py-3.5 text-right font-mono"><?=money($totalInvoiced, $client['currency'])?></td>
                        <td class="px-4 py-3.5 text-right font-mono text-emerald-600"><?=money($totalPaid, $client['currency'])?></td>
                        <td class="px-4 py-3.5 text-right font-mono text-base font-black text-amber-600"><?=money($closingBalance, $client['currency'])?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php 
if (!$isClientPortal) {
    page_end(); 
} else {
    echo '</div></body></html>';
}
?>
