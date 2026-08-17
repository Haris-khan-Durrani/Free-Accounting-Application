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
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>';
    
    echo '</head><body class="h-full font-sans antialiased text-slate-900 bg-slate-100 flex flex-col min-h-screen pb-20 lg:pb-0">';
    
    // Trial Status Banner Bar
    if (!empty($_SESSION['user_id'])) {
        if ($isTrialExpired) {
            echo '<div class="bg-gradient-to-r from-rose-600 to-rose-700 text-white px-4 py-2 text-xs font-bold text-center shadow-md flex items-center justify-center space-x-2 z-50 sticky top-0">';
            echo '<i class="fa-solid fa-triangle-exclamation text-amber-300 text-sm"></i>';
            echo '<span>Your Free Trial for <strong>' . e($activeTenant['name']) . '</strong> has expired. Upgrade your subscription plan to continue full invoicing features.</span>';
            echo '<a href="billing" class="ml-2 bg-white text-rose-700 px-3 py-0.5 rounded-full text-2xs font-extrabold hover:bg-rose-50 shadow-xs">Upgrade Plan →</a>';
            echo '</div>';
        } elseif ($isTrialActive && $daysLeftInTrial <= 30) {
            echo '<div class="bg-gradient-to-r from-amber-600 to-amber-700 text-white px-4 py-1.5 text-2xs sm:text-xs font-bold text-center shadow-xs flex items-center justify-center space-x-2 z-50 sticky top-0">';
            echo '<i class="fa-solid fa-clock text-amber-200"></i>';
            echo '<span>Free Trial Active: <strong>' . $daysLeftInTrial . ' Days Remaining</strong> (Expires on ' . date('d M Y', strtotime($trialEnds)) . ').</span>';
            echo '<a href="billing" class="ml-2 underline hover:text-amber-200">View SaaS Plans</a>';
            echo '</div>';
        }
    }

    // Header Bar
    echo '<header class="bg-slate-950/95 backdrop-blur-md text-white sticky top-0 z-40 border-b border-slate-800/80 shadow-lg">';
    echo '<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">';
    
    // Left: Brand Logo & Workspace Switcher
    echo '<div class="flex items-center space-x-3">';
    echo '<a href="index" class="flex items-center space-x-2.5 group">';
    if (!empty($brand['logo_url'])) {
        echo '<img src="' . e($brand['logo_url']) . '" alt="Logo" class="h-7 w-auto rounded bg-white p-0.5 object-contain">';
    } else {
        echo '<div class="h-7 w-7 rounded-lg bg-gradient-to-tr from-amber-500 to-amber-600 flex items-center justify-center font-bold text-white text-xs shadow-sm"><i class="fa-solid fa-bolt"></i></div>';
    }
    echo '<span class="font-extrabold text-base tracking-tight text-white group-hover:text-amber-400 transition-colors">' . e($brand['company_name']) . '</span>';
    echo '</a>';
    
    if (!empty($_SESSION['user_id'])) {
        echo '<a href="subaccounts" class="inline-flex items-center space-x-1.5 bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white px-3 py-1 rounded-full text-xs font-semibold border border-slate-800 transition-all">';
        echo '<i class="fa-solid fa-building text-amber-400 text-2xs"></i>';
        echo '<span class="truncate max-w-[100px] sm:max-w-[140px]">' . e($activeTenant['name']) . '</span>';
        echo '<i class="fa-solid fa-chevron-down text-[10px] text-slate-500"></i>';
        echo '</a>';
    }
    echo '</div>';

    // Right: Desktop Navigation Links
    if (!empty($_SESSION['user_id'])) {
        echo '<nav class="hidden lg:flex items-center space-x-1.5">';
        echo '<a href="index" class="inline-flex items-center space-x-1.5 px-3 py-2 rounded-lg text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/80 transition-all"><i class="fa-solid fa-chart-pie text-blue-400"></i><span>Dashboard</span></a>';
        echo '<a href="clients" class="inline-flex items-center space-x-1.5 px-3 py-2 rounded-lg text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/80 transition-all"><i class="fa-solid fa-users text-emerald-400"></i><span>Clients</span></a>';
        echo '<a href="quotes" class="inline-flex items-center space-x-1.5 px-3 py-2 rounded-lg text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/80 transition-all"><i class="fa-solid fa-file-signature text-amber-400"></i><span>Proposals</span></a>';
        echo '<a href="expenses" class="inline-flex items-center space-x-1.5 px-3 py-2 rounded-lg text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/80 transition-all"><i class="fa-solid fa-receipt text-rose-400"></i><span>Expenses</span></a>';
        
        // Reports Dropdown Menu
        echo '<div class="relative group py-2">';
        echo '<button class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/80 transition-all"><i class="fa-solid fa-folder-open text-purple-400"></i><span>Reports</span><i class="fa-solid fa-chevron-down text-[10px] ml-0.5 opacity-70"></i></button>';
        echo '<div class="absolute right-0 top-full pt-1 w-72 hidden group-hover:block z-50">';
        echo '<div class="bg-white rounded-2xl shadow-2xl border border-slate-200/90 overflow-hidden py-1 max-h-[85vh] overflow-y-auto">';
        
        // Category 1: Financial Statements
        echo '<div class="bg-slate-50/90 border-b border-slate-100 px-4 py-2 flex items-center space-x-2">';
        echo '<i class="fa-solid fa-chart-line text-slate-400 text-xs"></i>';
        echo '<span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Financial Statements</span>';
        echo '</div>';
        echo '<div class="py-1">';
        echo '<a href="reports_pnl" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-blue-50/60 hover:text-blue-600 transition-colors"><i class="fa-solid fa-chart-line w-6 text-blue-500 text-center"></i><span>Profit & Loss (P&L)</span></a>';
        echo '<a href="reports_balance_sheet" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-emerald-50/60 hover:text-emerald-600 transition-colors"><i class="fa-solid fa-scale-balanced w-6 text-emerald-500 text-center"></i><span>Balance Sheet</span></a>';
        echo '<a href="reports_cashflow" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-amber-50/60 hover:text-amber-600 transition-colors"><i class="fa-solid fa-money-bill-transfer w-6 text-amber-500 text-center"></i><span>Cash Flow Statement</span></a>';
        echo '<a href="reports_aging" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-purple-50/60 hover:text-purple-600 transition-colors"><i class="fa-solid fa-clock w-6 text-purple-500 text-center"></i><span>AR Aging Report</span></a>';
        echo '</div>';

        // Category 2: UAE Tax & VAT Compliance
        echo '<div class="bg-emerald-50/90 border-y border-emerald-100 px-4 py-2 flex items-center justify-between mt-1">';
        echo '<div class="flex items-center space-x-2"><i class="fa-solid fa-building-columns text-emerald-600 text-xs"></i><span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">UAE Tax Compliance</span></div>';
        echo '<span class="text-[9px] font-black bg-emerald-600 text-white px-1.5 py-0.5 rounded">FTA 201</span>';
        echo '</div>';
        echo '<div class="py-1">';
        echo '<a href="reports_vat201" class="flex items-center px-4 py-2 text-xs font-bold text-slate-900 hover:bg-emerald-50/60 hover:text-emerald-700 transition-colors"><i class="fa-solid fa-file-invoice-dollar w-6 text-emerald-600 text-center"></i><span>UAE FTA VAT 201 Declaration</span></a>';
        echo '<a href="reports_corporate_tax" class="flex items-center px-4 py-2 text-xs font-bold text-slate-900 hover:bg-blue-50/60 hover:text-blue-700 transition-colors"><i class="fa-solid fa-percent w-6 text-blue-600 text-center"></i><span>UAE Corporate Tax (9%)</span></a>';
        echo '<a href="export_faf" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-amber-50/60 hover:text-amber-700 transition-colors"><i class="fa-solid fa-file-arrow-down w-6 text-amber-500 text-center"></i><span>Export FTA Audit File (.faf)</span></a>';
        echo '<a href="reports_tax" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-indigo-50/60 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-calculator w-6 text-indigo-500 text-center"></i><span>VAT Return Summary</span></a>';
        echo '</div>';

        // Category 3: Analytics & Breakdown
        echo '<div class="bg-slate-50/90 border-y border-slate-100 px-4 py-2 flex items-center space-x-2 mt-1">';
        echo '<i class="fa-solid fa-chart-pie text-slate-400 text-xs"></i>';
        echo '<span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Sales & Expense Analytics</span>';
        echo '</div>';
        echo '<div class="py-1">';
        echo '<a href="reports_client_sales" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-blue-50/60 hover:text-blue-600 transition-colors"><i class="fa-solid fa-users w-6 text-blue-500 text-center"></i><span>Client Revenue Analysis</span></a>';
        echo '<a href="reports_expense_category" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-rose-50/60 hover:text-rose-600 transition-colors"><i class="fa-solid fa-receipt w-6 text-rose-500 text-center"></i><span>Expenses by Category</span></a>';
        echo '</div>';

        // Category 4: General Ledger
        echo '<div class="bg-slate-50/90 border-y border-slate-100 px-4 py-2 flex items-center space-x-2 mt-1">';
        echo '<i class="fa-solid fa-book text-slate-400 text-xs"></i>';
        echo '<span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">General Ledger</span>';
        echo '</div>';
        echo '<div class="py-1">';
        echo '<a href="accounts" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-blue-600 transition-colors"><i class="fa-solid fa-book w-6 text-slate-500 text-center"></i><span>Chart of Accounts</span></a>';
        echo '<a href="journal" class="flex items-center px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-blue-600 transition-colors"><i class="fa-solid fa-list-ol w-6 text-slate-500 text-center"></i><span>General Ledger</span></a>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Management Dropdown Menu (Compact 2-Column Mega Menu)
        echo '<div class="relative group py-2">';
        echo '<button class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-300 hover:text-white hover:bg-slate-800/80 transition-all"><i class="fa-solid fa-gear text-cyan-400"></i><span>Management</span><i class="fa-solid fa-chevron-down text-[10px] ml-0.5 opacity-70"></i></button>';
        echo '<div class="absolute right-0 top-full pt-1 w-[520px] hidden group-hover:block z-50">';
        echo '<div class="bg-white rounded-2xl shadow-2xl border border-slate-200/90 p-3 grid grid-cols-2 gap-3">';
        
        // Left Column
        echo '<div class="space-y-3">';
        
        // Group 1: Workspaces & Branding
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-0.5 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-building text-slate-400 text-3xs"></i><span>Workspaces & Branding</span></div>';
        echo '<a href="domain_settings" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-slate-900 hover:bg-blue-50/70 hover:text-blue-600 rounded-lg transition-colors"><i class="fa-solid fa-globe w-5 text-blue-500 text-center"></i><span>Whitelabel Domain & SSL</span></a>';
        echo '<a href="subaccounts" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-blue-600 rounded-lg transition-colors"><i class="fa-solid fa-sitemap w-5 text-blue-500 text-center"></i><span>Workspaces & Branches</span></a>';
        echo '<a href="branding" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-pink-600 rounded-lg transition-colors"><i class="fa-solid fa-palette w-5 text-pink-500 text-center"></i><span>Dynamic Branding & Colors</span></a>';
        echo '<a href="users" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-indigo-600 rounded-lg transition-colors"><i class="fa-solid fa-user-shield w-5 text-indigo-500 text-center"></i><span>Team & Roles</span></a>';
        echo '</div>';

        // Group 2: Invoice Customization
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-0.5 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-wand-magic-sparkles text-slate-400 text-3xs"></i><span>Invoice Customization</span></div>';
        echo '<a href="invoice_customize" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors"><i class="fa-solid fa-paint-roller w-5 text-amber-500 text-center"></i><span>Template Customizer</span></a>';
        echo '<a href="invoice_builder" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors"><i class="fa-solid fa-layer-group w-5 text-amber-500 text-center"></i><span>Drag & Drop Builder</span></a>';
        echo '</div>';

        // Group 3: System & Backup
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-0.5 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-database text-slate-400 text-3xs"></i><span>System & Backup</span></div>';
        echo '<a href="backup_admin" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors"><i class="fa-solid fa-database w-5 text-amber-500 text-center"></i><span>Database Backup (.sql)</span></a>';
        echo '<a href="cache_admin" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-emerald-600 rounded-lg transition-colors"><i class="fa-solid fa-bolt w-5 text-emerald-500 text-center"></i><span>Redis Cache Engine</span></a>';
        echo '</div>';

        echo '</div>';

        // Right Column
        echo '<div class="space-y-3">';

        // Group 4: Security & Integrations
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-0.5 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-shield-halved text-slate-400 text-3xs"></i><span>Security & Integrations</span></div>';
        echo '<a href="whatsapp_settings" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-slate-900 hover:bg-emerald-50/70 hover:text-emerald-600 rounded-lg transition-colors"><i class="fa-brands fa-whatsapp w-5 text-emerald-500 text-center"></i><span>WhatsApp Cloud API</span></a>';
        echo '<a href="security" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-indigo-600 rounded-lg transition-colors"><i class="fa-solid fa-shield-halved w-5 text-indigo-500 text-center"></i><span>2FA & Security</span></a>';
        echo '<a href="email_settings" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors"><i class="fa-solid fa-server w-5 text-amber-500 text-center"></i><span>Custom SMTP</span></a>';
        echo '<a href="api_keys" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors"><i class="fa-solid fa-key w-5 text-amber-500 text-center"></i><span>API Key Manager</span></a>';
        echo '<a href="super_admin_gateways" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-indigo-600 rounded-lg transition-colors"><i class="fa-solid fa-credit-card w-5 text-indigo-500 text-center"></i><span>Payment Gateway Keys</span></a>';
        echo '<a href="automation" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-purple-600 rounded-lg transition-colors"><i class="fa-solid fa-diagram-project w-5 text-purple-500 text-center"></i><span>n8n Automations</span></a>';
        echo '</div>';

        // Group 5: SaaS Admin & Help
        echo '<div>';
        echo '<div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-2.5 py-1 mb-0.5 flex items-center space-x-1.5 border-b border-slate-100"><i class="fa-solid fa-crown text-amber-500 text-3xs"></i><span>SaaS Admin & Help</span></div>';
        echo '<a href="billing" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-amber-600 rounded-lg transition-colors"><i class="fa-solid fa-crown w-5 text-amber-500 text-center"></i><span>Subscription & Billing</span></a>';
        echo '<a href="tenants_admin" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-emerald-600 rounded-lg transition-colors"><i class="fa-solid fa-users-gear w-5 text-emerald-500 text-center"></i><span>Tenant Manager (API)</span></a>';
        echo '<a href="subscriptions_admin" class="flex items-center px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100/70 hover:text-purple-600 rounded-lg transition-colors"><i class="fa-solid fa-layer-group w-5 text-purple-500 text-center"></i><span>SaaS Plans Tiers</span></a>';
        echo '<a href="guide" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-amber-900 bg-amber-50 hover:bg-amber-100/80 rounded-lg transition-colors mt-1"><i class="fa-solid fa-book-open w-5 text-amber-500 text-center"></i><span>User Guide & Docs</span></a>';
        echo '</div>';

        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';

        // + New Invoice Button
        echo '<a href="invoice_form" class="ml-2 whitespace-nowrap inline-flex items-center px-3.5 py-2 border border-transparent text-xs font-extrabold rounded-lg shadow-sm text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 transition-all transform hover:-translate-y-0.5">';
        echo '<i class="fa-solid fa-plus mr-1.5 text-2xs"></i>New Invoice';
        echo '</a>';

        echo '<a href="logout" class="ml-1 text-rose-400 hover:text-rose-300 p-2 text-xs font-bold" title="Logout"><i class="fa-solid fa-right-from-bracket text-sm"></i></a>';
        echo '</nav>';

        // Mobile Menu Button
        echo '<button onclick="toggleMobileAppMenu()" class="lg:hidden text-amber-400 hover:text-amber-300 p-2 text-xl focus:outline-none"><i class="fa-solid fa-grid-2"></i><i class="fa-solid fa-ellipsis-vertical text-lg ml-1"></i></button>';
    }

    echo '</div>';
    echo '</header>';

    // Mobile App Navigation Dock
    if (!empty($_SESSION['user_id'])) {
        echo '<nav class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-slate-950/95 backdrop-blur-xl border-t border-slate-800 px-4 py-2 flex items-center justify-around shadow-2xl">';
        
        echo '<a href="index" class="flex flex-col items-center text-slate-400 hover:text-amber-400 py-1 transition-colors">';
        echo '<i class="fa-solid fa-chart-pie text-lg mb-0.5"></i>';
        echo '<span class="text-[10px] font-bold">Home</span>';
        echo '</a>';

        echo '<a href="clients" class="flex flex-col items-center text-slate-400 hover:text-amber-400 py-1 transition-colors">';
        echo '<i class="fa-solid fa-users text-lg mb-0.5"></i>';
        echo '<span class="text-[10px] font-bold">Clients</span>';
        echo '</a>';

        // Floating Action Button (FAB)
        echo '<a href="invoice_form" class="-mt-6 flex flex-col items-center group">';
        echo '<div class="h-13 w-13 rounded-full bg-gradient-to-tr from-amber-500 to-amber-600 border-4 border-slate-950 flex items-center justify-center text-white text-xl font-bold shadow-xl transform group-hover:scale-105 transition-all">';
        echo '<i class="fa-solid fa-plus"></i>';
        echo '</div>';
        echo '<span class="text-[10px] font-extrabold text-amber-400 mt-0.5">Invoice</span>';
        echo '</a>';

        echo '<a href="quotes" class="flex flex-col items-center text-slate-400 hover:text-amber-400 py-1 transition-colors">';
        echo '<i class="fa-solid fa-file-signature text-lg mb-0.5"></i>';
        echo '<span class="text-[10px] font-bold">Quotes</span>';
        echo '</a>';

        echo '<button onclick="toggleMobileAppMenu()" class="flex flex-col items-center text-slate-400 hover:text-amber-400 py-1 transition-colors focus:outline-none">';
        echo '<i class="fa-solid fa-bars-staggered text-lg mb-0.5"></i>';
        echo '<span class="text-[10px] font-bold">Menu</span>';
        echo '</button>';

        echo '</nav>';
    }

    // Full-Screen Mobile Glassmorphism App Launcher Modal
    if (!empty($_SESSION['user_id'])) {
        echo '<div id="mobile-app-modal" class="fixed inset-0 bg-slate-950/95 backdrop-blur-2xl z-50 hidden p-6 overflow-y-auto flex flex-col justify-between">';
        echo '<div>';
        
        echo '<div class="flex items-center justify-between border-b border-slate-800/80 pb-4 mb-6">';
        echo '<div class="flex items-center space-x-3">';
        echo '<div class="h-10 w-10 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 font-bold text-lg"><i class="fa-solid fa-bolt"></i></div>';
        echo '<div><h3 class="text-base font-extrabold text-white">' . e($brand['company_name']) . ' App</h3><p class="text-2xs text-slate-400">' . e($activeTenant['name']) . '</p></div>';
        echo '</div>';
        echo '<button onclick="toggleMobileAppMenu()" class="h-9 w-9 rounded-full bg-slate-800 text-slate-300 hover:text-white flex items-center justify-center font-bold text-lg">×</button>';
        echo '</div>';

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
        echo '<span class="text-xs text-slate-400">Signed in as <strong>Owner</strong></span>';
        echo '<a href="logout" class="px-4 py-2 bg-rose-500/20 text-rose-400 font-bold text-xs rounded-xl border border-rose-500/30"><i class="fa-solid fa-right-from-bracket mr-1.5"></i>Logout</a>';
        echo '</div>';

        echo '</div>';
    }

    // Flash Notification Alert
    echo '<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 flex-grow w-full">';
    if ($flash) {
        $bgColor = $flash['type'] === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800';
        $icon = $flash['type'] === 'error' ? 'fa-triangle-exclamation text-rose-500' : 'fa-circle-check text-emerald-500';
        echo '<div class="' . $bgColor . ' border rounded-xl p-4 mb-6 flex items-center shadow-sm">';
        echo '<i class="fa-solid ' . $icon . ' text-xl mr-3"></i>';
        echo '<span class="font-medium text-sm">' . e($flash['message']) . '</span>';
        echo '</div>';
    }
}

function page_end(): void { 
    echo '</main>';
    echo '<footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500 mt-auto hidden lg:block">';
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
