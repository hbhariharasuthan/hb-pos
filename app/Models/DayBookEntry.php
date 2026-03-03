<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DayBookEntry extends Model
{
    public const TYPE_SALE = 'sale';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_RETURN = 'return';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_JOURNAL = 'journal';
    public const TYPE_OPENING_BALANCE = 'opening_balance';

    public const MANUAL_TYPES = [
        self::TYPE_PAYMENT,
        self::TYPE_RECEIPT,
        self::TYPE_JOURNAL,
        self::TYPE_OPENING_BALANCE,
    ];

    protected $fillable = [
        'user_id',
        'entry_date',
        'voucher_number',
        'entry_type',
        'amount',
        'narration',
        'reference_type',
        'reference_id',
        'reconciled_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isManual(): bool
    {
        return in_array($this->entry_type, self::MANUAL_TYPES, true);
    }

    /**
     * Generate voucher number for manual entries (e.g. JV-YYYYMMDD-0001, OB-YYYYMMDD-0001).
     */
    public static function generateVoucherNumber(string $prefix = 'JV'): string
    {
        $date = date('Ymd');
        $pattern = $prefix . '-' . $date . '-%';
        $last = static::where('voucher_number', 'like', $pattern)
            ->orderBy('id', 'desc')
            ->first();

        if ($last && preg_match('/\d+$/', $last->voucher_number, $m)) {
            $number = (int) $m[0] + 1;
        } else {
            $number = 1;
        }

        return $prefix . '-' . $date . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
