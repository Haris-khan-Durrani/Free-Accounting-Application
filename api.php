<?php
// External REST API Service Endpoint
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = $GLOBALS['pdo'];
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? array_merge($_REQUEST, $jsonInput) : $_REQUEST;

$action = $_GET['action'] ?? $input['action'] ?? '';

// API Response Helper
function api_response(bool $success, string $message, array $data = [], int $httpCode = 200): never {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Authenticate API Key — delegated to unified Core\ApiAuthenticator
function authenticate_api_key(PDO $pdo, string $requiredScope = ''): array {
    return \Core\ApiAuthenticator::authenticate($pdo, $requiredScope);
}


// Route Handlers
switch ($action) {
    
    // 1. Create New SaaS Sub-Account / Client Tenant (Programmatic Onboarding)
    case 'create_tenant':
        $tenant = authenticate_api_key($pdo, 'tenants:write');

        // Security check: Only Master Super-Admin (Tenant #1) can provision new SaaS accounts
        if ((int)($tenant['id'] ?? 0) !== 1) {
            api_response(false, 'Forbidden. Only Master Super-Admin API keys (Tenant #1) can create new SaaS tenant accounts.', [], 403);
        }

        $companyName = trim($input['company_name'] ?? '');
        $email = trim($input['email'] ?? '');
        
        if (!empty($input['password'])) {
            if (strlen($input['password']) < 12) {
                api_response(false, 'Initial account password must be at least 12 characters.', [], 400);
            }
            $password = $input['password'];
        } else {
            $password = bin2hex(random_bytes(16)); // Secure random initial password
        }

        $trialMonths = max(1, (int)($input['trial_months'] ?? 4)); // Default 4 months free trial!
        $planSlug = $input['plan_slug'] ?? 'professional';
        $currency = $input['currency'] ?? 'AED';

        if (!$companyName || !$email) {
            api_response(false, 'Missing required fields: company_name and email are mandatory.', [], 400);
        }

        // Check if user email exists
        $stUserCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stUserCheck->execute([$email]);
        if ($stUserCheck->fetch()) {
            api_response(false, 'A user account with this email address already exists.', [], 409);
        }

        // Fetch Plan ID
        $stPlan = $pdo->prepare("SELECT id FROM saas_plans WHERE slug = ?");
        $stPlan->execute([$planSlug]);
        $planId = $stPlan->fetchColumn() ?: 2;

        // Generate Code & Hashed API Key
        $tenantCode = strtolower(preg_replace('/[^a-z0-9]/i', '', $companyName)) . '_' . rand(100, 999);
        $rawApiKey = 'os_live_' . bin2hex(random_bytes(20));
        $keyHash = hash('sha256', $rawApiKey);
        $keyPrefix = substr($rawApiKey, 0, 12);
        $scopesJson = json_encode(['invoices:read', 'invoices:write', 'clients:read', 'clients:write', 'payments:write', 'reports:read']);
        $trialEndsAt = date('Y-m-d', strtotime("+$trialMonths months"));

        try {
            $pdo->beginTransaction();

            // Insert Tenant (api_key legacy column set to empty)
            $stT = $pdo->prepare("INSERT INTO tenants (name, code, currency, status, plan_id, subscription_status, trial_ends_at, api_key, custom_trial_months) VALUES (?, ?, ?, 'active', ?, 'trial', ?, '', ?)");
            $stT->execute([$companyName, $tenantCode, $currency, $planId, $trialEndsAt, $trialMonths]);
            $tenantId = (int)$pdo->lastInsertId();

            // Insert Owner User
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stU = $pdo->prepare("INSERT INTO users (tenant_id, email, password_hash, role) VALUES (?, ?, ?, 'owner')");
            $stU->execute([$tenantId, $email, $passwordHash]);
            $userId = (int)$pdo->lastInsertId();

            // Insert Hashed API Key in api_keys table
            $stAk = $pdo->prepare("INSERT INTO api_keys (tenant_id, name, key_hash, key_prefix, scopes, is_active) VALUES (?, 'Primary Provisioned Key', ?, ?, ?, 1)");
            $stAk->execute([$tenantId, $keyHash, $keyPrefix, $scopesJson]);

            // Seed default Chart of Accounts for new tenant
            \Core\Tenant::seedAccounts($pdo, $tenantId);

            $pdo->commit();

            api_response(true, "Tenant '$companyName' created successfully with a $trialMonths-month free trial until $trialEndsAt.", [
                'tenant_id' => $tenantId,
                'company_name' => $companyName,
                'owner_email' => $email,
                'subscription_status' => 'trial',
                'trial_months' => $trialMonths,
                'trial_ends_at' => $trialEndsAt,
                'tenant_api_key' => $rawApiKey
            ], 201);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("API create_tenant error: " . $e->getMessage());
            api_response(false, 'Failed to onboard tenant due to an internal error.', [], 500);
        }
        break;

    // 2. Get Tenant Subscription & Trial Status
    case 'get_tenant_status':
        $tenant = authenticate_api_key($pdo, 'reports:read');
        
        $trialEnds = $tenant['trial_ends_at'] ?: date('Y-m-d');
        $daysRemaining = max(0, (int)floor((strtotime($trialEnds) - time()) / 86400));
        $isTrialExpired = ($tenant['subscription_status'] === 'trial' && strtotime($trialEnds) < time());

        $stInvCount = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE tenant_id = ?");
        $stInvCount->execute([$tenant['id']]);
        $totalInvoices = (int)$stInvCount->fetchColumn();

        api_response(true, 'Tenant status retrieved successfully.', [
            'tenant_id' => (int)$tenant['id'],
            'company_name' => $tenant['name'],
            'subscription_status' => $tenant['subscription_status'],
            'trial_ends_at' => $tenant['trial_ends_at'],
            'days_remaining_in_trial' => $daysRemaining,
            'is_trial_expired' => $isTrialExpired,
            'total_invoices_issued' => $totalInvoices
        ]);
        break;

    // 3. List Tenant Invoices
    case 'list_invoices':
        $tenant = authenticate_api_key($pdo, 'invoices:read');
        $st = $pdo->prepare("SELECT i.id, i.invoice_number, i.invoice_date, i.valid_until, i.status, i.currency, i.subtotal, i.tax_amount, i.total, i.paid_amount, c.company_name client_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? ORDER BY i.id DESC LIMIT 50");
        $st->execute([$tenant['id']]);
        $invoices = $st->fetchAll();
        api_response(true, 'Invoices list retrieved.', ['invoices' => $invoices]);
        break;

    // 4. Create New Invoice
    case 'create_invoice':
        $tenant = authenticate_api_key($pdo, 'invoices:write');
        $clientId = (int)($input['client_id'] ?? 0);
        $items = $input['items'] ?? [];
        $invDate = $input['invoice_date'] ?? date('Y-m-d');
        $dueDate = $input['valid_until'] ?? date('Y-m-d', strtotime('+14 days'));
        $notes = trim($input['notes'] ?? '');

        if (!$clientId || empty($items)) {
            api_response(false, 'Missing required parameters: client_id and items array are required.', [], 400);
        }

        // Verify client belongs to current API key's tenant
        $stClientCheck = $pdo->prepare("SELECT id FROM clients WHERE id = ? AND tenant_id = ?");
        $stClientCheck->execute([$clientId, $tenant['id']]);
        if (!$stClientCheck->fetchColumn()) {
            api_response(false, 'Forbidden. The specified client_id does not belong to your tenant workspace.', [], 403);
        }

        $invNum = 'OS-INV-' . date('Ymd') . '-' . rand(100, 999);
        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += ((float)($it['qty'] ?? 1) * (float)($it['unit_price'] ?? 0));
        }
        $total = $subtotal;

        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare("INSERT INTO invoices (tenant_id, client_id, invoice_number, invoice_date, valid_until, subtotal, tax_amount, total, paid_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0, 'sent', ?)");
            $st->execute([$tenant['id'], $clientId, $invNum, $invDate, $dueDate, $subtotal, $total, $notes]);
            $newInvId = (int)$pdo->lastInsertId();

            foreach ($items as $it) {
                $stIt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, details, qty, unit_price, amount) VALUES (?, ?, ?, ?, ?, ?)");
                $qty = (float)($it['qty'] ?? 1);
                $uPrice = (float)($it['unit_price'] ?? 0);
                $amt = $qty * $uPrice;
                $stIt->execute([$newInvId, $it['description'] ?? 'Item', $it['details'] ?? '', $qty, $uPrice, $amt]);
            }

            $pdo->commit();

            api_response(true, 'Invoice created successfully.', [
                'invoice_id' => $newInvId,
                'invoice_number' => $invNum,
                'total' => $total,
                'status' => 'sent'
            ], 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("API create_invoice error: " . $e->getMessage());
            api_response(false, 'Failed to create invoice due to an internal error.', [], 500);
        }
        break;

    // 5. List Clients
    case 'list_clients':
        $tenant = authenticate_api_key($pdo, 'clients:read');
        $st = $pdo->prepare("SELECT id, company_name, contact_name, email, phone, address, tax_number FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
        $st->execute([$tenant['id']]);
        $clients = $st->fetchAll();
        api_response(true, 'Clients directory retrieved.', ['clients' => $clients]);
        break;

    // 6. Create Client
    case 'create_client':
        $tenant = authenticate_api_key($pdo, 'clients:write');
        $companyName = trim($input['company_name'] ?? '');
        $contactName = trim($input['contact_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if (!$companyName) {
            api_response(false, 'company_name is mandatory.', [], 400);
        }

        $st = $pdo->prepare("INSERT INTO clients (tenant_id, company_name, contact_name, email, phone) VALUES (?, ?, ?, ?, ?)");
        $st->execute([$tenant['id'], $companyName, $contactName, $email, $phone]);
        $newClientId = (int)$pdo->lastInsertId();

        api_response(true, "Client '$companyName' created.", ['client_id' => $newClientId, 'company_name' => $companyName], 201);
        break;

    // 7. Record Payment
    case 'record_payment':
        $tenant = authenticate_api_key($pdo, 'payments:write');
        $invId = (int)($input['invoice_id'] ?? 0);
        $amount = (float)($input['amount'] ?? 0);
        $payMethod = $input['payment_method'] ?? 'Bank Transfer';

        if (!$invId || $amount <= 0) {
            api_response(false, 'invoice_id and positive amount are required.', [], 400);
        }

        $stInv = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?");
        $stInv->execute([$invId, $tenant['id']]);
        $inv = $stInv->fetch();

        if (!$inv) {
            api_response(false, 'Invoice not found.', [], 404);
        }

        $stPay = $pdo->prepare("INSERT INTO payments (tenant_id, invoice_id, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stPay->execute([$tenant['id'], $invId, $amount, date('Y-m-d'), $payMethod, 'Recorded via REST API']);

        $stSum = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?");
        $stSum->execute([$invId]);
        $totalPaid = (float)$stSum->fetchColumn();

        $newStatus = ($totalPaid >= (float)$inv['total']) ? 'paid' : 'partially_paid';

        $stUpd = $pdo->prepare("UPDATE invoices SET status = ?, paid_amount = ? WHERE id = ? AND tenant_id = ?");
        $stUpd->execute([$newStatus, $totalPaid, $invId, $tenant['id']]);

        api_response(true, 'Payment recorded.', ['invoice_id' => $invId, 'total_paid' => $totalPaid, 'new_status' => $newStatus]);
        break;

    default:
        api_response(false, 'Invalid API action. Supported actions: create_tenant, get_tenant_status, list_invoices, create_invoice, list_clients, create_client, record_payment', [], 404);
}
