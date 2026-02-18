<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'bill_number', 'supplier_id', 'user_id', 'purchase_date',
        'subtotal', 'tax_rate', 'tax_amount', 'discount', 'total', 'notes'
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'supplier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($purchase) {
            if (empty($purchase->bill_number)) {
                $purchase->bill_number = static::generateBillNumber();
            }
        });
    }

    public static function generateBillNumber(): string
    {
        $prefix = 'PUR-';
        $date = date('Ymd');
        $last = static::whereDate('created_at', today())->latest()->first();
        if ($last && preg_match('/\d+$/', $last->bill_number, $m)) {
            $number = (int) $m[0] + 1;
        } else {
            $number = 1;
        }
        return $prefix . $date . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
