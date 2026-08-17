<?php
namespace Services;

use PDO;

class InvoiceRenderer {
    public static function render(array $invoice, array $items, array $branding, string $templateId = 'modern_minimal'): string {
        $templateFile = __DIR__ . '/../templates/invoices/' . $templateId . '.php';
        if (!file_exists($templateFile)) {
            $templateFile = __DIR__ . '/../templates/invoices/modern_minimal.php';
        }

        ob_start();
        extract([
            'inv' => $invoice,
            'items' => $items,
            'brand' => $branding,
        ]);
        include $templateFile;
        return ob_get_clean();
    }
}
