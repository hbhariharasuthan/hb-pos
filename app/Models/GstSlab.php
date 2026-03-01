<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstSlab extends Model
{
    use HasFactory;

    protected $fillable = [
        'hsn_code', 'gst_percent', 'description'
    ];

    protected $casts = [
        'gst_percent' => 'decimal:2',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'gst_slab_id');
    }
}
