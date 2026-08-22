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
        $html = ob_get_clean();

        // Inject Dynamic Paid / Partially Paid Watermark Rubber Stamp
        $status = strtolower($invoice['status'] ?? '');
        if ($status === 'paid' || $status === 'partially_paid') {
            $isFullyPaid = ($status === 'paid');
            $stampBg = $isFullyPaid ? 'rgba(236, 253, 245, 0.95)' : 'rgba(254, 243, 199, 0.95)';
            $stampBorder = $isFullyPaid ? '#059669' : '#d97706';
            $stampTitle = $isFullyPaid ? 'PAID' : 'PARTIALLY PAID';
            $subTitle = $isFullyPaid ? 'OFFICIAL RECEIPT • SETTLED' : 'PARTIAL SETTLEMENT';
            
            $paidVal = (float)($invoice['paid_amount'] ?? $invoice['total'] ?? 0);
            if ($paidVal <= 0 && $isFullyPaid) {
                $paidVal = (float)($invoice['total'] ?? 0);
            }
            $curr = $invoice['currency'] ?? 'AED';
            $paidAmtStr = function_exists('money') ? money($paidVal, $curr) : ($curr . ' ' . number_format($paidVal, 2));

            $stampHtml = '
            <div class="paid-rubber-stamp" style="position: absolute; top: 20px; right: 25px; transform: rotate(-10deg); border: 3px double ' . $stampBorder . '; border-radius: 12px; padding: 8px 20px; color: ' . $stampBorder . '; font-family: system-ui, -apple-system, sans-serif; text-align: center; background: ' . $stampBg . '; backdrop-filter: blur(4px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); pointer-events: none; z-index: 99;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 22px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; line-height: 1.1;">
                    <svg style="width: 22px; height: 22px; fill: ' . $stampBorder . ';" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    ' . $stampTitle . '
                </div>
                <div style="font-size: 8.5px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; margin-top: 3px; opacity: 0.9;">
                    ' . $subTitle . '
                </div>
                <div style="font-size: 10px; font-family: monospace; font-weight: 900; margin-top: 2px;">
                    ' . $paidAmtStr . '
                </div>
            </div>';

            if (str_contains($html, 'class="invoice-box"')) {
                $html = str_replace('class="invoice-box"', 'class="invoice-box" style="position:relative;"', $html);
                $html = preg_replace('/(<div[^>]*class="invoice-box"[^>]*>)/i', '$1' . $stampHtml, $html, 1);
            } elseif (str_contains($html, 'class="invoice-container"')) {
                $html = str_replace('class="invoice-container"', 'class="invoice-container" style="position:relative;"', $html);
                $html = preg_replace('/(<div[^>]*class="invoice-container"[^>]*>)/i', '$1' . $stampHtml, $html, 1);
            } elseif (str_contains($html, '<body')) {
                $html = preg_replace('/(<body[^>]*>)/i', '$1<div style="position:relative; max-width:800px; margin:0 auto;">' . $stampHtml . '</div>', $html, 1);
            } else {
                $html = '<div style="position:relative;">' . $stampHtml . '</div>' . $html;
            }
        }

        return $html;
    }
}

