<?php
require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$baseUrl = "{$protocol}://{$host}{$scriptDir}/api/v1/mcp.php";

$token = trim($_GET['token'] ?? $_GET['api_key'] ?? '');

$schema = [
    'openapi' => '3.0.1',
    'info' => [
        'title' => 'OneSol Invoice Manager AI API',
        'description' => 'Tenant-Isolated Financial Accounting and Invoicing API for ChatGPT Custom Actions.',
        'version' => 'v1.0'
    ],
    'servers' => [
        [
            'url' => $baseUrl . ($token !== '' ? '?token=' . urlencode($token) : '')
        ]
    ],
    'paths' => [
        '/' => [
            'post' => [
                'summary' => 'Execute MCP Tool or JSON-RPC Query',
                'operationId' => 'executeMcpTool',
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
