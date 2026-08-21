<?php
namespace Services;

use PDO;
use Throwable;

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

    /**
     * Reverses an existing journal entry for a given reference rather than physically deleting financial history.
     */
    private function reverseExistingJournal(string $reference, string $reason): void {
        $st = $this->pdo->prepare("SELECT * FROM journal_entries WHERE tenant_id = ? AND reference = ? AND entry_number NOT LIKE 'REV-%' ORDER BY id DESC LIMIT 1");
        $st->execute([$this->tenantId, $reference]);
        $existingJe = $st->fetch();

        if (!$existingJe) {
            return;
        }

        $revNumber = 'REV-' . $existingJe['entry_number'];
        $revRef = 'REV-' . $reference;

        // Check if reversal already exists
        $stCheck = $this->pdo->prepare("SELECT id FROM journal_entries WHERE tenant_id = ? AND entry_number = ?");
        $stCheck->execute([$this->tenantId, $revNumber]);
        if ($stCheck->fetch()) {
            return;
        }

        $stRev = $this->pdo->prepare("INSERT INTO journal_entries (tenant_id, entry_number, entry_date, reference, description) VALUES (?, ?, ?, ?, ?)");
        $stRev->execute([
            $this->tenantId,
            $revNumber,
            date('Y-m-d'),
            $revRef,
            'Reversal of ' . $existingJe['entry_number'] . ': ' . $reason
        ]);
        $revJeId = (int)$this->pdo->lastInsertId();

        // Invert debits and credits
        $stItems = $this->pdo->prepare("SELECT * FROM journal_items WHERE journal_id = ?");
        $stItems->execute([$existingJe['id']]);
        $originalItems = $stItems->fetchAll();

        $stInsItem = $this->pdo->prepare("INSERT INTO journal_items (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");
        foreach ($originalItems as $item) {
            $stInsItem->execute([
                $revJeId,
                $item['account_id'],
                $item['credit'],
                $item['debit'],
                'Reversal: ' . ($item['memo'] ?? '')
            ]);
        }
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
        $reference = 'INV-' . $inv['id'];

        $ownsTx = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $ownsTx = true;
            }

            $this->reverseExistingJournal($reference, 'Invoice updated');

            $st = $this->pdo->prepare("INSERT INTO journal_entries (tenant_id, entry_number, entry_date, reference, description) VALUES (?, ?, ?, ?, ?)");
            $st->execute([
                $this->tenantId,
                $entryNumber,
                $inv['invoice_date'],
                $reference,
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

            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function postPaymentReceived(int $paymentId): void {
        $stPay = $this->pdo->prepare("SELECT p.*, i.invoice_number, c.company_name FROM payments p JOIN invoices i ON i.id = p.invoice_id JOIN clients c ON c.id = i.client_id WHERE p.id = ? AND p.tenant_id = ?");
        $stPay->execute([$paymentId, $this->tenantId]);
        $pay = $stPay->fetch();
        if (!$pay) return;

        $bankAccount = $this->getAccountByCode('1010'); // Cash / Bank
        $arAccount = $this->getAccountByCode('1200'); // Accounts Receivable

        if (!$bankAccount || !$arAccount) return;

        $entryNumber = 'JE-PAY-' . $pay['id'];
        $reference = 'PAY-' . $pay['id'];

        $ownsTx = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $ownsTx = true;
            }

            $this->reverseExistingJournal($reference, 'Payment adjustment');

            $st = $this->pdo->prepare("INSERT INTO journal_entries (tenant_id, entry_number, entry_date, reference, description) VALUES (?, ?, ?, ?, ?)");
            $st->execute([
                $this->tenantId,
                $entryNumber,
                $pay['payment_date'],
                $reference,
                'Payment received (' . $pay['payment_method'] . ') for Invoice ' . $pay['invoice_number'] . ' (' . $pay['company_name'] . ')'
            ]);
            $jeId = (int)$this->pdo->lastInsertId();

            $stItem = $this->pdo->prepare("INSERT INTO journal_items (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");
            // Debit Cash / Bank for payment amount
            $stItem->execute([$jeId, $bankAccount['id'], $pay['amount'], 0, 'Payment received']);
            // Credit Accounts Receivable for payment amount
            $stItem->execute([$jeId, $arAccount['id'], 0, $pay['amount'], 'Clearing AR for ' . $pay['invoice_number']]);

            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
        $reference = 'EXP-' . $exp['id'];

        $ownsTx = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $ownsTx = true;
            }

            $this->reverseExistingJournal($reference, 'Expense updated');

            $st = $this->pdo->prepare("INSERT INTO journal_entries (tenant_id, entry_number, entry_date, reference, description) VALUES (?, ?, ?, ?, ?)");
            $st->execute([
                $this->tenantId,
                $entryNumber,
                $exp['expense_date'],
                $reference,
                'Expense payment to ' . $exp['vendor_name']
            ]);
            $jeId = (int)$this->pdo->lastInsertId();

            $stItem = $this->pdo->prepare("INSERT INTO journal_items (journal_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)");
            // Debit Expense
            $stItem->execute([$jeId, $expAccount['id'], $exp['total'], 0, 'Expense: ' . $exp['vendor_name']]);
            // Credit Bank / Cash
            $stItem->execute([$jeId, $bankAccount['id'], 0, $exp['total'], 'Cash Outflow']);

            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Static helper compatibility proxies for legacy callers.
     */
    public static function postPaymentReceivedStatic(PDO $pdo, int $tenantId, int $paymentId): void {
        $service = new self($pdo, $tenantId);
        $service->postPaymentReceived($paymentId);
    }

    public static function postInvoicePayment(PDO $pdo, int $tenantId, int $invoiceId, float $amount, string $payMethod = ''): void {
        $st = $pdo->prepare("SELECT id FROM payments WHERE invoice_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$invoiceId, $tenantId]);
        $payId = (int)$st->fetchColumn();
        if ($payId > 0) {
            $service = new self($pdo, $tenantId);
            $service->postPaymentReceived($payId);
        }
    }
}
