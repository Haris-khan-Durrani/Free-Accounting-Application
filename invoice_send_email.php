<?php
require __DIR__ . '/bootstrap.php';
require_login();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$id = (int)($_REQUEST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid invoice ID.');
    redirect('index');
}

// Fetch invoice and client
$st = $pdo->prepare('
    SELECT i.*, c.company_name, c.contact_name, c.email client_email
    FROM invoices i
    JOIN clients c ON c.id = i.client_id
    WHERE i.id = ? AND i.tenant_id = ?
');
$st->execute([$id, $tid]);
$inv = $st->fetch();

if (!$inv) {
    flash('error', 'Invoice not found.');
    redirect('index');
}

if (empty($inv['client_email'])) {
    flash('error', 'Client has no email address configured in Client Directory.');
    redirect("invoice_view.php?id=$id");
}

$tenantInfo = tenant();
$brand = branding();
$currency = $inv['currency'];
$total = money((float)$inv['total'], $currency);

$publicUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF']) . '/public_invoice.php?id=' . $inv['id'];

// Build Email Subject & HTML Body
$subject = sprintf('Tax Invoice #%s from %s', $inv['invoice_number'], $tenantInfo['name']);

$htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <div style="background: #0f172a; padding: 24px; text-align: center; color: #ffffff;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 800; text-transform: uppercase;">' . e($tenantInfo['name']) . '</h1>
            <p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8;">Tax Invoice Issued</p>
        </div>
        <div style="padding: 32px;">
            <p style="font-size: 15px; font-weight: bold; margin-top: 0;">Dear ' . e($inv['contact_name'] ?: $inv['company_name']) . ',</p>
            <p style="font-size: 14px; color: #475569; line-height: 1.6;">Please find detailed below your tax invoice <strong>#' . e($inv['invoice_number']) . '</strong> issued on <strong>' . date('d M Y', strtotime($inv['invoice_date'])) . '</strong>.</p>
            
            <div style="background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin: 24px 0; text-align: center;">
                <span style="font-size: 11px; text-transform: uppercase; font-weight: bold; color: #64748b; tracking-wider: 1px; display: block; margin-bottom: 4px;">Total Amount Due</span>
                <span style="font-size: 28px; font-weight: 900; color: #d97706; font-family: monospace;">' . $total . '</span>
                <p style="font-size: 12px; color: #64748b; margin: 6px 0 0 0;">Payment Due Date: <strong>' . date('d M Y', strtotime($inv['valid_until'])) . '</strong></p>
            </div>

            <div style="text-align: center; margin: 32px 0 24px 0;">
                <a href="' . e($publicUrl) . '" style="background: #d97706; color: #ffffff; padding: 14px 28px; border-radius: 12px; font-weight: 800; font-size: 14px; text-decoration: none; display: inline-block;">View & Pay Invoice Online →</a>
            </div>

            <p style="font-size: 12px; color: #94a3b8; text-align: center; margin-top: 24px;">Thank you for your valued business!</p>
        </div>
        <div style="background: #f1f5f9; padding: 16px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #e2e8f0;">
            &copy; ' . date('Y') . ' ' . e($tenantInfo['name']) . '. All rights reserved.
        </div>
    </div>
</body>
</html>
';

$sent = \Services\Mailer::send($pdo, $tid, $inv['client_email'], $subject, $htmlBody);

if ($sent) {
    if ($inv['status'] === 'draft') {
        $stUp = $pdo->prepare('UPDATE invoices SET status = "sent" WHERE id = ? AND tenant_id = ?');
        $stUp->execute([$id, $tid]);
    }
    log_audit($pdo, 'send_invoice_email', 'invoices', $id, "Emailed invoice #{$inv['invoice_number']} to {$inv['client_email']}");
    flash('success', "Invoice #{$inv['invoice_number']} successfully emailed to {$inv['client_email']}.");
} else {
    flash('error', "Failed to send email to {$inv['client_email']}. Check your SMTP settings under Management -> Custom SMTP.");
}

redirect("invoice_view.php?id=$id");
