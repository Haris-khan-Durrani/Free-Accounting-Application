<?php
namespace Services;

class WhatsAppService
{
    /**
     * Send WhatsApp or SMS message via configured provider (Meta Cloud API or Twilio)
     */
    public static function send($pdo, int $tenantId, string $toPhone, string $message): array
    {
        $st = $pdo->prepare("
            SELECT whatsapp_provider, whatsapp_phone_number_id, whatsapp_access_token, twilio_account_sid, twilio_auth_token, twilio_from_number
            FROM branding_settings 
            WHERE tenant_id = ?
        ");
        $st->execute([$tenantId]);
        $cfg = $st->fetch();

        $provider = $cfg['whatsapp_provider'] ?? 'meta';

        if ($provider === 'twilio') {
            return self::sendTwilio($cfg, $toPhone, $message);
        }

        return self::sendMeta($cfg, $toPhone, $message);
    }

    /**
     * Send via Meta WhatsApp Cloud API
     */
    private static function sendMeta(array $cfg, string $toPhone, string $message): array
    {
        $phoneId = $cfg['whatsapp_phone_number_id'] ?? '';
        $token   = $cfg['whatsapp_access_token'] ?? '';

        if (empty($phoneId) || empty($token)) {
            return [
                'success' => false,
                'message' => 'Meta WhatsApp Cloud API credentials not configured in Workspace Settings.'
            ];
        }

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
            return ['success' => true, 'message' => 'Meta WhatsApp message dispatched successfully.', 'data' => $json];
        }

        return ['success' => false, 'message' => $json['error']['message'] ?? 'Meta WhatsApp API dispatch failed.', 'data' => $json];
    }

    /**
     * Send via Twilio SMS & WhatsApp API
     */
    private static function sendTwilio(array $cfg, string $toPhone, string $message): array
    {
        $sid   = $cfg['twilio_account_sid'] ?? '';
        $token = $cfg['twilio_auth_token'] ?? '';
        $from  = $cfg['twilio_from_number'] ?? '';

        if (empty($sid) || empty($token) || empty($from)) {
            return [
                'success' => false,
                'message' => 'Twilio Account SID, Auth Token, or From Number missing.'
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        
        // Format recipient phone number
        $to = trim($toPhone);
        if (str_starts_with($from, 'whatsapp:') && !str_starts_with($to, 'whatsapp:')) {
            $to = 'whatsapp:' . preg_replace('/[^0-9+]/', '', $to);
        }

        $postData = [
            'From' => $from,
            'To'   => $to,
            'Body' => $message
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_USERPWD => "$sid:$token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'message' => "Twilio cURL error: $err"];
        }

        $json = json_decode($res, true);
        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'message' => "Twilio message dispatched successfully (SID: " . ($json['sid'] ?? 'OK') . ").", 'data' => $json];
        }

        return ['success' => false, 'message' => $json['message'] ?? 'Twilio dispatch failed.', 'data' => $json];
    }
}
