<?php
namespace Services;

class WhatsAppService
{
    /**
     * Send WhatsApp message via Meta Cloud API
     */
    public static function send($pdo, int $tenantId, string $toPhone, string $message): array
    {
        $st = $pdo->prepare("SELECT whatsapp_phone_number_id, whatsapp_access_token FROM branding_settings WHERE tenant_id = ?");
        $st->execute([$tenantId]);
        $cfg = $st->fetch();

        $phoneId = $cfg['whatsapp_phone_number_id'] ?? '';
        $token   = $cfg['whatsapp_access_token'] ?? '';

        if (empty($phoneId) || empty($token)) {
            return [
                'success' => false,
                'message' => 'WhatsApp Cloud API credentials not configured in Workspace Settings.'
            ];
        }

        // Clean phone number
        $cleanPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (empty($cleanPhone)) {
            return ['success' => false, 'message' => 'Invalid phone number format.'];
        }

        $url = "https://graph.facebook.com/v18.0/$phoneId/messages";
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $cleanPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $message
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                "Content-Type: application/json"
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'message' => "cURL error: $err"];
        }

        $json = json_decode($res, true);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'message' => 'WhatsApp message dispatched successfully.', 'data' => $json];
        }

        return ['success' => false, 'message' => $json['error']['message'] ?? 'WhatsApp API dispatch failed.', 'data' => $json];
    }
}
