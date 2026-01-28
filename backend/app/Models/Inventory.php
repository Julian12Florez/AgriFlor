<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasUuids;

    protected $table = 'inventory';

    protected $fillable = [
        'product_id',
        'brand_id',
        'location_id',
        'batch_number',
        'quantity',
        'unit',
        'expiration_date',
        'unit_price',
        'total_value',
        'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_value' => 'decimal:2',
        'expiration_date' => 'date',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeLowStock($query)
    {
        return $query->where('status', 'low');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeNearExpiry($query)
    {
        return $query->where('status', 'near_expiry');
    }

    public function scopeGood($query)
    {
        return $query->where('status', 'good');
    }

    // Accessors
    public function getTotalValueAttribute($value)
    {
        // Calculated field: quantity * unit_price
        if ($this->attributes['quantity'] && $this->attributes['unit_price']) {
            return $this->attributes['quantity'] * $this->attributes['unit_price'];
        }
        return $value;
    }

    // Relationships

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
