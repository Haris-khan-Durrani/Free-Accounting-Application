<?php
require __DIR__.'/bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
verify_csrf();

// Only admin, owner, accountant roles can delete invoices — viewers and sales cannot
if (!has_role(['owner', 'admin', 'accountant'])) {
    flash('error', 'Permission denied. You do not have access to delete invoices.');
    redirect('invoices.php');
}

$id  = (int)($_POST['id'] ?? 0);
$tid = tenant_id();

// Scope DELETE to current tenant — prevents cross-tenant deletion by guessing IDs
$st = $pdo->prepare('DELETE FROM invoices WHERE id = ? AND tenant_id = ?');
$st->execute([$id, $tid]);

flash('success', 'Invoice deleted.');
redirect('invoices.php');

