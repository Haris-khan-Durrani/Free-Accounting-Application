<?php
namespace Services;

use PDO;

class AccountingService {
    private PDO $pdo;
    private int $tenantId;

    public function __construct(PDO $pdo, ?int $tenantId = null) {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId ?? \Core\Tenant::getActiveId();
    }

    public function getAccountByCode(string $code): ?array {
        $st = $this->pdo->prepare("SELECT * FROM chart_of_accounts WHERE tenant_id = ? AND account_code = ?");
        $st->execute([$this->tenantId, $code]);
        return $st->fetch() ?: null;
    }

    public function postInvoiceCreated(int $invoiceId): void {
        $st = $this->pdo->prepare("SELECT i.*, c.company_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ? AND i.tenant_id = ?");
        $st->execute([$invoiceId, $this->tenantId]);
        $inv = $st->fetch();
        if (!$inv) return;

        $arAccount = $this->getAccountByCode('1200'); // Accounts Receivable
        $revAccount = $this->getAccountByCode('4000'); // Revenue
        $taxAccount = $this->getAccountByCode('2200'); // Tax Payable

        if (!$arAccount || !$revAccount) return;

        $entryNumber = 'JE-INV-' . $inv['invoice_number'];
        
        // Remove old entry if re-posting
        $stDel = $this->pdo->prepare("DELETE FROM journal_entries WHERE tenant_id = ? AND reference = ?");
        $stDel->execute([$this->tenantId, $entryNumber]);

        $st = $this->pdo->prepare("INSERT INTO journal_entries (tenant_id, entry_number, entry_date, reference, description) VALUES (?, ?, ?, ?, ?)");
        $st->execute([
            $this->tenantId,
            $entryNumber,
            $inv['invoice_date'],
            $entryNumber,
            'Automated entry for Invoice ' . $inv['invoice_number'] . ' (' . $inv['company_name'] . ')'
        ]);
        $jeId = (int)$this->pdo->lastInsertId();

        $stItem = $this->pdo->prepare("INSERT INTO journal_items (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");

        // Debit Accounts Receivable (Total)
        $stItem->execute([$jeId, $arAccount['id'], $inv['total'], 0, 'Receivable from ' . $inv['company_name']]);

        // Credit Revenue (Subtotal - Discount)
        $netRevenue = $inv['subtotal'] - $inv['discount_amount'];
        $stItem->execute([$jeId, $revAccount['id'], 0, $netRevenue, 'Sales Revenue']);

        // Credit Tax Payable if Tax > 0
        if ($inv['tax_amount'] > 0 && $taxAccount) {
            $stItem->execute([$jeId, $taxAccount['id'], 0, $inv['tax_amount'], 'Output VAT / Tax Collected']);
        }
    }

    public function postInvoicePaid(int $invoiceId): void {
        $st = $this->pdo->prepare("SELECT i.*, c.company_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ? AND i.tenant_id = ?");
        $st->execute([$invoiceId, $this->tenantId]);
        $inv = $st->fetch();
        if (!$inv) return;

        $bankAccount = $this->getAccountByCode('1010'); // Cash / Bank
        $arAccount = $this->getAccountByCode('1200'); // Accounts Receivable

        if (!$bankAccount || !$arAccount) return;

        $entryNumber = 'JE-PAY-' . $inv['invoice_number'];
        $stDel = $this->pdo->prepare("DELETE FROM journal_entries WHERE tenant_id = ? AND reference = ?");
        $stDel->execute([$this->tenantId, $entryNumber]);

        $st = $this->pdo->prepare("INSERT INTO journal_entries (tenant_id, entry_number, entry_date, reference, description) VALUES (?, ?, ?, ?, ?)");
        $st->execute([
            $this->tenantId,
            $entryNumber,
            date('Y-m-d'),
            $entryNumber,
            'Payment received for Invoice ' . $inv['invoice_number'] . ' (' . $inv['company_name'] . ')'
        ]);
        $jeId = (int)$this->pdo->lastInsertId();

        $stItem = $this->pdo->prepare("INSERT INTO journal_items (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");
        // Debit Cash / Bank
        $stItem->execute([$jeId, $bankAccount['id'], $inv['total'], 0, 'Payment received']);
        // Credit Accounts Receivable
        $stItem->execute([$jeId, $arAccount['id'], 0, $inv['total'], 'Clearing AR for ' . $inv['invoice_number']]);
    }

    public function postExpense(int $expenseId): void {
        $st = $this->pdo->prepare("SELECT * FROM expenses WHERE id = ? AND tenant_id = ?");
        $st->execute([$expenseId, $this->tenantId]);
        $exp = $st->fetch();
        if (!$exp) return;

        $expAccount = $this->getAccountByCode('5000'); // General Expense
        $bankAccount = $this->getAccountByCode('1010'); // Bank Account

        if (!$expAccount || !$bankAccount) return;

        $entryNumber = 'JE-EXP-' . $exp['id'];
        $stDel = $this->pdo->prepare("DELETE FROM journal_entries WHERE tenant_id = ? AND reference = ?");
        $stDel->execute([$this->tenantId, $entryNumber]);

        $st = $this->pdo->prepare("INSERT INTO journal_entries (tenant_id, entry_number, entry_date, reference, description) VALUES (?, ?, ?, ?, ?)");
        $st->execute([
            $this->tenantId,
            $entryNumber,
            $exp['expense_date'],
            $entryNumber,
            'Expense payment to ' . $exp['vendor_name']
        ]);
        $jeId = (int)$this->pdo->lastInsertId();

        $stItem = $this->pdo->prepare("INSERT INTO journal_items (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");
        // Debit Expense
        $stItem->execute([$jeId, $expAccount['id'], $exp['total'], 0, 'Expense: ' . $exp['vendor_name']]);
        // Credit Bank / Cash
        $stItem->execute([$jeId, $bankAccount['id'], 0, $exp['total'], 'Cash Outflow']);
    }
}
