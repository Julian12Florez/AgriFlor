<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReceptionItem extends Model
{
    use HasUuids;

    protected $table = 'reception_items';

    public $timestamps = false;

    protected $fillable = [
        'reception_id',
        'product_id',
        'brand_id',
        'source_item_id',
        'quantity_expected',
        'quantity_received',
        'quantity_pending',
        'unit',
        'expiration_date',
        'condition',
        'observations',
    ];

    protected $casts = [
        'quantity_expected' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'quantity_pending' => 'decimal:2',
        'expiration_date' => 'date',
        'condition' => 'string',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function reception()
    {
        return $this->belongsTo(Reception::class, 'reception_id');
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
