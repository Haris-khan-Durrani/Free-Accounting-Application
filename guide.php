<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$activeTenant = tenant();
$brand = branding();

page_start('Client Application Guide & Documentation');
?>

<style>
details > summary { list-style: none; cursor: pointer; }
details > summary::-webkit-details-marker { display: none; }
details[open] > summary .faq-arrow { transform: rotate(180deg); }
.faq-arrow { transition: transform 0.2s ease; }
.guide-topic { transition: all 0.2s ease; }
.guide-topic.hidden-by-search { display: none !important; }
@media print {
    header, nav, .no-print, #guide-search-wrapper { display: none !important; }
    .guide-topic { break-inside: avoid; page-break-inside: avoid; }
}
</style>

<!-- Header -->
<div class="sm:flex sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">📖 Complete Client Knowledge Base & User Manual</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Ultra-detailed operating guide with client FAQs, step-by-step instructions, and direct feature launchers for <strong><?=e($activeTenant['name'])?></strong>.</p>
    </div>
    <div class="mt-4 sm:mt-0 flex space-x-3 no-print">
        <button onclick="expandAll()" class="inline-flex items-center px-3 py-2 border border-slate-300 text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-angles-down mr-1.5 text-amber-500"></i>Expand All
        </button>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-print mr-2 text-slate-500"></i>Print Manual
        </button>
    </div>
</div>

<!-- Search Bar -->
<div id="guide-search-wrapper" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm mb-6 no-print">
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <i class="fa-solid fa-magnifying-glass text-sm"></i>
        </div>
        <input type="text" id="guide-search" onkeyup="filterGuideTopics()" placeholder="Search topics & FAQs (e.g. 'partial payment', '2FA setup', 'email invoice', 'PDF download', 'API key', 'P&L report')..." class="w-full rounded-xl border border-slate-300 bg-slate-50 pl-10 pr-4 py-3 text-sm font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
        <div id="search-result-count" class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs text-slate-400 font-semibold"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- PLATFORM CAPABILITIES VISUAL OVERVIEW          -->
<!-- ═══════════════════════════════════════════════ -->

<style>
@keyframes countUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes flowPulse { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }
@keyframes drawLine { from { stroke-dashoffset: 200; } to { stroke-dashoffset: 0; } }
@keyframes fadeSlideIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes glowPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); } 50% { box-shadow: 0 0 0 6px rgba(245,158,11,0.15); } }
.stat-counter { animation: countUp 0.6s ease forwards; }
.cap-card { animation: fadeSlideIn 0.5s ease forwards; opacity: 0; }
.cap-card:nth-child(1){animation-delay:0.05s} .cap-card:nth-child(2){animation-delay:0.10s}
.cap-card:nth-child(3){animation-delay:0.15s} .cap-card:nth-child(4){animation-delay:0.20s}
.cap-card:nth-child(5){animation-delay:0.25s} .cap-card:nth-child(6){animation-delay:0.30s}
.cap-card:nth-child(7){animation-delay:0.35s} .cap-card:nth-child(8){animation-delay:0.40s}
.cap-card:nth-child(9){animation-delay:0.45s} .cap-card:nth-child(10){animation-delay:0.50s}
.cap-card:nth-child(11){animation-delay:0.55s} .cap-card:nth-child(12){animation-delay:0.60s}
.flow-node { animation: glowPulse 2.5s infinite; }
.flow-arrow { stroke-dasharray: 200; animation: drawLine 1.2s ease forwards; }
</style>

<!-- Hero: Platform Overview Banner -->
<div class="relative bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 rounded-3xl p-8 sm:p-10 mb-8 overflow-hidden border border-slate-800 shadow-2xl">
    <!-- Background Grid Pattern -->
    <div class="absolute inset-0 opacity-5" style="background-image: url('data:image/svg+xml,%3Csvg width=\'40\' height=\'40\' viewBox=\'0 0 40 40\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M0 40L40 0H20L0 20M40 40V20L20 40\' fill=\'none\' stroke=\'%23fff\' stroke-width=\'0.5\'/%3E%3C/svg%3E');"></div>

    <div class="relative">
        <div class="flex items-center space-x-3 mb-4">
            <div class="h-10 w-10 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center">
                <i class="fa-solid fa-bolt text-amber-400 text-lg"></i>
            </div>
            <div>
                <span class="text-xs font-extrabold text-amber-400 uppercase tracking-widest">Platform Capabilities Overview</span>
                <h2 class="text-2xl sm:text-3xl font-black text-white leading-tight">What <?=e($brand['company_name'] ?: 'OneSol')?> Can Do For Your Business</h2>
            </div>
        </div>
        <p class="text-slate-400 text-sm max-w-3xl mb-8">A complete enterprise-grade multi-tenant accounting, invoicing, and client billing platform — built for UAE businesses with double-entry accounting, REST API automation, and multi-workspace management.</p>

        <!-- Animated Capability Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
            <?php
            $stats = [
                ['20+', 'Knowledge Base Chapters', 'book-open', 'amber'],
                ['11', 'Invoice Templates', 'wand-magic-sparkles', 'purple'],
                ['7', 'REST API Endpoints', 'code', 'emerald'],
                ['8', 'Financial & FTA Reports', 'chart-line', 'rose'],
                ['4', 'User Permission Roles', 'user-shield', 'indigo'],
                ['∞', 'Partial Payments', 'money-bill-wave', 'cyan'],
                ['100%', 'UAE VAT 201 Compliant', 'shield-halved', 'emerald'],
            ];
            foreach ($stats as [$num, $label, $icon, $c]) {
                echo "<div class='stat-counter bg-slate-800/80 border border-slate-700 rounded-2xl p-4 text-center hover:border-amber-500/50 transition-all hover:-translate-y-0.5 cursor-default'>";
                echo "<i class='fa-solid fa-$icon text-$c-400 text-xl mb-2 block'></i>";
                echo "<div class='text-2xl font-black text-white'>" . htmlspecialchars($num) . "</div>";
                echo "<div class='text-2xs font-semibold text-slate-400 leading-tight mt-0.5'>" . htmlspecialchars($label) . "</div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>
</div>

<!-- Feature Module Capability Grid -->
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm mb-8">
    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-100">
        <div class="h-8 w-8 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center">
            <i class="fa-solid fa-border-all text-sm"></i>
        </div>
        <div>
            <h3 class="text-lg font-extrabold text-slate-900">Complete Feature Module Map</h3>
            <p class="text-xs text-slate-500">All major capabilities of the platform — click any card to jump directly to the feature.</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php
        $modules = [
            ['fa-file-invoice', 'bg-amber-500/10 text-amber-600', 'Tax Invoice Engine', 'Create professional UAE-compliant tax invoices with sequential numbering, multi-currency, VAT calculation, discount engine, and PDF export.', 'invoice_form', 'amber'],
            ['fa-file-signature', 'bg-purple-500/10 text-purple-600', 'Proposal & Quotation Builder', 'Generate commercial proposals with validity dates, custom line items, and one-click conversion to live tax invoices.', 'quotes', 'purple'],
            ['fa-money-bill-wave', 'bg-emerald-500/10 text-emerald-600', 'Payment & Installment Tracker', 'Record unlimited partial payment milestones with automatic balance-due recalculation, payment audit trail, and status lifecycle management.', 'index', 'emerald'],
            ['fa-users', 'bg-blue-500/10 text-blue-600', 'CRM Client Directory', 'Maintain a comprehensive client address book with TRN numbers, billing addresses, multi-currency settings, and full transaction history.', 'clients', 'blue'],
            ['fa-wand-magic-sparkles', 'bg-pink-500/10 text-pink-600', '11 Invoice Template Designer', 'Choose from 11 professionally crafted invoice layouts with instant database auto-save, company logo upload, and custom brand color integration.', 'invoice_customize', 'pink'],
            ['fa-chart-line', 'bg-rose-500/10 text-rose-600', 'Financial Statement Reports', 'Generate P&L, Balance Sheet, Cash Flow, AR Aging, Tax/VAT return, and full General Ledger reports on demand.', 'reports_pnl', 'rose'],
            ['fa-sitemap', 'bg-sky-500/10 text-sky-600', 'Multi-Tenant Workspace Manager', 'Operate multiple isolated branch accounts (Dubai, Abu Dhabi, Riyadh) under a single login with independent ledgers and user teams.', 'subaccounts', 'sky'],
            ['fa-shield-halved', 'bg-indigo-500/10 text-indigo-600', '2FA OTP Security Engine', 'Protect every user account with 6-digit cryptographic email OTP codes with 15-minute expiry, resend functionality, and mandatory workspace enforcement policies.', 'security', 'indigo'],
            ['fa-server', 'bg-cyan-500/10 text-cyan-600', 'Custom SMTP Mail Server', 'Connect your company mail server (Gmail, Office 365, cPanel, Amazon SES) to send branded invoice emails and 2FA codes from your domain.', 'email_settings', 'cyan'],
            ['fa-code', 'bg-emerald-500/10 text-emerald-700', 'REST API Developer Portal', '7 REST endpoints for programmatic sub-account onboarding, invoice creation, client sync, and payment recording — with a live interactive test playground.', 'api_playground', 'emerald'],
            ['fa-crown', 'bg-amber-500/10 text-amber-700', 'SaaS Subscription Engine', 'Manage multi-tier subscription plans (Starter, Professional, Enterprise) with user quotas, trial countdown banners, and +4/+6 Month Free trial extensions.', 'subscriptions_admin', 'amber'],
            ['fa-receipt', 'bg-orange-500/10 text-orange-600', 'Expense & VAT Tracker', 'Log all operating expenses with receipt uploads, input VAT flagging, and automatic P&L and Cash Flow integration for complete accounting.', 'expenses', 'orange'],
        ];
        foreach ($modules as [$icon, $iconClass, $title, $desc, $url, $c]) {
            echo "<a href='$url' class='cap-card group bg-slate-50 hover:bg-white border border-slate-200 hover:border-$c-300 hover:shadow-md rounded-2xl p-4 transition-all hover:-translate-y-0.5 block'>";
            echo "<div class='h-9 w-9 rounded-xl $iconClass flex items-center justify-center mb-3 group-hover:scale-110 transition-transform'>";
            echo "<i class='fa-solid $icon text-base'></i></div>";
            echo "<h4 class='font-extrabold text-slate-900 text-xs mb-1.5 leading-snug'>$title</h4>";
            echo "<p class='text-2xs text-slate-500 leading-relaxed'>$desc</p>";
            echo "<div class='mt-3 text-2xs font-extrabold text-$c-600 flex items-center'><span>Open Feature</span><i class='fa-solid fa-arrow-right ml-1 text-[9px] group-hover:translate-x-0.5 transition-transform'></i></div>";
            echo "</a>";
        }
        ?>
    </div>
</div>

<!-- Invoice Lifecycle Flow Diagram -->
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm mb-8">
    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-100">
        <div class="h-8 w-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
            <i class="fa-solid fa-diagram-project text-sm"></i>
        </div>
        <div>
            <h3 class="text-lg font-extrabold text-slate-900">Invoice Lifecycle Flow Diagram</h3>
            <p class="text-xs text-slate-500">From client creation to fully paid invoice — the complete billing workflow in one visual.</p>
        </div>
    </div>

    <!-- Flow: Horizontal on Desktop, Vertical on Mobile -->
    <div class="overflow-x-auto pb-2">
        <div class="flex items-center justify-between min-w-max gap-0 px-2">
            <?php
            $flows = [
                ['fa-user-plus', 'bg-blue-500', 'white', 'Add Client', 'Register in CRM directory with TRN, address & billing profile'],
                ['fa-file-invoice', 'bg-amber-500', 'white', 'Create Invoice', 'Add line items, apply VAT%, discount & set due date'],
                ['fa-paper-plane', 'bg-indigo-500', 'white', 'Send to Client', 'Deliver via branded email from your custom SMTP server'],
                ['fa-circle-half-stroke', 'bg-orange-500', 'white', 'Partial Payment', 'Record deposit — status → PARTIALLY PAID, balance tracked'],
                ['fa-circle-check', 'bg-emerald-500', 'white', 'Fully Paid', 'All payments received — ledger closed, journals posted'],
                ['fa-chart-line', 'bg-rose-500', 'white', 'Reports Updated', 'P&L, Cash Flow & AR Aging reflect in real-time'],
            ];
            foreach ($flows as $i => [$icon, $bg, $text, $title, $desc]) {
                echo "<div class='flex items-center'>";
                echo "<div class='flex flex-col items-center w-32'>";
                echo "<div class='flow-node h-14 w-14 rounded-2xl $bg flex items-center justify-center text-$text text-xl shadow-lg mb-2'>";
                echo "<i class='fa-solid $icon'></i></div>";
                echo "<div class='text-center'>";
                echo "<div class='font-extrabold text-slate-900 text-2xs leading-tight mb-0.5'>$title</div>";
                echo "<div class='text-slate-500 text-[10px] leading-tight max-w-[110px]'>$desc</div>";
                echo "</div></div>";
                if ($i < count($flows) - 1) {
                    echo "<div class='flex flex-col items-center mx-1 flex-shrink-0'>";
                    echo "<svg width='48' height='20' viewBox='0 0 48 20' fill='none' xmlns='http://www.w3.org/2000/svg'>";
                    echo "<path d='M2 10 L38 10' stroke='#cbd5e1' stroke-width='2' stroke-dasharray='4 3' class='flow-arrow'/>";
                    echo "<path d='M34 5 L44 10 L34 15' fill='none' stroke='#94a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/>";
                    echo "</svg>";
                    echo "</div>";
                }
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- Alternative paths -->
    <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-3 flex items-start space-x-2">
            <i class="fa-solid fa-clock text-rose-500 mt-0.5 flex-shrink-0"></i>
            <div><strong class="text-rose-800">If Past Due Date:</strong> <span class="text-rose-700">Invoice automatically marked <strong>OVERDUE</strong> and appears in AR Aging Report under 30/60/90+ day buckets.</span></div>
        </div>
        <div class="bg-sky-50 border border-sky-200 rounded-xl p-3 flex items-start space-x-2">
            <i class="fa-solid fa-xmark text-sky-500 mt-0.5 flex-shrink-0"></i>
            <div><strong class="text-sky-800">If Cancelled:</strong> <span class="text-sky-700">Invoice is voided (soft-deleted) — preserves audit trail. A new invoice can be re-issued.</span></div>
        </div>
        <div class="bg-purple-50 border border-purple-200 rounded-xl p-3 flex items-start space-x-2">
            <i class="fa-solid fa-rotate text-purple-500 mt-0.5 flex-shrink-0"></i>
            <div><strong class="text-purple-800">Recurring Mode:</strong> <span class="text-purple-700">For subscription clients, the automation engine auto-generates new invoices on a monthly/quarterly schedule.</span></div>
        </div>
    </div>
</div>

<!-- Multi-Tenant Architecture Diagram -->
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm mb-8">
    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-100">
        <div class="h-8 w-8 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
            <i class="fa-solid fa-sitemap text-sm"></i>
        </div>
        <div>
            <h3 class="text-lg font-extrabold text-slate-900">Multi-Tenant Architecture & Data Isolation Model</h3>
            <p class="text-xs text-slate-500">How workspaces, users, and data are isolated within the platform.</p>
        </div>
    </div>

    <!-- Diagram -->
    <div class="flex flex-col items-center space-y-4">
        <!-- Root: SaaS Platform -->
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl px-8 py-4 text-center shadow-xl border border-slate-700 w-full max-w-xs">
            <i class="fa-solid fa-cloud text-amber-400 text-xl mb-1 block"></i>
            <div class="font-extrabold text-sm"><?=e($brand['company_name'] ?: 'OneSol')?> SaaS Platform</div>
            <div class="text-2xs text-slate-400 mt-0.5">Superadmin — Full multi-tenant management</div>
        </div>

        <!-- Connector -->
        <div class="flex flex-col items-center"><div class="w-0.5 h-5 bg-slate-300"></div><div class="w-4 h-4 rounded-full border-2 border-slate-300 bg-white flex items-center justify-center"><i class="fa-solid fa-chevron-down text-slate-400 text-[8px]"></i></div></div>

        <!-- Tenant Level: 3 Workspace Branches -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 w-full max-w-3xl">
            <?php
            $tenants = [
                ['Dubai HQ Workspace', 'Owner + 5 Admins', 'AED — UAE VAT', 'Invoices, P&L, AR Aging', 'bg-amber-500', 'amber'],
                ['Abu Dhabi Branch', 'Admin + 2 Accountants', 'AED — Isolated Ledger', 'Invoices, Expenses, Reports', 'bg-blue-500', 'blue'],
                ['Riyadh Subsidiary', 'Admin + 1 Viewer', 'SAR — KSA Tax', 'Invoices, Cash Flow', 'bg-emerald-500', 'emerald'],
            ];
            foreach ($tenants as [$name, $users, $currency, $modules, $bg, $c]) {
                echo "<div class='border-2 border-$c-200 bg-$c-50 rounded-2xl p-4'>";
                echo "<div class='h-8 w-8 rounded-xl $bg text-white flex items-center justify-center mb-2'><i class='fa-solid fa-building text-sm'></i></div>";
                echo "<div class='font-extrabold text-slate-900 text-xs mb-2'>$name</div>";
                echo "<div class='space-y-1 text-2xs'>";
                echo "<div class='flex items-center space-x-1.5 text-slate-600'><i class='fa-solid fa-users text-$c-500 w-3'></i><span>$users</span></div>";
                echo "<div class='flex items-center space-x-1.5 text-slate-600'><i class='fa-solid fa-coins text-$c-500 w-3'></i><span>$currency</span></div>";
                echo "<div class='flex items-center space-x-1.5 text-slate-600'><i class='fa-solid fa-cube text-$c-500 w-3'></i><span>$modules</span></div>";
                echo "</div>";
                echo "<div class='mt-2 pt-2 border-t border-$c-200 text-2xs font-bold text-$c-700 flex items-center space-x-1'>";
                echo "<i class='fa-solid fa-lock text-$c-400'></i><span>100% Data Isolated</span></div>";
                echo "</div>";
            }
            ?>
        </div>

        <!-- Connector -->
        <div class="flex flex-col items-center"><div class="w-0.5 h-5 bg-slate-300"></div><div class="w-4 h-4 rounded-full border-2 border-slate-300 bg-white flex items-center justify-center"><i class="fa-solid fa-chevron-down text-slate-400 text-[8px]"></i></div></div>

        <!-- Data Layer -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 w-full max-w-3xl">
            <?php
            $layers = [
                ['fa-file-invoice', 'text-amber-600', 'bg-amber-50 border-amber-200', 'Invoice Ledger', 'All issued invoices, line items & statuses'],
                ['fa-users', 'text-blue-600', 'bg-blue-50 border-blue-200', 'Client Directory', 'CRM contacts, TRNs & billing profiles'],
                ['fa-book', 'text-emerald-600', 'bg-emerald-50 border-emerald-200', 'General Ledger', 'Double-entry debit/credit journal'],
                ['fa-chart-bar', 'text-rose-600', 'bg-rose-50 border-rose-200', 'Financial Reports', 'P&L, Balance Sheet, Tax returns'],
            ];
            foreach ($layers as [$icon, $ic, $bg, $name, $desc]) {
                echo "<div class='$bg border rounded-xl p-3 text-center'>";
                echo "<i class='fa-solid $icon $ic text-lg mb-1.5 block'></i>";
                echo "<div class='font-extrabold text-slate-900 text-2xs'>$name</div>";
                echo "<div class='text-slate-500 text-[10px] mt-0.5'>$desc</div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>
</div>

<!-- Integration Ecosystem Map -->
<div class="bg-gradient-to-br from-slate-900 to-slate-950 rounded-3xl p-6 sm:p-8 border border-slate-800 shadow-xl mb-8">
    <div class="flex items-center space-x-3 mb-6 pb-4 border-b border-slate-800">
        <div class="h-8 w-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
            <i class="fa-solid fa-plug text-sm"></i>
        </div>
        <div>
            <h3 class="text-lg font-extrabold text-white">Integration Ecosystem & Connected Services</h3>
            <p class="text-xs text-slate-400">All external systems and services the platform connects with.</p>
        </div>
    </div>

    <!-- Central Hub + Spokes Layout -->
    <div class="flex flex-col items-center space-y-6">
        <!-- Central Platform Hub -->
        <div class="bg-amber-500 rounded-2xl px-6 py-3 text-slate-950 font-black text-sm shadow-xl shadow-amber-500/30 text-center">
            <i class="fa-solid fa-bolt mr-2"></i><?=e($brand['company_name'] ?: 'OneSol')?> Core Platform
        </div>

        <!-- Integration Cards Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 w-full">
            <?php
            // icon prefix: 'solid' = fa-solid, 'brands' = fa-brands
            $integrations = [
                ['fa-solid fa-envelope',          'bg-red-500/20 border-red-500/40 text-red-300',     'Gmail / G-Suite',          'SMTP TLS Port 587 — Invoice email delivery &amp; 2FA OTPs'],
                ['fa-solid fa-globe',             'bg-blue-500/20 border-blue-500/40 text-blue-300',  'Office 365 / cPanel',      'SMTP Port 587 — Branded emails from @company.ae domain'],
                ['fa-solid fa-credit-card',       'bg-purple-500/20 border-purple-500/40 text-purple-300', 'Stripe Payments',   'Online card checkout on public invoice payment links'],
                ['fa-solid fa-code',              'bg-emerald-500/20 border-emerald-500/40 text-emerald-300', 'REST API (7 Endpoints)', 'Programmatic invoice creation, client sync &amp; payment recording'],
                ['fa-solid fa-diagram-project',   'bg-amber-500/20 border-amber-500/40 text-amber-300',   'n8n / Zapier',         'Workflow automations triggered by invoice or payment events'],
                ['fa-solid fa-database',          'bg-cyan-500/20 border-cyan-500/40 text-cyan-300',  'MySQL Database',           'Isolated per-tenant data storage with double-entry audit trail'],
            ];
            foreach ($integrations as [$iconClass, $cls, $name, $desc]) {
                echo "<div class='$cls border rounded-2xl p-4 text-center hover:scale-105 transition-transform cursor-default'>";
                echo "<i class='$iconClass text-2xl mb-2 block'></i>";
                echo "<div class='font-extrabold text-white text-2xs mb-1'>$name</div>";
                echo "<div class='text-[10px] leading-tight' style='color:rgba(255,255,255,0.55)'>$desc</div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>
</div>

<!-- Chapter Navigation Pills -->
<!-- Table of Contents -->
<div class="bg-white rounded-3xl border border-slate-200 shadow-sm mb-8 overflow-hidden no-print">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-list-ul text-slate-500"></i>
            <span class="font-extrabold text-slate-800 text-sm">Table of Contents</span>
            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-2xs font-bold">24 Chapters</span>
        </div>
        <button onclick="document.getElementById('toc-grid').classList.toggle('hidden')" class="text-xs font-bold text-slate-500 hover:text-slate-800 flex items-center space-x-1 transition-colors">
            <span id="toc-toggle-label">Collapse</span>
            <i class="fa-solid fa-chevron-up text-[10px]" id="toc-toggle-icon"></i>
        </button>
    </div>
    <div id="toc-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-0 divide-y divide-slate-100 sm:divide-y-0">
        <?php
        $chapters = [
            ['ch1',  'rocket',              'bg-amber-500',  'text-amber-500',  'bg-amber-50  border-amber-200',  '1',  'Getting Started',        'Dashboard navigation, company setup, login, and mobile interface.'],
            ['ch2',  'users',               'bg-blue-500',   'text-blue-500',   'bg-blue-50   border-blue-200',   '2',  'Clients & Contacts',     'Adding, editing, TRN numbers, and managing your CRM directory.'],
            ['ch3',  'file-invoice',        'bg-amber-500',  'text-amber-500',  'bg-amber-50  border-amber-200',  '3',  'Tax Invoices',           'Full invoice lifecycle: draft, send, PDF export, and VAT calculation.'],
            ['ch4',  'file-signature',      'bg-purple-500', 'text-purple-500', 'bg-purple-50 border-purple-200', '4',  'Proposals & Quotes',     'Creating quotations and converting them to live invoices.'],
            ['ch5',  'money-bill-wave',     'bg-emerald-500','text-emerald-500','bg-emerald-50 border-emerald-200','5', 'Payments & Installments','Recording full and partial payments with balance tracking.'],
            ['ch6',  'wand-magic-sparkles', 'bg-pink-500',   'text-pink-500',   'bg-pink-50   border-pink-200',   '6',  'Templates & Design',     '11 invoice layouts, logo upload, and brand color customization.'],
            ['ch7',  'sitemap',             'bg-sky-500',    'text-sky-500',    'bg-sky-50    border-sky-200',    '7',  'Workspaces & Branches',  'Multi-tenant isolation, branch creation, and workspace switching.'],
            ['ch8',  'user-shield',         'bg-indigo-500', 'text-indigo-500', 'bg-indigo-50 border-indigo-200', '8',  'Team & Roles',           'User accounts, permission matrix, and concurrent access management.'],
            ['ch9',  'shield-halved',       'bg-red-500',    'text-red-500',    'bg-red-50    border-red-200',    '9',  '2FA Security',           'OTP codes, mandatory workspace policies, lockout and resend flows.'],
            ['ch10', 'server',              'bg-cyan-500',   'text-cyan-500',   'bg-cyan-50   border-cyan-200',   '10', 'SMTP Email Setup',       'Connecting Gmail, Office 365, cPanel, or Amazon SES for mail delivery.'],
            ['ch11', 'code',                'bg-emerald-600','text-emerald-600','bg-emerald-50 border-emerald-200','11','REST API & Playground',  '7 API endpoints with live interactive test console and cURL examples.'],
            ['ch12', 'crown',               'bg-amber-600',  'text-amber-600',  'bg-amber-50  border-amber-200',  '12', 'Subscription & Plans',   'Starter, Professional, Enterprise tiers, trials, and quotas.'],
            ['ch13', 'chart-line',          'bg-rose-500',   'text-rose-500',   'bg-rose-50   border-rose-200',   '13', 'Financial Reports',      'P&L, Balance Sheet, Cash Flow, AR Aging, and VAT return generation.'],
            ['ch14', 'receipt',             'bg-orange-500', 'text-orange-500', 'bg-orange-50 border-orange-200', '14', 'Expense Tracking',       'Logging operating costs, input VAT recovery, and receipt uploads.'],
            ['ch15', 'circle-question',     'bg-slate-600',  'text-slate-600',  'bg-slate-50  border-slate-200',  '15', 'Troubleshooting FAQ',    'Common errors, email delivery issues, and quick resolution guides.'],
            ['ch16', 'docker',              'bg-blue-600',   'text-blue-600',   'bg-blue-50   border-blue-200',   '16', 'Docker Deployment',      '1-Click container launch with PHP 8.3, Nginx, MySQL 8, and Redis.'],
            ['ch17', 'building-columns',    'bg-emerald-600','text-emerald-600','bg-emerald-50 border-emerald-200','17','UAE VAT 201 Filing',     'Official FTA 7-Emirate VAT 201 return form, Box 1a-1g, Box 9, Box 14.'],
            ['ch18', 'globe',               'bg-blue-600',   'text-blue-600',   'bg-blue-50   border-blue-200',   '18', 'Whitelabel Domain',      'Binding custom domain (billing.company.com), DNS CNAME & real-time test.'],
            ['ch19', 'file-invoice-dollar', 'bg-amber-600',  'text-amber-600',  'bg-amber-50  border-amber-200',  '19', 'Client Ledger Statement', 'Full client statement of account, ledger breakdown & 1-click email.'],
            ['ch20', 'database',            'bg-purple-600', 'text-purple-600', 'bg-purple-50 border-purple-200', '20', 'SQL Backup & Live Calc',  '1-Click .sql database dump download & sticky real-time invoice calculation.'],
            ['ch21', 'rotate',              'bg-amber-600',  'text-amber-600',  'bg-amber-50  border-amber-200',  '21', 'Auto-Recurring Billing',  'Automated subscription cron engine (cron_recurring.php) generating invoices.'],
            ['ch22', 'user-lock',           'bg-emerald-600','text-emerald-600','bg-emerald-50 border-emerald-200','22','Client Portal Hub',       'Passwordless client login hub (client_portal.php) for online payments.'],
            ['ch23', 'whatsapp',            'bg-emerald-500','text-emerald-500','bg-emerald-50 border-emerald-200','23','WhatsApp Cloud API',     'Meta WhatsApp Cloud API gateway (whatsapp_settings.php) for instant dispatch.'],
            ['ch24', 'coins',               'bg-cyan-600',   'text-cyan-600',   'bg-cyan-50   border-cyan-200',   '24', 'CBUAE Live FX Sync',     'Automated exchange rate sync (cron_exchange_rates.php) for USD, EUR to AED.'],
        ];
        foreach ($chapters as $i => [$id, $icon, $bg, $tc, $cardBg, $num, $title, $desc]) {
            $border = $i > 0 && $i % 3 !== 0 ? '' : '';
            echo "<a href='#$id' class='group flex items-start space-x-3.5 px-5 py-4 hover:bg-slate-50 transition-all border-b border-slate-100 last:border-b-0 sm:border-r sm:border-slate-100 " . ($i % 3 === 2 ? 'sm:border-r-0' : '') . "' onclick=\"scrollToChapter('$id')\">";
            echo "<div class='flex-shrink-0 h-9 w-9 rounded-xl $bg flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform'>";
            echo "<i class='fa-solid fa-$icon text-white text-sm'></i></div>";
            echo "<div class='min-w-0 flex-1'>";
            echo "<div class='flex items-center space-x-1.5 mb-0.5'>";
            echo "<span class='text-2xs font-extrabold text-slate-400 uppercase tracking-wider'>Chapter $num</span>";
            echo "</div>";
            echo "<div class='font-extrabold text-slate-900 text-xs leading-tight group-hover:$tc transition-colors'>$title</div>";
            echo "<p class='text-[10px] text-slate-500 leading-tight mt-0.5 line-clamp-2'>$desc</p>";
            echo "</div>";
            echo "<i class='fa-solid fa-arrow-right text-slate-300 text-[10px] flex-shrink-0 mt-1 group-hover:translate-x-0.5 group-hover:$tc transition-all'></i>";
            echo "</a>";
        }
        ?>
    </div>
</div>

<script>
function scrollToChapter(id) {
    event.preventDefault();
    const el = document.getElementById(id);
    if (el) { window.scrollTo({ top: el.offsetTop - 80, behavior: 'smooth' }); }
}
document.getElementById('toc-toggle-label') && document.querySelector('[onclick*="toc-grid"]').addEventListener('click', function() {
    const grid = document.getElementById('toc-grid');
    const lbl = document.getElementById('toc-toggle-label');
    const icon = document.getElementById('toc-toggle-icon');
    if (grid.classList.contains('hidden')) {
        lbl.textContent = 'Collapse';
        icon.className = 'fa-solid fa-chevron-up text-[10px]';
    } else {
        lbl.textContent = 'Expand';
        icon.className = 'fa-solid fa-chevron-down text-[10px]';
    }
});
</script>



<!-- HELPER MACRO FOR FAQ ITEMS -->
<?php
function faq(string $q, string $a): void {
    echo '<details class="group bg-slate-50 border border-slate-200 rounded-xl overflow-hidden">';
    echo '<summary class="flex items-center justify-between px-4 py-3 font-bold text-slate-800 text-xs hover:bg-slate-100 transition-colors">';
    echo '<span><i class="fa-solid fa-circle-question text-amber-500 mr-2"></i>' . htmlspecialchars($q) . '</span>';
    echo '<i class="fa-solid fa-chevron-down faq-arrow text-slate-400 text-xs flex-shrink-0 ml-2"></i>';
    echo '</summary>';
    echo '<div class="px-4 py-3 text-xs text-slate-600 border-t border-slate-200 leading-relaxed space-y-2">' . $a . '</div>';
    echo '</details>';
}

function launch_btn(string $url, string $icon, string $label, string $color = 'bg-slate-900'): void {
    echo "<a href=\"$url\" class=\"inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold text-white $color hover:opacity-90 shadow-xs self-start sm:self-auto\">";
    echo "<i class=\"fa-solid fa-$icon mr-1.5\"></i>$label →";
    echo "</a>";
}

function chapter_header(string $id, string $icon, string $bg, string $title, string $subtitle, string $btnUrl, string $btnIcon, string $btnLabel, string $btnColor): void {
    echo "<div class=\"flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3\">";
    echo "<div class=\"flex items-center space-x-3\">";
    echo "<div class=\"h-10 w-10 rounded-xl $bg flex items-center justify-center font-bold text-lg\">";
    echo "<i class=\"fa-solid fa-$icon\"></i></div>";
    echo "<div><h2 class=\"text-xl font-extrabold text-slate-900\">$title</h2>";
    echo "<p class=\"text-xs text-slate-500\">$subtitle</p></div></div>";
    launch_btn($btnUrl, $btnIcon, $btnLabel, $btnColor);
    echo "</div>";
}
?>

<div class="space-y-8 mb-12">

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 1: GETTING STARTED -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch1" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch1','rocket','bg-amber-500/10 text-amber-500','Chapter 1: Getting Started — Platform Overview & First Login','Dashboard navigation, company setup, multi-currency, and mobile interface.','index','chart-pie','Go to Dashboard','bg-slate-900'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>Welcome to <strong><?=e($brand['company_name'])?></strong> — a professional-grade multi-tenant invoicing, accounting, and client management platform designed specifically for UAE-registered businesses and international service providers.</p>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">What You Can Do With This Platform</h4>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200"><i class="fa-solid fa-file-invoice text-amber-500 mr-2"></i><strong>Invoice & Proposal Management</strong> — Create, send, track, and collect tax invoices and commercial proposals.</div>
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200"><i class="fa-solid fa-chart-line text-rose-500 mr-2"></i><strong>Full Accounting Suite</strong> — Double-entry general ledger, P&L, Balance Sheet, Cash Flow, and VAT returns.</div>
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200"><i class="fa-solid fa-users-gear text-indigo-500 mr-2"></i><strong>Multi-Workspace SaaS</strong> — Run multiple branch accounts (Dubai, Abu Dhabi, Riyadh) under one login.</div>
    </div>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mt-4">Dashboard Layout Overview</h4>
    <ul class="list-disc pl-5 space-y-1 text-xs">
        <li><strong>Top Header Bar:</strong> Brand logo, workspace switcher pill, main navigation links (Dashboard, Clients, Proposals, Expenses, Reports, Management), and + New Invoice button.</li>
        <li><strong>KPI Stat Cards:</strong> Real-time summary of Total Revenue, Total Outstanding Receivables, Total Expenses, and Net Profit for the current month.</li>
        <li><strong>Revenue Chart:</strong> Interactive 12-month bar chart showing monthly billing volume trends.</li>
        <li><strong>Recent Invoices Feed:</strong> Quick access list of your 10 most recently issued invoices with status indicators.</li>
        <li><strong>Mobile Navigation Dock:</strong> Fixed bottom navigation bar with Home, Clients, + Invoice FAB, Proposals, and Menu buttons.</li>
    </ul>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Getting Started</h4>
<div class="space-y-2">
<?php
faq("How do I log in for the first time?", "Navigate to your application URL and enter your registered email address and password on the login screen. If your account is protected with Two-Factor Authentication (2FA), you will be prompted to enter a 6-digit OTP security code delivered to your email address.");
faq("I forgot my password — how do I reset it?", "On the login screen, click <strong>\"Forgot Password?\"</strong> to trigger a password reset email. Check your inbox (and spam folder) for a reset link. The link expires in 30 minutes for security purposes.");
faq("What does the Dashboard show me?", "The Dashboard provides an instant financial snapshot: total revenue earned this month, total outstanding receivables (money owed by clients), total operating expenses, and calculated net profit. The revenue chart shows your 12-month billing trend.");
faq("Can I change my company name, address, and logo?", "Yes. Navigate to <strong>Management → Dynamic Branding & Colors</strong> to upload your company logo, set custom brand colors (primary, secondary, accent), and update company registration details displayed on all invoices.");
faq("Which currencies are supported?", "The platform supports AED (UAE Dirham), USD (US Dollar), EUR (Euro), GBP (British Pound), SAR (Saudi Riyal), and additional currencies. Currency is configurable per-workspace. Each invoice inherits the workspace default currency.");
faq("Does the application work on mobile phones?", "Yes — the platform is fully responsive with a dedicated mobile experience: a fixed bottom navigation dock, a full-screen glassmorphism mobile app launcher, and touch-optimized forms. All features are accessible on iPhone and Android browsers.");
faq("Is my data backed up automatically?", "All data is stored in a MySQL database on your server. Automated backups depend on your server/hosting configuration. We recommend setting up daily automated MySQL dumps using cron jobs or your hosting control panel.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 2: CLIENTS & CONTACTS -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch2" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch2','users','bg-blue-500/10 text-blue-500','Chapter 2: Client Directory — Adding, Editing & Organising Contacts','Managing your complete client address book, tax IDs, and billing profiles.','clients','users','Open Client Directory','bg-blue-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>The Client Directory is your centralised CRM contact book. Every invoice and proposal must be linked to a registered client. Clients retain permanent history of all transactions issued against them.</p>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">How to Add a New Client</h4>
    <ol class="list-decimal pl-5 space-y-1.5 text-xs">
        <li>Go to <strong>Clients</strong> in the top navigation or click <a href="clients" class="text-blue-600 hover:underline font-bold">here</a>.</li>
        <li>Click the <strong>"+ Add New Client"</strong> button.</li>
        <li>Fill in: <em>Company Name</em>, <em>Contact Person Name</em>, <em>Email Address</em>, <em>Phone Number</em>, <em>Billing Address</em>, <em>Trade License Number / Tax Registration Number (TRN)</em>.</li>
        <li>Click <strong>"Save Client"</strong> — the client is now available in all invoice and proposal dropdowns.</li>
    </ol>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-900 font-semibold">
        <i class="fa-solid fa-lightbulb text-blue-500 mr-2"></i><strong>Tip:</strong> Adding the client's TRN (Tax Registration Number) is critical for UAE VAT compliance. It appears automatically on issued tax invoices.
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Contacts & Billing Profiles</h4>
<div class="space-y-2">
<?php
faq("Can I have multiple contacts for the same company?", "Currently the client record stores one primary contact person. For companies with multiple billing contacts, you can store additional contact details in the client's Notes field, or create separate client records for each billing entity (e.g. Finance Dept vs. Procurement Dept).");
faq("How do I edit an existing client's details?", "Go to <strong>Clients</strong>, find the client in the table, and click the <strong>Edit (pencil)</strong> icon on their row. Update the fields and click <strong>Save</strong>. All previously issued invoices retain the billing address at the time of issuance.");
faq("Can I delete a client?", "Clients with existing invoices or payment records cannot be deleted to maintain financial integrity and audit trails. You can archive inactive clients by marking them inactive, which hides them from new invoice dropdowns while preserving all historical records.");
faq("Does the client get a notification when I create an invoice?", "Invoice notification emails are sent when you use the <strong>Send Invoice via Email</strong> button on the invoice view page. The email is dispatched from your configured Custom SMTP mail server with your company From Name and branding.");
faq("Can I import a list of clients from Excel or CSV?", "A bulk CSV import feature is on the development roadmap. Currently, clients must be added individually via the Add Client form. For large client migrations, our team can assist with direct database import.");
faq("What is the TRN field?", "TRN stands for <strong>Tax Registration Number</strong> — the unique VAT identification number assigned by the UAE Federal Tax Authority (FTA) to VAT-registered businesses. Including the client's TRN on invoices is a legal requirement under UAE VAT law for B2B transactions above AED 10,000.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 3: INVOICES -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch3" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch3','file-invoice','bg-amber-500/10 text-amber-500','Chapter 3: Tax Invoices — Creation, Line Items, Lifecycle & PDF Export','Full invoice workflow from draft to paid, VAT calculation, and PDF download.','invoice_form','plus','+ Create New Invoice','bg-gradient-to-r from-amber-500 to-amber-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>The invoice module generates fully compliant UAE tax invoices with automatic sequential numbering, multi-currency support, line-item tax calculation, and professional PDF output using your selected invoice template design.</p>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Invoice Status Lifecycle</h4>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-2xs font-bold my-3">
        <div class="p-3 rounded-xl bg-sky-50 text-sky-800 border border-sky-200 text-center"><i class="fa-solid fa-pencil block text-lg mb-1"></i>DRAFT<div class="font-normal mt-0.5 text-sky-600">Internal working copy, not delivered.</div></div>
        <div class="p-3 rounded-xl bg-blue-50 text-blue-800 border border-blue-200 text-center"><i class="fa-solid fa-paper-plane block text-lg mb-1"></i>SENT<div class="font-normal mt-0.5 text-blue-600">Delivered to client, awaiting payment.</div></div>
        <div class="p-3 rounded-xl bg-amber-50 text-amber-900 border border-amber-200 text-center"><i class="fa-solid fa-circle-half-stroke block text-lg mb-1"></i>PARTIALLY PAID<div class="font-normal mt-0.5 text-amber-700">Deposit received, balance outstanding.</div></div>
        <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-center"><i class="fa-solid fa-circle-check block text-lg mb-1"></i>PAID<div class="font-normal mt-0.5 text-emerald-700">Fully settled. Closed.</div></div>
        <div class="p-3 rounded-xl bg-rose-50 text-rose-800 border border-rose-200 text-center"><i class="fa-solid fa-clock block text-lg mb-1"></i>OVERDUE<div class="font-normal mt-0.5 text-rose-700">Past due date, unpaid.</div></div>
    </div>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mt-4">VAT / Tax Calculation Logic</h4>
    <div class="bg-slate-950 text-slate-100 p-4 rounded-xl font-mono text-xs space-y-1 border border-slate-800">
        <div class="text-amber-400 font-bold">// INVOICE TAX CALCULATION EXAMPLE (UAE 5% VAT)</div>
        <div>Subtotal (before tax):   <strong class="text-white">AED 10,000.00</strong></div>
        <div>UAE VAT @ 5%:            <strong class="text-amber-300">AED 500.00</strong></div>
        <div>Discount Applied:        <strong class="text-rose-400">- AED 0.00</strong></div>
        <div class="border-t border-slate-700 pt-1">Invoice Total Due:       <strong class="text-emerald-400">AED 10,500.00</strong></div>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Invoices</h4>
<div class="space-y-2">
<?php
faq("How do I create a new invoice?", "Click <strong>'+ New Invoice'</strong> in the topbar or mobile FAB button. Select the Client from your directory, set the Invoice Date and Due Date, add line items (Description, Quantity, Unit Price), apply any discount or VAT %, and click <strong>Save Invoice</strong>. The system auto-assigns a sequential invoice number (e.g. <code>OS-INV-20260810-001</code>).");
faq("Can I add multiple line items on one invoice?", "Yes — click <strong>'+ Add Line Item'</strong> to append additional rows. Each line item has its own Description, Quantity, and Unit Price fields. The subtotal for each line is automatically calculated (Qty × Unit Price). All line totals aggregate to the invoice Subtotal.");
faq("How do I apply a discount to an invoice?", "On the invoice form, enter a discount percentage or a fixed discount amount in the Discount field below the line items. The system automatically deducts this from the subtotal before applying VAT tax.");
faq("How do I download an invoice as a PDF?", "Open any invoice from the invoices list and click the <strong>'Download PDF'</strong> button. The PDF renders using your selected invoice template design with your company logo, branding colors, and all line items formatted professionally.");
faq("Can I email an invoice directly to my client?", "Yes — click <strong>'Send via Email'</strong> on the invoice view page. The email is sent from your configured SMTP mail server with a professional HTML template containing the invoice details and a PDF attachment.");
faq("What happens when an invoice becomes overdue?", "Once the invoice Due Date passes without full payment, the status automatically changes to <strong>OVERDUE</strong>. This appears as a red badge on the invoice and feeds into the AR Aging Report under the 30/60/90-day aging buckets.");
faq("Can I edit an invoice after saving it?", "Yes — invoices can be edited from the invoice view page as long as they haven't been fully paid or voided. Click the <strong>Edit</strong> button to modify line items, dates, or amounts. All changes are logged in the activity audit trail.");
faq("How do I void or cancel an invoice?", "Open the invoice and click <strong>Void Invoice</strong>. This marks the invoice as cancelled without deleting it, preserving the audit record. A voided invoice cannot be re-opened; create a new invoice if re-billing is needed.");
faq("Can I duplicate an existing invoice?", "Yes — on the invoice view page, click <strong>'Duplicate Invoice'</strong> to create a new draft copy with the same client, line items, and amounts. Update the date and any modified amounts before saving the new copy.");
faq("Is there a client-facing public invoice link?", "Yes — every invoice has a unique public URL (e.g. <code>/public_invoice?token=abc123</code>) that you can share with your client. They can view the invoice, download the PDF, and pay online without needing an account login.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 4: PROPOSALS -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch4" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch4','file-signature','bg-purple-500/10 text-purple-500','Chapter 4: Commercial Proposals & Quotations','Creating, sending, and converting proposals to invoices.','quotes','file-signature','Open Proposals','bg-purple-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>Proposals (also called Quotations) are pre-invoice commercial documents sent to prospective clients for approval before the engagement begins. Once a client approves a proposal, it can be <strong>one-click converted</strong> into a full tax invoice.</p>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Proposal Lifecycle</h4>
    <div class="flex items-center space-x-2 text-2xs font-bold flex-wrap gap-2 my-3">
        <div class="px-3 py-2 rounded-xl bg-sky-50 text-sky-800 border border-sky-200">Draft</div>
        <i class="fa-solid fa-arrow-right text-slate-400"></i>
        <div class="px-3 py-2 rounded-xl bg-blue-50 text-blue-800 border border-blue-200">Sent to Client</div>
        <i class="fa-solid fa-arrow-right text-slate-400"></i>
        <div class="px-3 py-2 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200">Accepted</div>
        <i class="fa-solid fa-arrow-right text-slate-400"></i>
        <div class="px-3 py-2 rounded-xl bg-amber-50 text-amber-800 border border-amber-200">Converted to Invoice</div>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Proposals & Quotations</h4>
<div class="space-y-2">
<?php
faq("What is the difference between a Proposal and an Invoice?", "A <strong>Proposal (Quotation)</strong> is a pre-approval commercial offer sent to a client before work begins. It has no legal payment obligation until accepted. An <strong>Invoice</strong> is a legally binding payment demand issued after services are rendered or goods delivered, requiring payment by the due date.");
faq("How do I convert an accepted proposal into an invoice?", "Open the accepted Proposal from the Proposals list and click the <strong>'Convert to Invoice'</strong> button. All client details, line items, and amounts are automatically copied to a new invoice draft. Verify the dates and click Save to issue the final tax invoice.");
faq("Can I set a validity/expiry date on a proposal?", "Yes — the <strong>Valid Until</strong> date field on the Proposal form sets the expiry date. After this date, the proposal is automatically marked as Expired and can no longer be accepted without issuing a new revised version.");
faq("Can clients sign proposals electronically?", "Digital signature acceptance functionality is on the development roadmap. Currently, clients can verbally or email-confirm acceptance, after which you manually convert the proposal to an invoice.");
faq("Can I create recurring proposals for subscription clients?", "For subscription-based services, the recurring invoice engine (in the automation module) is the preferred tool. However, you can maintain a template proposal and duplicate it for each renewal period.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 5: PAYMENTS & PARTIAL INSTALLMENTS -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch5" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch5','money-bill-wave','bg-emerald-500/10 text-emerald-500','Chapter 5: Recording Payments, Partial Installments & Reconciliation','Full vs partial payments, multi-installment tracking, and general ledger posting.','index','money-bill-wave','Record Invoice Payment','bg-emerald-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>The payment module supports <strong>unlimited installment payments</strong> against any single invoice. The system accumulates all payments, calculates the running balance due, and automatically updates the invoice status in real-time.</p>

    <div class="bg-slate-950 text-slate-100 p-5 rounded-2xl border border-slate-800 space-y-3 font-mono text-xs">
        <div class="text-amber-400 font-bold uppercase tracking-wider">// 3-INSTALLMENT MILESTONE PAYMENT SCENARIO</div>
        <div>Total Invoice Value:              <strong class="text-white">AED 15,000.00</strong></div>
        <div class="border-t border-slate-800 pt-2">
            <div>Milestone 1 — Mobilisation:    <strong class="text-emerald-400">+ AED 5,000.00</strong>  → Status: <span class="bg-amber-500/20 text-amber-300 px-1.5 rounded">PARTIALLY PAID</span></div>
            <div>Milestone 2 — Mid-Delivery:    <strong class="text-emerald-400">+ AED 5,000.00</strong>  → Status: <span class="bg-amber-500/20 text-amber-300 px-1.5 rounded">PARTIALLY PAID</span></div>
            <div>Milestone 3 — Final Delivery:  <strong class="text-emerald-400">+ AED 5,000.00</strong>  → Status: <span class="bg-emerald-500/20 text-emerald-300 px-1.5 rounded">FULLY PAID ✓</span></div>
        </div>
        <div class="border-t border-slate-800 pt-2 text-slate-400">Each payment automatically posts double-entry journals: Dr Accounts Receivable / Cr Revenue Account</div>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Payments & Collections</h4>
<div class="space-y-2">
<?php
faq("How do I record a payment against an invoice?", "Open the invoice from your invoices list. Click <strong>'+ Record Payment'</strong>. Enter the Amount Received, Payment Date, and Payment Method (Bank Transfer, Stripe, Cheque, Cash). Click <strong>Save Payment</strong>. The system instantly recalculates the balance due and updates the invoice status.");
faq("My client paid AED 1,250 out of AED 2,500 — how does it work?", "Enter AED 1,250 in the payment amount field. The system records this as a partial payment, sets invoice status to <strong>PARTIALLY PAID</strong>, and shows Balance Due: AED 1,250. When the client pays the remaining AED 1,250 later, record a second payment. The invoice automatically changes to <strong>PAID</strong> when cumulative payments reach the total.");
faq("Can I record multiple partial payments against one invoice?", "Yes — you can record unlimited partial payments. Every payment is stored individually in the payment ledger with its own date, amount, and method. The system sums all payments and continuously updates the balance due.");
faq("What payment methods can I record?", "Supported payment methods include: <strong>Bank Transfer / EFT</strong>, <strong>Stripe Card Payment</strong>, <strong>Network International NGenius</strong>, <strong>Cheque / Post-Dated Cheque (PDC)</strong>, and <strong>Cash</strong>. The method is stored in the payment audit trail.");
faq("Is there a payment audit trail or receipt history?", "Yes — every recorded payment is timestamped and logged in the Payment History table visible on the invoice view page. It shows Payment Date, Amount, Method, and any notes. This audit trail is permanent and cannot be retroactively deleted.");
faq("Can I mark an invoice as paid without recording an actual payment?", "No — to maintain financial integrity, all payments must be recorded via the payment form. This ensures your General Ledger, Accounts Receivable balances, Cash Flow Statement, and AR Aging reports remain accurate.");
faq("What is the difference between PAID and PARTIALLY PAID status?", "<strong>PARTIALLY PAID</strong> means cumulative payments are greater than zero but less than the total invoice amount — there is still an outstanding balance. <strong>PAID</strong> means cumulative payments equal or exceed the total invoice amount — the invoice is fully settled and closed.");
faq("Can I issue a refund through the platform?", "To record a refund, create a Credit Note (negative invoice) for the refund amount linked to the client. This reverses the accounting entries and reduces the client's outstanding balance.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 6: TEMPLATES & DESIGN CUSTOMIZER -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch6" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch6','wand-magic-sparkles','bg-pink-500/10 text-pink-500','Chapter 6: Invoice Template Customizer — 10+ Designer Layouts','Choosing flagship templates, custom brand colors, logo upload, and auto-save.','invoice_customize','wand-magic-sparkles','Open Template Customizer','bg-purple-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>The Invoice Customizer gives you full control over how your invoices look — choose from 10+ professionally engineered templates, or customize your own with company colors, logo, and fonts.</p>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Available Invoice Template Designs</h4>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
        <?php
        $templates = [
            ['OneSol Executive Gold', 'bg-amber-500', 'Flagship corporate layout. Dark navy header with gold accent typography. Premium commercial notes and structured totals grid.'],
            ['Modern Minimalist', 'bg-slate-700', 'Ultra-clean corporate design with subtle line dividers, status pills, and modern whitespace layout.'],
            ['Sleek Dark Mode', 'bg-slate-900', 'High-tech glassmorphism theme for SaaS companies, software agencies, and tech startups.'],
            ['Elegant Serif', 'bg-rose-700', 'Classic legal-style serif typography. Ideal for law firms, consultancies, and professional services.'],
            ['Creative Vibrant', 'bg-purple-600', 'Bold colorful gradient design for creative agencies, marketing firms, and design studios.'],
            ['Swiss Grid', 'bg-blue-700', 'Precision grid layout inspired by Swiss design principles. Clean, structured, and mathematically aligned.'],
            ['Corporate Executive', 'bg-indigo-700', 'Formal executive design with double-column structure, suitable for Fortune 500-style enterprise clients.'],
            ['Two-Column Split', 'bg-teal-600', 'Modern two-column layout with sidebar party cards and centralized totals section.'],
            ['Borderless Clean', 'bg-emerald-700', 'Minimalist borderless design with full-bleed sections and generous whitespace.'],
            ['Tech Glassmorphism', 'bg-violet-700', 'Next-generation frosted glass UI with backdrop blur effects and luminescent gradients.'],
            ['Compact Thermal', 'bg-gray-700', 'Print-optimized compact layout for thermal receipt printers and narrow-format PDF outputs.'],
        ];
        foreach ($templates as [$name, $bg, $desc]) {
            echo "<div class=\"bg-slate-50 p-3.5 rounded-xl border border-slate-200\">";
            echo "<div class=\"h-2 rounded-full $bg mb-2 w-full opacity-80\"></div>";
            echo "<strong class=\"text-slate-900 block mb-1\">$name</strong><span class=\"text-slate-500\">$desc</span>";
            echo "</div>";
        }
        ?>
    </div>

    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-xs text-emerald-900 font-semibold mt-2">
        <i class="fa-solid fa-floppy-disk text-emerald-600 mr-1.5"></i> <strong>Instant Auto-Save:</strong> Selecting any template from the dropdown instantly saves to your database. All future invoices automatically use your chosen design. No refresh or submit required.
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Templates & Branding</h4>
<div class="space-y-2">
<?php
faq("How do I change the default invoice template?", "Go to <strong>Management → 10+ Invoice Customizer Settings</strong>. Click the dropdown and select any of the 11 available template designs. Your selection is instantly saved to the database — no save button needed. All future invoices will use this template for PDF generation.");
faq("Can I use a different template for individual invoices?", "Yes — while a default template applies to all new invoices, you can switch templates per-invoice from the invoice creation form dropdown if the option is enabled.");
faq("How do I add my company logo?", "Go to <strong>Management → Dynamic Branding & Colors</strong>. Click the logo upload area, select your PNG or JPG logo file (recommended: 400×120px transparent PNG), and click Save Branding. The logo appears automatically on all invoice templates.");
faq("Can I customize the invoice colors to match my brand?", "Yes — in <strong>Management → Dynamic Branding & Colors</strong>, you can set your Primary Brand Color, Secondary Color, and Accent Color using hex color picker inputs. These colors dynamically update throughout the platform interface and invoice templates.");
faq("Do invoice templates work correctly on printed PDFs?", "Yes — every template is engineered with self-contained inline CSS styles specifically optimized for PDF rendering. Colors, fonts, borders, and layout grids are all print-safe and render identically in browser print preview and PDF download.");
faq("Can I add custom content to invoice templates (e.g. bank details, terms)?", "Yes — on the invoice creation form, there are fields for <strong>Notes to Client</strong> and <strong>Payment Terms</strong>. Content entered here appears in the designated section of every template. You can include bank account details, IBAN numbers, payment instructions, and legal terms.");
faq("Can I white-label the platform with my own brand for clients?", "Yes — the platform is fully white-label capable. Set your company name, logo, and brand colors. All client-facing pages (invoice views, proposal links, and public portal) display only your company branding without any OneSol references.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 7: WORKSPACES & BRANCHES -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch7" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch7','sitemap','bg-sky-500/10 text-sky-500','Chapter 7: Multi-Tenant Workspaces & Regional Branch Accounts','Creating isolated branch sub-accounts, database boundaries, and workspace switching.','subaccounts','sitemap','Manage Workspaces','bg-sky-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>The platform is built on a <strong>multi-tenant database isolation model</strong>. Each workspace (tenant) has completely separate clients, invoices, ledger accounts, settings, and user permissions — even if operated by the same parent company.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200"><i class="fa-solid fa-building text-amber-500 mr-2"></i><strong>Dubai HQ Workspace</strong> — Main headquarters account. Invoices in AED. UAE VAT registered.</div>
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200"><i class="fa-solid fa-building text-blue-500 mr-2"></i><strong>Abu Dhabi Branch</strong> — Regional branch with separate client list and independent P&L reporting.</div>
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200"><i class="fa-solid fa-building text-emerald-500 mr-2"></i><strong>Riyadh Subsidiary</strong> — Saudi Arabia sub-account in SAR with local tax number.</div>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Workspaces & Branches</h4>
<div class="space-y-2">
<?php
faq("What is a workspace?", "A <strong>workspace</strong> is an isolated company account within the platform. Each workspace has its own clients, invoices, ledger, users, settings, and billing data. Switching workspaces changes the entire active company context.");
faq("How do I switch between workspaces?", "Click the <strong>company name pill</strong> in the top header bar (e.g., 'Dubai HQ ▾'). A dropdown shows all workspaces you have access to. Click a workspace name to instantly switch — all data displayed updates to reflect the selected branch account.");
faq("Can data be shared between workspaces?", "No — workspaces are fully isolated by design. A client in 'Dubai HQ' is not visible in 'Abu Dhabi Branch'. This ensures complete data segregation between different regional entities, preventing accidental cross-contamination of financial records.");
faq("How do I create a new branch workspace?", "Go to <strong>Management → Workspaces & Branches</strong> and click <strong>'+ Create New Branch'</strong>. Enter the branch name, currency, and registered address. A complete double-entry Chart of Accounts is automatically seeded for the new branch.");
faq("Can I have separate users for each workspace?", "Yes — user permissions are workspace-specific. A user can be an Admin in Dubai HQ but only a Viewer in the Abu Dhabi Branch. Manage this under <strong>Management → Team & Roles</strong>.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 8: TEAM & ROLES -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch8" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch8','user-shield','bg-indigo-500/10 text-indigo-500','Chapter 8: Team Management, User Accounts & Permission Roles','Adding staff accounts, assigning roles, enforcing access controls, and user quotas.','users','users','Manage Team Members','bg-indigo-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>The team management module lets you add staff accounts with granular permission roles. Each user is assigned one of four predefined roles that determine what features they can access within their workspace.</p>

    <div class="overflow-x-auto">
        <table class="w-full text-xs border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-700 font-bold uppercase text-2xs">
                    <th class="px-3 py-2 text-left rounded-tl-lg">Permission</th>
                    <th class="px-3 py-2 text-center">Owner</th>
                    <th class="px-3 py-2 text-center">Admin</th>
                    <th class="px-3 py-2 text-center">Accountant</th>
                    <th class="px-3 py-2 text-center rounded-tr-lg">Viewer</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                $perms = [
                    ['Create & Edit Invoices', true, true, false, false],
                    ['Record Payments', true, true, true, false],
                    ['Manage Clients', true, true, false, false],
                    ['View Financial Reports', true, true, true, true],
                    ['Manage Team Users', true, false, false, false],
                    ['Configure SMTP & Security', true, false, false, false],
                    ['SaaS Subscription Settings', true, false, false, false],
                    ['View Invoices (Read-Only)', true, true, true, true],
                ];
                foreach ($perms as $row) {
                    $label = array_shift($row);
                    echo "<tr class=\"hover:bg-slate-50\">";
                    echo "<td class=\"px-3 py-2 font-semibold text-slate-700\">$label</td>";
                    foreach ($row as $allowed) {
                        echo $allowed
                            ? "<td class=\"px-3 py-2 text-center text-emerald-600\"><i class='fa-solid fa-circle-check'></i></td>"
                            : "<td class=\"px-3 py-2 text-center text-slate-300\"><i class='fa-solid fa-circle-minus'></i></td>";
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Team & User Management</h4>
<div class="space-y-2">
<?php
faq("How do I add a new team member?", "Go to <strong>Management → Team & Roles</strong>. Click <strong>'+ Invite Team Member'</strong>. Enter their email address, assign a Permission Role (Admin, Accountant, or Viewer), and click Send Invite. They receive an email with a login setup link.");
faq("What is the maximum number of users on my plan?", "The maximum team size depends on your SaaS subscription tier: <strong>Starter Plan</strong> — 2 users, <strong>Professional Plan</strong> — 10 users, <strong>Enterprise Plan</strong> — unlimited users. Attempting to add beyond the quota shows a plan upgrade prompt.");
faq("Can I change a user's role after they've been added?", "Yes — go to <strong>Management → Team & Roles</strong>, find the user, and click the role dropdown to reassign. Changes take effect immediately on their next page load.");
faq("How do I deactivate a staff account?", "Find the user in <strong>Management → Team & Roles</strong> and click <strong>Deactivate Account</strong>. Their login is disabled but their historical activity remains in audit logs. Reactivate anytime.");
faq("Can multiple users work simultaneously?", "Yes — the platform fully supports concurrent multi-user access. Locking mechanisms prevent simultaneous editing of the same invoice record.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 9: 2FA SECURITY -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch9" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch9','shield-halved','bg-indigo-500/10 text-indigo-500','Chapter 9: Two-Factor Authentication (2FA) & Account Security','6-digit cryptographic OTP codes, mandatory workspace policies, resend, and lockout.','security','shield-halved','Configure 2FA Security','bg-indigo-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>Two-Factor Authentication (2FA) adds a critical second layer of security beyond just a password. Even if a password is compromised, unauthorized access is blocked without the OTP code.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
            <h4 class="font-bold text-slate-900"><i class="fa-solid fa-user-shield text-indigo-600 mr-2"></i>How 2FA Works</h4>
            <ol class="list-decimal pl-4 space-y-1 text-slate-600">
                <li>User enters email and password on login screen.</li>
                <li>If 2FA is enabled, a 6-digit OTP code is emailed to their registered address.</li>
                <li>User opens the 2FA verification page and enters the OTP.</li>
                <li>OTP codes expire after <strong>15 minutes</strong> for security.</li>
                <li>Entering 3 incorrect OTPs triggers a temporary lockout.</li>
            </ol>
        </div>
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
            <h4 class="font-bold text-slate-900"><i class="fa-solid fa-building-shield text-amber-600 mr-2"></i>Workspace Enforcement Policy</h4>
            <p class="text-slate-600">Workspace Admins can mandate 2FA for all team members. When enabled, any user without 2FA configured is forced to enable it on their next login before accessing any platform features.</p>
        </div>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — 2FA & Security</h4>
<div class="space-y-2">
<?php
faq("How do I enable 2FA for my account?", "Go to <strong>Management → 2FA & Security Settings</strong>. Toggle the <strong>'Enable 2FA for My Account'</strong> switch. From your next login, a 6-digit OTP code will be emailed to you whenever you attempt to log in.");
faq("I didn't receive my OTP code — what do I do?", "Check your spam/junk folder first. If not found, on the 2FA verification screen click <strong>'Resend Verification Code'</strong>. A new 6-digit code is generated and emailed. Ensure your Custom SMTP settings are correctly configured under <strong>Management → SMTP Settings</strong>.");
faq("The OTP code I entered is showing as invalid?", "OTP codes expire after <strong>15 minutes</strong> from generation. If the code has expired, click <strong>'Resend Code'</strong> to generate a fresh one. Also verify you're entering the most recently received code if multiple were requested.");
faq("Can the workspace admin turn off 2FA?", "Individual users can disable 2FA on their own accounts unless the workspace Admin has enabled the <strong>Mandatory 2FA Policy</strong>. Under mandatory policy, all users are required to complete 2FA and cannot disable it individually.");
faq("What happens if I'm locked out due to incorrect OTP entries?", "After 3 consecutive failed OTP attempts, your account is temporarily locked for security. Contact your workspace Administrator to unlock your account from the Team Management screen.");
faq("Is 2FA via authenticator app supported?", "Currently, OTP codes are delivered via email using your configured SMTP server. TOTP authenticator app support (Google Authenticator, Authy) is planned for a future release.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 10: CUSTOM SMTP EMAIL -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch10" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch10','server','bg-cyan-500/10 text-cyan-500','Chapter 10: Custom Tenant SMTP Mail Server Configuration','Connecting Gmail, Office 365, cPanel, Amazon SES, and live test delivery.','email_settings','server','Configure SMTP Settings','bg-cyan-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>Connect your company's outbound mail server so all platform emails (invoice delivery, 2FA OTPs, payment receipts) are sent from <em>your domain</em> (e.g., <code>billing@yourcompany.ae</code>) with your professional branding.</p>

    <div class="overflow-x-auto">
        <table class="w-full text-xs border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-700 font-bold text-2xs uppercase">
                    <th class="px-3 py-2 text-left">Mail Provider</th>
                    <th class="px-3 py-2 text-left">SMTP Host</th>
                    <th class="px-3 py-2 text-center">Port</th>
                    <th class="px-3 py-2 text-center">Encryption</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs font-mono">
                <tr class="hover:bg-slate-50"><td class="px-3 py-2 font-sans font-bold">Gmail (Google Workspace)</td><td class="px-3 py-2">smtp.gmail.com</td><td class="px-3 py-2 text-center">587</td><td class="px-3 py-2 text-center font-sans font-bold text-blue-600">STARTTLS</td></tr>
                <tr class="hover:bg-slate-50"><td class="px-3 py-2 font-sans font-bold">Microsoft Office 365</td><td class="px-3 py-2">smtp.office365.com</td><td class="px-3 py-2 text-center">587</td><td class="px-3 py-2 text-center font-sans font-bold text-blue-600">STARTTLS</td></tr>
                <tr class="hover:bg-slate-50"><td class="px-3 py-2 font-sans font-bold">cPanel / Hosting Mail</td><td class="px-3 py-2">mail.yourdomain.com</td><td class="px-3 py-2 text-center">465</td><td class="px-3 py-2 text-center font-sans font-bold text-emerald-600">SSL/TLS</td></tr>
                <tr class="hover:bg-slate-50"><td class="px-3 py-2 font-sans font-bold">Amazon SES</td><td class="px-3 py-2">email-smtp.us-east-1.amazonaws.com</td><td class="px-3 py-2 text-center">587</td><td class="px-3 py-2 text-center font-sans font-bold text-blue-600">STARTTLS</td></tr>
                <tr class="hover:bg-slate-50"><td class="px-3 py-2 font-sans font-bold">Mailgun</td><td class="px-3 py-2">smtp.mailgun.org</td><td class="px-3 py-2 text-center">587</td><td class="px-3 py-2 text-center font-sans font-bold text-blue-600">STARTTLS</td></tr>
            </tbody>
        </table>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — SMTP Email Configuration</h4>
<div class="space-y-2">
<?php
faq("Why do I need to configure SMTP?", "Without SMTP configured, the platform cannot send invoice emails, 2FA OTP codes, or payment receipts. Configuring your company SMTP server ensures all emails are delivered from your professional domain address (e.g. <code>billing@company.ae</code>) rather than a generic system address.");
faq("How do I set up Gmail SMTP?", "In Gmail/Google Workspace: (1) Enable 2-Step Verification on your Google account. (2) Generate an <strong>App Password</strong> under Security → App Passwords (use 'Mail' + 'Other' device). (3) In SMTP Settings enter: Host: <code>smtp.gmail.com</code>, Port: <code>587</code>, Encryption: <code>STARTTLS</code>, Username: your Gmail address, Password: the 16-digit App Password generated.");
faq("Why should I use an App Password instead of my Gmail password?", "Google's security policy blocks SMTP access with your regular account password. App Passwords are specially generated 16-digit tokens that bypass this restriction while keeping your main account password secure and not exposed in server configuration.");
faq("How do I test that my SMTP configuration is working?", "After saving your SMTP credentials, click the <strong>'Send Test Email'</strong> button on the SMTP Settings page. Enter any destination email address and click Send. If successful, you'll receive a test email confirming the SMTP connection is working correctly.");
faq("My test email is going to spam — how do I fix this?", "Email deliverability depends on your domain's DNS records. Ensure your domain has: (1) <strong>SPF record</strong> authorising your SMTP server to send mail. (2) <strong>DKIM record</strong> for cryptographic email signing. (3) <strong>DMARC record</strong> for policy enforcement. Contact your DNS/hosting provider to add these records.");
faq("Can each workspace use a different SMTP mail server?", "Yes — SMTP settings are per-workspace (per-tenant). The Dubai HQ workspace can use <code>billing@dubai.company.ae</code> while the Abu Dhabi Branch uses <code>billing@abudhabi.company.ae</code>. Each workspace independently stores and uses its own SMTP credentials.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 11: REST API -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch11" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch11','code','bg-emerald-500/10 text-emerald-500','Chapter 11: External REST API — Integration, Keys & Live Test Playground','Programmatic onboarding, invoice creation, payment sync, and cURL examples.','api_playground','play','Launch API Playground','bg-gradient-to-r from-amber-400 to-amber-500 text-slate-950'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>The REST API enables external systems, mobile apps, and integrations (Zapier, n8n, custom CRM) to interact with the platform programmatically — creating sub-accounts, issuing invoices, and recording payments without manual UI interaction.</p>

    <div class="bg-slate-950 text-slate-100 p-4 rounded-xl font-mono text-xs space-y-2 border border-slate-800">
        <div class="text-amber-400 font-bold">// EXAMPLE: CREATE INVOICE VIA REST API</div>
        <div class="text-slate-400">curl -X POST "https://yourdomain.com/api?action=create_invoice&api_key=os_abc123" \</div>
        <div class="text-slate-400">  -H "Content-Type: application/json" \</div>
        <div class="text-slate-400">  -d '{</div>
        <div class="text-slate-300">&nbsp;&nbsp;"client_id": 5,</div>
        <div class="text-slate-300">&nbsp;&nbsp;"invoice_date": "2026-08-10",</div>
        <div class="text-slate-300">&nbsp;&nbsp;"valid_until": "2026-08-24",</div>
        <div class="text-slate-300">&nbsp;&nbsp;"items": [{"description": "Consulting Fees", "qty": 1, "unit_price": 5000}]</div>
        <div class="text-slate-400">}'</div>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — REST API Integration</h4>
<div class="space-y-2">
<?php
faq("Where do I find my REST API Access Key?", "Navigate to <strong>Management → Tenant & Trial Manager</strong>. Your unique <code>os_...</code> API Access Key is displayed in the Registered Tenants table. Click the copy button to copy it. You can also regenerate a new key if the current one is compromised.");
faq("How do I authenticate REST API requests?", "Pass your API key in the request as a query parameter (<code>?api_key=os_abc123</code>) or as a request header (<code>X-API-Key: os_abc123</code>) or as an Authorization Bearer header (<code>Authorization: Bearer os_abc123</code>). All three methods are supported.");
faq("What endpoints are available in the REST API?", "Available endpoints: <code>POST create_tenant</code> (onboard new workspace), <code>GET get_tenant_status</code> (trial info), <code>GET list_invoices</code> (invoice ledger), <code>POST create_invoice</code> (new invoice), <code>GET list_clients</code> (client directory), <code>POST create_client</code> (new client), <code>POST record_payment</code> (log payment).");
faq("Can I test API calls without writing code?", "Yes — the <strong>Interactive API Playground</strong> at <code>/api_playground</code> lets you select any endpoint, edit the JSON payload visually, click <strong>'Execute Live API Request'</strong>, and see the live HTTP response with status code and formatted JSON output — no coding required.");
faq("What format do API responses use?", "All API responses return standard <code>application/json</code> with fields: <code>success</code> (boolean), <code>message</code> (human-readable), <code>data</code> (response payload), and <code>timestamp</code>. HTTP status codes follow REST standards: 200 (OK), 201 (Created), 400 (Bad Request), 401 (Unauthorized), 404 (Not Found), 500 (Server Error).");
faq("Can I integrate the API with Zapier or n8n workflows?", "Yes — use the <strong>Webhook/HTTP Request</strong> action in Zapier or n8n to call any API endpoint. Trigger automated invoice creation when a CRM deal closes, or trigger payment recording when a bank webhook fires.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 12: SUBSCRIPTION & TRIAL -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch12" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch12','crown','bg-amber-500/10 text-amber-500','Chapter 12: SaaS Subscription Tiers, Free Trials & Billing Management','Plan comparison, trial extensions, user quotas, and internal unlimited status.','subscriptions_admin','crown','Manage SaaS Plans','bg-amber-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <div class="overflow-x-auto">
        <table class="w-full text-xs border-collapse">
            <thead>
                <tr class="bg-slate-900 text-white font-bold text-2xs uppercase">
                    <th class="px-3 py-2.5 text-left">Feature</th>
                    <th class="px-3 py-2.5 text-center">Starter</th>
                    <th class="px-3 py-2.5 text-center bg-amber-700">Professional</th>
                    <th class="px-3 py-2.5 text-center">Enterprise</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
                <?php
                $rows = [
                    ['Max Team Users', '2', '10', 'Unlimited'],
                    ['Invoices Per Month', '50', '500', 'Unlimited'],
                    ['Custom SMTP Email', '✓', '✓', '✓'],
                    ['2FA Security', '✓', '✓', '✓'],
                    ['REST API Access', '✗', '✓', '✓'],
                    ['Multi-Workspace Branches', '1', '5', 'Unlimited'],
                    ['Financial Reports', 'Basic', 'Full Suite', 'Full Suite + Export'],
                    ['Priority Support', '✗', '✓', 'Dedicated Manager'],
                ];
                foreach ($rows as [$feature, $s, $p, $e]) {
                    echo "<tr class='hover:bg-slate-50'>";
                    echo "<td class='px-3 py-2 font-semibold text-slate-700'>$feature</td>";
                    echo "<td class='px-3 py-2 text-center text-slate-600'>$s</td>";
                    echo "<td class='px-3 py-2 text-center font-bold text-amber-800 bg-amber-50'>$p</td>";
                    echo "<td class='px-3 py-2 text-center font-bold text-slate-800'>$e</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Subscriptions & Trials</h4>
<div class="space-y-2">
<?php
faq("How long is the free trial period?", "New workspaces start with a configurable free trial — default is <strong>4 months</strong>. During the trial, all plan features are fully accessible. The trial countdown is displayed in the orange banner at the top of every page when fewer than 30 days remain.");
faq("What happens when my free trial expires?", "After trial expiry, invoice creation is limited and some advanced features are restricted. A prominent expiry notice appears on all pages. You can continue to view existing invoices and reports. Upgrade your subscription to restore full access.");
faq("Can the trial period be extended?", "Yes — workspace Administrators can request a trial extension from their account manager. In the SaaS Admin panel, administrators can grant <strong>+4 Months Free</strong> or <strong>+6 Months Free</strong> extensions to any tenant account with a single click.");
faq("What is 'Internal Unlimited / Lifetime' status?", "<strong>Lifetime status</strong> grants permanent unlimited access to a workspace — bypassing all subscription checks, invoice quotas, and user caps. This is reserved for the operator's own headquarters workspace and close internal partner accounts.");
faq("How do I upgrade my subscription plan?", "Go to <strong>Management → SaaS Subscription & Billing</strong> to compare plan tiers and initiate an upgrade. Contact your account manager to process the upgrade payment and have the new plan activated immediately.");
faq("What is the Max Team Users quota?", "Each subscription plan limits the total number of user accounts within a workspace. For example, the Starter Plan allows 2 users. When the quota is reached, adding additional team members shows a plan upgrade prompt. The workspace Owner does not count toward the user quota.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 13: FINANCIAL REPORTS -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch13" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch13','chart-line','bg-rose-500/10 text-rose-500','Chapter 13: Double-Entry Financial Statements & Accounting Reports','P&L, Balance Sheet, Cash Flow, AR Aging, Tax/VAT returns, and General Ledger.','reports_pnl','chart-line','Open Financial Reports','bg-rose-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
        <?php
        $reports = [
            ['Profit & Loss (P&L)', 'chart-line', 'blue', 'reports_pnl', 'Revenue minus expenses = Net Profit or Loss over any custom date range. Required for income tax filings.'],
            ['Balance Sheet', 'scale-balanced', 'emerald', 'reports_balance_sheet', 'Total Assets = Liabilities + Equity. Real-time snapshot of financial position.'],
            ['Cash Flow Statement', 'money-bill-transfer', 'amber', 'reports_cashflow', 'Operating, investing, and financing cash inflows and outflows.'],
            ['AR Aging Report', 'clock', 'purple', 'reports_aging', 'Overdue receivables categorized into 30, 60, 90, 90+ day aging buckets for collections.'],
            ['Tax / VAT Return', 'building-columns', 'indigo', 'reports_tax', 'UAE VAT output/input aggregation for Federal Tax Authority (FTA) quarterly filing.'],
            ['General Ledger', 'book', 'slate', 'journal', 'Complete double-entry journal of every debit and credit transaction across all accounts.'],
        ];
        foreach ($reports as [$name, $icon, $c, $url, $desc]) {
            echo "<div class='bg-slate-50 p-3.5 rounded-xl border border-slate-200'>";
            echo "<i class='fa-solid fa-$icon text-$c-500 mr-2'></i><strong class='text-slate-900'>$name</strong>";
            echo "<p class='text-slate-500 mt-1'>$desc</p>";
            echo "<a href='$url' class='text-2xs font-bold text-$c-600 hover:underline block mt-2'>Open $name →</a>";
            echo "</div>";
        }
        ?>
    </div>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Financial Statements</h4>
<div class="space-y-2">
<?php
faq("What is a Profit & Loss (P&L) report?", "The Profit & Loss statement calculates your business's financial performance: total <strong>Revenue</strong> (invoices issued) minus total <strong>Operating Expenses</strong> = <strong>Net Profit</strong> (positive) or <strong>Net Loss</strong> (negative). Filter by any date range (monthly, quarterly, annual) for income tax or management reporting.");
faq("What does the Balance Sheet tell me?", "The Balance Sheet shows your company's financial position at a specific point in time: <strong>Assets</strong> (what you own — cash, receivables, equipment), <strong>Liabilities</strong> (what you owe — payables, loans), and <strong>Equity</strong> (net worth). By accounting law: Total Assets must always equal Total Liabilities + Equity.");
faq("What is the AR Aging Report used for?", "The Accounts Receivable (AR) Aging Report shows <strong>which clients owe you money</strong> and <strong>how overdue their payments are</strong>, categorized into buckets: <em>Current (0-30 days)</em>, <em>Late (31-60 days)</em>, <em>Very Late (61-90 days)</em>, and <em>Critical (90+ days)</em>. Use this to prioritize collection calls.");
faq("How do I generate a UAE VAT return?", "Go to <strong>Reports → Tax / VAT Return</strong>. Select the VAT reporting period (Q1, Q2, Q3, or Q4). The system automatically aggregates <strong>Output VAT</strong> collected on invoices and <strong>Input VAT</strong> paid on expenses to compute the Net VAT Payable to the UAE FTA.");
faq("What is double-entry accounting?", "Double-entry accounting means every financial transaction creates two equal entries: a <strong>Debit</strong> on one account and a <strong>Credit</strong> on another. Example: Recording a client payment of AED 5,000 Debits the Bank Account (money received) and Credits Accounts Receivable (balance cleared). This ensures the accounting equation is always balanced.");
faq("Can I export reports to Excel or PDF?", "Yes — most financial reports have an <strong>Export to PDF</strong> and <strong>Download Excel (CSV)</strong> button in the report header. These exports include the full report data formatted for accountant review or external filing submissions.");
faq("How do I view the Chart of Accounts?", "Go to <strong>Reports → Chart of Accounts</strong>. This displays the complete list of all ledger accounts organised by account type (Assets, Liabilities, Equity, Revenue, Expenses) with their current balances. You can add custom accounts or edit existing account names.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 14: EXPENSES -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch14" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
<?php chapter_header('ch14','receipt','bg-orange-500/10 text-orange-500','Chapter 14: Expense Tracking & Vendor Bills','Logging business operating expenses, input VAT, receipt uploads, and P&L impact.','expenses','receipt','Open Expense Tracker','bg-orange-600'); ?>

<div class="text-sm text-slate-600 space-y-4 mb-6">
    <p>The Expense module captures all business operating costs — office rent, utilities, supplier payments, salaries — and posts them as debits to the appropriate General Ledger expense accounts, reducing net profit.</p>

    <ul class="list-disc pl-5 space-y-1 text-xs">
        <li>Record expenses with category (e.g., Office Rent, Software Subscription, Marketing).</li>
        <li>Attach receipt images or PDF uploads as documentary evidence.</li>
        <li>Flag input VAT on supplier invoices for VAT return recovery claims.</li>
        <li>All expenses automatically flow into the P&L Expenses section and Cash Flow Statement.</li>
    </ul>
</div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Client FAQs — Expense Management</h4>
<div class="space-y-2">
<?php
faq("How do I log a new business expense?", "Go to <strong>Expenses</strong> in the top navigation. Click <strong>'+ Record Expense'</strong>. Enter: Expense Category, Vendor/Supplier Name, Amount, Date, VAT amount (if applicable), and any notes. Upload a receipt photo or PDF if available. Click Save.");
faq("Does recording expenses affect my P&L report?", "Yes — every recorded expense immediately appears in your Profit & Loss statement under the Expenses section. Higher expenses reduce your reported net profit. Ensure expenses are correctly categorized to maintain accurate financial reporting.");
faq("Can I claim VAT on my business expenses?", "Yes — when recording a supplier expense, enter the input VAT amount paid. This feeds into the Tax/VAT Return report as <strong>Input Tax</strong> which is offset against Output VAT collected on your invoices, reducing your net VAT payable.");
faq("Can I attach receipts to expense records?", "Yes — the expense form includes a file upload field. Attach PNG, JPG, or PDF receipt files as documentary evidence. Attached files are stored alongside the expense record for audit trail purposes and can be downloaded anytime.");
?>
</div>
</section>

<!-- ═══════════════════════════════════════════════ -->
<!-- CHAPTER 15: GENERAL TROUBLESHOOTING FAQ -->
<!-- ═══════════════════════════════════════════════ -->
<section id="ch15" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-circle-question"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 15: General Troubleshooting & Frequently Asked Questions</h2>
                <p class="text-xs text-slate-500">Common issues, errors, and how to resolve them quickly.</p>
            </div>
        </div>
    </div>

<h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Common Issues & Solutions</h4>
<div class="space-y-2">
<?php
faq("The page is showing a 'Permission Denied' error — what does it mean?", "This means your user account's Permission Role does not have access to the feature you're trying to use. Contact your Workspace Administrator to review your role assignment under <strong>Management → Team & Roles</strong>.");
faq("I submitted a payment form but nothing happened — the page just reloaded?", "This can occur if the session expired during form submission (idle timeout). Refresh the login page, log in again, re-open the invoice, and re-submit the payment form. If the issue persists, check with your administrator that the server session lifetime is sufficiently long.");
faq("My invoice PDF is downloading but it looks like plain HTML without styling?", "This means the invoice template may be missing its inline CSS. Go to <strong>Management → 10+ Invoice Customizer Settings</strong> and reselect your preferred template from the dropdown. This updates your default template and re-applies fully styled PDF rendering.");
faq("I can see the invoice but the amounts look wrong — taxes not calculating?", "Verify that the Tax % field on the invoice form is set correctly (e.g., 5% for UAE VAT). Also confirm the line items have valid Quantity and Unit Price values. Save the invoice again to trigger a fresh calculation.");
faq("Emails are not being delivered to my client — what should I check?", "Check: (1) SMTP settings are correctly saved under <strong>SMTP Settings</strong>. (2) Use <strong>Send Test Email</strong> to verify the connection. (3) Check if the client's email address is correct on their client profile. (4) Ask the client to check their spam folder. (5) Verify your domain has SPF/DKIM/DMARC DNS records.");
faq("The application is slow to load — what can I do?", "Performance issues are usually server-side. Check: (1) Your server PHP version (8.0+ recommended). (2) MySQL query cache is enabled. (3) Apache/Nginx caching headers are configured. (4) Your server has adequate RAM for PHP-FPM worker pools.");
faq("I accidentally deleted an invoice — can I recover it?", "Invoices are never hard-deleted by the delete action — they are soft-deleted (hidden) and remain in the database. Contact your system administrator to restore the invoice record from the database. Regular database backups ensure full recovery capability.");
faq("Can I change the invoice number format?", "Invoice number auto-generation uses a configurable prefix format (e.g., <code>OS-INV-YYYYMMDD-###</code>). The prefix can be customized under workspace settings. Contact your administrator to update the invoice number sequence format.");
faq("What browser is recommended for the best experience?", "We recommend <strong>Google Chrome</strong> (latest version) or <strong>Microsoft Edge</strong> (Chromium-based) for the best experience. Firefox and Safari are also supported. Internet Explorer is not supported. Enable JavaScript for full platform functionality.");
faq("How do I contact support if I have an issue not covered in this guide?", "Please contact your account manager directly via email or WhatsApp. Provide a screenshot of any error messages, the invoice/client ID involved, and a description of the steps that led to the issue. The support team responds within 1 business day.");
?>
</div>
</section>

<!-- CHAPTER 16: DOCKER CONTAINERIZATION & DEPLOYMENT -->
<section id="ch16" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-blue-500 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-box-archive"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 16: Docker Containerization & 1-Click Deployment</h2>
                <p class="text-xs text-slate-500">Deploy the full stack (PHP 8.3, Nginx, MySQL 8, Redis 7) using Docker Compose.</p>
            </div>
        </div>
        <?php launch_btn('cache_admin', 'bolt', 'Cache Diagnostics', 'bg-blue-600') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-6">
        <strong>OneSol Invoice Manager</strong> comes with complete 1-click Docker support. You can deploy the entire production multi-container stack (web, database, and Redis cache) using a single command on Windows, Linux, or macOS.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50/50 border border-blue-200 rounded-2xl p-4">
            <div class="font-extrabold text-blue-900 text-xs mb-1"><i class="fa-solid fa-server mr-1"></i>Web Container</div>
            <p class="text-2xs text-blue-700">PHP 8.3-FPM + Nginx on Alpine Linux with GD, OPcache, and Redis extension enabled.</p>
        </div>
        <div class="bg-emerald-50/50 border border-emerald-200 rounded-2xl p-4">
            <div class="font-extrabold text-emerald-900 text-xs mb-1"><i class="fa-solid fa-database mr-1"></i>Database Container</div>
            <p class="text-2xs text-emerald-700">MySQL 8.0 with persistent volume storage (<code class="font-mono">mysql_data</code>) and healthchecks.</p>
        </div>
        <div class="bg-purple-50/50 border border-purple-200 rounded-2xl p-4">
            <div class="font-extrabold text-purple-900 text-xs mb-1"><i class="fa-solid fa-bolt mr-1"></i>Redis Cache Container</div>
            <p class="text-2xs text-purple-700">Redis 7 Alpine container for high-speed TTL caching and stampede jitter prevention.</p>
        </div>
    </div>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">1-Click Launch Commands</h4>
    <div class="bg-slate-950 text-slate-200 rounded-2xl p-5 font-mono text-xs space-y-4 mb-6">
        <div>
            <span class="text-amber-400 font-bold"># Windows (Command Prompt / PowerShell)</span>
            <div class="bg-slate-900 p-2.5 rounded-xl border border-slate-800 text-emerald-300 mt-1">docker-start.bat</div>
        </div>
        <div>
            <span class="text-amber-400 font-bold"># Linux / macOS Terminal</span>
            <div class="bg-slate-900 p-2.5 rounded-xl border border-slate-800 text-emerald-300 mt-1">chmod +x docker-start.sh && ./docker-start.sh</div>
        </div>
        <div>
            <span class="text-amber-400 font-bold"># Manual Docker Compose Command</span>
            <div class="bg-slate-900 p-2.5 rounded-xl border border-slate-800 text-emerald-300 mt-1">docker-compose up -d --build</div>
        </div>
    </div>

    <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider mb-3">Container Ports & Access Links</h4>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200"><span class="text-slate-400 text-2xs block">Web Application</span><strong class="text-slate-900">http://localhost:8080</strong></div>
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200"><span class="text-slate-400 text-2xs block">Redis Diagnostics</span><strong class="text-slate-900">http://localhost:8080/cache_admin</strong></div>
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200"><span class="text-slate-400 text-2xs block">MySQL DB (External)</span><strong class="text-slate-900">Port 3307</strong></div>
        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200"><span class="text-slate-400 text-2xs block">Redis (External)</span><strong class="text-slate-900">Port 6380</strong></div>
    </div>
</section>

<!-- CHAPTER 17: UAE FTA VAT 201 FILING & COMPLIANCE -->
<section id="ch17" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 17: UAE FTA VAT 201 Return & 7-Emirate Compliance</h2>
                <p class="text-xs text-slate-500">Official Federal Tax Authority VAT 201 filing report with Box 1a-1g Emirate breakdown.</p>
            </div>
        </div>
        <?php launch_btn('reports_vat201', 'file-invoice-dollar', 'Open VAT 201 Return', 'bg-emerald-600') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-4">
        OneSol Invoice Manager provides native compliance with the UAE Federal Tax Authority (FTA) laws under Federal Decree-Law No. (8) of 2017. All invoices automatically support 15-digit TRN validation, 5% standard VAT, zero-rated exports, exempt items, and dual AED currency conversion.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <div class="font-extrabold text-emerald-900 text-xs mb-1">Box 1: Output Tax (7 Emirates)</div>
            <p class="text-2xs text-emerald-700">Calculates standard 5% VAT sales broken down by Abu Dhabi, Dubai, Sharjah, Ajman, UAQ, RAK, and Fujairah.</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="font-extrabold text-blue-900 text-xs mb-1">Box 9: Recoverable Input Tax</div>
            <p class="text-2xs text-blue-700">Automatically sums 5% Input VAT paid on business expenses for FTA tax credit claims.</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div class="font-extrabold text-amber-900 text-xs mb-1">Box 14: Net VAT Payable</div>
            <p class="text-2xs text-amber-700">Computes Net VAT Due = Output Tax (Box 1) − Recoverable Input Tax (Box 9) with 1-click CSV export.</p>
        </div>
    </div>
</section>

<!-- CHAPTER 18: WHITELABEL CUSTOM DOMAIN & REAL-TIME DNS TESTING -->
<section id="ch18" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-globe"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 18: Whitelabel Custom Domains & Real-Time DNS Testing</h2>
                <p class="text-xs text-slate-500">Bind your custom domain (billing.yourcompany.com) with live DNS validation & SSL indicators.</p>
            </div>
        </div>
        <?php launch_btn('domain_settings', 'globe', 'Domain Settings', 'bg-blue-600') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-4">
        Enterprise tenant accounts can connect their custom branded subdomain (e.g. <code>billing.acmecorp.com</code>) to host public payment portals and client invoice views under their own brand identity.
    </p>

    <div class="bg-slate-900 text-white rounded-2xl p-5 mb-6 space-y-3 font-mono text-2xs">
        <div class="text-amber-400 font-bold font-sans text-xs">DNS CNAME Record Setup:</div>
        <div>CNAME Record: <strong>billing</strong> &nbsp;&rarr;&nbsp; Points to: <strong class="text-emerald-400"><?=e($_SERVER['HTTP_HOST'] ?? 'app.onesol.ae')?></strong></div>
        <div>Real-Time Test: Click <strong>⚡ Test & Verify DNS</strong> to execute AJAX lookup & instant SSL validation.</div>
    </div>
</section>

<!-- CHAPTER 19: CLIENT LEDGER STATEMENTS & EMAIL DISPATCH -->
<section id="ch19" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 19: Client Statement of Account & 1-Click Email Dispatch</h2>
                <p class="text-xs text-slate-500">Financial ledger statement generation & instant SMTP email delivery.</p>
            </div>
        </div>
        <?php launch_btn('clients', 'users', 'Client Directory', 'bg-amber-500') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-4">
        Generate full financial Statements of Account for any client showing opening balances, invoice history, payment records, running balance, and PDF print formatting. Send HTML tax invoices directly to client email addresses using workspace custom SMTP settings with 1 click.
    </p>
</section>

<!-- CHAPTER 20: SQL BACKUP & LIVE CALCULATION ENGINE -->
<section id="ch20" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-purple-600 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-database"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 20: One-Click SQL Database Backup & Real-Time Calculation Engine</h2>
                <p class="text-xs text-slate-500">Database backup dumps and sticky dynamic calculation summary boxes.</p>
            </div>
        </div>
        <?php launch_btn('backup_admin', 'download', 'Database Backup', 'bg-purple-600') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-4">
        Workspace administrators can download raw <code>.sql</code> database dumps anytime for local archiving and disaster recovery. On invoice and proposal forms, sticky <strong>Real-Time Summary Cards</strong> calculate subtotal, discounts (fixed or %), VAT tax, and grand totals live as values change.
    </p>
</section>

<!-- CHAPTER 21: AUTO-RECURRING INVOICES CRON ENGINE -->
<section id="ch21" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-amber-600 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-rotate"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 21: Auto-Recurring Subscription Billing Engine</h2>
                <p class="text-xs text-slate-500">Automated background cron worker (cron_recurring.php) for subscription billing schedules.</p>
            </div>
        </div>
        <?php launch_btn('cron_recurring.php?key=onesol_cron_secret_2026', 'bolt', 'Trigger Cron', 'bg-amber-600') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-4">
        Set up automated subscription schedules for retainers or monthly client contracts. The background cron script <code>cron_recurring.php</code> auto-generates sequential tax invoices, posts double-entry journal entries, and emails client PDFs automatically.
    </p>
</section>

<!-- CHAPTER 22: CLIENT SELF-SERVICE PORTAL -->
<section id="ch22" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-user-lock"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 22: Client Self-Service Portal Hub</h2>
                <p class="text-xs text-slate-500">Dedicated passwordless login portal (client_portal.php) for client statement access & online checkout.</p>
            </div>
        </div>
        <?php launch_btn('client_login', 'right-to-bracket', 'Client Portal Portal', 'bg-emerald-600') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-4">
        Clients can sign into their dedicated self-service hub using their registered billing email to view outstanding balances, download financial Statements of Account, inspect line items, and complete online card payments.
    </p>
</section>

<!-- CHAPTER 23: WHATSAPP CLOUD API GATEWAY -->
<section id="ch23" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 23: Meta WhatsApp Cloud API Gateway</h2>
                <p class="text-xs text-slate-500">Automated WhatsApp payment reminder & PDF invoice link dispatches.</p>
            </div>
        </div>
        <?php launch_btn('whatsapp_settings', 'whatsapp', 'WhatsApp Settings', 'bg-emerald-500') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-4">
        Connect your Meta WhatsApp Cloud API credentials (Phone Number ID & Access Token) under <strong>WhatsApp Settings</strong> to dispatch automated payment links and PDF tax invoices directly to client WhatsApp numbers.
    </p>
</section>

<!-- CHAPTER 24: CBUAE LIVE EXCHANGE RATE SYNC -->
<section id="ch24" class="guide-topic bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm scroll-mt-20">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-xl bg-cyan-600 text-white flex items-center justify-center font-bold text-lg">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Chapter 24: CBUAE Live Exchange Rate Auto-Sync</h2>
                <p class="text-xs text-slate-500">Automated daily exchange rate synchronization for USD, EUR, GBP, SAR to AED.</p>
            </div>
        </div>
        <?php launch_btn('cron_exchange_rates', 'arrows-rotate', 'Sync FX Rates', 'bg-cyan-600') ?>
    </div>

    <p class="text-xs text-slate-600 leading-relaxed mb-4">
        Automated daily background worker <code>cron_exchange_rates.php</code> queries official central bank rates to keep multi-currency foreign exchange rates (USD, EUR, GBP, SAR, INR to AED) updated in real-time.
    </p>
</section>

</div>

<!-- Footer -->
<div class="bg-slate-900 rounded-2xl p-6 text-white text-center mb-8">
    <i class="fa-solid fa-book-open text-amber-400 text-2xl mb-3 block"></i>
    <h3 class="font-extrabold text-lg mb-1">Need More Help?</h3>
    <p class="text-slate-400 text-xs mb-4">Contact your <?=e($brand['company_name'])?> account manager for one-on-one training, platform demos, or custom feature requests.</p>
    <div class="flex justify-center space-x-4 text-xs font-bold">
        <a href="index" class="px-4 py-2 bg-amber-500 text-slate-950 rounded-xl hover:bg-amber-400"><i class="fa-solid fa-chart-pie mr-1.5"></i>Go to Dashboard</a>
        <a href="api_playground" class="px-4 py-2 bg-slate-800 text-white rounded-xl hover:bg-slate-700 border border-slate-700"><i class="fa-solid fa-code mr-1.5 text-amber-400"></i>API Playground</a>
    </div>
</div>

<script>
function filterGuideTopics() {
    const query = document.getElementById('guide-search').value.toLowerCase().trim();
    const topics = document.querySelectorAll('.guide-topic');
    let visibleCount = 0;

    topics.forEach(topic => {
        const text = topic.innerText.toLowerCase();
        if (!query || text.includes(query)) {
            topic.classList.remove('hidden-by-search');
            visibleCount++;
        } else {
            topic.classList.add('hidden-by-search');
        }
    });

    const countEl = document.getElementById('search-result-count');
    if (query) {
        countEl.innerText = visibleCount + ' chapter' + (visibleCount !== 1 ? 's' : '') + ' found';
    } else {
        countEl.innerText = '';
    }
}

function expandAll() {
    document.querySelectorAll('details').forEach(d => d.open = true);
}

// Smooth anchor scroll with offset for sticky header
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            window.scrollTo({ top: target.offsetTop - 80, behavior: 'smooth' });
        }
    });
});
</script>

<?php page_end(); ?>
