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

$name = trim($_POST['name'] ?? '');
$sku  = trim($_POST['sku'] ?? '');
$type = in_array($_POST['type'] ?? '', ['product', 'service'], true) ? $_POST['type'] : 'service';
$price = max(0, (float)($_POST['unit_price'] ?? 0));
$unit  = trim($_POST['unit'] ?? 'unit');
$description = trim($_POST['description'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Product / Service Name is required.']);
    exit;
}

try {
    $st = $pdo->prepare('INSERT INTO items (tenant_id, name, sku, type, description, unit_price, unit) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute([$tid, $name, $sku, $type, $description, $price, $unit]);
    $newId = (int)$pdo->lastInsertId();

    if (function_exists('log_audit')) {
        log_audit($pdo, 'create_item', 'items', $newId, "Created item '$name' via quick add modal");
    }

    echo json_encode([
        'success' => true,
        'message' => "Item '$name' added to catalog successfully.",
        'item' => [
            'id'          => $newId,
            'name'        => $name,
            'sku'         => $sku,
            'type'        => $type,
            'unit_price'  => $price,
            'unit'        => $unit,
            'description' => $description
        ]
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
