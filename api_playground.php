<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();

page_start('REST API Developer Portal & Test Playground');
?>

<!-- Header -->
<div class="sm:flex sm:items-center sm:justify-between mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">REST API Developer Portal & Test Playground</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">Interactive REST API documentation and live request testing playground for sub-account onboarding, automated invoicing, and client sync.</p>
    </div>
    <div class="mt-4 sm:mt-0 flex flex-wrap gap-2">
        <a href="api_keys" class="inline-flex items-center px-4 py-2 border border-amber-300 shadow-sm text-xs font-bold rounded-xl text-amber-700 bg-amber-50 hover:bg-amber-100 transition-all">
            <i class="fa-solid fa-key mr-2 text-amber-500"></i>API Key Manager
        </a>
        <a href="tenants_admin" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-xs font-bold rounded-xl text-slate-700 bg-white hover:bg-slate-50">
            <i class="fa-solid fa-users-gear mr-2 text-emerald-500"></i>Tenant Manager
        </a>
    </div>
</div>

<!-- API Key Header Banner -->
<div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 rounded-2xl p-6 text-white shadow-xl mb-8 border border-slate-700/80 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
        <span class="text-2xs font-extrabold text-amber-400 uppercase tracking-widest block mb-1">Scoped API Access Keys</span>
        <p class="text-xs text-slate-400 mb-3">Generate named, scoped API keys with expiry dates from the Key Manager. Each key is shown <strong class="text-white">once only</strong> and validated by SHA-256 hash.</p>
        <a href="api_keys" class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-extrabold text-slate-950 bg-amber-400 hover:bg-amber-300 transition-all shadow-md">
            <i class="fa-solid fa-plus mr-1.5"></i>Generate a New API Key →
        </a>
    </div>
    <div class="text-xs text-slate-400 font-semibold space-y-2">
        <div>Include header: <code class="text-amber-300 bg-slate-950 px-2 py-0.5 rounded font-mono">X-API-Key: os_live_...</code></div>
        <div>Or Bearer: <code class="text-amber-300 bg-slate-950 px-2 py-0.5 rounded font-mono">Authorization: Bearer os_live_...</code></div>
        <div>Or query: <code class="text-amber-300 bg-slate-950 px-2 py-0.5 rounded font-mono">?api_key=os_live_...</code></div>
    </div>
</div>


<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">

    <!-- Endpoint Explorer (Left Sidebar) -->
    <div class="lg:col-span-5 space-y-4">
        <h2 class="text-base font-bold text-slate-900 mb-2">Available REST API Endpoints</h2>

        <!-- Endpoint 1: Create Tenant -->
        <div onclick="selectEndpoint('create_tenant')" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs hover:border-amber-500 cursor-pointer transition-all endpoint-card border-amber-500 ring-2 ring-amber-500/20" id="card-create_tenant">
            <div class="flex items-center justify-between mb-1.5">
                <span class="px-2 py-0.5 rounded text-2xs font-mono font-black bg-emerald-100 text-emerald-800">POST</span>
                <span class="text-2xs font-bold text-slate-400 font-mono">/api?action=create_tenant</span>
            </div>
            <div class="font-extrabold text-slate-900 text-sm">Programmatic Client Onboarding</div>
            <p class="text-2xs text-slate-500 mt-1">Register new client sub-accounts with custom 4-month free trials.</p>
        </div>

        <!-- Endpoint 2: Get Status -->
        <div onclick="selectEndpoint('get_tenant_status')" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs hover:border-amber-500 cursor-pointer transition-all endpoint-card" id="card-get_tenant_status">
            <div class="flex items-center justify-between mb-1.5">
                <span class="px-2 py-0.5 rounded text-2xs font-mono font-black bg-blue-100 text-blue-800">GET</span>
                <span class="text-2xs font-bold text-slate-400 font-mono">/api?action=get_tenant_status</span>
            </div>
            <div class="font-extrabold text-slate-900 text-sm">Get Subscription & Trial Status</div>
            <p class="text-2xs text-slate-500 mt-1">Check remaining trial days and total invoice stats.</p>
        </div>

        <!-- Endpoint 3: List Invoices -->
        <div onclick="selectEndpoint('list_invoices')" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs hover:border-amber-500 cursor-pointer transition-all endpoint-card" id="card-list_invoices">
            <div class="flex items-center justify-between mb-1.5">
                <span class="px-2 py-0.5 rounded text-2xs font-mono font-black bg-blue-100 text-blue-800">GET</span>
                <span class="text-2xs font-bold text-slate-400 font-mono">/api?action=list_invoices</span>
            </div>
            <div class="font-extrabold text-slate-900 text-sm">Fetch Invoices Directory</div>
            <p class="text-2xs text-slate-500 mt-1">Retrieve historical invoices, balances, and payment statuses.</p>
        </div>

        <!-- Endpoint 4: Create Invoice -->
        <div onclick="selectEndpoint('create_invoice')" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs hover:border-amber-500 cursor-pointer transition-all endpoint-card" id="card-create_invoice">
            <div class="flex items-center justify-between mb-1.5">
                <span class="px-2 py-0.5 rounded text-2xs font-mono font-black bg-emerald-100 text-emerald-800">POST</span>
                <span class="text-2xs font-bold text-slate-400 font-mono">/api?action=create_invoice</span>
            </div>
            <div class="font-extrabold text-slate-900 text-sm">Create New Tax Invoice</div>
            <p class="text-2xs text-slate-500 mt-1">Generate invoices with custom line items, currency, and due date.</p>
        </div>

        <!-- Endpoint 5: Record Payment -->
        <div onclick="selectEndpoint('record_payment')" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs hover:border-amber-500 cursor-pointer transition-all endpoint-card" id="card-record_payment">
            <div class="flex items-center justify-between mb-1.5">
                <span class="px-2 py-0.5 rounded text-2xs font-mono font-black bg-emerald-100 text-emerald-800">POST</span>
                <span class="text-2xs font-bold text-slate-400 font-mono">/api?action=record_payment</span>
            </div>
            <div class="font-extrabold text-slate-900 text-sm">Record Invoice Payment</div>
            <p class="text-2xs text-slate-500 mt-1">Log full or partial payments programmatically.</p>
        </div>

    </div>

    <!-- Live Test Playground Console (Right Side) -->
    <div class="lg:col-span-7 space-y-6">
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                <h2 class="text-lg font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-terminal text-amber-500 mr-2"></i>Live Interactive Test Playground
                </h2>
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-2xs font-extrabold" id="http-method-badge">POST</span>
            </div>

            <!-- Endpoint URL Bar -->
            <div class="mb-5">
                <label class="block text-2xs font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">Request Endpoint URL</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-slate-300 bg-slate-100 text-slate-600 font-mono text-xs font-bold" id="method-prefix">POST</span>
                    <input type="text" id="endpoint-url" readonly class="w-full rounded-r-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-mono text-slate-900 font-bold">
                </div>
            </div>

            <!-- JSON Payload Request Editor -->
            <div class="mb-5" id="payload-container">
                <label class="block text-2xs font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">JSON Request Body Payload</label>
                <textarea id="json-payload" rows="7" class="w-full rounded-xl border border-slate-300 bg-slate-950 p-3.5 text-xs font-mono text-amber-300 font-semibold focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between pt-2">
                <button type="button" onclick="executeApiRequest()" class="inline-flex items-center px-5 py-2.5 border border-transparent text-xs font-black rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md">
                    <i class="fa-solid fa-play mr-2"></i>Execute Live API Request
                </button>
                <span class="text-2xs font-mono font-bold text-slate-400" id="execution-time">Ready</span>
            </div>
        </div>

        <!-- Live Response Output Card -->
        <div class="bg-slate-950 rounded-2xl p-6 border border-slate-800 shadow-xl text-slate-100">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">API Response Output</span>
                    <span id="response-status-badge" class="px-2.5 py-0.5 rounded-full text-2xs font-mono font-black bg-slate-800 text-slate-400">STATUS: READY</span>
                </div>
                <button onclick="navigator.clipboard.writeText(document.getElementById('response-code-output').innerText); alert('Response copied!')" class="text-2xs font-bold text-slate-400 hover:text-white">
                    <i class="fa-solid fa-copy mr-1"></i>Copy Output
                </button>
            </div>

            <pre id="response-code-output" class="text-xs font-mono text-emerald-400 overflow-x-auto min-h-[160px] max-h-[350px]">Select an endpoint and click "Execute Live API Request" above...</pre>
        </div>

        <!-- Copyable cURL Snippet -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
            <label class="block text-2xs font-extrabold text-slate-500 uppercase tracking-wider mb-1.5">cURL Terminal Command</label>
            <div class="relative">
                <textarea id="curl-snippet" readonly rows="3" class="w-full rounded-xl border border-slate-300 bg-slate-900 p-3 text-2xs font-mono text-slate-300 font-semibold select-all"></textarea>
            </div>
        </div>
    </div>

</div>

<!-- Playground JavaScript Logic -->
<script>
const apiKey = <?=json_encode($activeTenant['api_key'] ?: '')?>;
const baseUrl = window.location.origin + window.location.pathname.replace('/api_playground', '') + '/api';

const endpoints = {
    create_tenant: {
        method: 'POST',
        url: baseUrl + '?action=create_tenant',
        payload: {
            company_name: 'Al Maktoum Enterprise UAE',
            email: 'billing@almaktoum.ae',
            password: 'SecurePassword123!',
            trial_months: 4,
            plan_slug: 'professional',
            currency: 'AED'
        }
    },
    get_tenant_status: {
        method: 'GET',
        url: baseUrl + '?action=get_tenant_status&api_key=' + apiKey,
        payload: null
    },
    list_invoices: {
        method: 'GET',
        url: baseUrl + '?action=list_invoices&api_key=' + apiKey,
        payload: null
    },
    create_invoice: {
        method: 'POST',
        url: baseUrl + '?action=create_invoice&api_key=' + apiKey,
        payload: {
            client_id: 1,
            invoice_date: '2026-08-10',
            valid_until: '2026-08-24',
            notes: 'Created via REST API Playground',
            items: [
                { description: 'Cloud SaaS Infrastructure Architecture', qty: 1, unit_price: 2500.00 },
                { description: 'Custom API Endpoint Integration', qty: 1, unit_price: 1500.00 }
            ]
        }
    },
    record_payment: {
        method: 'POST',
        url: baseUrl + '?action=record_payment&api_key=' + apiKey,
        payload: {
            invoice_id: 1,
            amount: 1000.00,
            payment_method: 'Bank Transfer'
        }
    }
};

let currentEndpoint = 'create_tenant';

function selectEndpoint(key) {
    currentEndpoint = key;
    document.querySelectorAll('.endpoint-card').forEach(el => el.classList.remove('border-amber-500', 'ring-2', 'ring-amber-500/20'));
    document.getElementById('card-' + key).classList.add('border-amber-500', 'ring-2', 'ring-amber-500/20');

    const ep = endpoints[key];
    document.getElementById('http-method-badge').innerText = ep.method;
    document.getElementById('http-method-badge').className = 'px-2.5 py-0.5 rounded-full text-2xs font-mono font-black ' + (ep.method === 'POST' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800');
    document.getElementById('method-prefix').innerText = ep.method;
    document.getElementById('endpoint-url').value = ep.url;

    if (ep.payload) {
        document.getElementById('payload-container').style.display = 'block';
        document.getElementById('json-payload').value = JSON.stringify(ep.payload, null, 4);
    } else {
        document.getElementById('payload-container').style.display = 'none';
    }

    updateCurlSnippet();
}

function updateCurlSnippet() {
    const ep = endpoints[currentEndpoint];
    let curl = `curl -X ${ep.method} "${ep.url}"`;
    if (ep.method === 'POST' && ep.payload) {
        curl += ` \\\n  -H "Content-Type: application/json" \\\n  -d '${document.getElementById('json-payload').value}'`;
    }
    document.getElementById('curl-snippet').value = curl;
}

async function executeApiRequest() {
    const ep = endpoints[currentEndpoint];
    const statusBadge = document.getElementById('response-status-badge');
    const outputEl = document.getElementById('response-code-output');
    const timeEl = document.getElementById('execution-time');

    statusBadge.innerText = 'EXECUTING...';
    statusBadge.className = 'px-2.5 py-0.5 rounded-full text-2xs font-mono font-black bg-amber-500 text-slate-950 animate-pulse';
    outputEl.innerText = 'Sending request to endpoint...';

    const startTime = performance.now();

    try {
        const opts = {
            method: ep.method,
            headers: { 'Content-Type': 'application/json' }
        };

        if (ep.method === 'POST' && ep.payload) {
            opts.body = document.getElementById('json-payload').value;
        }

        const res = await fetch(ep.url, opts);
        const endTime = performance.now();
        const duration = Math.round(endTime - startTime);
        timeEl.innerText = `${duration} ms`;

        const data = await res.json();
        
        statusBadge.innerText = `HTTP ${res.status} ${res.statusText || ''}`;
        statusBadge.className = 'px-2.5 py-0.5 rounded-full text-2xs font-mono font-black ' + (res.ok ? 'bg-emerald-500 text-slate-950' : 'bg-rose-500 text-white');

        outputEl.innerText = JSON.stringify(data, null, 4);
    } catch (err) {
        statusBadge.innerText = 'HTTP ERROR';
        statusBadge.className = 'px-2.5 py-0.5 rounded-full text-2xs font-mono font-black bg-rose-600 text-white';
        outputEl.innerText = 'Request Error: ' + err.message;
    }
}

// Initialize Playground on page load
document.addEventListener('DOMContentLoaded', () => {
    selectEndpoint('create_tenant');
});
</script>

<?php page_end(); ?>
