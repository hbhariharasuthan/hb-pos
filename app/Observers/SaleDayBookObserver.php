<?php

namespace App\Observers;

use App\Models\DayBookEntry;
use App\Models\Sale;

class SaleDayBookObserver
{
    public function created(Sale $sale): void
    {
        DayBookEntry::create([
            'user_id' => $sale->user_id,
            'entry_date' => $sale->sale_date,
            'voucher_number' => $sale->invoice_number,
            'entry_type' => DayBookEntry::TYPE_SALE,
            'amount' => $sale->total,
            'narration' => $sale->notes,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);
    }

    public function updated(Sale $sale): void
    {
        $entry = DayBookEntry::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->first();

        if ($entry) {
            $entry->update([
                'entry_date' => $sale->sale_date,
                'voucher_number' => $sale->invoice_number,
                'amount' => $sale->total,
                'narration' => $sale->notes,
            ]);
        }
    }

    public function deleted(Sale $sale): void
    {
        DayBookEntry::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->delete();
    }
}
