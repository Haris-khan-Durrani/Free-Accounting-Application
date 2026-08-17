<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please sign in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

try {
    verify_csrf();
} catch (\Throwable $e) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token.']);
    exit;
}

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$companyName = trim($_POST['company_name'] ?? '');
$contactName = trim($_POST['contact_name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$taxNumber   = trim($_POST['tax_number'] ?? '');
$address     = trim($_POST['address'] ?? '');
$country     = trim($_POST['country'] ?? 'United Arab Emirates');
$currency    = $_POST['currency'] ?? 'AED';

if (empty($companyName)) {
    echo json_encode(['success' => false, 'message' => 'Company Name / Client Title is required.']);
    exit;
}

try {
    $st = $pdo->prepare('INSERT INTO clients (tenant_id, company_name, contact_name, email, phone, tax_number, address, country, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([$tid, $companyName, $contactName, $email, $phone, $taxNumber, $address, $country, $currency]);
    $newId = (int)$pdo->lastInsertId();

    if (function_exists('log_audit')) {
        log_audit($pdo, 'create_client', 'clients', $newId, "Created client $companyName via quick add modal");
    }

    echo json_encode([
        'success' => true,
        'message' => "Client '$companyName' created successfully.",
        'client' => [
            'id'           => $newId,
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'email'        => $email,
            'phone'        => $phone,
            'tax_number'   => $taxNumber,
            'currency'     => $currency,
            'country'      => $country,
            'address'      => $address
        ]
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
