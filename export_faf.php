<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

$brand = branding();
$tenant = tenant();

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="FTA_Audit_File_' . date('Ymd') . '.faf"');

$out = fopen('php://output', 'w');

// Section 1: Company Profile (Header)
fputs($out, "=== FTA AUDIT FILE (FAF) ===\r\n");
fputs($out, "Company Name: " . $brand['company_name'] . "\r\n");
fputs($out, "TRN: " . ($brand['tax_number'] ?: '100000000000003') . "\r\n");
fputs($out, "Tax Period Start: " . $startDate . "\r\n");
fputs($out, "Tax Period End: " . $endDate . "\r\n");
fputs($out, "FAF File Generated Date: " . date('Y-m-d H:i:s') . "\r\n");
fputs($out, "Currency: AED\r\n\r\n");

// Section 2: Sales Ledger (Tax Invoices Issued)
fputs($out, "=== SALES LEDGER (OUTPUT TAX) ===\r\n");
fputs($out, "CustomerName|CustomerTRN|InvoiceDate|InvoiceNumber|LineDescription|SalesValueAED|VATAmountAED|VATRate%\r\n");

$stSales = $pdo->prepare("
    SELECT i.*, c.company_name, c.tax_number as client_trn, ii.description, ii.amount as line_amount
    FROM invoices i
    JOIN clients c ON c.id = i.client_id
    LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
    WHERE i.tenant_id = ? AND i.status != 'cancelled' AND i.invoice_date BETWEEN ? AND ?
    ORDER BY i.invoice_date ASC
");
$stSales->execute([$tid, $startDate, $endDate]);
$sales = $stSales->fetchAll();

foreach ($sales as $s) {
    $lineVal = (float)($s['line_amount'] ?: $s['subtotal']);
    $vatVal = round($lineVal * 0.05, 2);
    $row = [
        str_replace('|', ' ', $s['company_name']),
        $s['client_trn'] ?: 'N/A',
        $s['invoice_date'],
        $s['invoice_number'],
        str_replace('|', ' ', $s['description'] ?: 'Invoice Item'),
        number_format($lineVal, 2, '.', ''),
        number_format($vatVal, 2, '.', ''),
        '5%'
    ];
    fputs($out, implode('|', $row) . "\r\n");
}

// Section 3: Purchase Ledger (Expenses Input Tax)
fputs($out, "\r\n=== PURCHASE LEDGER (INPUT TAX RECOVERABLE) ===\r\n");
fputs($out, "SupplierName|SupplierTRN|ExpenseDate|ExpenseCategory|Description|PurchaseValueAED|VATAmountAED\r\n");

$stExp = $pdo->prepare("
    SELECT e.*, COALESCE(cat.name, 'General Expense') as category_name
    FROM expenses e
    LEFT JOIN expense_categories cat ON cat.id = e.category_id
    WHERE e.tenant_id = ? AND e.expense_date BETWEEN ? AND ?
    ORDER BY e.expense_date ASC
");
$stExp->execute([$tid, $startDate, $endDate]);
$expenses = $stExp->fetchAll();

foreach ($expenses as $e) {
    $row = [
        str_replace('|', ' ', $e['vendor_name']),
        'N/A',
        $e['expense_date'],
        str_replace('|', ' ', $e['category_name']),
        str_replace('|', ' ', $e['notes'] ?: 'Business Expense'),
        number_format((float)$e['subtotal'], 2, '.', ''),
        number_format((float)$e['tax_amount'], 2, '.', '')
    ];
    fputs($out, implode('|', $row) . "\r\n");
}

fclose($out);
exit;
