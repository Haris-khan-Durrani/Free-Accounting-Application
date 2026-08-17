<?php
require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];

// Check Client Portal Session
if (empty($_SESSION['client_id'])) {
    redirect('client_login.php');
}

$clientId = (int)$_SESSION['client_id'];
$tid      = (int)$_SESSION['client_tenant_id'];

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['client_id'], $_SESSION['client_tenant_id'], $_SESSION['client_name'], $_SESSION['client_email']);
    redirect('client_login.php');
}

// Fetch Client Info
$stC = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
$stC->execute([$clientId, $tid]);
$client = $stC->fetch();

if (!$client) {
    session_destroy();
    redirect('client_login.php');
}

// Fetch Tenant Branding
$brand = \Core\Branding::get($pdo, $tid);

// Fetch All Invoices for this Client
$statusFilter = $_GET['status'] ?? 'all';
$query = "SELECT * FROM invoices WHERE tenant_id = ? AND client_id = ? AND status != 'cancelled'";
$params = [$tid, $clientId];

if (in_array($statusFilter, ['paid', 'unpaid', 'overdue', 'partially_paid'])) {
    if ($statusFilter === 'unpaid') {
        $query .= " AND status IN ('sent', 'draft')";
    } else {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
    }
}
$query .= " ORDER BY invoice_date DESC, id DESC";

$stInv = $pdo->prepare($query);
$stInv->execute($params);
$invoices = $stInv->fetchAll();

// Financial Summaries
$stSum = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total), 0) as total_invoiced,
        COALESCE(SUM(paid_amount), 0) as total_paid,
        COALESCE(SUM(total - paid_amount), 0) as total_due
    FROM invoices 
    WHERE tenant_id = ? AND client_id = ? AND status != 'cancelled'
");
$stSum->execute([$tid, $clientId]);
$summary = $stSum->fetch();

$totalInvoiced = (float)$summary['total_invoiced'];
$totalPaid = (float)$summary['total_paid'];
$totalDue = max(0, (float)$summary['total_due']);
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Portal - <?=e($client['company_name'])?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-full bg-slate-950 text-slate-100 font-sans pb-12">

<!-- Navigation Topbar -->
<header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50 backdrop-blur-xl bg-opacity-90">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg border border-amber-500/30">
                <i class="fa-solid fa-building"></i>
            </div>
            <div>
                <h1 class="text-sm font-extrabold text-white leading-tight"><?=e($brand['company_name'])?></h1>
                <span class="text-3xs font-extrabold text-amber-400 uppercase tracking-widest">Client Portal</span>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <div class="hidden sm:block text-right">
                <div class="text-xs font-bold text-white"><?=e($client['company_name'])?></div>
                <div class="text-2xs text-slate-400"><?=e($client['email'])?></div>
            </div>
            <a href="client_statement?client_id=<?=$clientId?>" target="_blank" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition-all border border-slate-700">
                <i class="fa-solid fa-file-invoice mr-1 text-amber-400"></i>Statement
            </a>
            <a href="client_portal?action=logout" class="text-slate-400 hover:text-rose-400 text-xs font-bold transition-colors" title="Sign Out">
                <i class="fa-solid fa-right-from-bracket text-base"></i>
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-8">
    <!-- Welcome Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-900 to-indigo-950/80 p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-2xl">
        <div>
            <span class="px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 text-3xs font-extrabold uppercase tracking-wider">Self-Service Account Ledger</span>
            <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight mt-2">Welcome back, <?=e($client['contact_name'] ?: $client['company_name'])?></h2>
            <p class="text-xs text-slate-400 mt-1">TRN: <?=e($client['tax_number'] ?: 'N/A')?> | Phone: <?=e($client['phone'] ?: 'N/A')?></p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="client_statement?client_id=<?=$clientId?>" target="_blank" class="px-5 py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 text-xs font-black rounded-2xl shadow-xl transition-all flex items-center space-x-2">
                <i class="fa-solid fa-print"></i>
                <span>Download Statement of Account</span>
            </a>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-widest">Total Invoiced Amount</span>
            <div class="text-2xl font-black text-blue-400 mt-1"><?=e($brand['currency'] ?: 'AED')?> <?=number_format($totalInvoiced, 2)?></div>
            <span class="text-2xs text-slate-500">Gross billing across all periods</span>
        </div>
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-widest">Total Payments Made</span>
            <div class="text-2xl font-black text-emerald-400 mt-1"><?=e($brand['currency'] ?: 'AED')?> <?=number_format($totalPaid, 2)?></div>
            <span class="text-2xs text-slate-500">Confirmed receipts & deposits</span>
        </div>
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <span class="text-3xs font-extrabold text-slate-400 uppercase tracking-widest">Outstanding Balance Due</span>
            <div class="text-2xl font-black text-amber-400 mt-1"><?=e($brand['currency'] ?: 'AED')?> <?=number_format($totalDue, 2)?></div>
            <span class="text-2xs text-slate-500">Pending payment balance</span>
        </div>
    </div>

    <!-- Invoice List Section -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-lg font-black text-white">Your Invoices & Billing Records</h3>
                <p class="text-xs text-slate-400">Click any invoice to view online or download printable PDF.</p>
            </div>
            
            <!-- Filters -->
            <div class="flex space-x-2 text-xs font-extrabold">
                <a href="client_portal?status=all" class="px-3 py-1.5 rounded-xl transition-all <?= $statusFilter === 'all' ? 'bg-amber-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">All</a>
                <a href="client_portal?status=unpaid" class="px-3 py-1.5 rounded-xl transition-all <?= $statusFilter === 'unpaid' ? 'bg-amber-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">Unpaid</a>
                <a href="client_portal?status=paid" class="px-3 py-1.5 rounded-xl transition-all <?= $statusFilter === 'paid' ? 'bg-amber-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">Paid</a>
                <a href="client_portal?status=overdue" class="px-3 py-1.5 rounded-xl transition-all <?= $statusFilter === 'overdue' ? 'bg-amber-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">Overdue</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-950 border-b border-slate-800 text-3xs font-black uppercase tracking-widest text-slate-400">
                    <tr>
                        <th class="px-6 py-4">Invoice #</th>
                        <th class="px-6 py-4">Issue Date</th>
                        <th class="px-6 py-4">Due Date</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Total</th>
                        <th class="px-6 py-4 text-right">Paid</th>
                        <th class="px-6 py-4 text-right">Balance</th>
                        <th class="px-6 py-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 font-semibold">
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="8" class="text-center py-12 text-slate-500 text-xs font-bold">No invoice records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): 
                            $bal = max(0, (float)$inv['total'] - (float)$inv['paid_amount']);
                            $status = $inv['status'];
                            $badgeClass = match($status) {
                                'paid' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                'partially_paid' => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
                                'overdue' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                default => 'bg-amber-500/20 text-amber-300 border-amber-500/30'
                            };
                        ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 text-white font-extrabold"><?=e($inv['invoice_number'])?></td>
                                <td class="px-6 py-4 text-xs text-slate-400"><?=e($inv['invoice_date'])?></td>
                                <td class="px-6 py-4 text-xs text-slate-400"><?=e($inv['valid_until'])?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-3xs font-black uppercase tracking-wider border <?= $badgeClass ?>">
                                        <?= str_replace('_', ' ', $status) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-black text-white"><?=e($inv['currency'])?> <?=number_format((float)$inv['total'], 2)?></td>
                                <td class="px-6 py-4 text-right text-emerald-400 font-bold"><?=e($inv['currency'])?> <?=number_format((float)$inv['paid_amount'], 2)?></td>
                                <td class="px-6 py-4 text-right text-amber-400 font-bold"><?=e($inv['currency'])?> <?=number_format($bal, 2)?></td>
                                <td class="px-6 py-4 text-center">
                                    <a href="public_invoice?id=<?=$inv['id']?>" target="_blank" class="px-3.5 py-1.5 bg-blue-600/20 hover:bg-blue-600 text-blue-300 hover:text-white border border-blue-500/30 rounded-xl text-xs font-bold transition-all inline-flex items-center space-x-1">
                                        <span>View & Pay</span>
                                        <i class="fa-solid fa-arrow-right text-2xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>
