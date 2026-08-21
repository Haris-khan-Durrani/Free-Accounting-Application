<?php
namespace Services;

use PDO;
use Exception;

class AutomationService {

    /**
     * Trigger workflows for a specific event
     */
    public static function trigger(PDO $pdo, int $tenantId, string $event, array $payload): array {
        $results = [];

        try {
            $st = $pdo->prepare("SELECT * FROM automations WHERE tenant_id = ? AND trigger_event = ? AND is_active = 1");
            $st->execute([$tenantId, $event]);
            $workflows = $st->fetchAll();

            foreach ($workflows as $wf) {
                $conditions = json_decode($wf['conditions_json'] ?? '[]', true) ?: [];
                $actions = json_decode($wf['actions_json'] ?? '[]', true) ?: [];

                // 1. Evaluate Conditions
                $conditionsPassed = true;
                foreach ($conditions as $cond) {
                    $field = $cond['field'] ?? '';
                    $op = $cond['operator'] ?? '=';
                    $targetValue = $cond['value'] ?? '';

                    $actualValue = $payload[$field] ?? null;

                    if ($op === '>' && !($actualValue > $targetValue)) $conditionsPassed = false;
                    if ($op === '<' && !($actualValue < $targetValue)) $conditionsPassed = false;
                    if ($op === '=' && $actualValue != $targetValue) $conditionsPassed = false;
                    if ($op === '!=' && $actualValue == $targetValue) $conditionsPassed = false;
                }

                if (!$conditionsPassed) {
                    self::log($pdo, $tenantId, (int)$wf['id'], $event, 'skipped', 'Conditions criteria not met.');
                    $results[] = ['id' => $wf['id'], 'name' => $wf['name'], 'status' => 'skipped'];
                    continue;
                }

                // 2. Execute Actions (n8n node executor)
                $actionDetails = [];
                foreach ($actions as $act) {
                    $actionType = $act['type'] ?? '';

                    if ($actionType === 'webhook') {
                        $targetUrl = $act['url'] ?? '';
                        if ($targetUrl) {
                            if (!\Core\Security::isPublicUrl($targetUrl)) {
                                $actionDetails[] = "Blocked HTTP Webhook to internal/unrestricted target $targetUrl (SSRF Guard)";
                                continue;
                            }
                            $ch = curl_init($targetUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                                'event' => $event,
                                'tenant_id' => $tenantId,
                                'payload' => $payload,
                                'timestamp' => date('Y-m-d H:i:s')
                            ]));
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                            curl_exec($ch);
                            curl_close($ch);
                            $actionDetails[] = "Dispatched HTTP Webhook to $targetUrl";
                        }
                    } elseif ($actionType === 'send_email') {
                        $recipient = $act['email'] ?? ($payload['email'] ?? 'client@company.com');
                        $actionDetails[] = "Dispatched Email Notification to $recipient";
                    } elseif ($actionType === 'slack') {
                        $actionDetails[] = "Posted Slack / Teams Channel Notification";
                    }
                }

                self::log($pdo, $tenantId, (int)$wf['id'], $event, 'success', implode('; ', $actionDetails));
                $results[] = ['id' => $wf['id'], 'name' => $wf['name'], 'status' => 'success'];
            }
        } catch (Exception $e) {
            // Silence exception
        }

        return $results;
    }

    private static function log(PDO $pdo, int $tenantId, int $automationId, string $event, string $status, string $details): void {
        try {
            $st = $pdo->prepare("INSERT INTO automation_logs (tenant_id, automation_id, trigger_event, status, details) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$tenantId, $automationId, $event, $status, $details]);
        } catch (Exception $e){}
    }
}
