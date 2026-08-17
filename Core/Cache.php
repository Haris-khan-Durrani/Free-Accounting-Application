<?php
namespace Core;

/**
 * Enterprise Multi-Tenant Cache Engine
 * 
 * Features:
 * - Redis Driver with Automatic Graceful Fallback to File & Memory Cache
 * - TTL Jitter Calculation (0 - 15% random TTL variance) to prevent Cache Stampedes
 * - Multi-Tenant Namespace Isolation (onesol:t{tenant_id}:{key})
 * - Event-Driven Invalidation Helpers
 */
class Cache {
    private static mixed $redis = null;
    private static bool $redisChecked = false;
    private static array $memoryCache = [];
    private static int $hits = 0;
    private static int $misses = 0;
    private static string $cacheDir = '';

    /**
     * Get initialized Redis instance, or null if unavailable
     */
    private static function getRedis(): mixed {
        if (self::$redisChecked) {
            return self::$redis;
        }

        self::$redisChecked = true;

        if (class_exists('\\Redis')) {
            try {
                $r = new \Redis();
                // Attempt connection with tight 0.5s timeout
                $connected = @$r->connect(
                    getenv('REDIS_HOST') ?: '127.0.0.1',
                    (int)(getenv('REDIS_PORT') ?: 6379),
                    0.5
                );
                if ($connected) {
                    if ($pass = getenv('REDIS_PASSWORD')) {
                        @$r->auth($pass);
                    }
                    @$r->select((int)(getenv('REDIS_DB') ?: 0));
                    self::$redis = $r;
                    return self::$redis;
                }
            } catch (\Throwable $e) {
                self::$redis = null;
            }
        }

        return null;
    }

    /**
     * Get or create file storage cache directory
     */
    private static function getCacheDir(): string {
        if (empty(self::$cacheDir)) {
            self::$cacheDir = dirname(__DIR__) . '/storage/cache/';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0777, true);
            }
        }
        return self::$cacheDir;
    }

    /**
     * Format tenant-scoped key: onesol:t{tenant_id}:{key}
     */
    public static function formatKey(string $key, ?int $tenantId = null): string {
        $tid = $tenantId ?? (class_exists('\\Core\\Tenant') ? Tenant::getActiveId() : 1);
        $cleanKey = preg_replace('/[^a-zA-Z0-9_\-\.\:]/', '_', $key);
        return "onesol:t{$tid}:{$cleanKey}";
    }

    /**
     * Calculate TTL with Random Jitter (0 to 15% variance)
     * Stagger expiration times across concurrent requests to prevent Cache Stampedes.
     */
    public static function applyJitter(int $ttl): int {
        if ($ttl <= 0) return 0;
        $maxJitter = (int)ceil($ttl * 0.15);
        if ($maxJitter < 1) $maxJitter = 1;
        return $ttl + random_int(0, $maxJitter);
    }

    /**
     * Remember: Get from cache or execute callback and cache result with TTL + Jitter
     */
    public static function remember(string $key, int $ttlSeconds, callable $callback, ?int $tenantId = null) {
        $formattedKey = self::formatKey($key, $tenantId);
        $val = self::getFormatted($formattedKey);

        if ($val !== null) {
            return $val;
        }

        $result = $callback();
        self::setFormatted($formattedKey, $result, $ttlSeconds);
        return $result;
    }

    /**
     * Public Get by key (auto-namespaced by active tenant)
     */
    public static function get(string $key, ?int $tenantId = null) {
        return self::getFormatted(self::formatKey($key, $tenantId));
    }

    /**
     * Internal Get by formatted key
     */
    private static function getFormatted(string $formattedKey) {
        // 1. Check in-memory per-request cache
        if (array_key_exists($formattedKey, self::$memoryCache)) {
            $item = self::$memoryCache[$formattedKey];
            if ($item['exp'] === 0 || $item['exp'] > time()) {
                self::$hits++;
                return $item['val'];
            }
            unset(self::$memoryCache[$formattedKey]);
        }

        // 2. Try Redis Driver
        if ($redis = self::getRedis()) {
            try {
                $raw = $redis->get($formattedKey);
                if ($raw !== false && $raw !== null) {
                    $val = unserialize($raw);
                    self::$hits++;
                    self::$memoryCache[$formattedKey] = ['val' => $val, 'exp' => time() + 60];
                    return $val;
                }
            } catch (\Throwable $e) {}
        }

        // 3. Fallback: File Cache Driver
        $filePath = self::getCacheDir() . md5($formattedKey) . '.json';
        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if ($content) {
                $data = @json_decode($content, true);
                if ($data && is_array($data)) {
                    if ($data['exp'] === 0 || $data['exp'] > time()) {
                        self::$hits++;
                        $val = $data['val'];
                        self::$memoryCache[$formattedKey] = ['val' => $val, 'exp' => $data['exp']];
                        return $val;
                    }
                    @unlink($filePath); // Expired file cleanup
                }
            }
        }

        self::$misses++;
        return null;
    }

    /**
     * Public Set key with TTL seconds + automatic Jitter variance
     */
    public static function set(string $key, $value, int $ttlSeconds = 300, ?int $tenantId = null): bool {
        return self::setFormatted(self::formatKey($key, $tenantId), $value, $ttlSeconds);
    }

    /**
     * Internal Set by formatted key
     */
    private static function setFormatted(string $formattedKey, $value, int $ttlSeconds): bool {
        $jitteredTtl = self::applyJitter($ttlSeconds);
        $expTime     = $jitteredTtl > 0 ? (time() + $jitteredTtl) : 0;

        // Store in memory
        self::$memoryCache[$formattedKey] = ['val' => $value, 'exp' => $expTime];

        $success = false;

        // 1. Try Redis
        if ($redis = self::getRedis()) {
            try {
                $serialized = serialize($value);
                if ($jitteredTtl > 0) {
                    $success = (bool)$redis->setex($formattedKey, $jitteredTtl, $serialized);
                } else {
                    $success = (bool)$redis->set($formattedKey, $serialized);
                }
            } catch (\Throwable $e) {}
        }

        // 2. File fallback (written always to guarantee persistent fallback)
        $filePath = self::getCacheDir() . md5($formattedKey) . '.json';
        $payload  = json_encode([
            'key'     => $formattedKey,
            'exp'     => $expTime,
            'ttl'     => $jitteredTtl,
            'val'     => $value,
            'created' => time(),
        ]);
        @file_put_contents($filePath, $payload, LOCK_EX);

        return $success || true;
    }

    /**
     * Forget/Delete specific cache key
     */
    public static function forget(string $key, ?int $tenantId = null): bool {
        $formattedKey = self::formatKey($key, $tenantId);
        unset(self::$memoryCache[$formattedKey]);

        if ($redis = self::getRedis()) {
            try { $redis->del($formattedKey); } catch (\Throwable $e) {}
        }

        $filePath = self::getCacheDir() . md5($formattedKey) . '.json';
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        return true;
    }

    /**
     * Flush all cache keys for a specific tenant workspace
     */
    public static function flushTenant(?int $tenantId = null): int {
        $tid = $tenantId ?? (class_exists('\\Core\\Tenant') ? Tenant::getActiveId() : 1);
        $prefix = "onesol:t{$tid}:";
        $count = 0;

        // Clear memory
        foreach (array_keys(self::$memoryCache) as $k) {
            if (str_starts_with($k, $prefix)) {
                unset(self::$memoryCache[$k]);
            }
        }

        // Clear Redis
        if ($redis = self::getRedis()) {
            try {
                $keys = $redis->keys("{$prefix}*");
                if ($keys && is_array($keys)) {
                    foreach ($keys as $k) {
                        $redis->del($k);
                        $count++;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Clear Files
        $dir = self::getCacheDir();
        if (is_dir($dir)) {
            $files = glob($dir . '*.json');
            if ($files) {
                foreach ($files as $file) {
                    $content = @file_get_contents($file);
                    if ($content && str_contains($content, "\"key\":\"{$prefix}")) {
                        @unlink($file);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Flush ENTIRE Cache across all tenants
     */
    public static function flushAll(): bool {
        self::$memoryCache = [];

        if ($redis = self::getRedis()) {
            try { $redis->flushDB(); } catch (\Throwable $e) {}
        }

        $dir = self::getCacheDir();
        if (is_dir($dir)) {
            $files = glob($dir . '*.json');
            if ($files) {
                foreach ($files as $f) @unlink($f);
            }
        }
        return true;
    }

    /**
     * Get Diagnostic Statistics for Cache Monitor Panel
     */
    public static function getStats(): array {
        $redisActive = (self::getRedis() !== null);
        $fileCount   = 0;
        $dir         = self::getCacheDir();

        if (is_dir($dir)) {
            $files = glob($dir . '*.json');
            $fileCount = $files ? count($files) : 0;
        }

        $redisMemory = 'N/A';
        $redisKeys   = 0;

        if ($redisActive) {
            try {
                $info = self::$redis->info('memory');
                $redisMemory = $info['used_memory_human'] ?? 'Active';
                $keys = self::$redis->keys('onesol:*');
                $redisKeys = is_array($keys) ? count($keys) : 0;
            } catch (\Throwable $e) {}
        }

        return [
            'driver'       => $redisActive ? 'Redis (Primary) + File Backup' : 'File Storage + Memory Array',
            'is_redis'     => $redisActive,
            'redis_host'   => getenv('REDIS_HOST') ?: '127.0.0.1:6379',
            'redis_keys'   => $redisKeys,
            'redis_memory' => $redisMemory,
            'file_keys'    => $fileCount,
            'hits'         => self::$hits,
            'misses'       => self::$misses,
            'hit_ratio'    => (self::$hits + self::$misses) > 0 ? round((self::$hits / (self::$hits + self::$misses)) * 100, 1) . '%' : '100%',
            'ttl_jitter'   => 'Active (0–15% Random Variance)',
        ];
    }
}
