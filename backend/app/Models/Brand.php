<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Brand extends Model implements AuditableContract
{
    use HasUuids, Auditable;

    protected $table = 'brands';

    /**
     * Disable automatic timestamps since table only has created_at (INC-002 fix)
     * Laravel will not try to update updated_at column which doesn't exist
     */
    public $timestamps = false;

    /**
     * The name of the "created at" column.
     * Set to null since we manage it manually via DB default
     */
    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
        'created_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Relationships

    // Products of this brand
    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    // Recipe products with this brand
    public function recipeProducts()
    {
        return $this->hasMany(RecipeProduct::class, 'brand_id');
    }

    // Technical order products with this brand
    public function technicalOrderProducts()
    {
        return $this->hasMany(TechnicalOrderProduct::class, 'brand_id');
    }

    // Purchase items with this brand
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'brand_id');
    }

    // Output products with this brand
    public function outputProducts()
    {
        return $this->hasMany(OutputProduct::class, 'brand_id');
    }

    // Reception items with this brand
    public function receptionItems()
    {
        return $this->hasMany(ReceptionItem::class, 'brand_id');
    }

    // Inventory entries with this brand
    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'brand_id');
    }

    // Inventory movements with this brand
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'brand_id');
    }
}
