<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();
$brand = branding();

// Date Range Filtering Logic
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$preset = $_GET['preset'] ?? 'year_to_date';

if ($preset === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($preset === 'this_quarter') {
    $currentMonth = date('n');
    $quarterStartMonth = floor(($currentMonth - 1) / 3) * 3 + 1;
    $startDate = date('Y-' . str_pad((string)$quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
    $endDate = date('Y-m-t');
} elseif ($preset === 'last_30_days') {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
} elseif ($preset === 'all_time') {
    $startDate = '2020-01-01';
    $endDate = date('Y-m-d');
}

// 1. Overall Stats Query for Filtered Date Range
$stStats = $pdo->prepare("SELECT 
    COUNT(*) total_count, 
    COALESCE(SUM(total), 0) total_value, 
    COALESCE(SUM(paid_amount), 0) paid_value, 
    COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) paid_count, 
    COALESCE(SUM(CASE WHEN status IN ('draft', 'sent', 'partially_paid', 'overdue') THEN (total - paid_amount) ELSE 0 END), 0) outstanding 
    FROM invoices WHERE tenant_id = ? AND invoice_date BETWEEN ? AND ?");
$stStats->execute([$tid, $startDate, $endDate]);
$stats = $stStats->fetch();

// 2. Expenses Query for Filtered Date Range
$stExp = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
$stExp->execute([$tid, $startDate, $endDate]);
$totalExpenses = (float)$stExp->fetchColumn();

$netIncome = (float)$stats['paid_value'] - $totalExpenses;

// 3. Overdue Invoices Query
$stOverdue = $pdo->prepare("SELECT i.*, c.company_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? AND i.status IN ('sent', 'draft', 'partially_paid') AND i.valid_until < CURRENT_DATE() ORDER BY i.valid_until ASC");
$stOverdue->execute([$tid]);
$overdueInvoices = $stOverdue->fetchAll();

// 4. Monthly Performance Data Query for Chart.js
$months = [];
$monthlyRevenue = [];
$monthlyCollected = [];
$monthlyExpenses = [];

for ($i = 5; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-$i months"));
    $mEnd = date('Y-m-t', strtotime("-$i months"));
    $mLabel = date('M Y', strtotime("-$i months"));
    $months[] = $mLabel;

    // Monthly Gross Invoiced
    $stMRev = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoices WHERE tenant_id = ? AND invoice_date BETWEEN ? AND ?");
    $stMRev->execute([$tid, $mStart, $mEnd]);
    $monthlyRevenue[] = (float)$stMRev->fetchColumn();

    // Monthly Cash Collected
    $stMPay = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = ? AND payment_date BETWEEN ? AND ?");
    $stMPay->execute([$tid, $mStart, $mEnd]);
    $monthlyCollected[] = (float)$stMPay->fetchColumn();

    // Monthly Expenses
    $stMExp = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM expenses WHERE tenant_id = ? AND expense_date BETWEEN ? AND ?");
    $stMExp->execute([$tid, $mStart, $mEnd]);
    $monthlyExpenses[] = (float)$stMExp->fetchColumn();
}

// 5. Invoice Status Breakdown Query (Chart 2)
$stStatus = $pdo->prepare("SELECT status, COUNT(*) count, COALESCE(SUM(total), 0) total FROM invoices WHERE tenant_id = ? AND invoice_date BETWEEN ? AND ? GROUP BY status");
$stStatus->execute([$tid, $startDate, $endDate]);
$statusDataRaw = $stStatus->fetchAll();
$statusLabels = [];
$statusCounts = [];
$statusColorsMap = [
    'paid' => '#10b981',
    'partially_paid' => '#f59e0b',
    'sent' => '#3b82f6',
    'draft' => '#06b6d4',
    'overdue' => '#f43f5e',
    'void' => '#64748b',
    'cancelled' => '#94a3b8'
];
$statusColorList = [];
foreach ($statusDataRaw as $s) {
    $statusLabels[] = strtoupper($s['status']);
    $statusCounts[] = (int)$s['count'];
    $statusColorList[] = $statusColorsMap[$s['status']] ?? '#64748b';
}

// 6. Top 5 Clients by Revenue Query (Chart 3)
$stTopClients = $pdo->prepare("SELECT c.company_name, COALESCE(SUM(i.total), 0) revenue FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? AND i.invoice_date BETWEEN ? AND ? GROUP BY i.client_id, c.company_name ORDER BY revenue DESC LIMIT 5");
$stTopClients->execute([$tid, $startDate, $endDate]);
$topClientRows = $stTopClients->fetchAll();
$topClientNames = array_column($topClientRows, 'company_name');
$topClientRevenues = array_map('floatval', array_column($topClientRows, 'revenue'));

// 7. Expense Category Distribution Query (Chart 4)
$stExpCat = $pdo->prepare("SELECT COALESCE(ec.name, 'General Expense') AS category_name, COALESCE(SUM(e.total), 0) AS total FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id WHERE e.tenant_id = ? AND e.expense_date BETWEEN ? AND ? GROUP BY ec.id, ec.name ORDER BY total DESC LIMIT 6");
$stExpCat->execute([$tid, $startDate, $endDate]);
$expCatRows = $stExpCat->fetchAll();
$expCatLabels = array_column($expCatRows, 'category_name');
$expCatTotals = array_map('floatval', array_column($expCatRows, 'total'));

// 8. Recent Invoices Log with Search & Pagination
$invSearch = trim($_GET['inv_search'] ?? '');
$invPage = max(1, (int)($_GET['inv_page'] ?? 1));
$invPerPage = 15;
$invOffset = ($invPage - 1) * $invPerPage;

$invWhere = "WHERE i.tenant_id = ? AND i.invoice_date BETWEEN ? AND ?";
$invParams = [$tid, $startDate, $endDate];

if (!empty($invSearch)) {
    $invWhere .= " AND (i.invoice_number LIKE ? OR c.company_name LIKE ? OR i.notes LIKE ?)";
    $sT = "%$invSearch%";
    $invParams = array_merge($invParams, [$sT, $sT, $sT]);
}

$stInvCount = $pdo->prepare("SELECT COUNT(*) FROM invoices i JOIN clients c ON c.id = i.client_id $invWhere");
$stInvCount->execute($invParams);
$totalInvRecords = (int)$stInvCount->fetchColumn();
$totalInvPages = max(1, (int)ceil($totalInvRecords / $invPerPage));

$stRows = $pdo->prepare("
    SELECT i.*, c.company_name 
    FROM invoices i 
    JOIN clients c ON c.id = i.client_id 
    $invWhere 
    ORDER BY i.id DESC 
    LIMIT $invPerPage OFFSET $invOffset
");
$stRows->execute($invParams);
$rows = $stRows->fetchAll();

page_start('Dashboard');
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Mobile-Optimized Page Header -->
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <div class="flex items-center space-x-2">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Executive Dashboard</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-blue-100 text-blue-800">
                <i class="fa-solid fa-coins mr-1 text-2xs"></i> <?=e($activeTenant['currency'])?>
            </span>
        </div>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Real-time financial performance for <strong class="text-slate-800 font-bold"><?=e($activeTenant['name'])?></strong></p>
    </div>

    <!-- Mobile Action Buttons Toolbar -->
    <div class="flex items-center space-x-2">
        <a href="quote_form" class="flex-1 sm:flex-none justify-center whitespace-nowrap inline-flex items-center px-3.5 py-2 border border-slate-300 shadow-xs text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50 transition-all">
            <i class="fa-solid fa-file-signature mr-1.5 text-amber-500"></i>Proposal
        </a>
        <a href="expense_form" class="flex-1 sm:flex-none justify-center whitespace-nowrap inline-flex items-center px-3.5 py-2 border border-slate-300 shadow-xs text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50 transition-all">
            <i class="fa-solid fa-receipt mr-1.5 text-rose-500"></i>Expense
        </a>
        <a href="invoice_form" class="flex-1 sm:flex-none justify-center whitespace-nowrap inline-flex items-center px-4 py-2 border border-transparent shadow-md text-xs font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 transition-all">
            <i class="fa-solid fa-plus mr-1.5"></i>Invoice
        </a>
    </div>
</div>

<!-- Fancy Mobile-First Date Range Filter Bar -->
<div class="bg-gradient-to-r from-slate-900 to-slate-950 rounded-2xl p-4 text-white shadow-xl border border-slate-800 mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="h-8 w-8 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 font-bold text-xs"><i class="fa-solid fa-calendar-day"></i></div>
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-200">Date Range Filter</span>
            </div>
            <button onclick="document.getElementById('custom-date-drawer').classList.toggle('hidden')" class="md:hidden text-2xs font-bold text-amber-400 bg-slate-800 hover:bg-slate-700 px-2.5 py-1 rounded-lg border border-slate-700">
                <i class="fa-solid fa-sliders mr-1"></i>Custom
            </button>
        </div>

        <!-- Scrollable Preset Buttons Carousel -->
        <div class="flex items-center space-x-2 overflow-x-auto py-1 scrollbar-none">
            <a href="index?preset=this_month" class="whitespace-nowrap px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?=$preset==='this_month'?'bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md':'bg-slate-800/90 text-slate-300 hover:bg-slate-700'?>">This Month</a>
            <a href="index?preset=this_quarter" class="whitespace-nowrap px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?=$preset==='this_quarter'?'bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md':'bg-slate-800/90 text-slate-300 hover:bg-slate-700'?>">This Quarter</a>
            <a href="index?preset=year_to_date" class="whitespace-nowrap px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?=$preset==='year_to_date'?'bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md':'bg-slate-800/90 text-slate-300 hover:bg-slate-700'?>">YTD</a>
            <a href="index?preset=last_30_days" class="whitespace-nowrap px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?=$preset==='last_30_days'?'bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md':'bg-slate-800/90 text-slate-300 hover:bg-slate-700'?>">Last 30 Days</a>
            <a href="index?preset=all_time" class="whitespace-nowrap px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?=$preset==='all_time'?'bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md':'bg-slate-800/90 text-slate-300 hover:bg-slate-700'?>">All Time</a>
        </div>

        <!-- Custom Date Range Controls -->
        <form method="get" id="custom-date-drawer" class="hidden md:flex items-center space-x-2 pt-2 md:pt-0 border-t border-slate-800 md:border-t-0">
            <input type="date" name="start_date" value="<?=e($startDate)?>" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-1 text-xs font-bold text-white focus:outline-none">
            <span class="text-xs text-slate-400 font-bold">to</span>
            <input type="date" name="end_date" value="<?=e($endDate)?>" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-1 text-xs font-bold text-white focus:outline-none">
            <button type="submit" class="px-3.5 py-1 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl shadow-xs">Apply</button>
        </form>
    </div>
</div>

<!-- Overdue Alert Notice -->
<?php if (!empty($overdueInvoices)): ?>
    <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 mb-6 shadow-sm flex items-center justify-between">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-rose-100 p-2.5 rounded-xl text-rose-600">
                <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-bold text-rose-900"><?=count($overdueInvoices)?> Overdue Invoices Requiring Collection</h3>
                <p class="text-xs text-rose-700 mt-0.5">Follow up with clients to maintain positive cash flow.</p>
            </div>
        </div>
        <a href="reports_aging" class="text-xs font-bold text-rose-700 hover:text-rose-900 bg-rose-100 hover:bg-rose-200 px-3 py-1.5 rounded-xl transition-all">
            View Aging →
        </a>
    </div>
<?php endif; ?>

<!-- Responsive Grid KPI Cards (Zero Horizontal Scrollbars) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    
    <!-- Stat Card 1: Gross Revenue -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Gross Revenue</span>
            <div class="h-9 w-9 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center text-sm font-bold">
                <i class="fa-solid fa-vault"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-black text-slate-900 tracking-tight"><?=money((float)$stats['total_value'])?></div>
            <div class="text-xs font-bold text-slate-500 mt-1 flex items-center">
                <span class="text-blue-700 font-extrabold mr-1"><?=e((string)$stats['total_count'])?></span> Invoices Issued
            </div>
        </div>
    </div>

    <!-- Stat Card 2: Paid Revenue -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Cash Collected</span>
            <div class="h-9 w-9 bg-emerald-100 text-emerald-700 rounded-xl flex items-center justify-center text-sm font-bold">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-black text-emerald-600 tracking-tight"><?=money((float)$stats['paid_value'])?></div>
            <div class="text-xs font-bold text-slate-500 mt-1 flex items-center">
                <span class="text-emerald-700 font-extrabold mr-1"><?=e((string)$stats['paid_count'])?></span> Settled Payments
            </div>
        </div>
    </div>

    <!-- Stat Card 3: Outstanding Receivables -->
    <div class="bg-white rounded-2xl p-5 border border-amber-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-amber-900 uppercase tracking-wider">Outstanding A/R</span>
            <div class="h-9 w-9 bg-amber-100 text-amber-700 rounded-xl flex items-center justify-center text-sm font-bold">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-black text-amber-600 tracking-tight"><?=money((float)$stats['outstanding'])?></div>
            <div class="text-xs font-bold text-amber-700 mt-1">Pending Collection</div>
        </div>
    </div>

    <!-- Stat Card 4: Operating Expenses -->
    <div class="bg-white rounded-2xl p-5 border border-rose-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-rose-900 uppercase tracking-wider">Expenses</span>
            <div class="h-9 w-9 bg-rose-100 text-rose-700 rounded-xl flex items-center justify-center text-sm font-bold">
                <i class="fa-solid fa-receipt"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-black text-rose-600 tracking-tight"><?=money($totalExpenses)?></div>
            <div class="text-xs font-bold text-rose-700 mt-1">Total Operating Bills</div>
        </div>
    </div>

    <!-- Stat Card 5: Net Profit -->
    <div class="bg-white rounded-2xl p-5 border border-purple-200 shadow-sm hover:shadow-md transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-purple-900 uppercase tracking-wider">Net Income</span>
            <div class="h-9 w-9 bg-purple-100 text-purple-700 rounded-xl flex items-center justify-center text-sm font-bold">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-2xl font-black <?= $netIncome >= 0 ? 'text-purple-700' : 'text-rose-600' ?> tracking-tight"><?=money($netIncome)?></div>
            <div class="text-xs font-bold text-purple-800 mt-1"><?= $netIncome >= 0 ? 'Operating Surplus' : 'Operating Deficit' ?></div>
        </div>
    </div>

</div>

<!-- ════════════════════════════════════════════════════════════════════ -->
<!-- Executive Multi-Chart Financial Analytics Suite -->
<!-- ════════════════════════════════════════════════════════════════════ -->
<div class="space-y-6 mb-8">

    <!-- Top Chart 1: 6-Month Revenue vs Cash vs Expenses (Full Width) -->
    <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-base sm:text-lg font-extrabold text-slate-900 flex items-center">
                    <i class="fa-solid fa-chart-area text-blue-500 mr-2"></i>6-Month Financial Performance Trends
                </h2>
                <p class="text-xs text-slate-500">Gross invoiced revenue, settled cash collections, and operating expense trajectory</p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                <i class="fa-solid fa-clock-rotate-left mr-1.5 text-blue-500"></i>Rolling 6 Months
            </span>
        </div>
        <div class="w-full h-64 sm:h-72">
            <canvas id="financialTrendChart"></canvas>
        </div>
    </div>

    <!-- Sub-Grid: 3 Complementary Analytics Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Chart 2: Invoice Status Distribution -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center">
                    <i class="fa-solid fa-pie-chart text-emerald-500 mr-2"></i>Invoice Status Mix
                </h3>
                <span class="text-2xs font-bold text-slate-400">By Count</span>
            </div>
            <div class="w-full h-52 relative flex items-center justify-center">
                <?php if (empty($statusCounts)): ?>
                    <div class="text-center text-xs text-slate-400">No invoice status data</div>
                <?php else: ?>
                    <canvas id="statusDistributionChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart 3: Top 5 Clients by Revenue -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center">
                    <i class="fa-solid fa-building-user text-amber-500 mr-2"></i>Top 5 Clients
                </h3>
                <span class="text-2xs font-bold text-slate-400">By Revenue</span>
            </div>
            <div class="w-full h-52 relative flex items-center justify-center">
                <?php if (empty($topClientRevenues)): ?>
                    <div class="text-center text-xs text-slate-400">No client revenue data</div>
                <?php else: ?>
                    <canvas id="topClientsChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart 4: Operating Expense Category Breakdown -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center">
                    <i class="fa-solid fa-tags text-rose-500 mr-2"></i>Expense Categories
                </h3>
                <span class="text-2xs font-bold text-slate-400">By Spending</span>
            </div>
            <div class="w-full h-52 relative flex items-center justify-center">
                <?php if (empty($expCatTotals)): ?>
                    <div class="text-center text-xs text-slate-400">No expense records found</div>
                <?php else: ?>
                    <canvas id="expenseCategoryChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>


<!-- Invoice Management Log (Desktop Table + Fancy Mobile Touch Cards) -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-900">Recent Invoices <span class="text-xs font-semibold text-slate-400">(<?=$totalInvRecords?> total)</span></h2>
            <p class="text-xs text-slate-500">Active billing log for <strong><?=e($activeTenant['name'])?></strong></p>
        </div>

        <div class="flex items-center space-x-3">
            <form method="get" class="flex items-center space-x-2">
                <input type="hidden" name="start_date" value="<?=e($startDate)?>">
                <input type="hidden" name="end_date" value="<?=e($endDate)?>">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" name="inv_search" value="<?=e($invSearch)?>" placeholder="Search invoice #, client name..." class="pl-8 pr-4 py-1.5 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 w-48 sm:w-64">
                </div>
                <?php if ($invSearch): ?>
                    <a href="index" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-all">Clear</a>
                <?php endif; ?>
                <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-xs">Search</button>
            </form>

            <a href="invoice_form" class="text-xs font-extrabold text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-xl transition-all whitespace-nowrap">
                + New Invoice
            </a>
        </div>
    </div>

    <!-- Desktop Table View (Hidden on Mobile) -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">Invoice #</th>
                    <th class="px-6 py-3.5">Client Company</th>
                    <th class="px-6 py-3.5">Issue Date</th>
                    <th class="px-6 py-3.5">Due Date</th>
                    <th class="px-6 py-3.5 text-right">Total Value</th>
                    <th class="px-6 py-3.5 text-center">Status</th>
                    <th class="px-6 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-file-invoice text-4xl mb-3 text-slate-300 block"></i>
                            <span class="font-bold text-slate-700 block mb-1">No invoices found for selected date range.</span>
                            <a href="invoice_form" class="text-xs font-bold text-amber-600 hover:underline">Click here to create your first tax invoice →</a>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-50/80 transition-all group">
                        <td class="px-6 py-4 font-mono font-extrabold text-blue-600">
                            <a href="invoice_view?id=<?=$r['id']?>" class="hover:underline"><?=e($r['invoice_number'])?></a>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-900">
                            <?=e($r['company_name'])?>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500">
                            <?=e(date('d M Y', strtotime($r['invoice_date'])))?>
                        </td>
                        <td class="px-6 py-4 text-xs font-semibold text-slate-500">
                            <?=e(date('d M Y', strtotime($r['valid_until'])))?>
                        </td>
                        <td class="px-6 py-4 text-right font-extrabold text-slate-900 font-mono">
                            <?=money((float)$r['total'], $r['currency'] ?: $activeTenant['currency'])?>
                        </td>
                        <td class="px-6 py-4 text-center">
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
                            $sClass = $statusClasses[$r['status']] ?? 'bg-slate-100 text-slate-800';
                            ?>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold <?=$sClass?>">
                                <?=strtoupper(e($r['status']))?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="invoice_view?id=<?=$r['id']?>" class="text-xs font-bold text-slate-600 hover:text-blue-600 bg-slate-100 hover:bg-blue-50 px-2.5 py-1.5 rounded-lg transition-all">View</a>
                            <a href="invoice_print?id=<?=$r['id']?>" target="_blank" class="text-xs font-bold text-slate-600 hover:text-emerald-600 bg-slate-100 hover:bg-emerald-50 px-2.5 py-1.5 rounded-lg transition-all">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Touch Cards View (Visible only on Mobile) -->
    <div class="sm:hidden divide-y divide-slate-100">
        <?php if (empty($rows)): ?>
            <div class="p-6 text-center text-slate-400">
                <i class="fa-solid fa-file-invoice text-3xl mb-2 text-slate-300 block"></i>
                <span class="font-bold text-slate-700 block text-xs">No invoices found.</span>
            </div>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <div class="p-4 hover:bg-slate-50 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <a href="invoice_view?id=<?=$r['id']?>" class="font-mono font-black text-blue-600 text-sm"><?=e($r['invoice_number'])?></a>
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
                    $sClass = $statusClasses[$r['status']] ?? 'bg-slate-100 text-slate-800';
                    ?>
                    <span class="px-2.5 py-0.5 rounded-full text-2xs font-black <?=$sClass?>"><?=strtoupper(e($r['status']))?></span>
                </div>
                <div class="flex justify-between items-end">
                    <div>
                        <div class="font-extrabold text-slate-900 text-sm"><?=e($r['company_name'])?></div>
                        <div class="text-2xs text-slate-400 font-semibold mt-0.5"><i class="fa-regular fa-clock mr-1"></i>Due: <?=e(date('d M Y', strtotime($r['valid_until'])))?></div>
                    </div>
                    <div class="text-right">
                        <div class="font-black text-slate-900 text-base font-mono"><?=money((float)$r['total'], $r['currency'] ?: $activeTenant['currency'])?></div>
                        <div class="mt-1 flex justify-end space-x-1.5">
                            <a href="invoice_view?id=<?=$r['id']?>" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-600 text-2xs font-extrabold rounded-lg">View</a>
                            <a href="invoice_print?id=<?=$r['id']?>" target="_blank" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-50 text-slate-700 hover:text-emerald-600 text-2xs font-extrabold rounded-lg">PDF</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Invoice Pagination Controls -->
    <?php if ($totalInvPages > 1): ?>
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs font-bold text-slate-600">
            <div>
                Showing <strong><?=min($invOffset + 1, $totalInvRecords)?></strong> to <strong><?=min($invOffset + $invPerPage, $totalInvRecords)?></strong> of <strong><?=$totalInvRecords?></strong> invoices
            </div>
            
            <div class="flex items-center space-x-1.5">
                <?php if ($invPage > 1): ?>
                    <a href="index?inv_page=<?=$invPage - 1?>&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?><?=!empty($invSearch) ? '&inv_search=' . urlencode($invSearch) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                        <i class="fa-solid fa-chevron-left mr-1"></i>Prev
                    </a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalInvPages; $p++): ?>
                    <a href="index?inv_page=<?=$p?>&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?><?=!empty($invSearch) ? '&inv_search=' . urlencode($invSearch) : ''?>" class="px-3 py-1.5 rounded-lg border <?= $p === $invPage ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100' ?>">
                        <?=$p?>
                    </a>
                <?php endfor; ?>

                <?php if ($invPage < $totalInvPages): ?>
                    <a href="index?inv_page=<?=$invPage + 1?>&start_date=<?=urlencode($startDate)?>&end_date=<?=urlencode($endDate)?><?=!empty($invSearch) ? '&inv_search=' . urlencode($invSearch) : ''?>" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-slate-700 shadow-xs">
                        Next<i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Chart 1: 6-Month Revenue vs Cash vs Expenses
    const el1 = document.getElementById('financialTrendChart');
    if (el1) {
        new Chart(el1.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?=json_encode($months)?>,
                datasets: [
                    {
                        label: 'Gross Revenue',
                        data: <?=json_encode($monthlyRevenue)?>,
                        backgroundColor: 'rgba(37, 99, 235, 0.85)',
                        borderColor: '#2563eb',
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: 'Cash Collected',
                        data: <?=json_encode($monthlyCollected)?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderColor: '#10b981',
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: 'Expenses',
                        data: <?=json_encode($monthlyExpenses)?>,
                        type: 'line',
                        borderColor: '#f43f5e',
                        backgroundColor: 'rgba(244, 63, 94, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'Inter', weight: 'bold', size: 10 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { family: 'Inter', size: 9 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', weight: 'bold', size: 10 } }
                    }
                }
            }
        });
    }

    // Chart 2: Invoice Status Mix (Doughnut)
    const el2 = document.getElementById('statusDistributionChart');
    if (el2) {
        new Chart(el2.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?=json_encode($statusLabels)?>,
                datasets: [{
                    data: <?=json_encode($statusCounts)?>,
                    backgroundColor: <?=json_encode($statusColorList)?>,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { font: { family: 'Inter', weight: 'bold', size: 9 }, boxWidth: 10 }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // Chart 3: Top 5 Clients by Revenue (Horizontal Bar)
    const el3 = document.getElementById('topClientsChart');
    if (el3) {
        new Chart(el3.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?=json_encode($topClientNames)?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?=json_encode($topClientRevenues)?>,
                    backgroundColor: 'rgba(245, 158, 11, 0.85)',
                    borderColor: '#f59e0b',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { family: 'Inter', size: 9 } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', weight: 'bold', size: 9 } }
                    }
                }
            }
        });
    }

    // Chart 4: Expense Categories (Doughnut)
    const el4 = document.getElementById('expenseCategoryChart');
    if (el4) {
        new Chart(el4.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?=json_encode($expCatLabels)?>,
                datasets: [{
                    data: <?=json_encode($expCatTotals)?>,
                    backgroundColor: ['#f43f5e', '#8b5cf6', '#ec4899', '#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { font: { family: 'Inter', weight: 'bold', size: 9 }, boxWidth: 10 }
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>

<?php page_end(); ?>
