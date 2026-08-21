<?php
namespace Services;

use PDO;

class InvoiceRenderer {
    public static function render(array $invoice, array $items, array $branding, string $templateId = 'modern_minimal'): string {
        $pdo = $GLOBALS['pdo'] ?? null;
        $tid = (int)($invoice['tenant_id'] ?? 1);

        $wording = [];
        $customLayoutJson = '';

        if ($pdo instanceof PDO) {
            $wording = get_custom_wording($pdo, $tid);

            $stLayout = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'custom_invoice_layout'");
            $stLayout->execute([$tid]);
            $customLayoutJson = $stLayout->fetchColumn() ?: '';
        }

        // Enforce strict server-side template allowlist to prevent LFI / path traversal
        $allowedTemplates = [
            'modern_minimal',
            'corporate_executive',
            'onesol_executive_gold',
            'classic_traditional',
            'compact_receipt',
            'custom_drag_drop'
        ];
        if (!in_array($templateId, $allowedTemplates, true)) {
            $templateId = 'modern_minimal';
        }

        $templateFile = __DIR__ . '/../templates/invoices/' . $templateId . '.php';
        if (!file_exists($templateFile)) {
            $templateFile = __DIR__ . '/../templates/invoices/modern_minimal.php';
        }

        ob_start();
        extract([
            'inv'     => $invoice,
            'items'   => $items,
            'brand'   => $branding,
            'wording' => $wording,
            'customLayoutJson' => $customLayoutJson,
        ]);
        include $templateFile;
        return ob_get_clean();
    }
}

