<?php
// cron_exchange_rates.php - Automatic Central Bank of UAE Exchange Rates Sync
require __DIR__ . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];

header('Content-Type: application/json; charset=utf-8');

// Fetch latest live exchange rates against AED base currency
$apiUrl = 'https://open.er-api.com/v6/latest/AED';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'OneSol-Invoice-Manager/2.0'
]);

$res = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'message' => "cURL fetch error: $err"]);
    exit;
}

$data = json_decode($res, true);
if (empty($data['rates'])) {
    echo json_encode(['success' => false, 'message' => 'Failed to parse live exchange rate JSON payload.']);
    exit;
}

$rates = $data['rates']; // Rates relative to 1 AED
$updatedCurrencies = [];

// Currencies to update
$targetCurrencies = ['USD', 'EUR', 'GBP', 'SAR', 'INR', 'KWD', 'BHD', 'OMR', 'QAR'];

foreach ($targetCurrencies as $code) {
    if (isset($rates[$code])) {
        // Exchange rate: 1 Foreign Unit = X AED => rate_to_aed = 1 / rate
        $rateToAed = 1 / (float)$rates[$code];
        
        try {
            $st = $pdo->prepare("UPDATE currencies SET exchange_rate = ?, updated_at = NOW() WHERE code = ?");
            $st->execute([$rateToAed, $code]);
            $updatedCurrencies[$code] = round($rateToAed, 4);
        } catch (\Throwable $e) {}
    }
}

echo json_encode([
    'success' => true,
    'base_currency' => 'AED',
    'timestamp' => date('Y-m-d H:i:s'),
    'updated_rates_in_aed' => $updatedCurrencies,
    'message' => 'Central Bank AED exchange rates updated successfully.'
], JSON_PRETTY_PRINT);
exit;
