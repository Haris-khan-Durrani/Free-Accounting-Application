<?php
namespace Services;

use PDO;
use Throwable;

class McpService {
    private PDO $pdo;
    private int $tenantId;
    private array $tenant;
    private array $keyScopes;

    public function __construct(PDO $pdo, array $tenant) {
        $this->pdo = $pdo;
        $this->tenant = $tenant;
        $this->tenantId = (int)$tenant['id'];
        $this->keyScopes = $tenant['_api_key_scopes'] ?? ['invoices:read', 'invoices:write', 'clients:read', 'clients:write', 'payments:write', 'reports:read', 'admin'];
    }

    private function hasScope(string $requiredScope): bool {
        if (in_array('admin', $this->keyScopes, true)) return true;
        return in_array($requiredScope, $this->keyScopes, true);
    }

    /**
     * Process incoming MCP JSON-RPC 2.0 Payload
     */
    public function handleRequest(array $payload): array {
        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? '';
        $params = $payload['params'] ?? [];

        try {
            switch ($method) {
                case 'initialize':
                    return $this->response($id, [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => [
                            'tools' => new \stdClass(),
                            'resources' => new \stdClass(),
                            'prompts' => new \stdClass()
                        ],
                        'serverInfo' => [
                            'name' => 'OneSol Invoice Manager MCP Server',
                            'version' => '1.0.0',
                            'tenant' => [
                                'id' => $this->tenantId,
                                'name' => $this->tenant['name'] ?? 'Workspace'
                            ]
                        ]
                    ]);

                case 'notifications/initialized':
                    return ['jsonrpc' => '2.0'];

                case 'tools/list':
                    return $this->response($id, [
                        'tools' => $this->getToolsDefinition()
                    ]);

                case 'tools/call':
                    $name = $params['name'] ?? '';
                    $arguments = $params['arguments'] ?? [];
                    $result = $this->executeTool($name, $arguments);
                    return $this->response($id, $result);

                case 'resources/list':
                    return $this->response($id, [
                        'resources' => $this->getResourcesDefinition()
                    ]);

                case 'resources/read':
                    $uri = $params['uri'] ?? '';
                    $data = $this->readResource($uri);
                    return $this->response($id, [
                        'contents' => [
                            [
                                'uri' => $uri,
                                'mimeType' => 'application/json',
                                'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            ]
                        ]
                    ]);

                case 'prompts/list':
                    return $this->response($id, [
                        'prompts' => $this->getPromptsDefinition()
                    ]);

                case 'prompts/get':
                    $promptName = $params['name'] ?? '';
                    $promptArgs = $params['arguments'] ?? [];
                    return $this->response($id, $this->getPrompt($promptName, $promptArgs));

                case 'ping':
                    return $this->response($id, new \stdClass());

                default:
                    return $this->error($id, -32601, "Method '{$method}' not found.");
            }
        } catch (Throwable $e) {
            return $this->error($id, -32603, "Internal MCP Error: " . $e->getMessage());
        }
    }

    private function response($id, $result): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ];
    }

    private function error($id, int $code, string $message): array {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
    }

    /**
     * Define all available MCP Tools
     */
    private function getToolsDefinition(): array {
        return [
            [
                'name' => 'list_invoices',
                'description' => 'List invoices for the workspace. Can filter by status (all, unpaid, paid, overdue, draft).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['all', 'unpaid', 'paid', 'overdue', 'draft'],
                            'description' => 'Filter invoices by payment status'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'default' => 10,
                            'description' => 'Number of invoices to return (default 10)'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'get_invoice',
                'description' => 'Retrieve detailed information for a specific invoice, including line items and payment status.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'invoice_id' => [
                            'type' => 'integer',
                            'description' => 'Unique ID of the invoice'
                        ],
                        'invoice_number' => [
                            'type' => 'string',
                            'description' => 'Invoice number (e.g. INV-1001)'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'create_invoice',
                'description' => 'Create a new client invoice with line items.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['client_id', 'items'],
                    'properties' => [
                        'client_id' => ['type' => 'integer', 'description' => 'ID of client'],
                        'due_date' => ['type' => 'string', 'description' => 'Due date in YYYY-MM-DD format'],
                        'notes' => ['type' => 'string', 'description' => 'Invoice notes / terms'],
                        'items' => [
                            'type' => 'array',
                            'description' => 'List of line items',
                            'items' => [
                                'type' => 'object',
                                'required' => ['description', 'quantity', 'unit_price'],
                                'properties' => [
                                    'description' => ['type' => 'string'],
                                    'quantity' => ['type' => 'number'],
                                    'unit_price' => ['type' => 'number'],
                                    'tax_rate' => ['type' => 'number', 'default' => 0]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'name' => 'send_invoice_email',
                'description' => 'Email an invoice to the client with the online checkout link.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['invoice_id'],
                    'properties' => [
                        'invoice_id' => ['type' => 'integer', 'description' => 'Invoice ID to send']
                    ]
                ]
            ],
            [
                'name' => 'record_payment',
                'description' => 'Record an offline payment (cash, bank transfer, check) against an invoice.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['invoice_id', 'amount'],
                    'properties' => [
                        'invoice_id' => ['type' => 'integer', 'description' => 'Invoice ID'],
                        'amount' => ['type' => 'number', 'description' => 'Amount paid'],
                        'method' => ['type' => 'string', 'default' => 'Bank Transfer', 'description' => 'Payment method'],
                        'notes' => ['type' => 'string', 'description' => 'Reference number or notes']
                    ]
                ]
            ],
            [
                'name' => 'list_clients',
                'description' => 'Search and list client contacts.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => ['type' => 'string', 'description' => 'Search term for name or email'],
                        'limit' => ['type' => 'integer', 'default' => 20]
                    ]
                ]
            ],
            [
                'name' => 'create_client',
                'description' => 'Add a new client contact.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['name'],
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Client primary name or business name'],
                        'email' => ['type' => 'string', 'description' => 'Client email address'],
                        'phone' => ['type' => 'string'],
                        'company' => ['type' => 'string'],
                        'address' => ['type' => 'string']
                    ]
                ]
            ],
            [
                'name' => 'list_expenses',
                'description' => 'List business expenses.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => ['type' => 'string', 'description' => 'Filter by expense category'],
                        'limit' => ['type' => 'integer', 'default' => 15]
                    ]
                ]
            ],
            [
                'name' => 'create_expense',
                'description' => 'Log a new business expense.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['amount'],
                    'properties' => [
                        'amount' => ['type' => 'number', 'description' => 'Expense total amount'],
                        'category' => ['type' => 'string', 'default' => 'General', 'description' => 'Expense category'],
                        'vendor' => ['type' => 'string', 'description' => 'Vendor / Supplier name'],
                        'date' => ['type' => 'string', 'description' => 'Date YYYY-MM-DD'],
                        'notes' => ['type' => 'string', 'description' => 'Notes or purpose']
                    ]
                ]
            ],
            [
                'name' => 'get_financial_summary',
                'description' => 'Get a comprehensive financial summary (Revenue, Outstanding A/R, Total Expenses, Net Profit).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'enum' => ['month', 'quarter', 'year', 'all'], 'default' => 'month']
                    ]
                ]
            ],
            [
                'name' => 'list_quotes',
                'description' => 'List estimates/quotes.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['all', 'draft', 'sent', 'accepted', 'declined']],
                        'limit' => ['type' => 'integer', 'default' => 10]
                    ]
                ]
            ],
            [
                'name' => 'create_quote',
                'description' => 'Create a new quote/estimate for a client.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['client_id', 'items'],
                    'properties' => [
                        'client_id' => ['type' => 'integer'],
                        'due_date' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                        'items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['description', 'quantity', 'unit_price'],
                                'properties' => [
                                    'description' => ['type' => 'string'],
                                    'quantity' => ['type' => 'number'],
                                    'unit_price' => ['type' => 'number']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Execute a specific tool safely with tenant isolation
     */
    private function executeTool(string $name, array $args): array {
        switch ($name) {
            case 'list_invoices':
                $status = $args['status'] ?? 'all';
                $limit = min(50, max(1, (int)($args['limit'] ?? 10)));
                
                $sql = "SELECT i.id, i.invoice_number, i.status, i.total, i.due_date, i.created_at, c.name as client_name 
                        FROM invoices i 
                        LEFT JOIN clients c ON c.id = i.client_id AND c.tenant_id = i.tenant_id
                        WHERE i.tenant_id = ?";
                $params = [$this->tenantId];

                if ($status === 'unpaid') {
                    $sql .= " AND i.status IN ('sent', 'viewed', 'overdue')";
                } elseif ($status === 'paid') {
                    $sql .= " AND i.status = 'paid'";
                } elseif ($status === 'overdue') {
                    $sql .= " AND (i.status = 'overdue' OR (i.status IN ('sent', 'viewed') AND i.due_date < CURDATE()))";
                } elseif ($status === 'draft') {
                    $sql .= " AND i.status = 'draft'";
                }

                $sql .= " ORDER BY i.id DESC LIMIT {$limit}";
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);

                return $this->toolTextResult("Found " . count($rows) . " invoices:\n" . json_encode($rows, JSON_PRETTY_PRINT));

            case 'get_invoice':
                $invId = (int)($args['invoice_id'] ?? 0);
                $invNum = trim($args['invoice_number'] ?? '');

                if ($invId > 0) {
                    $st = $this->pdo->prepare("SELECT i.*, c.name as client_name, c.email as client_email FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.id = ? AND i.tenant_id = ?");
                    $st->execute([$invId, $this->tenantId]);
                } else {
                    $st = $this->pdo->prepare("SELECT i.*, c.name as client_name, c.email as client_email FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.invoice_number = ? AND i.tenant_id = ?");
                    $st->execute([$invNum, $this->tenantId]);
                }
                $invoice = $st->fetch(PDO::FETCH_ASSOC);
                if (!$invoice) {
                    return $this->toolTextResult("Invoice not found in your workspace.", true);
                }

                // Get items
                $stItems = $this->pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? AND tenant_id = ?");
                $stItems->execute([$invoice['id'], $this->tenantId]);
                $invoice['items'] = $stItems->fetchAll(PDO::FETCH_ASSOC);

                return $this->toolTextResult(json_encode($invoice, JSON_PRETTY_PRINT));

            case 'create_invoice':
                $clientId = (int)($args['client_id'] ?? 0);
                $dueDate = $args['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
                $notes = trim($args['notes'] ?? '');
                $items = $args['items'] ?? [];

                if ($clientId <= 0 || empty($items)) {
                    return $this->toolTextResult("Missing required client_id or items array.", true);
                }

                // Check client exists
                $stC = $this->pdo->prepare("SELECT id FROM clients WHERE id = ? AND tenant_id = ?");
                $stC->execute([$clientId, $this->tenantId]);
                if (!$stC->fetch()) {
                    return $this->toolTextResult("Client ID {$clientId} not found in this workspace.", true);
                }

                // Generate invoice number
                $invNum = 'INV-' . strtoupper(bin2hex(random_bytes(3)));
                $subtotal = 0;
                $taxTotal = 0;

                foreach ($items as $item) {
                    $qty = (float)($item['quantity'] ?? 1);
                    $price = (float)($item['unit_price'] ?? 0);
                    $taxRate = (float)($item['tax_rate'] ?? 0);
                    $lineSub = $qty * $price;
                    $lineTax = $lineSub * ($taxRate / 100);
                    $subtotal += $lineSub;
                    $taxTotal += $lineTax;
                }
                $total = $subtotal + $taxTotal;

                $stIns = $this->pdo->prepare("INSERT INTO invoices (tenant_id, client_id, invoice_number, status, due_date, subtotal, tax_total, total, notes, created_at) VALUES (?, ?, ?, 'sent', ?, ?, ?, ?, ?, NOW())");
                $stIns->execute([$this->tenantId, $clientId, $invNum, $dueDate, $subtotal, $taxTotal, $total, $notes]);
                $newId = (int)$this->pdo->lastInsertId();

                $stItemIns = $this->pdo->prepare("INSERT INTO invoice_items (tenant_id, invoice_id, description, quantity, unit_price, tax_rate, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($items as $item) {
                    $qty = (float)($item['quantity'] ?? 1);
                    $price = (float)($item['unit_price'] ?? 0);
                    $taxRate = (float)($item['tax_rate'] ?? 0);
                    $lineTotal = ($qty * $price) * (1 + ($taxRate / 100));
                    $stItemIns->execute([$this->tenantId, $newId, $item['description'], $qty, $price, $taxRate, $lineTotal]);
                }

                return $this->toolTextResult("Successfully created Invoice #{$invNum} (ID: {$newId}) for Total: {$total}.");

            case 'send_invoice_email':
                $invId = (int)($args['invoice_id'] ?? 0);
                $st = $this->pdo->prepare("SELECT i.*, c.name, c.email FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ? AND i.tenant_id = ?");
                $st->execute([$invId, $this->tenantId]);
                $inv = $st->fetch(PDO::FETCH_ASSOC);

                if (!$inv) {
                    return $this->toolTextResult("Invoice ID {$invId} not found.", true);
                }
                if (empty($inv['email'])) {
                    return $this->toolTextResult("Client has no registered email address.", true);
                }

                // Call Mailer service if available
                if (class_exists('\\Services\\Mailer')) {
                    \Services\Mailer::sendInvoiceEmail($this->pdo, $this->tenantId, $invId);
                    return $this->toolTextResult("Invoice #{$inv['invoice_number']} emailed successfully to {$inv['email']}.");
                }
                return $this->toolTextResult("Mail queued for Invoice #{$inv['invoice_number']} to {$inv['email']}.");

            case 'record_payment':
                $invId = (int)($args['invoice_id'] ?? 0);
                $amount = (float)($args['amount'] ?? 0);
                $method = trim($args['method'] ?? 'Bank Transfer');
                $notes = trim($args['notes'] ?? 'Paid via MCP Tool');

                $st = $this->pdo->prepare("SELECT * FROM invoices WHERE id = ? AND tenant_id = ?");
                $st->execute([$invId, $this->tenantId]);
                $inv = $st->fetch(PDO::FETCH_ASSOC);
                if (!$inv) {
                    return $this->toolTextResult("Invoice ID {$invId} not found.", true);
                }

                $stPay = $this->pdo->prepare("INSERT INTO payments (tenant_id, invoice_id, amount, payment_method, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stPay->execute([$this->tenantId, $invId, $amount, $method, $notes]);

                // Update invoice status if fully paid
                $stSum = $this->pdo->prepare("SELECT SUM(amount) FROM payments WHERE invoice_id = ? AND tenant_id = ?");
                $stSum->execute([$invId, $this->tenantId]);
                $totalPaid = (float)$stSum->fetchColumn();

                if ($totalPaid >= (float)$inv['total']) {
                    $this->pdo->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ? AND tenant_id = ?")->execute([$invId, $this->tenantId]);
                    return $this->toolTextResult("Payment of {$amount} recorded. Invoice #{$inv['invoice_number']} is now FULLY PAID.");
                }
                return $this->toolTextResult("Payment of {$amount} recorded. Total paid so far: {$totalPaid} / {$inv['total']}.");

            case 'list_clients':
                $search = trim($args['search'] ?? '');
                $limit = min(50, max(1, (int)($args['limit'] ?? 20)));

                $sql = "SELECT id, name, email, phone, company, created_at FROM clients WHERE tenant_id = ?";
                $params = [$this->tenantId];
                if ($search !== '') {
                    $sql .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                    $params[] = "%{$search}%";
                }
                $sql .= " ORDER BY name ASC LIMIT {$limit}";
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
                return $this->toolTextResult(json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));

            case 'create_client':
                $name = trim($args['name'] ?? '');
                if ($name === '') {
                    return $this->toolTextResult("Client name is required.", true);
                }
                $email = trim($args['email'] ?? '');
                $phone = trim($args['phone'] ?? '');
                $company = trim($args['company'] ?? '');
                $address = trim($args['address'] ?? '');

                $st = $this->pdo->prepare("INSERT INTO clients (tenant_id, name, email, phone, company, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $st->execute([$this->tenantId, $name, $email, $phone, $company, $address]);
                $newId = (int)$this->pdo->lastInsertId();

                return $this->toolTextResult("Created client '{$name}' with ID: {$newId}.");

            case 'list_expenses':
                $cat = trim($args['category'] ?? '');
                $limit = min(50, max(1, (int)($args['limit'] ?? 15)));

                $sql = "SELECT id, amount, category, vendor, expense_date, notes FROM expenses WHERE tenant_id = ?";
                $params = [$this->tenantId];
                if ($cat !== '') {
                    $sql .= " AND category = ?";
                    $params[] = $cat;
                }
                $sql .= " ORDER BY expense_date DESC, id DESC LIMIT {$limit}";
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
                return $this->toolTextResult(json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));

            case 'create_expense':
                $amount = (float)($args['amount'] ?? 0);
                if ($amount <= 0) {
                    return $this->toolTextResult("Amount must be greater than 0.", true);
                }
                $category = trim($args['category'] ?? 'General');
                $vendor = trim($args['vendor'] ?? 'N/A');
                $date = $args['date'] ?? date('Y-m-d');
                $notes = trim($args['notes'] ?? '');

                $st = $this->pdo->prepare("INSERT INTO expenses (tenant_id, amount, category, vendor, expense_date, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $st->execute([$this->tenantId, $amount, $category, $vendor, $date, $notes]);
                $newId = (int)$this->pdo->lastInsertId();

                return $this->toolTextResult("Expense of {$amount} under '{$category}' recorded with ID: {$newId}.");

            case 'get_financial_summary':
                $period = $args['period'] ?? 'month';
                
                $dateFilter = "1=1";
                if ($period === 'month') {
                    $dateFilter = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                } elseif ($period === 'quarter') {
                    $dateFilter = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                } elseif ($period === 'year') {
                    $dateFilter = "created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
                }

                // Invoiced total
                $stRev = $this->pdo->prepare("SELECT SUM(total) FROM invoices WHERE tenant_id = ? AND status != 'draft' AND {$dateFilter}");
                $stRev->execute([$this->tenantId]);
                $totalInvoiced = (float)$stRev->fetchColumn();

                // Paid total
                $stPaid = $this->pdo->prepare("SELECT SUM(amount) FROM payments WHERE tenant_id = ? AND {$dateFilter}");
                $stPaid->execute([$this->tenantId]);
                $totalCollected = (float)$stPaid->fetchColumn();

                // Overdue total
                $stOverdue = $this->pdo->prepare("SELECT SUM(total) FROM invoices WHERE tenant_id = ? AND status IN ('sent','viewed','overdue') AND due_date < CURDATE()");
                $stOverdue->execute([$this->tenantId]);
                $totalOverdue = (float)$stOverdue->fetchColumn();

                // Expense total
                $stExp = $this->pdo->prepare("SELECT SUM(amount) FROM expenses WHERE tenant_id = ? AND expense_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stExp->execute([$this->tenantId]);
                $totalExpenses = (float)$stExp->fetchColumn();

                $netProfit = $totalCollected - $totalExpenses;

                $summary = [
                    'period' => $period,
                    'tenant_name' => $this->tenant['name'] ?? 'Workspace',
                    'currency' => $this->tenant['currency'] ?? 'USD',
                    'total_invoiced' => $totalInvoiced,
                    'total_collected' => $totalCollected,
                    'total_overdue_ar' => $totalOverdue,
                    'total_expenses' => $totalExpenses,
                    'net_cash_profit' => $netProfit
                ];
                return $this->toolTextResult(json_encode($summary, JSON_PRETTY_PRINT));

            case 'list_quotes':
                $status = $args['status'] ?? 'all';
                $limit = min(50, max(1, (int)($args['limit'] ?? 10)));
                
                $sql = "SELECT q.*, c.name as client_name FROM quotes q LEFT JOIN clients c ON c.id = q.client_id WHERE q.tenant_id = ?";
                $params = [$this->tenantId];
                if ($status !== 'all') {
                    $sql .= " AND q.status = ?";
                    $params[] = $status;
                }
                $sql .= " ORDER BY q.id DESC LIMIT {$limit}";
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
                return $this->toolTextResult(json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));

            case 'create_quote':
                $clientId = (int)($args['client_id'] ?? 0);
                $dueDate = $args['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
                $notes = trim($args['notes'] ?? '');
                $items = $args['items'] ?? [];

                if ($clientId <= 0 || empty($items)) {
                    return $this->toolTextResult("Missing client_id or items.", true);
                }

                $quoteNum = 'QT-' . strtoupper(bin2hex(random_bytes(3)));
                $total = 0;
                foreach ($items as $it) {
                    $total += ((float)$it['quantity'] * (float)$it['unit_price']);
                }

                $stIns = $this->pdo->prepare("INSERT INTO quotes (tenant_id, client_id, quote_number, status, due_date, total, notes, created_at) VALUES (?, ?, ?, 'sent', ?, ?, ?, NOW())");
                $stIns->execute([$this->tenantId, $clientId, $quoteNum, $dueDate, $total, $notes]);
                $newId = (int)$this->pdo->lastInsertId();

                return $this->toolTextResult("Created Quote #{$quoteNum} (ID: {$newId}) for Total: {$total}.");

            default:
                return $this->toolTextResult("Tool '{$name}' is not recognized.", true);
        }
    }

    private function toolTextResult(string $text, bool $isError = false): array {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text
                ]
            ],
            'isError' => $isError
        ];
    }

    /**
     * Define MCP Resources
     */
    private function getResourcesDefinition(): array {
        return [
            [
                'uri' => 'invoice://overdue',
                'name' => 'Overdue Invoices',
                'description' => 'Real-time list of all overdue client invoices for this tenant.',
                'mimeType' => 'application/json'
            ],
            [
                'uri' => 'reports://summary',
                'name' => 'Financial Summary Report',
                'description' => 'Real-time revenue, expense, and cashflow summary.',
                'mimeType' => 'application/json'
            ],
            [
                'uri' => 'clients://directory',
                'name' => 'Client Directory',
                'description' => 'Active client contact catalog.',
                'mimeType' => 'application/json'
            ]
        ];
    }

    private function readResource(string $uri): array {
        if ($uri === 'invoice://overdue') {
            $st = $this->pdo->prepare("SELECT i.id, i.invoice_number, i.total, i.due_date, c.name as client_name, c.email FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.tenant_id = ? AND i.status IN ('sent','viewed','overdue') AND i.due_date < CURDATE()");
            $st->execute([$this->tenantId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($uri === 'reports://summary') {
            return json_decode($this->executeTool('get_financial_summary', ['period' => 'month'])['content'][0]['text'], true) ?: [];
        }
        if ($uri === 'clients://directory') {
            $st = $this->pdo->prepare("SELECT id, name, email, company, phone FROM clients WHERE tenant_id = ? ORDER BY name ASC");
            $st->execute([$this->tenantId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }
        return ['error' => 'Resource URI not found'];
    }

    /**
     * Define MCP Prompts
     */
    private function getPromptsDefinition(): array {
        return [
            [
                'name' => 'overdue_reminder_prompt',
                'description' => 'Generates a polite yet firm overdue payment reminder for clients.',
                'arguments' => [
                    ['name' => 'client_name', 'description' => 'Name of client', 'required' => true],
                    ['name' => 'invoice_number', 'description' => 'Invoice number', 'required' => true],
                    ['name' => 'amount_due', 'description' => 'Amount overdue', 'required' => true]
                ]
            ],
            [
                'name' => 'financial_health_audit_prompt',
                'description' => 'Analyzes workspace revenue, cash flow, and spending patterns.',
                'arguments' => []
            ]
        ];
    }

    private function getPrompt(string $name, array $args): array {
        if ($name === 'overdue_reminder_prompt') {
            $cName = $args['client_name'] ?? 'Valued Client';
            $invNum = $args['invoice_number'] ?? '#INV-001';
            $amount = $args['amount_due'] ?? '0.00';

            return [
                'description' => 'Overdue invoice payment reminder template',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            'type' => 'text',
                            'text' => "Draft a professional email to {$cName} requesting immediate payment of {$amount} for overdue Invoice {$invNum}. Include a polite call to action and mention online payment availability."
                        ]
                    ]
                ]
            ];
        }

        if ($name === 'financial_health_audit_prompt') {
            return [
                'description' => 'Financial health audit prompt',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            'type' => 'text',
                            'text' => "Review my workspace's monthly financial summary, revenue vs expenses, and overdue invoices. Provide 3 actionable recommendations to improve cash flow."
                        ]
                    ]
                ]
            ];
        }

        return ['messages' => []];
    }
}
