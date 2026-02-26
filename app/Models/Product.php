<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name', 'sku', 'barcode', 'category_id', 'brand_id', 'description',
        'cost_price', 'selling_price', 'stock_quantity', 'min_stock_level',
        'unit', 'image', 'is_active'
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'min_stock_level' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    /** Whether quantity is in weight (kg/g) and allows decimals */
    public function isWeightUnit(): bool
    {
        $u = strtolower($this->unit ?? 'pcs');
        return in_array($u, ['kg', 'g', 'gm', 'gram', 'grams', 'kilogram', 'kilograms'], true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }
}
