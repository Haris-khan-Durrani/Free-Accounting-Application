<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$brand = \Core\Branding::get($pdo, $tid);

page_start('Master System & Workspace Settings');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-0.5 rounded-full bg-slate-900 text-white font-black text-xs uppercase tracking-wider">Control Center</span>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Master Workspace & System Settings</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Centralized control center to configure branding, custom domains, SMTP, payment gateways, WhatsApp/Twilio, and security.</p>
    </div>
</div>

<!-- All Settings Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

    <!-- Card 1: Business Profile & Dynamic Branding -->
    <a href="branding" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-amber-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-palette"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-amber-100 text-amber-800 rounded-md uppercase">Core Branding</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-amber-600 transition-colors">Business Profile & Themes</h3>
        <p class="text-xs text-slate-500 mt-1">Configure company TRN, logos, authorized signatures, invoice templates, and brand colors.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-amber-600 flex items-center justify-between">
            <span>Configure Profile</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 2: Whitelabel Custom Domain & SSL -->
    <a href="domain_settings" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-blue-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-globe"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-blue-100 text-blue-800 rounded-md uppercase">Whitelabel</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-blue-600 transition-colors">Custom Domain & SSL</h3>
        <p class="text-xs text-slate-500 mt-1">Bind custom domain (billing.company.com) with real-time CNAME DNS lookup & SSL indicators.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-blue-600 flex items-center justify-between">
            <span>Test & Verify DNS</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 3: WhatsApp & Twilio Messaging Gateway -->
    <a href="whatsapp_settings" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-emerald-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-emerald-100 text-emerald-800 rounded-md uppercase">WhatsApp & SMS</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-emerald-600 transition-colors">WhatsApp & Twilio API</h3>
        <p class="text-xs text-slate-500 mt-1">Configure Meta WhatsApp Cloud API or Twilio SMS for automated PDF dispatches & due alerts.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-emerald-600 flex items-center justify-between">
            <span>Configure Messaging</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 4: Custom SMTP Email Server -->
    <a href="email_settings" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-indigo-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-server"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-indigo-100 text-indigo-800 rounded-md uppercase">Email Delivery</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-indigo-600 transition-colors">Custom SMTP Server</h3>
        <p class="text-xs text-slate-500 mt-1">Connect G-Suite, Office 365, cPanel, or SES for branded invoice email dispatches.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-indigo-600 flex items-center justify-between">
            <span>SMTP Settings</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 5: Payment Gateways (Stripe / PayPal / Network International) -->
    <a href="payment_settings" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-purple-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-credit-card"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-purple-100 text-purple-800 rounded-md uppercase">Checkout</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-purple-600 transition-colors">Payment Gateways</h3>
        <p class="text-xs text-slate-500 mt-1">Configure Stripe & PayPal API keys for online client credit card invoice payments.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-purple-600 flex items-center justify-between">
            <span>Gateway Keys</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 6: Security & 2FA Policies -->
    <a href="security" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-rose-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-rose-500/10 text-rose-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-rose-100 text-rose-800 rounded-md uppercase">Security</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-rose-600 transition-colors">2FA & Brute-Force Security</h3>
        <p class="text-xs text-slate-500 mt-1">Enforce mandatory 2FA OTP codes, password policies, and view IP lockout throttles.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-rose-600 flex items-center justify-between">
            <span>Security Console</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 7: Plug & Play Extensions -->
    <a href="plugins_admin" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-purple-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-puzzle-piece"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-purple-100 text-purple-800 rounded-md uppercase">Plug & Play</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-purple-600 transition-colors">Plug & Play Extensions</h3>
        <p class="text-xs text-slate-500 mt-1">Upload custom .zip plugin features, custom discounts, and third-party modules.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-purple-600 flex items-center justify-between">
            <span>Manage Plugins</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 8: REST API Keys -->
    <a href="api_keys" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-amber-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-key"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-amber-100 text-amber-800 rounded-md uppercase">API</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-amber-600 transition-colors">REST API Keys Manager</h3>
        <p class="text-xs text-slate-500 mt-1">Generate Bearer API tokens for custom integrations with access permissions.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-amber-600 flex items-center justify-between">
            <span>Manage Keys</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 8: Database Backups -->
    <a href="backup_admin" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-cyan-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 text-cyan-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-database"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-cyan-100 text-cyan-800 rounded-md uppercase">Database</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-cyan-600 transition-colors">1-Click SQL Database Backup</h3>
        <p class="text-xs text-slate-500 mt-1">Download raw `.sql` snapshot dumps for disaster recovery and offline archiving.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-cyan-600 flex items-center justify-between">
            <span>Export Database Dump</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 9: Redis & Cache Diagnostics -->
    <a href="cache_admin" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-emerald-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-emerald-100 text-emerald-800 rounded-md uppercase">Cache</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-emerald-600 transition-colors">Redis Engine & Performance</h3>
        <p class="text-xs text-slate-500 mt-1">Inspect Redis cache key counts, flush TTL caches, and monitor execution times.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-emerald-600 flex items-center justify-between">
            <span>Cache Console</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 10: Create & Edit SaaS Plans -->
    <a href="subscriptions_admin" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-amber-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-crown"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-amber-100 text-amber-900 rounded-md uppercase">SaaS Plans</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-amber-600 transition-colors">Create & Edit SaaS Plans</h3>
        <p class="text-xs text-slate-500 mt-1">Build Starter, Pro & Enterprise tiers, configure invoice limits, user seats, and monthly/yearly pricing.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-amber-600 flex items-center justify-between">
            <span>Manage SaaS Plans</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

    <!-- Card 11: Auto-Subscription Billing -->
    <a href="recurring_invoices" class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-xl hover:border-purple-500/50 transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-rotate"></i>
            </div>
            <span class="text-3xs font-extrabold px-2 py-1 bg-purple-100 text-purple-900 rounded-md uppercase">Recurring</span>
        </div>
        <h3 class="font-extrabold text-base text-slate-900 group-hover:text-purple-600 transition-colors">Auto-Subscription Billing</h3>
        <p class="text-xs text-slate-500 mt-1">Set up automated recurring client subscriptions, invoice generation schedules, and auto-email PDF receipts.</p>
        <div class="mt-4 pt-3 border-t border-slate-100 text-2xs font-extrabold text-purple-600 flex items-center justify-between">
            <span>Manage Subscriptions</span>
            <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </div>
    </a>

</div>

<?php page_end(); ?>
