<?php
namespace Core;

use PDO;

class ApiAuthenticator {

    /**
     * Authenticate an API request strictly via HTTP Headers (Authorization Bearer or X-API-Key).
     * Enforces active tenant status, key expiration, and fine-grained permission scopes.
     * Rejects key secrets passed in URL query strings or POST body.
     */
    public static function authenticate(PDO $pdo, string $requiredScope = ''): array {
        // Disallow API key in query parameters or POST body (security requirement)
        if (isset($_GET['api_key']) || isset($_POST['api_key'])) {
            self::respondJson(false, 'Unauthorized. Passing API keys in query parameters or form body is insecure and prohibited. Use the Authorization: Bearer <key> header or X-API-Key header.', [], 401);
        }

        $headers = getallheaders();
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $headers['X-API-Key'] ?? $headers['x-api-key'] ?? '';

        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $apiKey = trim($matches[1]);
        }

        if (empty($apiKey)) {
            self::respondJson(false, 'Unauthorized. Missing API Key header. Provide your API key via X-API-Key header or Authorization: Bearer header.', [], 401);
        }

        $keyHash = hash('sha256', $apiKey);

        // Enforce 120 req/min rate limit per API Key
        if (!\Core\SecurityThrottle::checkRateLimit('api_' . $keyHash, 120, 60)) {
            self::respondJson(false, 'Too Many Requests. API key rate limit exceeded (120 requests per minute).', [], 429);
        }

        // 1. Query scoped api_keys table by key_hash (or fallback to legacy column if exists)
        $st = $pdo->prepare("
            SELECT ak.*, t.id as tenant_id, t.name as tenant_name, t.status as tenant_status
            FROM api_keys ak
            JOIN tenants t ON t.id = ak.tenant_id
            WHERE (ak.key_hash = ? OR ak.api_key = ?)
        ");
        $st->execute([$keyHash, $apiKey]);
        $keyRow = $st->fetch();

        if (!$keyRow) {
            self::respondJson(false, 'Forbidden. Invalid API key.', [], 403);
        }

        if (empty($keyRow['is_active'])) {
            self::respondJson(false, 'Forbidden. This API key has been revoked.', [], 403);
        }

        if (!empty($keyRow['expires_at']) && strtotime($keyRow['expires_at']) < time()) {
            self::respondJson(false, 'Forbidden. This API key expired on ' . $keyRow['expires_at'] . '.', [], 403);
        }

        if ($keyRow['tenant_status'] !== 'active' && $keyRow['tenant_status'] !== 'lifetime') {
            self::respondJson(false, 'Forbidden. The workspace for this API key is suspended.', [], 403);
        }

        // Scope check
        $scopes = json_decode($keyRow['scopes'] ?? '[]', true) ?: [];
        if (!empty($requiredScope)) {
            if (!in_array($requiredScope, $scopes, true) && !in_array('admin', $scopes, true)) {
                self::respondJson(false, "Forbidden. This API key does not have the required scope: '{$requiredScope}'.", [
                    'key_scopes'     => $scopes,
                    'required_scope' => $requiredScope,
                ], 403);
            }
        }

        // Stamp last used
        $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$keyRow['id']]);

        // Return tenant array
        $st2 = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $st2->execute([$keyRow['tenant_id']]);
        $tenant = $st2->fetch();

        if (!$tenant) {
            self::respondJson(false, 'Forbidden. Tenant workspace not found.', [], 403);
        }

        $tenant['_api_key_id']     = $keyRow['id'];
        $tenant['_api_key_name']   = $keyRow['name'];
        $tenant['_api_key_scopes'] = $scopes;

        return $tenant;
    }

    private static function respondJson(bool $success, string $message, array $data = [], int $httpCode = 200): never {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'   => $success,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
