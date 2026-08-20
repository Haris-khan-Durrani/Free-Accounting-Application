<?php
function page_start(string $title): void {
    $flash = get_flash();
    $brand = branding();
    $activeTenant = tenant();
    $primaryColor = $brand['primary_color'] ?? '#0f172a';
    $secondaryColor = $brand['secondary_color'] ?? '#2563eb';
    $accentColor = $brand['accent_color'] ?? '#d97706';

    // Trial Expiry Check Logic
    $trialEnds = $activeTenant['trial_ends_at'] ?: date('Y-m-d');
    $daysLeftInTrial = (int)floor((strtotime($trialEnds) - time()) / 86400);
    $isTrialExpired = ($activeTenant['subscription_status'] === 'trial' && $daysLeftInTrial < 0);
    $isTrialActive = ($activeTenant['subscription_status'] === 'trial' && $daysLeftInTrial >= 0);

    echo '<!doctype html><html lang="en" class="h-full bg-slate-100"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">';
    echo '<title>' . e($title) . ' - ' . e($brand['company_name']) . ' SaaS</title>';
    
    // Tailwind CSS, FontAwesome 6, and GSAP
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brandPrimary: "' . e($primaryColor) . '",
                        brandSecondary: "' . e($secondaryColor) . '",
                        brandAccent: "' . e($accentColor) . '"
                    }
                }
            }
        }
    </script>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    echo '<link rel="stylesheet" href="assets/css/style.css">';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>';
    
    echo '</head><body class="h-full font-sans antialiased text-slate-900 bg-slate-100 flex flex-col min-h-screen pb-28 lg:pb-0">';
    
    // Trial Status Banner Bar
    if (!empty($_SESSION['user_id'])) {
        if ($isTrialExpired) {
            echo '<div class="no-print bg-gradient-to-r from-rose-600 to-rose-700 text-white px-4 py-2 text-xs font-bold text-center shadow-md flex items-center justify-center space-x-2 z-50 sticky top-0">';
            echo '<i class="fa-solid fa-triangle-exclamation text-amber-300 text-sm"></i>';
            echo '<span>Your Free Trial for <strong>' . e($activeTenant['name']) . '</strong> has expired. Upgrade your subscription plan to continue full invoicing features.</span>';
            echo '<a href="billing" class="ml-2 bg-white text-rose-700 px-3 py-0.5 rounded-full text-2xs font-extrabold hover:bg-rose-50 shadow-xs">Upgrade Plan →</a>';
            echo '</div>';
        } elseif ($isTrialActive && $daysLeftInTrial <= 30) {
            echo '<div class="no-print bg-gradient-to-r from-amber-600 to-amber-700 text-white px-4 py-1.5 text-2xs sm:text-xs font-bold text-center shadow-xs flex items-center justify-center space-x-2 z-50 sticky top-0">';
            echo '<i class="fa-solid fa-clock text-amber-200"></i>';
            echo '<span>Free Trial Active: <strong>' . $daysLeftInTrial . ' Days Remaining</strong> (Expires on ' . date('d M Y', strtotime($trialEnds)) . ').</span>';
            echo '<a href="billing" class="ml-2 underline hover:text-amber-200">View SaaS Plans</a>';
            echo '</div>';
        }
    }    // Header Bar (Ultra-Sleek Glassmorphism Topbar)
    $currScript = basename($_SERVER['SCRIPT_NAME']);
    $activeClass = fn($pages) => in_array($currScript, (array)$pages) 
        ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30 font-black' 
        : 'text-slate-300 hover:text-white hover:bg-slate-800/70 font-semibold';

    echo '<header class="no-print bg-slate-950/95 backdrop-blur-xl text-white sticky top-0 z-40 border-b border-slate-800/80 shadow-2xl">';
    echo '<div class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">';
    
    // Left: Brand Logo & Workspace Switcher
    echo '<div class="flex items-center space-x-3 sm:space-x-4 flex-shrink-0 mr-4 lg:mr-8">';
    echo '<a href="index" class="flex items-center space-x-2.5 group flex-shrink-0">';
    if (!empty($brand['logo_url'])) {
        echo '<img src="' . e($brand['logo_url']) . '" alt="' . e($brand['company_name']) . '" class="h-8 w-auto rounded bg-white p-0.5 object-contain shadow-xs">';
        echo '<span class="hidden md:inline-block font-extrabold text-sm sm:text-base tracking-tight text-white group-hover:text-amber-400 transition-colors whitespace-nowrap">' . e($brand['company_name']) . '</span>';
    } else {
        echo '<div class="h-7 w-7 rounded-lg bg-gradient-to-tr from-amber-500 to-amber-600 flex items-center justify-center font-black text-slate-950 text-xs shadow-sm"><i class="fa-solid fa-bolt"></i></div>';
        echo '<span class="font-extrabold text-sm sm:text-base tracking-tight text-white group-hover:text-amber-400 transition-colors whitespace-nowrap">' . e($brand['company_name']) . '</span>';
    }
    echo '</a>';

    if (!empty($_SESSION['user_id'])) {
        echo '<div class="h-4 w-px bg-slate-800 hidden md:block"></div>';
        
        $myTenants = \Core\Tenant::getUserTenants($GLOBALS['pdo'], (int)$_SESSION['user_id']);
        // Desktop Workspace Switcher Pill (hidden on mobile, managed inside mobile menu)
        echo '<div class="hidden md:flex items-center">';
        if (count($myTenants) > 1) {
            echo '<div class="relative group py-2">';
            echo '<button class="inline-flex items-center space-x-1.5 bg-slate-900/90 hover:bg-slate-800 text-slate-300 hover:text-white px-2.5 py-1 rounded-xl text-xs font-bold border border-slate-800/90 transition-all shadow-xs">';
            echo '<i class="fa-solid fa-building text-amber-400 text-3xs"></i>';
            echo '<span class="truncate max-w-[130px]">' . e($activeTenant['name']) . '</span>';
            echo '<i class="fa-solid fa-chevron-down text-[9px] text-slate-500"></i>';
            echo '</button>';
            echo '<div class="absolute left-0 top-full pt-1 w-56 hidden group-hover:block z-50">';
            echo '<div class="bg-white rounded-xl shadow-2xl border border-slate-200 py-1 text-left text-xs font-semibold overflow-hidden">';
            echo '<div class="px-3 py-1.5 text-[10px] font-black uppercase text-slate-400 border-b border-slate-100">Switch Workspace</div>';
            foreach ($myTenants as $mt) {
                $isCurr = ($mt['id'] == $activeTenant['id']);
                echo '<a href="subaccounts?switch=' . $mt['id'] . '" class="flex items-center justify-between px-3 py-2 text-slate-700 hover:bg-slate-50 hover:text-amber-600 transition-colors ' . ($isCurr ? 'font-black text-amber-600 bg-amber-50/50' : '') . '">';
                echo '<span class="truncate">' . e($mt['name']) . '</span>';
                if ($isCurr) {
                    echo '<i class="fa-solid fa-check text-amber-500 text-2xs"></i>';
                }
                echo '</a>';
            }
            echo '<a href="subaccounts" class="flex items-center space-x-1 px-3 py-2 text-slate-500 hover:text-slate-900 border-t border-slate-100 text-[11px] font-bold bg-slate-50/80"><i class="fa-solid fa-sitemap mr-1"></i>Manage Workspaces</a>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<a href="subaccounts" class="inline-flex items-center space-x-1.5 bg-slate-900/90 hover:bg-slate-800 text-slate-300 hover:text-white px-2.5 py-1 rounded-xl text-xs font-bold border border-slate-800/90 transition-all shadow-xs">';
            echo '<i class="fa-solid fa-building text-amber-400 text-3xs"></i>';
            echo '<span class="truncate max-w-[130px]">' . e($activeTenant['name']) . '</span>';
            echo '<i class="fa-solid fa-chevron-down text-[9px] text-slate-500"></i>';
            echo '</a>';
        }
        echo '</div>';
    }
    echo '</div>';

    // Right: Desktop Navigation Links
    if (!empty($_SESSION['user_id'])) {
        echo '<nav class="hidden xl:flex items-center space-x-1 sm:space-x-2 whitespace-nowrap flex-shrink-0">';
        echo '<a href="index" class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl text-xs whitespace-nowrap transition-all ' . $activeClass(['index.php', 'index']) . '"><i class="fa-solid fa-chart-pie text-blue-400 text-2xs"></i><span>Dashboard</span></a>';
        echo '<a href="invoices" class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl text-xs whitespace-nowrap transition-all ' . $activeClass(['invoices.php', 'invoices', 'invoice_view.php']) . '"><i class="fa-solid fa-file-invoice text-amber-400 text-2xs"></i><span>Invoices</span></a>';
        echo '<a href="clients" class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl text-xs whitespace-nowrap transition-all ' . $activeClass(['clients.php', 'clients', 'client_import.php']) . '"><i class="fa-solid fa-users text-emerald-400 text-2xs"></i><span>Clients</span></a>';
        echo '<a href="quotes" class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl text-xs whitespace-nowrap transition-all ' . $activeClass(['quotes.php', 'quotes', 'quote_view.php']) . '"><i class="fa-solid fa-file-signature text-sky-400 text-2xs"></i><span>Proposals</span></a>';
        echo '<a href="expenses" class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl text-xs whitespace-nowrap transition-all ' . $activeClass(['expenses.php', 'expenses', 'expense_form.php']) . '"><i class="fa-solid fa-receipt text-rose-400 text-2xs"></i><span>Expenses</span></a>';
        
        // Reports Dropdown Menu
        $isReportsActive = str_contains($currScript, 'reports_') || in_array($currScript, ['export_faf.php', 'accounts.php', 'journal.php']);
        echo '<div class="relative group py-2">';
        echo '<button class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl text-xs whitespace-nowrap transition-all ' . ($isReportsActive ? 'bg-purple-500/10 text-purple-300 border border-purple-500/30 font-black' : 'text-slate-300 hover:text-white hover:bg-slate-800/70 font-semibold') . '"><i class="fa-solid fa-folder-open text-purple-400 text-2xs"></i><span>Reports & Ledger</span><i class="fa-solid fa-chevron-down text-[9px] ml-0.5 opacity-70"></i></button>';
        echo '<div class="absolute right-0 top-full pt-1 w-72 hidden group-hover:block z-50">';
        echo '<div class="bg-white rounded-2xl shadow-2xl border border-slate-200/90 overflow-hidden py-1 max-h-[85vh] overflow-y-auto">';
        
        // Category 1: General Ledger & Accounting
        echo '<div class="bg-indigo-50/90 border-b border-indigo-100 px-4 py-2 flex items-center space-x-2">';
        echo '<i class="fa-solid fa-book-bookmark text-indigo-600 text-xs"></i>';
        echo '<span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-900">General Ledger & Accounting</span>';
        echo '</div>';
        echo '<div class="py-1">';
        echo '<a href="accounts" class="flex items-center px-4 py-2 text-xs font-bold text-slate-900 hover:bg-indigo-50/60 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-list-check w-6 text-indigo-500 text-center"></i><span>Chart of Accounts (COA)</span></a>';
        echo '<a href="journal" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-cyan-50/60 hover:text-cyan-600 transition-colors"><i class="fa-solid fa-book w-6 text-cyan-500 text-center"></i><span>General Ledger & Journal</span></a>';
        echo '</div>';

        // Category 2: Financial Statements
        echo '<div class="bg-slate-50/90 border-y border-slate-100 px-4 py-2 flex items-center space-x-2 mt-1">';
        echo '<i class="fa-solid fa-chart-line text-slate-400 text-xs"></i>';
        echo '<span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Financial Statements</span>';
        echo '</div>';
        echo '<div class="py-1">';
        echo '<a href="reports_pnl" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-blue-50/60 hover:text-blue-600 transition-colors"><i class="fa-solid fa-chart-line w-6 text-blue-500 text-center"></i><span>Profit & Loss (P&L)</span></a>';
        echo '<a href="reports_balance_sheet" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-emerald-50/60 hover:text-emerald-600 transition-colors"><i class="fa-solid fa-scale-balanced w-6 text-emerald-500 text-center"></i><span>Balance Sheet</span></a>';
        echo '<a href="reports_cashflow" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-amber-50/60 hover:text-amber-600 transition-colors"><i class="fa-solid fa-money-bill-transfer w-6 text-amber-500 text-center"></i><span>Cash Flow Statement</span></a>';
        echo '<a href="reports_aging" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-purple-50/60 hover:text-purple-600 transition-colors"><i class="fa-solid fa-clock w-6 text-purple-500 text-center"></i><span>AR Aging Report</span></a>';
        echo '</div>';

        // Category 3: UAE Tax & VAT Compliance
        echo '<div class="bg-emerald-50/90 border-y border-emerald-100 px-4 py-2 flex items-center justify-between mt-1">';
        echo '<div class="flex items-center space-x-2"><i class="fa-solid fa-building-columns text-emerald-600 text-xs"></i><span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">UAE Tax Compliance</span></div>';
        echo '<span class="text-[9px] font-black bg-emerald-600 text-white px-1.5 py-0.5 rounded">FTA 201</span>';
        echo '</div>';
        echo '<div class="py-1">';
        echo '<a href="reports_vat201" class="flex items-center px-4 py-2 text-xs font-bold text-slate-900 hover:bg-emerald-50/60 hover:text-emerald-700 transition-colors"><i class="fa-solid fa-file-invoice-dollar w-6 text-emerald-600 text-center"></i><span>UAE FTA VAT 201 Declaration</span></a>';
        echo '<a href="reports_corporate_tax" class="flex items-center px-4 py-2 text-xs font-bold text-slate-900 hover:bg-blue-50/60 hover:text-blue-700 transition-colors"><i class="fa-solid fa-percent w-6 text-blue-600 text-center"></i><span>UAE Corporate Tax (9%)</span></a>';
        echo '<a href="export_faf" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-amber-50/60 hover:text-amber-700 transition-colors"><i class="fa-solid fa-file-export w-6 text-amber-600 text-center"></i><span>Export FTA Audit File (.faf)</span></a>';
        echo '<a href="reports_tax" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-slate-900 transition-colors"><i class="fa-solid fa-calculator w-6 text-slate-400 text-center"></i><span>General VAT Summary</span></a>';
        echo '</div>';

        // Category 4: Analytics & Audit
        echo '<div class="bg-slate-50/90 border-y border-slate-100 px-4 py-2 flex items-center space-x-2 mt-1">';
        echo '<i class="fa-solid fa-chart-pie text-slate-400 text-xs"></i>';
        echo '<span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Business Analytics</span>';
        echo '</div>';
        echo '<div class="py-1">';
        echo '<a href="reports_client_sales" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-blue-50/60 hover:text-blue-600 transition-colors"><i class="fa-solid fa-user-tag w-6 text-blue-500 text-center"></i><span>Client Revenue Analysis</span></a>';
        echo '<a href="reports_expense_category" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-rose-50/60 hover:text-rose-600 transition-colors"><i class="fa-solid fa-pie-chart w-6 text-rose-500 text-center"></i><span>Expense Breakdown</span></a>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<a href="settings" class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl text-xs whitespace-nowrap transition-all ' . $activeClass(['settings.php', 'settings', 'branding.php', 'payment_settings.php']) . '"><i class="fa-solid fa-sliders text-amber-400 text-2xs"></i><span>Settings</span></a>';

        // Management Dropdown Menu (Balanced 2-Column Mega Menu)
        echo '<div class="relative group py-2">';
        echo '<button class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl text-xs whitespace-nowrap font-semibold text-slate-300 hover:text-white hover:bg-slate-800/70 transition-all"><i class="fa-solid fa-gear text-cyan-400 text-2xs"></i><span>Management</span><i class="fa-solid fa-chevron-down text-[9px] ml-0.5 opacity-70"></i></button>';
        echo '<div class="absolute right-0 top-full pt-1 w-[580px] hidden group-hover:block z-[100]">';
        echo '<div class="bg-white rounded-2xl shadow-2xl border border-slate-200 p-4 grid grid-cols-2 gap-4 max-h-[85vh] overflow-y-auto text-left">';
        
        // Left Column
        echo '<div class="space-y-3.5">';
        
        // Group 1: Workspaces & Branding
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-1 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-building text-slate-400 text-3xs"></i><span>Workspaces & Branding</span></div>';
        echo '<a href="settings" class="flex items-center px-2.5 py-1.5 text-xs font-extrabold text-amber-900 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-sliders w-5 text-amber-500 text-center"></i><span>Master Settings Hub</span></a>';
        echo '<a href="subaccounts" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-blue-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-sitemap w-5 text-blue-500 text-center"></i><span>Workspaces & Branches</span></a>';
        echo '<a href="branding" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-palette w-5 text-amber-500 text-center"></i><span>Branding & Logo Setup</span></a>';
        echo '</div>';

        // Group 2: Invoice Design & Templates
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-1 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-wand-magic-sparkles text-slate-400 text-3xs"></i><span>Invoice Design & Templates</span></div>';
        echo '<a href="invoice_customize" class="flex items-center px-2.5 py-1.5 text-xs font-extrabold text-amber-900 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-paint-roller w-5 text-amber-500 text-center"></i><span>Template Selector (11 Designs)</span></a>';
        echo '<a href="invoice_builder" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-layer-group w-5 text-amber-500 text-center"></i><span>Drag & Drop Builder</span></a>';
        echo '</div>';

        // Group 3: Accounting & Catalog
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-1 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-book-bookmark text-slate-400 text-3xs"></i><span>Accounting & Catalog</span></div>';
        echo '<a href="accounts" class="flex items-center px-2.5 py-1.5 text-xs font-extrabold text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-list-check w-5 text-indigo-600 text-center"></i><span>Chart of Accounts (COA)</span></a>';
        echo '<a href="journal" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-cyan-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-book w-5 text-cyan-500 text-center"></i><span>General Ledger & Journal</span></a>';
        echo '<a href="items" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-slate-900 hover:bg-emerald-50/70 hover:text-emerald-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-box w-5 text-emerald-500 text-center"></i><span>Product Catalog</span></a>';
        echo '<a href="expense_categories" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-slate-900 hover:bg-rose-50/70 hover:text-rose-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-tags w-5 text-rose-500 text-center"></i><span>Expense Categories</span></a>';
        echo '<a href="backup_admin" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-cyan-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-database w-5 text-cyan-500 text-center"></i><span>SQL Database Backup</span></a>';
        echo '</div>';

        echo '</div>';

        // Right Column
        echo '<div class="space-y-3.5">';

        // Group 4: SaaS Subscription & Plan
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-1 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-crown text-slate-400 text-3xs"></i><span>SaaS Subscription & Plan</span></div>';
        echo '<a href="billing" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-credit-card w-5 text-amber-500 text-center"></i><span>Subscription Plan</span></a>';
        echo '<a href="recurring_invoices" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-purple-900 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-rotate w-5 text-purple-600 text-center"></i><span>Auto-Subscription Billing</span></a>';
        if (has_role(['owner', 'admin'])) {
            echo '<a href="users" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-blue-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-users-gear w-5 text-blue-500 text-center"></i><span>Team Members</span></a>';
        }
        echo '</div>';

        // Group 5: Security & Integrations
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-1 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-shield-halved text-slate-400 text-3xs"></i><span>Security & Integrations</span></div>';
        if (has_role(['owner', 'admin'])) {
            echo '<a href="payment_settings" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-slate-900 hover:bg-purple-50/70 hover:text-purple-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-credit-card w-5 text-purple-500 text-center"></i><span>Payment Gateways</span></a>';
            echo '<a href="whatsapp_settings" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-emerald-50/70 hover:text-emerald-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-brands fa-whatsapp w-5 text-emerald-500 text-center"></i><span>WhatsApp API</span></a>';
            echo '<a href="email_settings" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-server w-5 text-amber-500 text-center"></i><span>Custom SMTP</span></a>';
        }
        echo '<a href="security" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-indigo-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-shield-halved w-5 text-indigo-500 text-center"></i><span>2FA & Security</span></a>';
        echo '<a href="api_keys" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-key w-5 text-amber-500 text-center"></i><span>API Key Manager</span></a>';
        echo '<a href="automation" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-purple-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-diagram-project w-5 text-purple-500 text-center"></i><span>n8n Automations</span></a>';
        echo '</div>';

        // Group 6: SaaS Admin & Extensions
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-1 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-crown text-amber-500 text-3xs"></i><span>SaaS Admin & Extensions</span></div>';
        if (has_role(['owner']) && tenant_id() === 1) {
            echo '<a href="tenants_admin" class="flex items-center px-2.5 py-1.5 text-xs font-extrabold text-purple-900 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-building-user w-5 text-purple-600 text-center"></i><span>SaaS Tenant Workspaces (+New)</span></a>';
            echo '<a href="subscriptions_admin" class="flex items-center px-2.5 py-1.5 text-xs font-extrabold text-amber-900 bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-layer-group w-5 text-amber-600 text-center"></i><span>Create SaaS Plan Tiers</span></a>';
            echo '<a href="plugins_admin" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-purple-900 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-puzzle-piece w-5 text-purple-600 text-center"></i><span>Plug & Play Extensions</span></a>';
            echo '<a href="super_admin_gateways" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-indigo-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-credit-card w-5 text-indigo-500 text-center"></i><span>Super Admin Gateways</span></a>';
        }
        echo '<a href="guide" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-blue-600 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-book-open w-5 text-blue-500 text-center"></i><span>Interactive User Guide</span></a>';
        if (has_role(['owner', 'admin'])) {
            echo '<a href="audit_log" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-slate-900 rounded-lg transition-colors cursor-pointer relative z-10"><i class="fa-solid fa-clock-rotate-left w-5 text-slate-400 text-center"></i><span>System Audit Log</span></a>';
        }
        
        // Execute Plugin Menu Hooks
        \Services\PluginEngine::do_action('management_menu_items');
        echo '</div>'; // Close Group 6

        echo '</div>'; // Close Right Column
        echo '</div>'; // Close Grid
        echo '</div>'; // Close Dropdown
        echo '</div>'; // Close Relative Container


        // Action Button: Create New Invoice
        echo '<a href="invoice_form" class="ml-1.5 whitespace-nowrap flex-shrink-0 inline-flex items-center px-3 py-1.5 border border-amber-400/40 text-xs font-black rounded-xl shadow-md text-slate-950 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 transition-all transform hover:-translate-y-0.5">';
        echo '<i class="fa-solid fa-plus mr-1 text-2xs"></i>New Invoice';
        echo '</a>';

        echo '<a href="logout" class="ml-1 text-slate-400 hover:text-rose-400 p-1.5 text-xs font-bold transition-colors whitespace-nowrap flex-shrink-0" title="Logout"><i class="fa-solid fa-right-from-bracket text-sm"></i></a>';
        echo '</nav>';

        // Mobile Menu Button
        echo '<button onclick="toggleMobileAppMenu()" class="xl:hidden text-amber-400 hover:text-amber-300 p-2 text-xl focus:outline-none"><i class="fa-solid fa-bars-staggered"></i></button>';
    }

    echo '</div>';
    echo '</header>';

    // Mobile App Navigation Dock (Ultra-Sleek Inline Design with Active Page Glow & Spring Micro-Animations)
    if (!empty($_SESSION['user_id'])) {
        echo '<nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-950/95 backdrop-blur-2xl border-t border-slate-800/80 px-2 py-1.5 flex items-center justify-around shadow-2xl">';
        
        $mItem = function($url, $icon, $label, $matches = []) use ($currScript) {
            $isActive = in_array($currScript, $matches);
            $bgStyle = $isActive 
                ? 'bg-amber-500/15 text-amber-400 font-extrabold border border-amber-500/30 shadow-xs' 
                : 'text-slate-400 hover:text-slate-200 font-semibold border border-transparent';
            $iconAnim = $isActive ? 'scale-110 text-amber-400' : 'group-hover:scale-110 group-active:scale-95';
            
            echo '<a href="' . $url . '" class="flex flex-col items-center justify-center py-1.5 px-2 rounded-2xl group transition-all duration-200 ' . $bgStyle . ' active:scale-95">';
            echo '<i class="' . $icon . ' text-base mb-0.5 transform transition-transform duration-200 ' . $iconAnim . '"></i>';
            echo '<span class="text-[10px] tracking-tight whitespace-nowrap">' . $label . '</span>';
            echo '</a>';
        };

        // Home / Dashboard
        $mItem('index', 'fa-solid fa-chart-pie', 'Home', ['index.php', 'index']);

        // Invoices List
        $mItem('invoices', 'fa-solid fa-file-invoice', 'Invoices', ['invoices.php', 'invoices', 'invoice_view.php']);

        // Inline + Invoice Action Button (Sleek Inline Glow Badge, zero awkward protrusion)
        $isAddInvoice = in_array($currScript, ['invoice_form.php', 'invoice_form']);
        echo '<a href="invoice_form" class="flex flex-col items-center justify-center py-1.5 px-2.5 rounded-2xl group transition-all duration-200 active:scale-90 ' . ($isAddInvoice ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-black shadow-lg ring-2 ring-amber-400/40' : 'bg-slate-900 border border-amber-500/40 text-amber-400 hover:bg-slate-800 shadow-xs') . '">';
        echo '<i class="fa-solid fa-circle-plus text-base mb-0.5 transform group-hover:rotate-90 transition-transform duration-300"></i>';
        echo '<span class="text-[10px] font-extrabold tracking-tight">+ Invoice</span>';
        echo '</a>';

        // Clients
        $mItem('clients', 'fa-solid fa-users', 'Clients', ['clients.php', 'clients']);

        // Proposals / Quotes
        $mItem('quotes', 'fa-solid fa-file-signature', 'Quotes', ['quotes.php', 'quotes']);

        // Menu Button
        echo '<button onclick="toggleMobileAppMenu()" class="flex flex-col items-center justify-center py-1.5 px-2 rounded-2xl text-slate-400 hover:text-slate-200 group transition-all duration-200 active:scale-95 focus:outline-none">';
        echo '<i class="fa-solid fa-bars-staggered text-base mb-0.5 transform group-hover:scale-110 transition-transform duration-200"></i>';
        echo '<span class="text-[10px] font-semibold tracking-tight">Menu</span>';
        echo '</button>';

        echo '</nav>';
    }

    // Full-Screen Mobile Glassmorphism App Launcher Modal
    if (!empty($_SESSION['user_id'])) {
        echo '<div id="mobile-app-modal" class="fixed inset-0 bg-slate-950/95 backdrop-blur-2xl z-[150] hidden p-6 overflow-y-auto flex flex-col justify-between">';
        echo '<div>';
        
        echo '<div class="flex items-center justify-between border-b border-slate-800/80 pb-4 mb-6">';
        echo '<div class="flex items-center space-x-3">';
        echo '<div class="h-10 w-10 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 font-bold text-lg"><i class="fa-solid fa-bolt"></i></div>';
        echo '<div><h3 class="text-base font-extrabold text-white">' . e($brand['company_name']) . ' App</h3><p class="text-2xs text-slate-400">' . e($activeTenant['name']) . '</p></div>';
        echo '</div>';
        echo '<button onclick="toggleMobileAppMenu()" class="h-9 w-9 rounded-full bg-slate-800 text-slate-300 hover:text-white flex items-center justify-center font-bold text-lg">×</button>';
        echo '</div>';

        // Mobile Workspace Switcher Section
        $mobileTenants = \Core\Tenant::getUserTenants($GLOBALS['pdo'], (int)$_SESSION['user_id']);
        if (count($mobileTenants) > 0) {
            echo '<div class="mb-6 bg-slate-900/80 border border-slate-800 rounded-2xl p-3.5">';
            echo '<div class="text-2xs font-extrabold uppercase tracking-wider text-slate-400 mb-2 flex items-center justify-between">';
            echo '<span><i class="fa-solid fa-building text-amber-400 mr-1.5"></i>Active Workspace</span>';
            echo '<a href="subaccounts" onclick="toggleMobileAppMenu()" class="text-3xs text-amber-400 font-bold hover:underline">Manage (+New)</a>';
            echo '</div>';
            echo '<div class="space-y-1.5 max-h-36 overflow-y-auto">';
            foreach ($mobileTenants as $mt) {
                $isCurr = ($mt['id'] == $activeTenant['id']);
                echo '<a href="subaccounts?switch=' . $mt['id'] . '" class="flex items-center justify-between p-2.5 rounded-xl border text-xs font-bold transition-all ' . ($isCurr ? 'bg-amber-500/10 border-amber-500/40 text-amber-400' : 'bg-slate-950/60 border-slate-800 text-slate-300 hover:text-white hover:bg-slate-800') . '">';
                echo '<div class="flex items-center space-x-2 truncate"><i class="fa-solid fa-location-dot ' . ($isCurr ? 'text-amber-400' : 'text-slate-500') . '"></i><span class="truncate">' . e($mt['name']) . '</span></div>';
                if ($isCurr) {
                    echo '<span class="text-3xs font-extrabold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 uppercase">Active</span>';
                } else {
                    echo '<span class="text-3xs font-semibold text-slate-400">Switch &rarr;</span>';
                }
                echo '</a>';
            }
            echo '</div>';
            echo '</div>';
        }

        // Grid 1: Core Apps
        echo '<div class="mb-6">';
        echo '<h4 class="text-2xs font-bold text-amber-400 uppercase tracking-wider mb-3">Core Apps</h4>';
        echo '<div class="grid grid-cols-3 gap-3">';
        echo '<a href="index" onclick="toggleMobileAppMenu()" class="bg-slate-900/90 border border-slate-800 p-3.5 rounded-2xl flex flex-col items-center text-center hover:bg-slate-800 transition-all"><i class="fa-solid fa-chart-pie text-blue-400 text-xl mb-1.5"></i><span class="text-xs font-bold text-slate-200">Dashboard</span></a>';
        echo '<a href="clients" onclick="toggleMobileAppMenu()" class="bg-slate-900/90 border border-slate-800 p-3.5 rounded-2xl flex flex-col items-center text-center hover:bg-slate-800 transition-all"><i class="fa-solid fa-users text-emerald-400 text-xl mb-1.5"></i><span class="text-xs font-bold text-slate-200">Clients</span></a>';
        echo '<a href="quotes" onclick="toggleMobileAppMenu()" class="bg-slate-900/90 border border-slate-800 p-3.5 rounded-2xl flex flex-col items-center text-center hover:bg-slate-800 transition-all"><i class="fa-solid fa-file-signature text-amber-400 text-xl mb-1.5"></i><span class="text-xs font-bold text-slate-200">Proposals</span></a>';
        echo '<a href="expenses" onclick="toggleMobileAppMenu()" class="bg-slate-900/90 border border-slate-800 p-3.5 rounded-2xl flex flex-col items-center text-center hover:bg-slate-800 transition-all"><i class="fa-solid fa-receipt text-rose-400 text-xl mb-1.5"></i><span class="text-xs font-bold text-slate-200">Expenses</span></a>';
        echo '<a href="invoice_form" onclick="toggleMobileAppMenu()" class="bg-amber-500/20 border border-amber-500/40 p-3.5 rounded-2xl flex flex-col items-center text-center hover:bg-amber-500/30 transition-all"><i class="fa-solid fa-plus text-amber-400 text-xl mb-1.5"></i><span class="text-xs font-extrabold text-amber-300">+ Invoice</span></a>';
        echo '<a href="subaccounts" onclick="toggleMobileAppMenu()" class="bg-slate-900/90 border border-slate-800 p-3.5 rounded-2xl flex flex-col items-center text-center hover:bg-slate-800 transition-all"><i class="fa-solid fa-building text-purple-400 text-xl mb-1.5"></i><span class="text-xs font-bold text-slate-200">Workspaces</span></a>';
        echo '</div>';
        echo '</div>';

        // Grid 2: Security & Email Setup
        echo '<div class="mb-6">';
        echo '<h4 class="text-2xs font-bold text-amber-400 uppercase tracking-wider mb-3">User Guide & Security</h4>';
        echo '<div class="grid grid-cols-2 gap-3 text-xs font-semibold">';
        echo '<a href="guide" onclick="toggleMobileAppMenu()" class="bg-amber-500/20 border border-amber-500/40 p-3 rounded-xl flex items-center space-x-2.5 text-amber-300 font-bold"><i class="fa-solid fa-book-open"></i><span>User Guide</span></a>';
        echo '<a href="email_settings" onclick="toggleMobileAppMenu()" class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl flex items-center space-x-2.5 text-slate-200"><i class="fa-solid fa-server text-amber-400"></i><span>Custom SMTP</span></a>';
        echo '<a href="security" onclick="toggleMobileAppMenu()" class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl flex items-center space-x-2.5 text-slate-200"><i class="fa-solid fa-shield-halved text-indigo-400"></i><span>2FA Security</span></a>';
        echo '<a href="api_playground" onclick="toggleMobileAppMenu()" class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl flex items-center space-x-2.5 text-slate-200"><i class="fa-solid fa-code text-emerald-400"></i><span>API Portal</span></a>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        // Bottom User Footer
        echo '<div class="pt-4 border-t border-slate-800 flex justify-between items-center">';
        $sessionRole = strtoupper($_SESSION['user_role'] ?? 'user');
        $sessionName = e($_SESSION['user_name'] ?? 'User');
        echo '<span class="text-xs text-slate-400">Signed in as <strong>' . $sessionName . '</strong> &bull; <span class="text-amber-400 font-bold">' . e($sessionRole) . '</span></span>';
        echo '<a href="logout" class="px-4 py-2 bg-rose-500/20 text-rose-400 font-bold text-xs rounded-xl border border-rose-500/30"><i class="fa-solid fa-right-from-bracket mr-1.5"></i>Logout</a>';
        echo '</div>';

        echo '</div>';
    }

    // Flash Notification Alert
    echo '<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 flex-grow w-full">';
    if ($flash) {
        $bgColor = $flash['type'] === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800';
        $icon = $flash['type'] === 'error' ? 'fa-triangle-exclamation text-rose-500' : 'fa-circle-check text-emerald-500';
        echo '<div class="no-print ' . $bgColor . ' border rounded-xl p-4 mb-6 flex items-center shadow-sm">';
        echo '<i class="fa-solid ' . $icon . ' text-xl mr-3"></i>';
        echo '<span class="font-medium text-sm">' . e($flash['message']) . '</span>';
        echo '</div>';
    }
}

function page_end(): void { 
    echo '</main>';
    echo '<footer class="no-print bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500 mt-auto hidden lg:block">';
    echo '<div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">';
    echo '<div>&copy; ' . date('Y') . ' <strong>' . e(branding()['company_name']) . '</strong>. Enterprise Multi-Tenant Accounting Suite.</div>';
    echo '<div class="flex space-x-4"><a href="guide" class="hover:underline">User Guide</a><a href="public_invoice" class="hover:underline">Client Portal</a><a href="email_settings" class="hover:underline">Custom SMTP</a><a href="security" class="hover:underline">2FA Security</a></div>';
    echo '</div>';
    echo '</footer>';

    echo '<script>
        function toggleMobileAppMenu() {
            const modal = document.getElementById("mobile-app-modal");
            if (modal.classList.contains("hidden")) {
                modal.classList.remove("hidden");
                document.body.style.overflow = "hidden";
            } else {
                modal.classList.add("hidden");
                document.body.style.overflow = "auto";
            }
        }
        document.addEventListener("DOMContentLoaded", () => {
            if (typeof gsap !== "undefined") {
                gsap.from(".stat-card", { opacity: 0, y: 15, duration: 0.4, stagger: 0.06, ease: "power2.out" });
                gsap.from(".card-animate", { opacity: 0, y: 15, duration: 0.5, delay: 0.1, ease: "power2.out" });
            }
        });
    </script>';
    echo '</body></html>';
}
