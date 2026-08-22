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

// Fetch All Invoices for this Client (un-cancelled)
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

// Count totals per status for Filter Badges
$counts = [
    'all' => 0,
    'unpaid' => 0,
    'paid' => 0,
    'partially_paid' => 0,
    'overdue' => 0
];

$stCounts = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM invoices WHERE tenant_id = ? AND client_id = ? AND status != 'cancelled' GROUP BY status");
$stCounts->execute([$tid, $clientId]);
while ($r = $stCounts->fetch()) {
    $stName = $r['status'];
    $cnt = (int)$r['cnt'];
    $counts['all'] += $cnt;
    if ($stName === 'draft' || $stName === 'sent') {
        $counts['unpaid'] += $cnt;
    } elseif (isset($counts[$stName])) {
        $counts[$stName] += $cnt;
    }
}

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

$settledPercentage = $totalInvoiced > 0 ? min(100, round(($totalPaid / $totalInvoiced) * 100)) : 100;
$clientCurr = !empty($client['currency']) ? $client['currency'] : ($invoices[0]['currency'] ?? 'AED');
?>
<!doctype html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Portal - <?=e($client['company_name'])?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brandPrimary: '<?=e($brand['primary_color'] ?? '#0f172a')?>',
                        brandAccent: '<?=e($brand['accent_color'] ?? '#d97706')?>'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Apply saved theme before render to prevent flash of unstyled content
        (function() {
            const savedTheme = localStorage.getItem('portalTheme');
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="min-h-full bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans transition-colors duration-300 pb-16">

<!-- Navigation Topbar -->
<header class="bg-white/90 dark:bg-slate-900/90 border-b border-slate-200 dark:border-slate-800 sticky top-0 z-50 backdrop-blur-xl transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <!-- Logo & Portal Badge -->
        <div class="flex items-center space-x-3">
            <?php if (!empty($brand['logo_url'])): ?>
                <img src="<?=e($brand['logo_url'])?>" alt="Logo" class="h-9 w-auto object-contain">
            <?php else: ?>
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-500 flex items-center justify-center font-bold text-lg border border-amber-500/30 shadow-sm">
                    <i class="fa-solid fa-building"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-sm font-black text-slate-900 dark:text-white leading-tight"><?=e($brand['company_name'])?></h1>
                <span class="text-3xs font-extrabold text-amber-600 dark:text-amber-400 uppercase tracking-widest block">Client Portal</span>
            </div>
        </div>

        <!-- Controls: Theme Toggle + Statement + Logout -->
        <div class="flex items-center space-x-3 sm:space-x-4">
            <!-- Theme Toggle Button -->
            <button type="button" onclick="togglePortalTheme()" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold transition-all border border-slate-300 dark:border-slate-700 flex items-center space-x-2 shadow-sm" title="Toggle Dark / Light Mode">
                <i id="themeToggleIcon" class="fa-solid fa-moon text-amber-500 dark:text-amber-400 text-sm"></i>
                <span id="themeToggleLabel" class="hidden sm:inline-block">Theme</span>
            </button>

            <!-- User Info Pill -->
            <div class="hidden md:block text-right border-l border-slate-200 dark:border-slate-800 pl-4">
                <div class="text-xs font-extrabold text-slate-900 dark:text-white leading-tight"><?=e($client['company_name'])?></div>
                <div class="text-2xs text-slate-500 dark:text-slate-400"><?=e($client['email'])?></div>
            </div>

            <!-- Statement Link -->
            <a href="client_statement.php" target="_blank" class="px-3.5 py-1.5 bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-slate-950 border border-amber-500/30 rounded-xl text-xs font-extrabold transition-all inline-flex items-center space-x-1.5 shadow-sm">
                <i class="fa-solid fa-file-invoice text-2xs"></i>
                <span class="hidden sm:inline-block">Statement</span>
            </a>

            <!-- Logout Link -->
            <a href="client_portal.php?action=logout" class="p-2 text-slate-400 hover:text-rose-500 dark:hover:text-rose-400 rounded-xl hover:bg-rose-500/10 transition-colors" title="Sign Out">
                <i class="fa-solid fa-right-from-bracket text-sm"></i>
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-8">

    <!-- Welcome Hero Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 shadow-2xl border border-slate-800">
        <!-- Subtle Ambient Background Glow -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 text-3xs font-extrabold uppercase tracking-wider border border-amber-500/30">
                    <i class="fa-solid fa-shield-halved text-2xs"></i>
                    <span>Self-Service Account Ledger</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                    Welcome back, <?=e($client['contact_name'] ?: $client['company_name'])?>
                </h2>
                <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-300 font-medium pt-1">
                    <span><i class="fa-solid fa-id-card text-amber-400 mr-1.5"></i>TRN / Tax ID: <strong><?=e($client['tax_number'] ?: 'N/A')?></strong></span>
                    <span><i class="fa-solid fa-phone text-amber-400 mr-1.5"></i>Phone: <strong><?=e($client['phone'] ?: 'N/A')?></strong></span>
                    <span><i class="fa-solid fa-envelope text-amber-400 mr-1.5"></i>Email: <strong><?=e($client['email'])?></strong></span>
                </div>
            </div>

            <!-- Hero Action Buttons -->
            <div class="flex flex-wrap items-center gap-3">
                <a href="client_statement.php" target="_blank" class="px-5 py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 text-xs font-black rounded-2xl shadow-xl hover:shadow-amber-500/20 transition-all flex items-center space-x-2.5">
                    <i class="fa-solid fa-file-pdf text-sm"></i>
                    <span>Download Statement of Account</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Financial KPI Overview & Progress Bar -->
    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <!-- Total Invoiced -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-3xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Total Invoiced Amount</span>
                    <div class="w-9 h-9 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-extrabold">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-blue-400"><?=money((float)$totalInvoiced, $clientCurr)?></div>
                <span class="text-2xs font-semibold text-slate-500 dark:text-slate-400 mt-1 block">Gross billing across all transactions</span>
            </div>

            <!-- Total Paid -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-3xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Total Payments Made</span>
                    <div class="w-9 h-9 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm font-extrabold">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400"><?=money((float)$totalPaid, $clientCurr)?></div>
                <span class="text-2xs font-semibold text-slate-500 dark:text-slate-400 mt-1 block">Confirmed receipts & settled payments</span>
            </div>

            <!-- Outstanding Balance Due -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-3xs font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Outstanding Balance Due</span>
                    <div class="w-9 h-9 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm font-extrabold">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-600 dark:text-amber-400"><?=money((float)$totalDue, $clientCurr)?></div>
                <span class="text-2xs font-semibold text-slate-500 dark:text-slate-400 mt-1 block">Pending balance across open invoices</span>
            </div>
        </div>

        <!-- Overall Settlement Progress Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between text-xs font-extrabold mb-2">
                <span class="text-slate-700 dark:text-slate-300 flex items-center gap-2">
                    <i class="fa-solid fa-chart-line text-emerald-500"></i>
                    <span>Account Settlement Status</span>
                </span>
                <span class="text-emerald-600 dark:text-emerald-400 font-mono font-black"><?= $settledPercentage ?>% Settled</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-2.5 rounded-full transition-all duration-500" style="width: <?= $settledPercentage ?>%;"></div>
            </div>
            <p class="text-2xs text-slate-500 dark:text-slate-400 mt-2 font-medium">
                <?php if ($totalDue <= 0): ?>
                    🎉 <strong>Great job!</strong> Your account is fully settled with zero outstanding balance.
                <?php else: ?>
                    💡 You have settled <strong><?=$settledPercentage?>%</strong> of your total billing. Outstanding balance remaining: <strong><?=money($totalDue, $clientCurr)?></strong>.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Invoice Records Table & Search Section -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-xl overflow-hidden transition-colors duration-300">
        
        <!-- Header & Live Search Bar -->
        <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-amber-500"></i>
                    <span>Your Invoices & Billing Records</span>
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">View payment status, download invoices, or settle open balances.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <!-- Instant Search Input -->
                <div class="relative w-full sm:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="invoiceSearchInput" onkeyup="filterInvoices()" placeholder="Search invoice # or amount..." class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                </div>

                <!-- Status Filters -->
                <div class="flex items-center space-x-1.5 text-xs font-extrabold overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
                    <a href="client_portal.php?status=all" class="px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5 <?= $statusFilter === 'all' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' ?>">
                        <span>All</span>
                        <span class="px-1.5 py-0.2 text-3xs rounded-full bg-slate-950/20 font-black"><?= $counts['all'] ?></span>
                    </a>
                    <a href="client_portal.php?status=unpaid" class="px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5 <?= $statusFilter === 'unpaid' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' ?>">
                        <span>Unpaid</span>
                        <span class="px-1.5 py-0.2 text-3xs rounded-full bg-slate-950/20 font-black"><?= $counts['unpaid'] ?></span>
                    </a>
                    <a href="client_portal.php?status=paid" class="px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5 <?= $statusFilter === 'paid' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' ?>">
                        <span>Paid</span>
                        <span class="px-1.5 py-0.2 text-3xs rounded-full bg-slate-950/20 font-black"><?= $counts['paid'] ?></span>
                    </a>
                    <a href="client_portal.php?status=overdue" class="px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5 <?= $statusFilter === 'overdue' ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' ?>">
                        <span>Overdue</span>
                        <span class="px-1.5 py-0.2 text-3xs rounded-full bg-slate-950/20 font-black"><?= $counts['overdue'] ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-3xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
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
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-semibold" id="invoiceTableBody">
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400 dark:text-slate-500 text-xs font-bold">
                                <i class="fa-solid fa-folder-open text-2xl mb-2 block"></i>
                                No invoice records found for the selected status.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): 
                            $bal = max(0, (float)$inv['total'] - (float)$inv['paid_amount']);
                            $status = $inv['status'];
                            
                            $badgeClass = match($status) {
                                'paid' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
                                'partially_paid' => 'bg-blue-500/15 text-blue-700 dark:text-blue-400 border-blue-500/30',
                                'overdue' => 'bg-rose-500/15 text-rose-700 dark:text-rose-400 border-rose-500/30',
                                default => 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30'
                            };

                            $actionText = match($status) {
                                'paid' => 'View Receipt',
                                'partially_paid' => 'Pay Balance',
                                default => 'Pay Now'
                            };

                            $actionBtnClass = match($status) {
                                'paid' => 'bg-emerald-500/10 hover:bg-emerald-600 text-emerald-700 dark:text-emerald-300 hover:text-white border-emerald-500/30',
                                'partially_paid' => 'bg-amber-500/10 hover:bg-amber-600 text-amber-700 dark:text-amber-300 hover:text-white border-amber-500/30',
                                default => 'bg-blue-600/10 hover:bg-blue-600 text-blue-700 dark:text-blue-300 hover:text-white border-blue-500/30'
                            };

                            $actionIcon = match($status) {
                                'paid' => 'fa-receipt',
                                'partially_paid' => 'fa-credit-card',
                                default => 'fa-arrow-right'
                            };

                            $searchData = $inv['invoice_number'] . ' ' . $inv['currency'] . ' ' . $inv['total'] . ' ' . $inv['invoice_date'];
                        ?>
                            <tr class="invoice-row hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors" data-search="<?=e($searchData)?>">
                                <td class="px-6 py-4 font-extrabold text-slate-900 dark:text-white font-mono">
                                    <a href="<?=e(get_public_invoice_url($inv))?>" target="_blank" class="hover:text-amber-500 transition-colors">
                                        <?=e($inv['invoice_number'])?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400"><?=e(date('d M Y', strtotime($inv['invoice_date'])))?></td>
                                <td class="px-6 py-4 text-xs text-slate-500 dark:text-slate-400"><?=e(!empty($inv['valid_until']) ? date('d M Y', strtotime($inv['valid_until'])) : 'N/A')?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-3xs font-black uppercase tracking-wider border <?= $badgeClass ?>">
                                        <?= str_replace('_', ' ', $status) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-black text-slate-900 dark:text-white font-mono"><?=e($inv['currency'])?> <?=number_format((float)$inv['total'], 2)?></td>
                                <td class="px-6 py-4 text-right text-emerald-600 dark:text-emerald-400 font-bold font-mono"><?=e($inv['currency'])?> <?=number_format((float)$inv['paid_amount'], 2)?></td>
                                <td class="px-6 py-4 text-right text-amber-600 dark:text-amber-400 font-bold font-mono"><?=e($inv['currency'])?> <?=number_format($bal, 2)?></td>
                                <td class="px-6 py-4 text-center">
                                    <a href="<?=e(get_public_invoice_url($inv))?>" target="_blank" class="px-3.5 py-1.5 border rounded-xl text-xs font-extrabold transition-all inline-flex items-center space-x-1.5 shadow-sm <?= $actionBtnClass ?>">
                                        <i class="fa-solid <?= $actionIcon ?> text-2xs"></i>
                                        <span><?= $actionText ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr id="noSearchResults" style="display: none;">
                        <td colspan="8" class="text-center py-12 text-slate-400 text-xs font-bold">
                            <i class="fa-solid fa-magnifying-glass text-xl mb-2 block"></i>
                            No matching invoices found for your search query.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Supportive Accounts & Customer Help Banner -->
    <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="space-y-1">
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-extrabold text-3xs uppercase tracking-wider border border-amber-500/30">Client Support</span>
                <h4 class="text-base font-black text-white">Need Assistance with your Account or Billing?</h4>
            </div>
            <p class="text-xs text-slate-300 max-w-2xl leading-relaxed pt-1">
                If you have questions regarding an invoice, require payment assistance, or need remittance reconciliation, our accounts team is available to assist you.
            </p>
            <div class="flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-400 pt-2">
                <?php if (!empty($brand['company_email'])): ?>
                    <span><i class="fa-solid fa-envelope text-amber-400 mr-1.5"></i><?=e($brand['company_email'])?></span>
                <?php endif; ?>
                <?php if (!empty($brand['company_phone'])): ?>
                    <span><i class="fa-solid fa-phone text-amber-400 mr-1.5"></i><?=e($brand['company_phone'])?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center space-x-3 self-end md:self-center shrink-0">
            <?php if (!empty($brand['company_email'])): ?>
                <a href="mailto:<?=e($brand['company_email'])?>?subject=Client%20Account%20Inquiry%20-%20<?=urlencode($client['company_name'])?>" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs rounded-xl shadow-md transition-all flex items-center space-x-2">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Contact Accounts</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function togglePortalTheme() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('portalTheme', isDark ? 'dark' : 'light');
    updateThemeIcon(isDark);
}

function updateThemeIcon(isDark) {
    const icon = document.getElementById('themeToggleIcon');
    const label = document.getElementById('themeToggleLabel');
    if (icon) {
        icon.className = isDark ? 'fa-solid fa-sun text-amber-400 text-sm' : 'fa-solid fa-moon text-indigo-600 text-sm';
    }
    if (label) {
        label.textContent = isDark ? 'Light' : 'Dark';
    }
}

function filterInvoices() {
    const input = document.getElementById('invoiceSearchInput');
    const q = input.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.invoice-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.getAttribute('data-search').toLowerCase();
        if (text.includes(q)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    const noResults = document.getElementById('noSearchResults');
    if (noResults) {
        noResults.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateThemeIcon(document.documentElement.classList.contains('dark'));
});
</script>

</body>
</html>
