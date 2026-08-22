<?php
require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) ? "https" : "http";
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $protocol = "https";
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$token = trim($_GET['token'] ?? $_GET['api_key'] ?? '');

$serverHost = "{$protocol}://{$host}";
$mcpPath = "{$scriptDir}/mcp.php";

$schema = [
    'openapi' => '3.1.0',
    'info' => [
        'title' => 'OneSol Invoice Manager AI API',
        'description' => 'Tenant-Isolated Financial Accounting and Invoicing API for ChatGPT Custom Actions.',
        'version' => 'v1.0'
    ],
    'servers' => [
        [
            'url' => $serverHost
        ]
    ],
    'paths' => [
        $mcpPath => [
            'post' => [
                'summary' => 'Execute MCP Tool or JSON-RPC Query',
                'operationId' => 'executeMcpTool',
                'parameters' => [
                    [
                        'name' => 'token',
                        'in' => 'query',
                        'required' => true,
                        'description' => 'Tenant MCP Authentication Token',
                        'schema' => [
                            'type' => 'string',
                            'default' => $token
                        ]
                    ]
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'jsonrpc' => ['type' => 'string', 'example' => '2.0'],
                                    'id' => ['type' => 'integer', 'example' => 1],
                                    'method' => ['type' => 'string', 'example' => 'tools/call'],
                                    'params' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'get_financial_summary'],
                                            'arguments' => ['type' => 'object']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Successful MCP JSON-RPC Response'
                    ]
                ]
            ]
        ]
    ]
];

echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
