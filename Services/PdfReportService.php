<?php
namespace Services;

class PdfReportService {
    /**
     * Render Header with Tenant Branding & Filter Summary
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
            if (!empty($v) && $v !== 'all' && $v !== 'custom') {
                $label = ucwords(str_replace('_', ' ', $k));
                $filterBadges[] = '<span style="display: inline-block; background: #e2e8f0; color: #1e293b; padding: 3px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; margin-right: 6px; margin-bottom: 4px; border: 1px solid #cbd5e1;">' . e($label) . ': <span style="color: #0284c7;">' . e($v) . '</span></span>';
            }
        }
        $filterStr = implode('', $filterBadges);

        $html = '
        <div style="border-bottom: 3px solid ' . $primaryColor . '; padding-bottom: 16px; margin-bottom: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: top; width: 55%; border: none;">
                        ' . $logoHtml . '
                        <div style="margin-top: 8px; font-size: 11px; color: #475569; line-height: 1.4;">
                            <strong style="font-size: 14px; color: #0f172a;">' . e($brand['company_name']) . '</strong><br>
                            ' . ($address ? $address . '<br>' : '') . '
                            ' . ($contactStr ? $contactStr . '<br>' : '') . '
                            ' . ($trn ? '<span style="color: #0284c7; font-weight: 800;">' . $trn . '</span>' : '') . '
                        </div>
                    </td>
                    <td style="vertical-align: top; width: 45%; text-align: right; border: none;">
                        <h1 style="margin: 0; font-size: 20px; font-weight: 900; color: ' . $primaryColor . '; text-transform: uppercase; letter-spacing: -0.5px;">' . e($reportTitle) . '</h1>
                        ' . ($subtitle ? '<div style="font-size: 11px; color: #64748b; font-weight: 700; margin-top: 3px;">' . e($subtitle) . '</div>' : '') . '
                        <div style="margin-top: 10px; font-size: 10px; color: #64748b;">
                            <strong>Generated:</strong> ' . date('d M Y, h:i A') . '<br>
                            <strong>Active Workspace:</strong> ' . e($tenant['name']) . '
                        </div>
                    </td>
                </tr>
            </table>
            ' . ($filterStr ? '<div style="margin-top: 10px; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;"><strong style="font-size: 10px; color: #475569; text-transform: uppercase; margin-right: 8px;">Applied Filters:</strong> ' . $filterStr . '</div>' : '') . '
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
        <div style="margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; width: 100%;">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="border: none; padding: 0;">' . e($brand['company_name']) . ' &bull; Official Financial Statement &bull; Confidential</td>
                    <td style="text-align: right; border: none; padding: 0;">Page 1 &bull; System Generated on ' . date('d M Y') . '</td>
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
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap");
                @page { size: A4 portrait; margin: 12mm; }
                body { font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 11px; color: #0f172a; line-height: 1.5; margin: 0; padding: 0; background: #ffffff; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
                th { background: #0f172a; color: #ffffff; font-size: 10px; text-transform: uppercase; font-weight: 800; padding: 8px 10px; text-align: left; letter-spacing: 0.3px; }
                td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; color: #1e293b; }
                tr:nth-child(even) td { background: #f8fafc; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .font-bold { font-weight: 700; }
                .font-black { font-weight: 900; }
                .bg-total { background: #f1f5f9 !important; font-weight: 800; border-top: 2px solid #0f172a !important; border-bottom: 2px solid #0f172a !important; }
                .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; }
                .kpi-title { font-size: 9px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
                .kpi-value { font-size: 16px; font-weight: 900; color: #0f172a; }
                .badge { padding: 3px 7px; border-radius: 4px; font-weight: 800; font-size: 9px; text-transform: uppercase; }
                .badge-success { background: #dcfce7; color: #166534; }
                .badge-warning { background: #fef3c7; color: #92400e; }
                .badge-danger { background: #ffe4e6; color: #9f1239; }
                @media print {
                    .no-print { display: none !important; }
                    body { padding: 0; }
                    @page { margin: 10mm; }
                }
            </style>
        </head>
        <body onload="setTimeout(function(){ window.print(); }, 400);">
            <div class="no-print" style="background: #0f172a; color: #ffffff; padding: 10px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                <div style="font-size: 13px; font-weight: 800; display: flex; items-center;">
                    <i class="fa-solid fa-file-pdf" style="color: #f59e0b; margin-right: 8px; font-size: 15px;"></i> ' . e($title) . ' (Print & PDF Preview)
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.print()" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #0f172a; border: none; padding: 7px 18px; border-radius: 8px; font-weight: 900; cursor: pointer; font-size: 12px; shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        <i class="fa-solid fa-print"></i> Print / Save as PDF
                    </button>
                    <button onclick="window.close()" style="background: #334155; color: #ffffff; border: none; padding: 7px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 12px;">
                        Close Window
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
