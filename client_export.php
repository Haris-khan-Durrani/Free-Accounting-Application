<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="clients_export_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');

// Header
fputcsv($output, [
    'Company Name',
    'Contact Name',
    'Email Address',
    'Phone Number',
    'Tax / TRN Number',
    'Address',
    'City',
    'Country',
    'Currency',
    'Created At'
]);

$st = $pdo->prepare("SELECT * FROM clients WHERE tenant_id = ? ORDER BY company_name ASC");
$st->execute([$tid]);
$clients = $st->fetchAll();

foreach ($clients as $c) {
    fputcsv($output, [
        $c['company_name'],
        $c['contact_name'],
        $c['email'],
        $c['phone'],
        $c['tax_number'],
        $c['address'],
        $c['city'],
        $c['country'] ?: 'UAE',
        $c['currency'] ?: 'AED',
        $c['created_at']
    ]);
}

fclose($output);
exit;
