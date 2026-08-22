<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();

// Fetch or generate a permanent One-Click MCP Direct Token for this tenant
$stMcp = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'mcp_direct_token'");
$stMcp->execute([$tid]);
$mcpToken = $stMcp->fetchColumn();

if (empty($mcpToken)) {
    $mcpToken = 'os_mcp_' . bin2hex(random_bytes(18));
    $keyHash = hash('sha256', $mcpToken);
    $keyPrefix = substr($mcpToken, 0, 16) . '...';
    $name = 'One-Click AI Connection Key';
    $scopes = json_encode(['invoices:read', 'invoices:write', 'clients:read', 'clients:write', 'payments:write', 'reports:read']);

    // Save token in settings table
    try {
        $stSet = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, 'mcp_direct_token', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stSet->execute([$tid, $mcpToken]);
    } catch (\Throwable $e) {}

    // Register token in api_keys table so ApiAuthenticator recognizes it seamlessly
    try {
        $stIns = $pdo->prepare("INSERT INTO api_keys (tenant_id, created_by_user_id, name, key_hash, key_prefix, scopes, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stIns->execute([$tid, $_SESSION['user_id'] ?? 1, $name, $keyHash, $keyPrefix, $scopes]);
    } catch (\Throwable $e) {
        try {
            $stIns = $pdo->prepare("INSERT INTO api_keys (tenant_id, name, key_hash, key_prefix, scopes, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stIns->execute([$tid, $name, $keyHash, $keyPrefix, $scopes]);
        } catch (\Throwable $e2) {
            try {
                $stIns = $pdo->prepare("INSERT INTO api_keys (tenant_id, name, api_key, is_active) VALUES (?, ?, ?, 1)");
                $stIns->execute([$tid, $name, $mcpToken]);
            } catch (\Throwable $e3) {}
        }
    }
}

// Handle AJAX Tool Execution for in-page testing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'test_mcp_tool') {
    header('Content-Type: application/json');
    $toolName = trim($_POST['tool_name'] ?? '');
    $argsRaw = $_POST['tool_args'] ?? '{}';
    $args = json_decode($argsRaw, true) ?: [];

    $mcpService = new \Services\McpService($pdo, $activeTenant);
    $response = $mcpService->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $toolName,
            'arguments' => $args
        ]
    ]);

    echo json_encode($response);
    exit;
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$mcpBaseUrl = "{$protocol}://{$host}{$scriptDir}/api/v1/mcp.php";
$mcpDirectUrl = "{$mcpBaseUrl}?token={$mcpToken}";
$openApiUrl = "{$protocol}://{$host}{$scriptDir}/api/v1/openapi.php?token={$mcpToken}";

// Download Handlers for 1-Click Auto-Install Scripts & Config Files
if (isset($_GET['download'])) {
    $dl = $_GET['download'];
    $tenantNameClean = strtolower(preg_replace('/[^a-z0-9]/i', '_', $activeTenant['name']));

    if ($dl === 'claude_bat') {
        header('Content-Type: application/x-bat');
        header('Content-Disposition: attachment; filename="install_claude_mcp.bat"');
        echo "@echo off\r\n";
        echo "echo ==========================================================\r\n";
        echo "echo Installing OneSol Invoice Manager AI for Claude Desktop...\r\n";
        echo "echo ==========================================================\r\n";
        echo "set CLAUDE_DIR=%APPDATA%\\Claude\r\n";
        echo "if not exist \"%CLAUDE_DIR%\" mkdir \"%CLAUDE_DIR%\"\r\n";
        echo "set CONFIG_PATH=%CLAUDE_DIR%\\claude_desktop_config.json\r\n\r\n";
        echo "echo {\"mcpServers\":{\"{$tenantNameClean}\":{\"command\":\"npx\",\"args\":[\"-y\",\"@modelcontextprotocol/server-fetch\",\"{$mcpDirectUrl}\"]}}} > \"%CONFIG_PATH%\"\r\n\r\n";
        echo "echo.\r\n";
        echo "echo SUCCESS! Your AI Invoicing Assistant is now connected to Claude.\r\n";
        echo "echo Please restart the Claude Desktop app to start using it!\r\n";
        echo "echo.\r\n";
        echo "pause\r\n";
        exit;
    }

    if ($dl === 'claude_json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="claude_desktop_config.json"');
        echo json_encode([
            'mcpServers' => [
                $tenantNameClean => [
                    'command' => 'npx',
                    'args' => [
                        '-y',
                        '@modelcontextprotocol/server-fetch',
                        $mcpDirectUrl
                    ]
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($dl === 'cursor_bat') {
        header('Content-Type: application/x-bat');
        header('Content-Disposition: attachment; filename="install_cursor_mcp.bat"');
        echo "@echo off\r\n";
        echo "echo Installing OneSol Invoice Manager AI into Cursor...\r\n";
        echo "set CURSOR_DIR=%USERPROFILE%\\.cursor\r\n";
        echo "if not exist \"%CURSOR_DIR%\" mkdir \"%CURSOR_DIR%\"\r\n";
        echo "set CONFIG_PATH=%CURSOR_DIR%\\mcp.json\r\n\r\n";
        echo "echo {\"mcpServers\":{\"{$tenantNameClean}\":{\"url\":\"{$mcpDirectUrl}\",\"type\":\"sse\"}}} > \"%CONFIG_PATH%\"\r\n\r\n";
        echo "echo SUCCESS! OneSol AI configured for Cursor IDE.\r\n";
        echo "pause\r\n";
        exit;
    }
}

page_start('AI Connection Center');
?>

<!-- Header -->
<div class="md:flex md:items-center md:justify-between mb-8">
    <div>
        <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            <span class="h-11 w-11 bg-gradient-to-tr from-purple-600 to-indigo-600 text-white rounded-2xl flex items-center justify-center text-xl shadow-lg shadow-purple-500/20">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </span>
            Connect AI to Your Workspace
        </h1>
        <p class="mt-1 text-sm text-slate-500">Connect <strong><?=e($activeTenant['name'])?></strong> to ChatGPT, Claude, Cursor, or use our built-in AI assistant in 1 click.</p>
    </div>
    <div class="mt-4 md:mt-0 flex gap-3">
        <button onclick="toggleMcpGuide(true)" class="inline-flex items-center px-4 py-2.5 text-xs font-bold rounded-2xl text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 shadow-sm transition-all cursor-pointer">
            <i class="fa-solid fa-book-open text-purple-600 mr-2"></i>📖 View User Guide
        </button>
        <button onclick="openInAppAiModal()" class="inline-flex items-center px-5 py-2.5 text-xs font-black rounded-2xl text-white bg-gradient-to-r from-purple-600 via-indigo-600 to-purple-600 hover:scale-105 shadow-xl transition-all cursor-pointer">
            <i class="fa-solid fa-comments mr-2 text-amber-300 text-sm animate-pulse"></i>Instant Web AI Assistant
        </button>
    </div>
</div>

<!-- Hero Banner: Zero Setup Required -->
<div class="bg-gradient-to-br from-slate-950 via-indigo-950 to-purple-950 rounded-3xl p-6 sm:p-8 text-white shadow-2xl mb-10 border border-purple-800/40 relative overflow-hidden">
    <div class="absolute -right-10 -bottom-10 h-64 w-64 bg-purple-600/10 rounded-full blur-3xl pointer-events-none"></div>
    
    <div class="flex items-center justify-between flex-wrap gap-4 mb-4 relative z-10">
        <div>
            <span class="px-3 py-1 rounded-full text-2xs font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 uppercase tracking-widest inline-flex items-center gap-1.5 mb-2">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-ping"></span> 100% Pre-Configured & Ready
            </span>
            <h2 class="text-2xl sm:text-3xl font-black text-white">1-Click AI Assistant Connect</h2>
            <p class="text-xs sm:text-sm text-purple-200/80 max-w-2xl mt-1">No coding or technical setup required. Click any button below to connect your favorite AI assistant to your invoicing data.</p>
        </div>
    </div>

    <!-- Ready Direct Link Box -->
    <div class="mt-4 bg-slate-900/90 p-4 rounded-2xl border border-purple-500/30 flex items-center justify-between flex-wrap gap-3 relative z-10">
        <div class="flex items-center gap-3 overflow-hidden flex-1">
            <span class="h-9 w-9 rounded-xl bg-purple-600/30 text-purple-300 flex items-center justify-center font-bold text-sm shrink-0 border border-purple-500/30">
                <i class="fa-solid fa-link"></i>
            </span>
            <div class="truncate">
                <div class="text-2xs uppercase tracking-wider font-extrabold text-slate-400">Your Ready Connection Link:</div>
                <div id="mcp-direct-url" class="font-mono text-xs sm:text-sm font-bold text-emerald-400 truncate select-all"><?=e($mcpDirectUrl)?></div>
            </div>
        </div>
        <button onclick="copyToClipboard('mcp-direct-url')" class="px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-sans text-xs font-black rounded-xl transition-all shadow-md flex items-center shrink-0">
            <i class="fa-solid fa-copy mr-1.5"></i> Copy Ready Link
        </button>
    </div>
</div>

<!-- 1-Click Platform Launcher Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">

    <!-- Card 1: ChatGPT -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-md hover:shadow-xl hover:border-emerald-400 transition-all flex flex-col justify-between group">
        <div>
            <div class="h-14 w-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl font-black mb-4 group-hover:scale-110 transition-transform shadow-xs">
                <i class="fa-solid fa-robot"></i>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-emerald-100 text-emerald-800 uppercase tracking-wider inline-block mb-2">Most Popular</span>
            <h3 class="text-xl font-black text-slate-900 mb-1">ChatGPT</h3>
            <p class="text-xs text-slate-500 mb-6">Ask ChatGPT to check unpaid invoices, create estimates, or calculate your monthly profit.</p>
        </div>

        <div class="space-y-3">
            <button onclick="launchChatGPT()" class="w-full py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-2xl text-xs font-black transition-all shadow-md flex items-center justify-center gap-2">
                <i class="fa-solid fa-paper-plane text-sm"></i> 🚀 Connect to ChatGPT Now
            </button>
            <div class="p-3 bg-slate-50 rounded-2xl text-2xs text-slate-600 font-medium">
                <i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> Copies pre-filled link & opens ChatGPT automatically.
            </div>
        </div>
    </div>

    <!-- Card 2: Claude Desktop -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-md hover:shadow-xl hover:border-purple-400 transition-all flex flex-col justify-between group">
        <div>
            <div class="h-14 w-14 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-3xl font-black mb-4 group-hover:scale-110 transition-transform shadow-xs">
                <i class="fa-solid fa-sparkles"></i>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-purple-100 text-purple-800 uppercase tracking-wider inline-block mb-2">Desktop App</span>
            <h3 class="text-xl font-black text-slate-900 mb-1">Claude Desktop</h3>
            <p class="text-xs text-slate-500 mb-6">Use Claude on Windows to manage your business financials with voice or text.</p>
        </div>

        <div class="space-y-3">
            <a href="mcp_settings.php?download=claude_bat" class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white rounded-2xl text-xs font-black transition-all shadow-md flex items-center justify-center gap-2">
                <i class="fa-solid fa-bolt text-amber-300 text-sm"></i> ⚡ 1-Click Auto Setup (.bat)
            </a>
            <div class="p-3 bg-slate-50 rounded-2xl text-2xs text-slate-600 font-medium">
                <i class="fa-solid fa-circle-check text-purple-500 mr-1"></i> Double-click downloaded file to configure Claude automatically.
            </div>
        </div>
    </div>

    <!-- Card 3: In-App AI Assistant -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-md hover:shadow-xl hover:border-indigo-400 transition-all flex flex-col justify-between group">
        <div>
            <div class="h-14 w-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl font-black mb-4 group-hover:scale-110 transition-transform shadow-xs">
                <i class="fa-solid fa-brain"></i>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-indigo-100 text-indigo-800 uppercase tracking-wider inline-block mb-2">Zero Installation</span>
            <h3 class="text-xl font-black text-slate-900 mb-1">Instant Web AI Assistant</h3>
            <p class="text-xs text-slate-500 mb-6">Talk to your workspace AI assistant right now directly inside your browser.</p>
        </div>

        <div class="space-y-3">
            <button onclick="openInAppAiModal()" class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-2xl text-xs font-black transition-all shadow-md flex items-center justify-center gap-2">
                <i class="fa-solid fa-comments text-amber-300 text-sm"></i> 💬 Open Web AI Assistant
            </button>
            <div class="p-3 bg-slate-50 rounded-2xl text-2xs text-slate-600 font-medium">
                <i class="fa-solid fa-circle-check text-indigo-500 mr-1"></i> Works on mobile & desktop with no apps required.
            </div>
        </div>
    </div>

</div>

<!-- Developer / Advanced Options (Collapsible Accordion) -->
<details class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs mb-12 group">
    <summary class="font-extrabold text-sm text-slate-900 cursor-pointer flex items-center justify-between select-none">
        <span class="flex items-center gap-2">
            <i class="fa-solid fa-code text-indigo-600"></i> Advanced Technical Code Snippets & Cursor Config
        </span>
        <span class="text-xs text-indigo-600 font-bold group-open:rotate-180 transition-transform">
            <i class="fa-solid fa-chevron-down"></i>
        </span>
    </summary>
    
    <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h4 class="text-xs font-bold text-slate-800 mb-2">Claude Desktop Raw JSON (`claude_desktop_config.json`):</h4>
            <pre id="claude-json" class="bg-slate-950 text-indigo-300 p-4 rounded-2xl text-2xs font-mono overflow-x-auto border border-slate-800">{
  "mcpServers": {
    "<?=e(strtolower(preg_replace('/[^a-z0-9]/i', '_', $activeTenant['name'])))?>": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-fetch",
        "<?=e($mcpDirectUrl)?>"
      ]
    }
  }
}</pre>
            <button onclick="copyToClipboard('claude-json')" class="mt-2 text-2xs font-bold text-indigo-600 hover:text-indigo-800"><i class="fa-solid fa-copy mr-1"></i> Copy Claude JSON</button>
        </div>

        <div>
            <h4 class="text-xs font-bold text-slate-800 mb-2">Cursor / VS Code MCP Server Config:</h4>
            <pre id="cursor-json" class="bg-slate-950 text-purple-300 p-4 rounded-2xl text-2xs font-mono overflow-x-auto border border-slate-800">{
  "name": "<?=e($activeTenant['name'])?> Invoicing",
  "type": "sse",
  "url": "<?=e($mcpDirectUrl)?>"
}</pre>
            <div class="flex items-center gap-4 mt-2">
                <button onclick="copyToClipboard('cursor-json')" class="text-2xs font-bold text-indigo-600 hover:text-indigo-800"><i class="fa-solid fa-copy mr-1"></i> Copy Cursor JSON</button>
                <a href="mcp_settings.php?download=cursor_bat" class="text-2xs font-bold text-purple-600 hover:text-purple-800"><i class="fa-solid fa-download mr-1"></i> Download Cursor Auto-Installer (.bat)</a>
            </div>
        </div>
    </div>
</details>

<!-- Live Interactive Tester -->
<div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm mb-12">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-terminal text-purple-600"></i> Test Live AI Commands & Tools
            </h3>
            <p class="text-xs text-slate-500">Test how AI tools respond to queries in real time.</p>
        </div>
        <span class="text-xs font-mono text-purple-700 bg-purple-50 px-3 py-1 rounded-xl font-bold">Tenant ID: #<?=$tid?></span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-5 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Select Command / Tool:</label>
                <select id="tester-tool" onchange="updateSampleArgs()" class="w-full text-xs font-bold rounded-xl border-slate-300 focus:border-purple-500 focus:ring-purple-500">
                    <option value="get_financial_summary">get_financial_summary (Financial P&L & AR)</option>
                    <option value="list_invoices">list_invoices (Filter invoices)</option>
                    <option value="list_clients">list_clients (Search client catalog)</option>
                    <option value="list_expenses">list_expenses (Business expenses)</option>
                    <option value="list_quotes">list_quotes (Estimates & Quotes)</option>
                    <option value="create_expense">create_expense (Log new expense)</option>
                    <option value="create_client">create_client (Add client contact)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Command Parameters (JSON):</label>
                <textarea id="tester-args" rows="4" class="w-full font-mono text-xs rounded-xl border-slate-300 focus:border-purple-500 focus:ring-purple-500">{"period":"month"}</textarea>
            </div>

            <button onclick="runMcpTester()" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs shadow-md transition-all flex items-center justify-center">
                <i class="fa-solid fa-play mr-2"></i> Run Test Command
            </button>
        </div>

        <div class="lg:col-span-7">
            <label class="block text-xs font-bold text-slate-700 mb-1">Live Output Response:</label>
            <pre id="tester-output" class="bg-slate-950 text-emerald-400 font-mono text-xs p-4 rounded-2xl h-56 overflow-y-auto border border-slate-800">// Click 'Run Test Command' to view response...</pre>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied link to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}

function launchChatGPT() {
    const openApiUrl = "<?=e($openApiUrl)?>";
    navigator.clipboard.writeText(openApiUrl).then(() => {
        alert('🚀 OpenAPI Link Copied!\n\nOpening ChatGPT GPT Creator in a new tab now...\nIn ChatGPT, click Actions -> Import from URL and paste the link!');
        window.open('https://chatgpt.com/gpts/editor', '_blank');
    }).catch(() => {
        window.open('https://chatgpt.com/gpts/editor', '_blank');
    });
}

function updateSampleArgs() {
    const tool = document.getElementById('tester-tool').value;
    const argsBox = document.getElementById('tester-args');
    
    const samples = {
        'get_financial_summary': '{\n  "period": "month"\n}',
        'list_invoices': '{\n  "status": "unpaid",\n  "limit": 5\n}',
        'list_clients': '{\n  "search": "",\n  "limit": 10\n}',
        'list_expenses': '{\n  "limit": 10\n}',
        'list_quotes': '{\n  "status": "all"\n}',
        'create_expense': '{\n  "amount": 45.50,\n  "category": "Office Supplies",\n  "vendor": "Staples",\n  "notes": "Test expense via MCP"\n}',
        'create_client': '{\n  "name": "Acme Innovations",\n  "email": "contact@acme.com",\n  "company": "Acme Corp"\n}'
    };

    argsBox.value = samples[tool] || '{}';
}

function runMcpTester() {
    const tool = document.getElementById('tester-tool').value;
    const args = document.getElementById('tester-args').value;
    const output = document.getElementById('tester-output');

    output.innerText = '// Executing Tool Call...';

    const formData = new FormData();
    formData.append('ajax_action', 'test_mcp_tool');
    formData.append('tool_name', tool);
    formData.append('tool_args', args);

    fetch('mcp_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        output.innerText = JSON.stringify(data, null, 2);
    })
    .catch(err => {
        output.innerText = '// Error: ' + err;
    });
}

function toggleMcpGuide(show) {
    const modal = document.getElementById('mcp-guide-modal');
    if (modal) {
        modal.style.display = show ? 'flex' : 'none';
    }
}
</script>

<!-- Master User Guide Step-by-Step Instructions Modal -->
<div id="mcp-guide-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-3xl max-w-4xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 max-h-[92vh] overflow-y-auto">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
            <div class="flex items-center gap-3">
                <span class="h-11 w-11 bg-gradient-to-tr from-purple-600 to-indigo-600 text-white rounded-2xl flex items-center justify-center text-xl font-black shadow-md">
                    <i class="fa-solid fa-book-open"></i>
                </span>
                <div>
                    <h3 class="text-2xl font-black text-slate-900">AI Assistant Master User Guide</h3>
                    <p class="text-xs text-slate-500">Comprehensive, step-by-step walkthrough for non-technical users.</p>
                </div>
            </div>
            <button onclick="toggleMcpGuide(false)" class="h-9 w-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-lg cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="space-y-8">

            <!-- Section 1: Instant In-App Web AI Assistant -->
            <div class="bg-gradient-to-br from-indigo-50 to-purple-50 p-6 rounded-3xl border border-indigo-100/80">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                    <div class="flex items-center gap-2.5 font-black text-indigo-950 text-base">
                        <span class="h-7 w-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs shadow-xs">1</span>
                        Instant In-App Web AI Assistant (Zero Installation)
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-indigo-200 text-indigo-900 uppercase tracking-wider">Fastest & Easiest</span>
                </div>

                <p class="text-xs text-slate-600 mb-4 leading-relaxed font-medium">Use the built-in AI Copilot directly inside your browser without installing any software or apps.</p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    <div class="bg-white p-3.5 rounded-2xl border border-indigo-100 shadow-2xs">
                        <div class="text-2xs font-extrabold uppercase text-indigo-600 mb-1">Step 1</div>
                        <div class="text-xs font-bold text-slate-900">Click AI Copilot</div>
                        <p class="text-2xs text-slate-500 mt-1">Click the 🤖 floating button at the bottom-right of any page.</p>
                    </div>
                    <div class="bg-white p-3.5 rounded-2xl border border-indigo-100 shadow-2xs">
                        <div class="text-2xs font-extrabold uppercase text-indigo-600 mb-1">Step 2</div>
                        <div class="text-xs font-bold text-slate-900">Pick a Quick Action</div>
                        <p class="text-2xs text-slate-500 mt-1">Click 📊 P&L Summary, ⚠️ Unpaid Invoices, or 👥 Client List.</p>
                    </div>
                    <div class="bg-white p-3.5 rounded-2xl border border-indigo-100 shadow-2xs">
                        <div class="text-2xs font-extrabold uppercase text-indigo-600 mb-1">Step 3</div>
                        <div class="text-xs font-bold text-slate-900">Ask Anything</div>
                        <p class="text-2xs text-slate-500 mt-1">Type in plain English to create invoices, expenses, or get summaries.</p>
                    </div>
                </div>
            </div>

            <!-- Section 2: ChatGPT Connection -->
            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-6 rounded-3xl border border-emerald-100/80">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                    <div class="flex items-center gap-2.5 font-black text-emerald-950 text-base">
                        <span class="h-7 w-7 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xs shadow-xs">2</span>
                        Connecting to ChatGPT (chatgpt.com)
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-emerald-200 text-emerald-900 uppercase tracking-wider">Web & Mobile</span>
                </div>

                <p class="text-xs text-slate-600 mb-4 leading-relaxed font-medium">Connect ChatGPT to your workspace so ChatGPT can view, draft, and manage your financial records.</p>

                <ol class="space-y-3 text-xs text-slate-700 font-medium">
                    <li class="flex items-start gap-3 bg-white p-3.5 rounded-2xl border border-emerald-100">
                        <span class="h-6 w-6 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">1</span>
                        <div>
                            <strong class="text-slate-900">Click Green Button:</strong> Click the green <strong>"🚀 Connect to ChatGPT Now"</strong> button on the AI Hub page.
                        </div>
                    </li>
                    <li class="flex items-start gap-3 bg-white p-3.5 rounded-2xl border border-emerald-100">
                        <span class="h-6 w-6 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">2</span>
                        <div>
                            <strong class="text-slate-900">Automatic Link Copy:</strong> The app automatically copies your custom OpenAPI link to your clipboard and opens ChatGPT in a new tab.
                        </div>
                    </li>
                    <li class="flex items-start gap-3 bg-white p-3.5 rounded-2xl border border-emerald-100">
                        <span class="h-6 w-6 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">3</span>
                        <div>
                            <strong class="text-slate-900">Import in ChatGPT:</strong> In ChatGPT, scroll down to <strong>Actions</strong> ➔ Click <strong>Import from URL</strong> ➔ Right-click and <strong>Paste</strong> your link ➔ Click <strong>Import</strong>!
                        </div>
                    </li>
                    <li class="flex items-start gap-3 bg-white p-3.5 rounded-2xl border border-emerald-100">
                        <span class="h-6 w-6 rounded-full bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">4</span>
                        <div>
                            <strong class="text-slate-900">Save GPT:</strong> Click <strong>Save / Publish</strong> at top right. Done! Now you can ask ChatGPT anything about your invoices!
                        </div>
                    </li>
                </ol>
            </div>

            <!-- Section 3: Claude Desktop App -->
            <div class="bg-gradient-to-br from-purple-50 to-indigo-50 p-6 rounded-3xl border border-purple-100/80">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                    <div class="flex items-center gap-2.5 font-black text-purple-950 text-base">
                        <span class="h-7 w-7 rounded-xl bg-purple-600 text-white flex items-center justify-center text-xs shadow-xs">3</span>
                        Connecting Claude Desktop App (Windows)
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-purple-200 text-purple-900 uppercase tracking-wider">1-Click Auto Setup</span>
                </div>

                <p class="text-xs text-slate-600 mb-4 leading-relaxed font-medium">Use the official Claude Desktop app on Windows with zero manual code editing.</p>

                <ol class="space-y-3 text-xs text-slate-700 font-medium">
                    <li class="flex items-start gap-3 bg-white p-3.5 rounded-2xl border border-purple-100">
                        <span class="h-6 w-6 rounded-full bg-purple-100 text-purple-800 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">1</span>
                        <div>
                            <strong class="text-slate-900">Download Installer:</strong> Click the purple <strong>"⚡ 1-Click Auto Setup (.bat)"</strong> button under Claude Desktop.
                        </div>
                    </li>
                    <li class="flex items-start gap-3 bg-white p-3.5 rounded-2xl border border-purple-100">
                        <span class="h-6 w-6 rounded-full bg-purple-100 text-purple-800 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">2</span>
                        <div>
                            <strong class="text-slate-900">Run Setup File:</strong> Go to your Downloads folder and double-click <strong>`install_claude_mcp.bat`</strong>.
                        </div>
                    </li>
                    <li class="flex items-start gap-3 bg-white p-3.5 rounded-2xl border border-purple-100">
                        <span class="h-6 w-6 rounded-full bg-purple-100 text-purple-800 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">3</span>
                        <div>
                            <strong class="text-slate-900">Restart Claude:</strong> Close and re-open the Claude Desktop app. Your tools will be automatically active!
                        </div>
                    </li>
                </ol>
            </div>

            <!-- Section 4: What You Can Ask AI (Prompt Examples) -->
            <div class="bg-slate-900 text-white p-6 rounded-3xl border border-slate-800">
                <h4 class="text-sm font-black text-white mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-lightbulb text-amber-400"></i> Example Commands You Can Give Your AI:
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 text-indigo-300 font-mono">
                        📊 "What is our revenue and net profit for this month?"
                    </div>
                    <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 text-amber-300 font-mono">
                        ⚠️ "List all overdue invoices and tell me who owes money."
                    </div>
                    <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 text-emerald-300 font-mono">
                        💵 "Record a payment of $200 against Invoice #INV-1001."
                    </div>
                    <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 text-purple-300 font-mono">
                        🏷️ "Log an expense of $65 for Office Supplies under Category General."
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="mt-8 pt-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-2xs text-slate-400 font-semibold">OneSol Invoice Manager • AI & MCP Integration Hub</span>
            <button onclick="toggleMcpGuide(false)" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs rounded-2xl shadow-md transition-all cursor-pointer">Close & Return to Dashboard</button>
        </div>
    </div>
</div>

<?php page_end(); ?>
