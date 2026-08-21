<?php
require __DIR__ . '/bootstrap.php';
require_login();

header('Content-Type: application/json');

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$q = trim($_GET['q'] ?? '');

if (strlen($q) < 1) {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $q . '%';
$results = [];

// 1. System Navigation Pages & Reports Index (Instant Client-Side & Server Match)
$pages = [
    ['title' => 'Dashboard', 'category' => 'Navigation', 'url' => 'index.php', 'icon' => 'fa-solid fa-chart-pie text-blue-500'],
    ['title' => 'Invoices List', 'category' => 'Navigation', 'url' => 'invoices.php', 'icon' => 'fa-solid fa-file-invoice text-amber-500'],
    ['title' => 'Create New Invoice', 'category' => 'Action', 'url' => 'invoice_form.php', 'icon' => 'fa-solid fa-plus text-amber-500'],
    ['title' => 'Clients & Contacts', 'category' => 'Navigation', 'url' => 'clients.php', 'icon' => 'fa-solid fa-users text-emerald-500'],
    ['title' => 'Add New Client', 'category' => 'Action', 'url' => 'client_form.php', 'icon' => 'fa-solid fa-user-plus text-emerald-500'],
    ['title' => 'Proposals & Estimates', 'category' => 'Navigation', 'url' => 'quotes.php', 'icon' => 'fa-solid fa-file-signature text-sky-500'],
    ['title' => 'Create Proposal', 'category' => 'Action', 'url' => 'quote_form.php', 'icon' => 'fa-solid fa-file-circle-plus text-sky-500'],
    ['title' => 'Expenses Tracker', 'category' => 'Navigation', 'url' => 'expenses.php', 'icon' => 'fa-solid fa-receipt text-rose-500'],
    ['title' => 'Record Expense', 'category' => 'Action', 'url' => 'expense_form.php', 'icon' => 'fa-solid fa-receipt text-rose-500'],
    ['title' => 'Profit & Loss (P&L)', 'category' => 'Financial Statement', 'url' => 'reports_pnl.php', 'icon' => 'fa-solid fa-chart-line text-blue-500'],
    ['title' => 'Balance Sheet Report', 'category' => 'Financial Statement', 'url' => 'reports_balance_sheet.php', 'icon' => 'fa-solid fa-scale-balanced text-emerald-500'],
    ['title' => 'Cash Flow Statement', 'category' => 'Financial Statement', 'url' => 'reports_cashflow.php', 'icon' => 'fa-solid fa-money-bill-transfer text-amber-500'],
    ['title' => 'AR Aging Summary', 'category' => 'Financial Statement', 'url' => 'reports_aging.php', 'icon' => 'fa-solid fa-clock text-purple-500'],
    ['title' => 'UAE FTA VAT 201 Return', 'category' => 'Tax Compliance', 'url' => 'reports_vat201.php', 'icon' => 'fa-solid fa-file-invoice-dollar text-emerald-600'],
    ['title' => 'Chart of Accounts (COA)', 'category' => 'Accounting', 'url' => 'accounts.php', 'icon' => 'fa-solid fa-list-check text-indigo-500'],
    ['title' => 'General Ledger & Journal', 'category' => 'Accounting', 'url' => 'journal.php', 'icon' => 'fa-solid fa-book text-cyan-500'],
    ['title' => 'Master Settings Hub', 'category' => 'Management', 'url' => 'settings.php', 'icon' => 'fa-solid fa-sliders text-amber-500'],
    ['title' => 'Workspaces & Sub-Accounts', 'category' => 'Management', 'url' => 'subaccounts.php', 'icon' => 'fa-solid fa-sitemap text-blue-500'],
    ['title' => 'Branding & Logo Setup', 'category' => 'Management', 'url' => 'branding.php', 'icon' => 'fa-solid fa-palette text-amber-500'],
    ['title' => 'Template Selector (11 Designs)', 'category' => 'Management', 'url' => 'invoice_customize.php', 'icon' => 'fa-solid fa-paint-roller text-amber-500'],
    ['title' => 'Payment Gateways (Stripe/PayPal)', 'category' => 'Settings', 'url' => 'payment_settings.php', 'icon' => 'fa-solid fa-credit-card text-purple-500'],
    ['title' => 'WhatsApp API Settings', 'category' => 'Settings', 'url' => 'whatsapp_settings.php', 'icon' => 'fa-brands fa-whatsapp text-emerald-500'],
    ['title' => 'Custom SMTP Mailer', 'category' => 'Settings', 'url' => 'email_settings.php', 'icon' => 'fa-solid fa-server text-amber-500'],
    ['title' => '2FA & Account Security', 'category' => 'Settings', 'url' => 'security.php', 'icon' => 'fa-solid fa-shield-halved text-indigo-500'],
    ['title' => 'API Key Manager', 'category' => 'Settings', 'url' => 'api_keys.php', 'icon' => 'fa-solid fa-key text-amber-500'],
    ['title' => 'n8n Automations', 'category' => 'Settings', 'url' => 'automation.php', 'icon' => 'fa-solid fa-diagram-project text-purple-500'],
    ['title' => 'Team Members & Permissions', 'category' => 'Management', 'url' => 'users.php', 'icon' => 'fa-solid fa-users-gear text-indigo-500'],
];

foreach ($pages as $p) {
    if (stripos($p['title'], $q) !== false || stripos($p['category'], $q) !== false) {
        $results[] = [
            'type' => 'Page / Action',
            'title' => $p['title'],
            'subtitle' => $p['category'] . ' Page Shortcut',
            'url' => $p['url'],
            'icon' => $p['icon'],
            'badge' => 'Shortcut'
        ];
    }
}

// 2. Invoices Search (Number, Client Name, Total, Status)
try {
    $stInv = $pdo->prepare("
        SELECT i.id, i.number, i.total, i.status, i.currency, c.name as client_name, c.company_name
        FROM invoices i
        LEFT JOIN clients c ON c.id = i.client_id
        WHERE i.tenant_id = ? AND (
            i.number LIKE ? OR 
            i.total LIKE ? OR 
            i.status LIKE ? OR 
            c.name LIKE ? OR 
            c.company_name LIKE ?
        )
        ORDER BY i.id DESC
        LIMIT 6
    ");
    $stInv->execute([$tid, $like, $like, $like, $like, $like]);
    while ($r = $stInv->fetch()) {
        $clientLabel = $r['company_name'] ?: ($r['client_name'] ?: 'Unknown Client');
        $results[] = [
            'type' => 'Invoices',
            'title' => 'Invoice #' . $r['number'],
            'subtitle' => $clientLabel . ' • ' . ($r['currency'] ?: 'AED') . ' ' . number_format((float)$r['total'], 2),
            'url' => 'invoice_view.php?id=' . $r['id'],
            'icon' => 'fa-solid fa-file-invoice text-amber-500',
            'badge' => strtoupper($r['status'])
        ];
    }
} catch (Throwable $e) {}

// 3. Clients Search (Name, Company Name, Email, Phone)
try {
    $stCli = $pdo->prepare("
        SELECT id, name, company_name, email, phone
        FROM clients
        WHERE tenant_id = ? AND (
            name LIKE ? OR 
            company_name LIKE ? OR 
            email LIKE ? OR 
            phone LIKE ?
        )
        ORDER BY id DESC
        LIMIT 6
    ");
    $stCli->execute([$tid, $like, $like, $like, $like]);
    while ($r = $stCli->fetch()) {
        $subtitleParts = array_filter([$r['company_name'], $r['email'], $r['phone']]);
        $results[] = [
            'type' => 'Clients',
            'title' => $r['name'] ?: $r['company_name'],
            'subtitle' => implode(' • ', $subtitleParts) ?: 'Client Contact',
            'url' => 'client_form.php?id=' . $r['id'],
            'icon' => 'fa-solid fa-user-tag text-emerald-500',
            'badge' => 'Client'
        ];
    }
} catch (Throwable $e) {}

// 4. Proposals / Quotes Search
try {
    $stQuo = $pdo->prepare("
        SELECT q.id, q.number, q.total, q.status, c.name as client_name, c.company_name
        FROM quotes q
        LEFT JOIN clients c ON c.id = q.client_id
        WHERE q.tenant_id = ? AND (
            q.number LIKE ? OR 
            q.total LIKE ? OR 
            c.name LIKE ? OR 
            c.company_name LIKE ?
        )
        ORDER BY q.id DESC
        LIMIT 5
    ");
    $stQuo->execute([$tid, $like, $like, $like, $like]);
    while ($r = $stQuo->fetch()) {
        $clientLabel = $r['company_name'] ?: ($r['client_name'] ?: 'Unknown Client');
        $results[] = [
            'type' => 'Proposals',
            'title' => 'Proposal #' . $r['number'],
            'subtitle' => $clientLabel . ' • AED ' . number_format((float)$r['total'], 2),
            'url' => 'quote_view.php?id=' . $r['id'],
            'icon' => 'fa-solid fa-file-signature text-sky-500',
            'badge' => strtoupper($r['status'] ?? 'DRAFT')
        ];
    }
} catch (Throwable $e) {}

// 5. Expenses Search
try {
    $stExp = $pdo->prepare("
        SELECT id, category, vendor, amount, description
        FROM expenses
        WHERE tenant_id = ? AND (
            category LIKE ? OR 
            vendor LIKE ? OR 
            description LIKE ? OR 
            amount LIKE ?
        )
        ORDER BY id DESC
        LIMIT 5
    ");
    $stExp->execute([$tid, $like, $like, $like, $like]);
    while ($r = $stExp->fetch()) {
        $vendorLabel = $r['vendor'] ?: ($r['category'] ?: 'Expense');
        $results[] = [
            'type' => 'Expenses',
            'title' => $vendorLabel . ' (AED ' . number_format((float)$r['amount'], 2) . ')',
            'subtitle' => ($r['category'] ?? 'General Expense') . ($r['description'] ? ' • ' . $r['description'] : ''),
            'url' => 'expense_form.php?id=' . $r['id'],
            'icon' => 'fa-solid fa-receipt text-rose-500',
            'badge' => 'Expense'
        ];
    }
} catch (Throwable $e) {}

echo json_encode(['results' => $results]);
