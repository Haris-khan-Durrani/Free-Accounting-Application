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

    private static function getIpKey(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return md5('login_throttle_' . $ip);
    }

    public static function isLockedOut(): bool {
        $file = self::getStorageDir() . '/' . self::getIpKey() . '.json';
        if (!file_exists($file)) return false;

        $data = json_decode(@file_get_contents($file), true);
        if (!$data) return false;

        if ($data['attempts'] >= self::$maxAttempts) {
            $elapsed = time() - $data['last_attempt'];
            if ($elapsed < self::$lockoutSeconds) {
                return true;
            }
            // Lockout period expired, reset file
            @unlink($file);
        }
        return false;
    }

    public static function getRemainingLockoutTime(): int {
        $file = self::getStorageDir() . '/' . self::getIpKey() . '.json';
        if (!file_exists($file)) return 0;

        $data = json_decode(@file_get_contents($file), true);
        if (!$data) return 0;

        $elapsed = time() - ($data['last_attempt'] ?? time());
        return max(0, self::$lockoutSeconds - $elapsed);
    }

    public static function recordFailedAttempt(): void {
        $file = self::getStorageDir() . '/' . self::getIpKey() . '.json';
        $data = file_exists($file) ? json_decode(@file_get_contents($file), true) : ['attempts' => 0];

        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        $data['last_attempt'] = time();

        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    public static function clearAttempts(): void {
        $file = self::getStorageDir() . '/' . self::getIpKey() . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}
