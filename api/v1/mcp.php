<?php
require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = $GLOBALS['pdo'];

// Authenticate MCP Request via Header (Bearer/X-API-Key) or Query Token (?token=os_live_...)
$tenant = \Core\ApiAuthenticator::authenticateMcp($pdo);

// Initialize MCP Service
$mcpService = new \Services\McpService($pdo, $tenant);

// Handle GET / Info request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'online',
        'server' => 'OneSol Invoice Manager MCP Server',
        'version' => '1.0.0',
        'protocol' => '2024-11-05',
        'tenant' => [
            'id' => (int)$tenant['id'],
            'name' => $tenant['name'] ?? 'Workspace',
            'code' => $tenant['code'] ?? 'TENANT'
        ],
        'endpoints' => [
            'mcp_json_rpc' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}"
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Handle POST JSON-RPC Request
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => null,
        'error' => [
            'code' => -32700,
            'message' => 'Parse error. Invalid JSON payload.'
        ]
    ]);
    exit;
}

$response = $mcpService->handleRequest($payload);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
