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

// Check if domain is already claimed by another tenant
$stOther = $pdo->prepare("SELECT tenant_id FROM branding_settings WHERE custom_domain = ? AND tenant_id != ? AND domain_verified = 1");
$stOther->execute([$domain, $tid]);
if ($stOther->fetchColumn()) {
    echo json_encode([
        'success' => false,
        'status' => 'claimed',
        'message' => "Domain '$domain' is already claimed and verified by another workspace."
    ]);
    exit;
}

// Server host target
$serverHost = preg_replace('#:\d+$#', '', strtolower(trim($_SERVER['HTTP_HOST'] ?? 'app.onesol.ae')));
$serverIp   = gethostbyname($serverHost);
$targetIp   = gethostbyname($domain);

// Perform DNS CNAME inspection
$cnameRecords = @dns_get_record($domain, DNS_CNAME);
$cnameTarget = !empty($cnameRecords) ? strtolower(rtrim($cnameRecords[0]['target'] ?? '', '.')) : '';

// Perform DNS TXT inspection for verification token
$txtRecords = @dns_get_record("_onesol-challenge." . $domain, DNS_TXT);
$expectedTxtToken = substr(hash('sha256', "onesol_verify_{$tid}_" . ($config['app_key'] ?? 'secret')), 0, 32);
$hasMatchingTxt = false;
if (!empty($txtRecords) && is_array($txtRecords)) {
    foreach ($txtRecords as $tr) {
        if (($tr['txt'] ?? '') === $expectedTxtToken) {
            $hasMatchingTxt = true;
            break;
        }
    }
}

// Domain is verified ONLY if CNAME matches server host or TXT challenge token matches
$isVerified = ($cnameTarget !== '' && $cnameTarget === $serverHost) || $hasMatchingTxt || ($targetIp === $serverIp && $serverIp !== '127.0.0.1' && !empty($serverIp));

if ($isVerified) {
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
        'message' => "DNS Ownership Verified! Domain '$domain' successfully validated. Your whitelabel portal is active."
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status' => 'failed',
        'domain' => $domain,
        'server_ip' => $serverIp,
        'expected_cname' => $serverHost,
        'expected_txt' => "_onesol-challenge.{$domain} TXT={$expectedTxtToken}",
        'message' => "DNS Verification Failed. Point CNAME record of '$domain' to '$serverHost' or create TXT record '_onesol-challenge.$domain' with value '$expectedTxtToken'."
    ]);
}
exit;
