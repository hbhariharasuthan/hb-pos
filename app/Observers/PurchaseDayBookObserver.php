<?php

namespace App\Observers;

use App\Models\DayBookEntry;
use App\Models\Purchase;

class PurchaseDayBookObserver
{
    public function created(Purchase $purchase): void
    {
        DayBookEntry::create([
            'user_id' => $purchase->user_id,
            'entry_date' => $purchase->purchase_date,
            'voucher_number' => $purchase->bill_number,
            'entry_type' => DayBookEntry::TYPE_PURCHASE,
            'amount' => $purchase->total,
            'narration' => $purchase->notes,
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
        ]);
    }

    public function updated(Purchase $purchase): void
    {
        $entry = DayBookEntry::where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->first();

        if ($entry) {
            $entry->update([
                'entry_date' => $purchase->purchase_date,
                'voucher_number' => $purchase->bill_number,
                'amount' => $purchase->total,
                'narration' => $purchase->notes,
            ]);
        }
    }

    public function deleted(Purchase $purchase): void
    {
        DayBookEntry::where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->delete();
    }
}
