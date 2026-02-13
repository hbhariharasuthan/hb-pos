<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnModel extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'return_number', 'sale_id', 'customer_id', 'user_id',
        'return_date', 'reason', 'notes', 'refund_amount', 'status'
    ];

    protected $casts = [
        'return_date' => 'date',
        'refund_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($return) {
            if (empty($return->return_number)) {
                $return->return_number = static::generateReturnNumber();
            }
        });
    }

    public static function generateReturnNumber(): string
    {
        $prefix = 'RET-';
        $date = date('Ymd');
        $lastReturn = static::whereDate('created_at', today())->latest()->first();
        
        if ($lastReturn && preg_match('/\d+$/', $lastReturn->return_number, $matches)) {
            $number = intval($matches[0]) + 1;
        } else {
            $number = 1;
        }
        
        return $prefix . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
