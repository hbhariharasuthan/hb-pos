<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number', 'customer_id', 'user_id', 'sale_date',
        'subtotal', 'tax_rate', 'tax_amount', 'discount', 'total',
        'payment_method', 'status', 'notes'
    ];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

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
        return $this->hasMany(SaleItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnModel::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->invoice_number)) {
                $sale->invoice_number = static::generateInvoiceNumber();
            }
        });

        static::created(function ($sale) {
            // Automatically increase customer balance for credit sales
            if ($sale->customer_id && $sale->payment_method === 'credit') {
                $sale->customer()->increment('balance', $sale->total);
            }
        });
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-';
        $date = date('Ymd');
        $lastSale = static::whereDate('created_at', today())->latest()->first();
        
        if ($lastSale && preg_match('/\d+$/', $lastSale->invoice_number, $matches)) {
            $number = intval($matches[0]) + 1;
        } else {
            $number = 1;
        }
        
        return $prefix . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
