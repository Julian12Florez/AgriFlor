<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TechnicalOrderProduct extends Model
{
    use HasUuids;

    protected $table = 'technical_order_products';

    public $timestamps = false;

    protected $fillable = [
        'technical_order_id',
        'product_id',
        'brand_id',
        'quantity',
        'unit',
        'observations',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function technicalOrder()
    {
        return $this->belongsTo(TechnicalOrder::class, 'technical_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
