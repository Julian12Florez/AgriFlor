<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasUuids;

    protected $table = 'inventory_movements';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'product_id',
        'brand_id',
        'location_id',
        'quantity',
        'unit',
        'expiration_date',
        'unit_price',
        'total_price',
        'responsible_user',
        'related_document_id',
        'related_document_type',
        'observations',
    ];

    protected $casts = [
        'type' => 'string',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'expiration_date' => 'date',
        'created_at' => 'datetime',
    ];

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeEntries($query)
    {
        return $query->where('type', 'entry');
    }

    public function scopeExits($query)
    {
        return $query->where('type', 'exit');
    }

    public function scopeTransfers($query)
    {
        return $query->where('type', 'transfer');
    }

    public function scopeApplications($query)
    {
        return $query->where('type', 'application');
    }

    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
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

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user');
    }

    // Polymorphic relationship to related document
    // The related document can be: Purchase, ProductOutput, Reception, TechnicalOrder, or Adjustment
    public function relatedDocument()
    {
        return $this->morphTo('relatedDocument', 'related_document_type', 'related_document_id');
    }
}
