<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'gst_number', 'address', 'city', 'state',
        'postal_code', 'country', 'credit_limit', 'balance', 'is_active'
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnModel::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'supplier_id');
    }

    /**
     * Calculate available credit for customer
     */
    public function getAvailableCreditAttribute(): float
    {
        return (float) $this->credit_limit - (float) $this->balance;
    }

    /**
     * Check if customer has sufficient credit for a given amount
     */
    public function hasSufficientCredit(float $amount): bool
    {
        return $this->getAvailableCreditAttribute() >= $amount;
    }
}
