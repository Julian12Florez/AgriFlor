<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductPackagingUnit extends Pivot
{
    use HasUuids;

    protected $table = 'product_packaging_units';

    protected $fillable = [
        'product_id',
        'packaging_unit_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function packagingUnit()
    {
        return $this->belongsTo(PackagingUnit::class, 'packaging_unit_id');
    }
}
