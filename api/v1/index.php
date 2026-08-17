<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

// Authenticate via Bearer Token or X-API-KEY header
$headers = getallheaders();
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (!$apiKey && isset($headers['Authorization'])) {
    if (preg_match('/Bearer\s+(\S+)/i', $headers['Authorization'], $matches)) {
        $apiKey = $matches[1];
    }
}

if (!$apiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Missing API Token header (X-API-KEY or Authorization Bearer).']);
    exit;
}

$stKey = $pdo->prepare("SELECT * FROM api_keys WHERE api_key = ? AND is_active = 1");
$stKey->execute([$apiKey]);
$keyRecord = $stKey->fetch();

if (!$keyRecord) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid or inactive API Key.']);
    exit;
}

$tenantId = (int)$keyRecord['tenant_id'];

// Simple API Router
$endpoint = $_GET['endpoint'] ?? 'invoices';
$method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'invoices':
        if ($method === 'GET') {
            $st = $pdo->prepare("SELECT i.*, c.company_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? ORDER BY i.id DESC LIMIT 100");
            $st->execute([$tenantId]);
            echo json_encode(['status' => 'success', 'data' => $st->fetchAll()]);
        } elseif ($method === 'POST') {
            $body = json_decode(file_get_contents('php://input'), true);
            if (empty($body['client_id']) || empty($body['items'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields: client_id, items array']);
                exit;
            }

            $invNum = invoice_number($pdo);
            $subtotal = 0;
            foreach ($body['items'] as $it) {
                $subtotal += ($it['qty'] ?? 1) * ($it['unit_price'] ?? 0);
            }
            $total = $subtotal;

            $stInv = $pdo->prepare("INSERT INTO invoices (tenant_id, invoice_number, client_id, invoice_date, status, currency, subtotal, total) VALUES (?, ?, ?, ?, 'draft', ?, ?, ?)");
            $stInv->execute([$tenantId, $invNum, $body['client_id'], date('Y-m-d'), $body['currency'] ?? 'AED', $subtotal, $total]);
            $invId = (int)$pdo->lastInsertId();

            $stItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, qty, unit_price, amount) VALUES (?, ?, ?, ?, ?)");
            foreach ($body['items'] as $it) {
                $q = (float)($it['qty'] ?? 1);
                $p = (float)($it['unit_price'] ?? 0);
                $stItem->execute([$invId, $it['description'] ?? 'Item', $q, $p, $q * $p]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Invoice created', 'invoice_number' => $invNum, 'id' => $invId]);
        }
        break;

    case 'clients':
        if ($method === 'GET') {
            $st = $pdo->prepare("SELECT * FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
            $st->execute([$tenantId]);
            echo json_encode(['status' => 'success', 'data' => $st->fetchAll()]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown endpoint. Available endpoints: /invoices, /clients']);
        break;
}
