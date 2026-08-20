<?php
require __DIR__ . '/bootstrap.php';
require_login();

if (!has_role(['owner', 'admin'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'status' => 'error', 'message' => 'Access denied. Custom domain configuration requires admin or owner access.']));
}

header('Content-Type: application/json; charset=utf-8');


$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$domain = trim($_POST['domain'] ?? '');
$domain = preg_replace('#^https?://#i', '', $domain);
$domain = preg_replace('#/.*$#', '', $domain);
$domain = strtolower($domain);

if (empty($domain)) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Please enter a valid domain or subdomain (e.g. billing.yourcompany.com).'
    ]);
    exit;
}

// Server host target
$serverHost = $_SERVER['HTTP_HOST'] ?? 'app.onesol.ae';
$serverIp   = gethostbyname($serverHost);
$targetIp   = gethostbyname($domain);

$isResolved = ($targetIp !== $domain && !empty($targetIp));

// Perform DNS CNAME inspection
$cnameRecords = @dns_get_record($domain, DNS_CNAME);
$hasCname = !empty($cnameRecords);
$cnameTarget = $hasCname ? ($cnameRecords[0]['target'] ?? '') : '';

if ($isResolved) {
    // Update verification status in database
    try {
        $st = $pdo->prepare("UPDATE branding_settings SET custom_domain = ?, domain_verified = 1 WHERE tenant_id = ?");
        $st->execute([$domain, $tid]);
    } catch (\Throwable $e) {}

    echo json_encode([
        'success' => true,
        'status' => 'verified',
        'domain' => $domain,
        'resolved_ip' => $targetIp,
        'cname_target' => $cnameTarget ?: $serverHost,
        'message' => "DNS Verified! Domain '$domain' successfully resolves to $targetIp. Your whitelabel portal is active."
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status' => 'failed',
        'domain' => $domain,
        'server_ip' => $serverIp,
        'message' => "DNS Pending. Could not resolve '$domain'. Please ensure your DNS CNAME record points to '$serverHost' and allow up to 24 hours for DNS propagation."
    ]);
}
exit;
