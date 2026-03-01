<?php

namespace App\Services;

class ConfigService
{
    /**
     * Whether totals (price/discount/tax) can be edited in the POS view.
     */
    public static function viewTotalEditable(): bool
    {
        return config('view.view_total_editable', false);
    }
}