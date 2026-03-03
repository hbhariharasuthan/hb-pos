<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'user_id', 'expense_category_id', 'voucher_number', 'amount',
        'expense_date', 'payment_method', 'reference', 'status', 'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($expense) {
            if (empty($expense->voucher_number)) {
                $expense->voucher_number = static::generateVoucherNumber();
            }
        });
    }

    public static function generateVoucherNumber(): string
    {
        $prefix = 'EXP-';
        $date = date('Ymd');
        $last = static::whereDate('created_at', today())->latest()->first();
        if ($last && preg_match('/\d+$/', $last->voucher_number, $m)) {
            $number = (int) $m[0] + 1;
        } else {
            $number = 1;
        }
        return $prefix . $date . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
