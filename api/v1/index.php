<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../bootstrap.php';

$pdo = $GLOBALS['pdo'];

// Simple API Router
$endpoint = $_GET['endpoint'] ?? 'invoices';
$method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'invoices':
        if ($method === 'GET') {
            $tenant = \Core\ApiAuthenticator::authenticate($pdo, 'invoices:read');
            $tenantId = (int)$tenant['id'];
            $st = $pdo->prepare("SELECT i.*, c.company_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? ORDER BY i.id DESC LIMIT 100");
            $st->execute([$tenantId]);
            echo json_encode(['status' => 'success', 'data' => $st->fetchAll()]);
        } elseif ($method === 'POST') {
            $tenant = \Core\ApiAuthenticator::authenticate($pdo, 'invoices:write');
            $tenantId = (int)$tenant['id'];
            $body = json_decode(file_get_contents('php://input'), true);
            if (empty($body['client_id']) || empty($body['items'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields: client_id, items array']);
                exit;
            }

            $clientId = (int)$body['client_id'];
            $stClientCheck = $pdo->prepare("SELECT id FROM clients WHERE id = ? AND tenant_id = ?");
            $stClientCheck->execute([$clientId, $tenantId]);
            if (!$stClientCheck->fetchColumn()) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden. The specified client_id does not belong to your tenant workspace.']);
                exit;
            }

            $invNum = function_exists('invoice_number') ? invoice_number($pdo) : ('OS-INV-' . date('Ymd') . '-' . rand(100, 999));
            $subtotal = 0;
            foreach ($body['items'] as $it) {
                $subtotal += ($it['qty'] ?? 1) * ($it['unit_price'] ?? 0);
            }
            $total = $subtotal;

            $stInv = $pdo->prepare("INSERT INTO invoices (tenant_id, invoice_number, client_id, invoice_date, status, currency, subtotal, total) VALUES (?, ?, ?, ?, 'draft', ?, ?, ?)");
            $stInv->execute([$tenantId, $invNum, $clientId, date('Y-m-d'), $body['currency'] ?? 'AED', $subtotal, $total]);
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
            $tenant = \Core\ApiAuthenticator::authenticate($pdo, 'clients:read');
            $tenantId = (int)$tenant['id'];
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

