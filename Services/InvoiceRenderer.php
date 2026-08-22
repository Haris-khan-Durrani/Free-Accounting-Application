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
            $stampBg = $isFullyPaid ? 'rgba(236, 253, 245, 0.96)' : 'rgba(254, 243, 199, 0.96)';
            $stampBorder = $isFullyPaid ? '#059669' : '#d97706';
            $stampTitle = $isFullyPaid ? 'PAID' : 'PARTIALLY PAID';
            $subTitle = $isFullyPaid ? 'SETTLED' : 'PARTIAL';
            
            $paidVal = (float)($invoice['paid_amount'] ?? $invoice['total'] ?? 0);
            if ($paidVal <= 0 && $isFullyPaid) {
                $paidVal = (float)($invoice['total'] ?? 0);
            }
            $curr = $invoice['currency'] ?? 'AED';
            $paidAmtStr = function_exists('money') ? money($paidVal, $curr) : ($curr . ' ' . number_format($paidVal, 2));

            $stampHtml = '
            <div class="paid-rubber-stamp" style="display: inline-flex; align-items: center; gap: 7px; border: 2px double ' . $stampBorder . '; border-radius: 8px; padding: 4px 10px; color: ' . $stampBorder . '; font-family: system-ui, -apple-system, sans-serif; background: ' . $stampBg . '; backdrop-filter: blur(2px); box-shadow: 0 4px 10px -2px rgba(0,0,0,0.08); pointer-events: none; z-index: 50; transform: rotate(-3deg); margin: 4px 0;">
                <svg style="width: 14px; height: 14px; fill: ' . $stampBorder . '; flex-shrink: 0;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <div style="text-align: left;">
                    <div style="font-size: 11px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; line-height: 1;">' . $stampTitle . '</div>
                    <div style="font-size: 8px; font-weight: 800; letter-spacing: 0.5px; opacity: 0.9; margin-top: 1.5px;">' . $subTitle . ' • ' . $paidAmtStr . '</div>
                </div>
            </div>';

            if (str_contains($html, 'class="doc-title-block"')) {
                // Place floating in clean empty space inside doc-title-block
                $html = preg_replace('/(<div[^>]*class="doc-title-block"[^>]*>)/i', '$1<div style="float:right; margin-left:16px; margin-bottom:8px;">' . $stampHtml . '</div>', $html, 1);
            } elseif (str_contains($html, 'class="body-container"')) {
                // Place at top of body-container in an empty flex row
                $html = preg_replace('/(<div[^>]*class="body-container"[^>]*>)/i', '$1<div style="display:flex; justify-content:flex-end; margin-bottom:12px;">' . $stampHtml . '</div>', $html, 1);
            } elseif (str_contains($html, 'class="inv-meta-box"')) {
                // Place inside inv-meta-box in modern minimal template
                $html = preg_replace('/(<div[^>]*class="inv-meta-box"[^>]*>)/i', '$1<div style="margin-bottom:8px;">' . $stampHtml . '</div>', $html, 1);
            } elseif (str_contains($html, 'class="invoice-box"')) {
                $html = preg_replace('/(<div[^>]*class="invoice-box"[^>]*>)/i', '$1<div style="display:flex; justify-content:flex-end; padding:12px 24px 0 24px;">' . $stampHtml . '</div>', $html, 1);
            } else {
                $html = '<div style="display:flex; justify-content:flex-end; margin-bottom:10px;">' . $stampHtml . '</div>' . $html;
            }
        }

        return $html;
    }
}

