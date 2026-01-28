<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasUuids;

    protected $table = 'purchase_items';

    public $timestamps = false;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'brand_id',
        'packaging_unit_id',
        'quantity',
        'quantity_in_base_units',
        'unit_price',
        'subtotal',
        'iva_percentage',
        'tax_amount',
        'total',
        'expiration_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_in_base_units' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva_percentage' => 'integer',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'expiration_date' => 'date',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function packagingUnit()
    {
        return $this->belongsTo(PackagingUnit::class, 'packaging_unit_id');
    }
}
