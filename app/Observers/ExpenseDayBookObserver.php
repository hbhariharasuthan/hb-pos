<?php

namespace App\Observers;

use App\Models\DayBookEntry;
use App\Models\Expense;

class ExpenseDayBookObserver
{
    public function created(Expense $expense): void
    {
        DayBookEntry::create([
            'user_id' => $expense->user_id,
            'entry_date' => $expense->expense_date,
            'voucher_number' => $expense->voucher_number,
            'entry_type' => DayBookEntry::TYPE_EXPENSE,
            'amount' => $expense->amount,
            'narration' => $expense->notes,
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
        ]);
    }

    public function updated(Expense $expense): void
    {
        $entry = DayBookEntry::where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)
            ->first();

        if ($entry) {
            $entry->update([
                'entry_date' => $expense->expense_date,
                'voucher_number' => $expense->voucher_number,
                'amount' => $expense->amount,
                'narration' => $expense->notes,
            ]);
        }
    }

    public function deleted(Expense $expense): void
    {
        DayBookEntry::where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)
            ->delete();
    }
}
