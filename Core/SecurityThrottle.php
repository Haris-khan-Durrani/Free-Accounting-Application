<?php
namespace Core;

class SecurityThrottle {
    private static int $maxAttempts = 5;
    private static int $lockoutSeconds = 900; // 15 minutes

    private static function getStorageDir(): string {
        $dir = __DIR__ . '/../storage/throttle';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function getIpKey(?string $identifier = null): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userIdentifier = strtolower(trim($_POST['email'] ?? $_POST['username'] ?? $identifier ?? ''));
        return md5('login_throttle_' . $ip . '_' . $userIdentifier);
    }

    public static function isLockedOut(?string $identifier = null): bool {
        $file = self::getStorageDir() . '/' . self::getIpKey($identifier) . '.json';
        if (!file_exists($file)) return false;

        $data = json_decode(@file_get_contents($file), true);
        if (!$data) return false;

        if (($data['attempts'] ?? 0) >= self::$maxAttempts) {
            $elapsed = time() - ($data['last_attempt'] ?? 0);
            if ($elapsed < self::$lockoutSeconds) {
                return true;
            }
            // Lockout period expired, reset file
            @unlink($file);
        }
        return false;
    }

    public static function getRemainingLockoutTime(?string $identifier = null): int {
        $file = self::getStorageDir() . '/' . self::getIpKey($identifier) . '.json';
        if (!file_exists($file)) return 0;

        $data = json_decode(@file_get_contents($file), true);
        if (!$data) return 0;

        $elapsed = time() - ($data['last_attempt'] ?? time());
        return max(0, self::$lockoutSeconds - $elapsed);
    }

    public static function recordFailedAttempt(?string $identifier = null): void {
        $file = self::getStorageDir() . '/' . self::getIpKey($identifier) . '.json';
        $data = file_exists($file) ? json_decode(@file_get_contents($file), true) : ['attempts' => 0];

        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        $data['last_attempt'] = time();

        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    public static function clearAttempts(?string $identifier = null): void {
        $file = self::getStorageDir() . '/' . self::getIpKey($identifier) . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * General purpose sliding window rate-limiter for APIs and webhooks.
     */
    public static function checkRateLimit(string $key, int $maxAttempts = 120, int $decaySeconds = 60): bool {
        $file = self::getStorageDir() . '/' . md5('ratelimit_' . $key) . '.json';
        $now = time();

        $data = file_exists($file) ? json_decode(@file_get_contents($file), true) : ['requests' => [], 'reset_at' => $now + $decaySeconds];
        if (!$data || !is_array($data)) {
            $data = ['requests' => [], 'reset_at' => $now + $decaySeconds];
        }

        // Filter out timestamp entries older than window decay
        $data['requests'] = array_filter($data['requests'] ?? [], fn($t) => ($now - $t) < $decaySeconds);

        if (count($data['requests']) >= $maxAttempts) {
            return false; // Rate limit exceeded
        }

        $data['requests'][] = $now;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }
}
