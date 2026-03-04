<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    protected $table = 'purchase_returns';

    protected $fillable = [
        'purchase_return_number', 'purchase_id', 'supplier_id', 'user_id',
        'return_date', 'reason', 'notes', 'return_amount', 'status',
    ];

    protected $casts = [
        'return_date' => 'date',
        'return_amount' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

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
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($purchaseReturn) {
            if (empty($purchaseReturn->purchase_return_number)) {
                $purchaseReturn->purchase_return_number = static::generateReturnNumber();
            }
        });
    }

    public static function generateReturnNumber(): string
    {
        $prefix = 'PRET-';
        $date = date('Ymd');
        $last = static::whereDate('created_at', today())->latest()->first();
        if ($last && preg_match('/\d+$/', $last->purchase_return_number, $m)) {
            $number = (int) $m[0] + 1;
        } else {
            $number = 1;
        }
        return $prefix . $date . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
