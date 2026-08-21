<?php
namespace Core;

class Security {

    /**
     * Check if host or IP resolves to a public internet address.
     * Rejects loopback, RFC1918 private IPs, link-local, cloud metadata, multicast, and internal hostnames.
     */
    public static function isPublicHost(string $host, int $port = 0): bool {
        $host = trim($host);
        if ($host === '') return false;

        // Strip brackets if IPv6 literal
        $cleanHost = trim($host, '[]');

        // Reject obvious internal hostnames / loopback strings
        $lowerHost = strtolower($cleanHost);
        if (
            $lowerHost === 'localhost' ||
            $lowerHost === 'broadcasthost' ||
            str_ends_with($lowerHost, '.local') ||
            str_ends_with($lowerHost, '.internal') ||
            str_ends_with($lowerHost, '.lan') ||
            $lowerHost === 'db' ||
            $lowerHost === 'cache' ||
            $lowerHost === 'web'
        ) {
            return false;
        }

        // Check if $cleanHost is an IP address directly
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($cleanHost);
        }

        // Resolve DNS hostname to IPv4/IPv6 addresses
        $ips = [];
        $records = @dns_get_record($cleanHost, DNS_A + DNS_AAAA);
        if (!empty($records) && is_array($records)) {
            foreach ($records as $rec) {
                if (isset($rec['ip'])) $ips[] = $rec['ip'];
                if (isset($rec['ipv6'])) $ips[] = $rec['ipv6'];
            }
        }

        // Fallback DNS lookup
        if (empty($ips)) {
            $ipv4 = gethostbyname($cleanHost);
            if ($ipv4 && $ipv4 !== $cleanHost) {
                $ips[] = $ipv4;
            }
        }

        if (empty($ips)) {
            return false; // Could not resolve hostname
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an IP address is a valid public IP (not private, loopback, link-local, or cloud metadata).
     */
    public static function isPublicIp(string $ip): bool {
        // Use PHP built-in filter flags
        $filtered = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($filtered === false) {
            return false;
        }

        // Explicit check for AWS/GCP/Azure Metadata IP 169.254.169.254
        if ($ip === '169.254.169.254') {
            return false;
        }

        // Explicit check for 127.0.0.0/8 loopback subnet
        if (str_starts_with($ip, '127.')) {
            return false;
        }

        // Explicit check for 0.0.0.0
        if ($ip === '0.0.0.0') {
            return false;
        }

        return true;
    }

    /**
     * Check if a full URL targets a valid public HTTP/HTTPS endpoint.
     */
    public static function isPublicUrl(string $url): bool {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return self::isPublicHost($host, $port);
    }
}
