<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Purchase;
use App\Models\ReturnModel;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class JournalPostingService
{
    protected function accountByCode(string $code): ?Account
    {
        return Account::where('code', $code)->where('is_active', true)->first();
    }

    protected function createEntry(object $model, string $voucherNumber, string $narration): JournalEntry
    {
        $date = match (true) {
            $model instanceof Sale => $model->sale_date,
            $model instanceof Purchase => $model->purchase_date,
            $model instanceof ReturnModel => $model->return_date,
            $model instanceof Expense => $model->expense_date,
            default => now(),
        };

        return JournalEntry::create([
            'user_id' => $model->user_id,
            'entry_date' => $date,
            'voucher_number' => $voucherNumber,
            'narration' => $narration,
            'reference_type' => get_class($model),
            'reference_id' => $model->id,
        ]);
    }

    protected function addLine(JournalEntry $entry, string $accountCode, float $debit, float $credit): void
    {
        $account = $this->accountByCode($accountCode);
        if (! $account) {
            return;
        }

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    public function postSale(Sale $sale): ?JournalEntry
    {
        $cash = $this->accountByCode('1000');
        $debtors = $this->accountByCode('2000');
        $sales = $this->accountByCode('3000');
        if (! $cash || ! $sales) {
            return null;
        }

        $entry = $this->createEntry($sale, $sale->invoice_number, $sale->notes ?? 'Sale');

        $total = (float) $sale->total;
        $isCredit = in_array($sale->payment_method, ['credit'], true);

        if ($isCredit && $debtors) {
            $this->addLine($entry, '2000', $total, 0);
        } else {
            $this->addLine($entry, '1000', $total, 0);
        }
        $this->addLine($entry, '3000', 0, $total);

        return $entry;
    }

    public function postPurchase(Purchase $purchase): ?JournalEntry
    {
        if (! $this->accountByCode('3100') || ! $this->accountByCode('2100')) {
            return null;
        }

        $entry = $this->createEntry($purchase, $purchase->bill_number, $purchase->notes ?? 'Purchase');
        $total = (float) $purchase->total;

        $this->addLine($entry, '3100', $total, 0);
        $this->addLine($entry, '2100', 0, $total);

        return $entry;
    }

    public function postReturn(ReturnModel $return): ?JournalEntry
    {
        $salesReturns = $this->accountByCode('3010');
        $debtors = $this->accountByCode('2000');
        if (! $salesReturns || ! $debtors) {
            return null;
        }

        $entry = $this->createEntry($return, $return->return_number, $return->notes ?? 'Sale return');
        $amount = (float) $return->refund_amount;

        $this->addLine($entry, '3010', $amount, 0); // Dr Sales Returns (contra income)
        $this->addLine($entry, '2000', 0, $amount); // Cr Debtors

        return $entry;
    }

    public function postExpense(Expense $expense): ?JournalEntry
    {
        if (! $this->accountByCode('3200') || ! $this->accountByCode('1000')) {
            return null;
        }

        $entry = $this->createEntry($expense, $expense->voucher_number, $expense->notes ?? 'Expense');
        $amount = (float) $expense->amount;

        $this->addLine($entry, '3200', $amount, 0);
        $this->addLine($entry, '1000', 0, $amount);

        return $entry;
    }

    public function unpost(object $model): void
    {
        $entry = JournalEntry::where('reference_type', get_class($model))
            ->where('reference_id', $model->id)
            ->first();

        if ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        }
    }

    public function updateSale(Sale $sale): void
    {
        $this->unpost($sale);
        $this->postSale($sale);
    }

    public function updatePurchase(Purchase $purchase): void
    {
        $this->unpost($purchase);
        $this->postPurchase($purchase);
    }

    public function updateReturn(ReturnModel $return): void
    {
        $this->unpost($return);
        $this->postReturn($return);
    }

    public function updateExpense(Expense $expense): void
    {
        $this->unpost($expense);
        $this->postExpense($expense);
    }
}
