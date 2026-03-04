<?php

namespace App\Observers;

use App\Models\DayBookEntry;
use App\Models\ReturnModel;

class ReturnDayBookObserver
{
    public function created(ReturnModel $return): void
    {
        if ($return->status !== 'approved') {
            return;
        }
        DayBookEntry::create([
            'user_id' => $return->user_id,
            'entry_date' => $return->return_date,
            'voucher_number' => $return->return_number,
            'entry_type' => DayBookEntry::TYPE_RETURN,
            'amount' => (float) $return->refund_amount * -1,
            'narration' => $return->notes,
            'reference_type' => ReturnModel::class,
            'reference_id' => $return->id,
        ]);
    }

    public function updated(ReturnModel $return): void
    {
        $entry = DayBookEntry::where('reference_type', ReturnModel::class)
            ->where('reference_id', $return->id)
            ->first();

        if ($return->status === 'cancelled') {
            if ($entry) {
                $entry->delete();
            }
            return;
        }

        if ($return->status === 'approved') {
            if ($entry) {
                $entry->update([
                    'entry_date' => $return->return_date,
                    'voucher_number' => $return->return_number,
                    'amount' => (float) $return->refund_amount * -1,
                    'narration' => $return->notes,
                ]);
            } else {
                DayBookEntry::create([
                    'user_id' => $return->user_id,
                    'entry_date' => $return->return_date,
                    'voucher_number' => $return->return_number,
                    'entry_type' => DayBookEntry::TYPE_RETURN,
                    'amount' => (float) $return->refund_amount * -1,
                    'narration' => $return->notes,
                    'reference_type' => ReturnModel::class,
                    'reference_id' => $return->id,
                ]);
            }
        }
    }

    public function deleted(ReturnModel $return): void
    {
        DayBookEntry::where('reference_type', ReturnModel::class)
            ->where('reference_id', $return->id)
            ->delete();
    }
}
