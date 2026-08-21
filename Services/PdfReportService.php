<?php
namespace Services;

class PdfReportService {
    /**
     * Render Header with Tenant Branding
     */
    public static function renderHeader(string $reportTitle, string $subtitle = '', array $filters = []): string {
        $brand = branding();
        $tenant = tenant();
        
        $logoHtml = '';
        if (!empty($brand['logo_url'])) {
            $logoHtml = '<img src="' . e($brand['logo_url']) . '" style="max-height: 55px; max-width: 200px; object-fit: contain;">';
        } else {
            $logoHtml = '<div style="background: ' . e($brand['primary_color'] ?? '#0f172a') . '; color: #ffffff; font-weight: 900; padding: 10px 16px; border-radius: 8px; font-size: 18px; display: inline-block;">' . e($brand['company_name']) . '</div>';
        }

        $primaryColor = e($brand['primary_color'] ?? '#0f172a');
        $trn = !empty($brand['tax_number']) ? '<strong>TRN / VAT ID:</strong> ' . e($brand['tax_number']) : '';
        $address = !empty($brand['address']) ? nl2br(e($brand['address'])) : '';
        $phoneEmail = [];
        if (!empty($brand['phone'])) $phoneEmail[] = 'Tel: ' . e($brand['phone']);
        if (!empty($brand['email'])) $phoneEmail[] = 'Email: ' . e($brand['email']);
        $contactStr = implode(' | ', $phoneEmail);

        $filterBadges = [];
        foreach ($filters as $k => $v) {
            if (!empty($v) && $v !== 'all') {
                $filterBadges[] = '<span style="background: #f1f5f9; color: #334155; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-right: 6px; border: 1px solid #cbd5e1;">' . e(ucwords(str_replace('_', ' ', $k))) . ': ' . e($v) . '</span>';
            }
        }
        $filterStr = implode('', $filterBadges);

        $html = '
        <div style="border-bottom: 3px solid ' . $primaryColor . '; padding-bottom: 16px; margin-bottom: 24px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: top; width: 55%;">
                        ' . $logoHtml . '
                        <div style="margin-top: 10px; font-size: 12px; color: #334155; line-height: 1.4;">
                            <strong style="font-size: 14px; color: #0f172a;">' . e($brand['company_name']) . '</strong><br>
                            ' . ($address ? $address . '<br>' : '') . '
                            ' . ($contactStr ? $contactStr . '<br>' : '') . '
                            ' . ($trn ? '<span style="color: #0284c7; font-weight: bold;">' . $trn . '</span>' : '') . '
                        </div>
                    </td>
                    <td style="vertical-align: top; width: 45%; text-align: right;">
                        <h1 style="margin: 0; font-size: 22px; font-weight: 900; color: ' . $primaryColor . '; text-transform: uppercase; letter-spacing: -0.5px;">' . e($reportTitle) . '</h1>
                        ' . ($subtitle ? '<div style="font-size: 12px; color: #64748b; font-weight: 600; margin-top: 4px;">' . e($subtitle) . '</div>' : '') . '
                        <div style="margin-top: 12px; font-size: 11px; color: #64748b;">
                            <strong>Generated:</strong> ' . date('d M Y, h:i A') . '<br>
                            <strong>Workspace:</strong> ' . e($tenant['name']) . '
                        </div>
                    </td>
                </tr>
            </table>
            ' . ($filterStr ? '<div style="margin-top: 12px; padding-top: 8px; border-t: 1px dashed #e2e8f0;">' . $filterStr . '</div>' : '') . '
        </div>
        ';

        return $html;
    }

    /**
     * Render Footer with Tenant Branding
     */
    public static function renderFooter(): string {
        $brand = branding();
        return '
        <div style="margin-top: 30px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; width: 100%;">
            <table style="width: 100%;">
                <tr>
                    <td>' . e($brand['company_name']) . ' &bull; Confidential Financial Record</td>
                    <td style="text-align: right;">System Generated &bull; ' . date('Y-m-d') . '</td>
                </tr>
            </table>
        </div>
        ';
    }

    /**
     * Wrap HTML in Full Print & PDF Document Layout
     */
    public static function wrapDocument(string $title, string $headerHtml, string $contentHtml, string $footerHtml): string {
        $brand = branding();
        $primaryColor = e($brand['primary_color'] ?? '#0f172a');

        return '<!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . e($title) . '</title>
            <style>
                @page { size: A4 portrait; margin: 15mm 15mm 15mm 15mm; }
                body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 12px; color: #0f172a; line-height: 1.5; margin: 0; padding: 0; background: #ffffff; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                th { background: #f8fafc; color: #334155; font-size: 11px; text-transform: uppercase; font-weight: 800; padding: 8px 10px; border-bottom: 2px solid #cbd5e1; text-align: left; }
                td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
                tr:nth-child(even) td { background: #f8fafc/50; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .font-bold { font-weight: bold; }
                .font-black { font-weight: 900; }
                .bg-total { background: #f1f5f9 !important; font-weight: bold; }
                .highlight-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 16px; }
                .badge-success { background: #dcfce7; color: #166534; padding: 3px 6px; border-radius: 4px; font-weight: bold; font-size: 10px; }
                .badge-warning { background: #fef3c7; color: #92400e; padding: 3px 6px; border-radius: 4px; font-weight: bold; font-size: 10px; }
                .badge-danger { background: #ffe4e6; color: #9f1239; padding: 3px 6px; border-radius: 4px; font-weight: bold; font-size: 10px; }
                @media print {
                    .no-print { display: none !important; }
                    body { padding: 0; }
                    @page { margin: 10mm; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="background: #0f172a; color: #ffffff; padding: 10px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px;">
                <div style="font-size: 13px; font-weight: bold;">
                    <i class="fa-solid fa-file-pdf" style="color: #f59e0b; margin-right: 6px;"></i> ' . e($title) . ' (Print Preview)
                </div>
                <div>
                    <button onclick="window.print()" style="background: ' . $primaryColor . '; color: #ffffff; border: 1px solid rgba(255,255,255,0.3); padding: 6px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 12px;">
                        🖨️ Print / Save as PDF
                    </button>
                </div>
            </div>
            
            ' . $headerHtml . '
            ' . $contentHtml . '
            ' . $footerHtml . '
        </body>
        </html>';
    }
}
