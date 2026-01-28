<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OutputProduct extends Model
{
    use HasUuids;

    protected $table = 'output_products';

    public $timestamps = false;

    protected $fillable = [
        'output_id',
        'product_id',
        'brand_id',
        'quantity_requested',
        'quantity_delivered',
        'unit',
        'batch_number',
        'expiration_date',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:2',
        'quantity_delivered' => 'decimal:2',
        'expiration_date' => 'date',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function output()
    {
        return $this->belongsTo(ProductOutput::class, 'output_id');
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
