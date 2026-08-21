<?php
require __DIR__ . '/bootstrap.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$rawInput = json_decode(file_get_contents('php://input'), true);
$name = trim($rawInput['name'] ?? $_POST['name'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Category name cannot be empty.']);
    exit;
}

// Check if category already exists for this tenant
$stChk = $pdo->prepare("SELECT id, name FROM expense_categories WHERE tenant_id = ? AND LOWER(name) = LOWER(?)");
$stChk->execute([$tid, $name]);
$existing = $stChk->fetch();

if ($existing) {
    echo json_encode(['success' => true, 'id' => (int)$existing['id'], 'name' => $existing['name'], 'exists' => true]);
    exit;
}

// Insert new expense category
$stIns = $pdo->prepare("INSERT INTO expense_categories (tenant_id, name) VALUES (?, ?)");
$stIns->execute([$tid, $name]);
$newId = (int)$pdo->lastInsertId();

log_audit($pdo, 'create_expense_category', 'expense_categories', $newId, "Created expense category '$name' via quick dropdown");

echo json_encode([
    'success' => true,
    'id' => $newId,
    'name' => $name,
    'exists' => false
]);
