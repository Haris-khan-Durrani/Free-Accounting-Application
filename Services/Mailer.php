<?php
namespace Services;

use PDO;
use Exception;

class Mailer {
    /**
     * Send email using Tenant's Custom SMTP configuration (or fallback PHP mail)
     */
    public static function send(PDO $pdo, int $tenantId, string $toEmail, string $subject, string $htmlBody, string $altText = ''): bool {
        // Fetch Tenant SMTP settings
        $st = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, from_email, from_name, name FROM tenants WHERE id = ?");
        $st->execute([$tenantId]);
        $tenant = $st->fetch();

        $fromEmail = !empty($tenant['from_email']) ? $tenant['from_email'] : 'no-reply@onesol.ae';
        $fromName = !empty($tenant['from_name']) ? $tenant['from_name'] : ($tenant['name'] ?? 'OneSol Invoice Manager');

        $smtpHost = trim($tenant['smtp_host'] ?? '');
        $smtpPort = (int)($tenant['smtp_port'] ?? 587);
        $smtpEnc = strtolower(trim($tenant['smtp_encryption'] ?? 'tls'));
        $smtpUser = trim($tenant['smtp_username'] ?? '');
        $smtpPass = $tenant['smtp_password'] ?? '';

        // If custom SMTP is configured, use Socket SMTP connection
        if ($smtpHost && $smtpUser) {
            try {
                return self::sendViaSmtp($smtpHost, $smtpPort, $smtpEnc, $smtpUser, $smtpPass, $fromEmail, $fromName, $toEmail, $subject, $htmlBody);
            } catch (Exception $e) {
                error_log("Tenant #$tenantId SMTP Error: " . $e->getMessage());
                // Fallback to PHP mail if SMTP fails
            }
        }

        // Native PHP mail() fallback
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset=utf-8";
        $headers[] = "From: " . sprintf('%s <%s>', $fromName, $fromEmail);
        $headers[] = "Reply-To: " . $fromEmail;
        $headers[] = "X-Mailer: OneSol-MultiTenant-SaaS";

        return @mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
    }

    /**
     * Native Socket SMTP Mailer with TLS / SSL / STARTTLS support
     */
    private static function sendViaSmtp(string $host, int $port, string $encryption, string $username, string $password, string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody): bool {
        $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
        $socketHost = $prefix . $host;
        $timeout = 10;

        $socket = @fsockopen($socketHost, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new Exception("Could not connect to SMTP host $host:$port - $errstr ($errno)");
        }

        self::readSmtpResponse($socket, 220);

        // EHLO Command
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        self::readSmtpResponse($socket, 250);

        // STARTTLS Encryption Command
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            self::readSmtpResponse($socket, 220);

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                throw new Exception("TLS Encryption Handshake Failed");
            }

            fputs($socket, "EHLO " . gethostname() . "\r\n");
            self::readSmtpResponse($socket, 250);
        }

        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        self::readSmtpResponse($socket, 334);

        fputs($socket, base64_encode($username) . "\r\n");
        self::readSmtpResponse($socket, 334);

        fputs($socket, base64_encode($password) . "\r\n");
        self::readSmtpResponse($socket, 235);

        // MAIL FROM
        fputs($socket, "MAIL FROM: <$fromEmail>\r\n");
        self::readSmtpResponse($socket, 250);

        // RCPT TO
        fputs($socket, "RCPT TO: <$toEmail>\r\n");
        self::readSmtpResponse($socket, 250);

        // DATA
        fputs($socket, "DATA\r\n");
        self::readSmtpResponse($socket, 354);

        $headers = [];
        $headers[] = "From: " . sprintf('%s <%s>', $fromName, $fromEmail);
        $headers[] = "To: <$toEmail>";
        $headers[] = "Subject: $subject";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Date: " . date('r');
        $headers[] = "X-Mailer: OneSol-MultiTenant-SaaS";

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.\r\n";
        fputs($socket, $message);
        self::readSmtpResponse($socket, 250);

        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    private static function readSmtpResponse($socket, int $expectedCode): string {
        $response = '';
        while ($str = fgets($socket, 512)) {
            $response .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }

        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP Expected code $expectedCode, received: $response");
        }

        return $response;
    }
}
