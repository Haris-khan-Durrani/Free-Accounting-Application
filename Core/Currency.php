<?php
namespace Core;

use PDO;

class Currency {
    private static array $rates = [];

    public static function format(float $amount, string $currencyCode = 'AED', ?PDO $pdo = null): string {
        $curr = self::getCurrencyDetails($currencyCode, $pdo);
        $formatted = number_format($amount, $curr['decimal_places']);
        if ($curr['symbol_position'] === 'after') {
            return $formatted . ' ' . $curr['symbol'];
        }
        return $curr['symbol'] . ' ' . $formatted;
    }

    public static function getCurrencyDetails(string $code, ?PDO $pdo = null): array {
        if ($pdo) {
            $st = $pdo->prepare("SELECT * FROM currencies WHERE code = ?");
            $st->execute([$code]);
            $c = $st->fetch();
            if ($c) return $c;
        }

        $defaults = [
            'AED' => ['symbol' => 'AED', 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 3.672500],
            'USD' => ['symbol' => '$', 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 1.000000],
            'EUR' => ['symbol' => '€', 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 0.920000],
            'GBP' => ['symbol' => '£', 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 0.780000],
            'SAR' => ['symbol' => 'SAR', 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 3.750000],
            'INR' => ['symbol' => '₹', 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 83.500000],
            'CAD' => ['symbol' => 'CA$', 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 1.360000],
            'AUD' => ['symbol' => 'A$', 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 1.520000],
        ];

        return $defaults[$code] ?? ['symbol' => $code, 'symbol_position' => 'before', 'decimal_places' => 2, 'exchange_rate' => 1.0];
    }

    public static function convert(float $amount, string $fromCode, string $toCode, PDO $pdo): float {
        if ($fromCode === $toCode) return $amount;
        $from = self::getCurrencyDetails($fromCode, $pdo);
        $to = self::getCurrencyDetails($toCode, $pdo);
        // Base rate is USD
        $usdAmount = $amount / ($from['exchange_rate'] ?: 1.0);
        return $usdAmount * ($to['exchange_rate'] ?: 1.0);
    }
}
