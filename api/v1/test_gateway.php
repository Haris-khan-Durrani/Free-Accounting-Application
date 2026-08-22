<?php
require __DIR__ . '/../../bootstrap.php';

// Clean any previous output buffer to guarantee 100% pure JSON output
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// CSRF Token Verification
if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh the page.']);
    exit;
}

// Rotate CSRF token for security & return new token in JSON payload
$_SESSION['csrf'] = bin2hex(random_bytes(24));

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$gateway = trim($_POST['gateway'] ?? '');

if (empty($gateway)) {
    echo json_encode(['success' => false, 'message' => 'Payment gateway parameter missing.', 'new_csrf' => $_SESSION['csrf']]);
    exit;
}

$result = \Services\PaymentGatewayService::testGatewayConnection($pdo, $gateway, $_POST, $tid);
$result['new_csrf'] = $_SESSION['csrf'];

echo json_encode($result);
exit;
