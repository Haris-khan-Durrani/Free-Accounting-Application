<?php
namespace Core;

use Exception;

class Crypto {
    private static function getMasterKey(): string {
        $config = file_exists(__DIR__ . '/../config.php') ? require(__DIR__ . '/../config.php') : [];
        $appKey = $config['app_key'] ?? ($config['session_secret'] ?? 'onesol-default-secret-key-32chars!!');
        return hash('sha256', $appKey, true); // 32 bytes binary key
    }

    /**
     * Encrypt plaintext using AES-256-GCM authenticated encryption.
     */
    public static function encrypt(string $plaintext, ?string $customKey = null): string {
        if ($plaintext === '') return '';
        
        $key = $customKey ? hash('sha256', $customKey, true) : self::getMasterKey();
        $iv = random_bytes(12); // 96-bit IV for AES-GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new Exception("Encryption failed.");
        }

        // Return base64 payload containing IV (12 bytes) + Tag (16 bytes) + Ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt ciphertext encrypted with AES-256-GCM (supports unwrapping multi-layer legacy encryptions).
     */
    public static function decrypt(string $encryptedPayload, ?string $customKey = null): ?string {
        if ($encryptedPayload === '') return '';

        $current = $encryptedPayload;
        for ($i = 0; $i < 5; $i++) {
            $decoded = base64_decode($current, true);
            if ($decoded === false || strlen($decoded) < 28) {
                break;
            }

            $key = $customKey ? hash('sha256', $customKey, true) : self::getMasterKey();
            $iv = substr($decoded, 0, 12);
            $tag = substr($decoded, 12, 16);
            $ciphertext = substr($decoded, 28);

            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($plaintext !== false && $plaintext !== '') {
                $current = $plaintext;
            } else {
                break;
            }
        }

        return $current;
    }
}
