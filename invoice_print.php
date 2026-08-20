<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare('SELECT i.*, c.company_name, c.contact_name, c.email, c.phone, c.address, c.tax_number FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ? AND i.tenant_id = ?');
$st->execute([$id, $tid]);
$inv = $st->fetch();

if (!$inv) exit('Invoice not found.');

$st = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id');
$st->execute([$id]);
$items = $st->fetchAll();

$brand = branding();
$templateId = $_GET['template'] ?? ($inv['template_id'] ?: $brand['default_invoice_template']);
if (!empty($brand['default_invoice_template']) && $brand['default_invoice_template'] === 'custom_drag_drop' && !isset($_GET['template'])) {
    $templateId = 'custom_drag_drop';
}

?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?=e($inv['invoice_number'])?> - <?=e($brand['company_name'])?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        body { background: #ffffff; margin: 0; padding: 20px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-page-wrapper { max-width: 900px; margin: 0 auto; }
        .print-actions-bar { text-align: right; margin-bottom: 20px; }
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .print-actions-bar, .no-print { display: none !important; }
            .print-page-wrapper { max-width: none; margin: 0; width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">
<div class="print-page-wrapper">
    <div class="print-actions-bar no-print">
        <button class="btn btn-gold btn-large" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>
    <?=\Services\InvoiceRenderer::render($inv, $items, $brand, $templateId)?>
</div>
</body>
</html>
