<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PackagingUnit extends Model
{
    use HasUuids;

    protected $table = 'packaging_units';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'base_quantity',
        'base_unit',
    ];

    protected $casts = [
        'base_quantity' => 'decimal:2',
        'base_unit' => 'string',
        'created_at' => 'datetime',
    ];

    // Relationships

    // Products that use this packaging unit (many-to-many)
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_packaging_units', 'packaging_unit_id', 'product_id')
            ->withTimestamps()
            ->using(ProductPackagingUnit::class);
    }

    // Purchase items using this packaging unit
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'packaging_unit_id');
    }
}
