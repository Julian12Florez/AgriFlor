<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TechnicalOrder extends Model
{
    use HasUuids;

    protected $table = 'technical_orders';

    /**
     * Disable automatic timestamps since table only has created_at (INC-005 fix)
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
        'order_number',
        'scheduled_date',
        'status',
        'recipe_id',
        'responsible_agronomist',
        'estimated_cost',
        'observations',
        'applied_at',
        'applied_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'status' => 'string',
        'estimated_cost' => 'decimal:2',
        'applied_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'approved']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Relationships

    // Recipe used (optional)
    public function recipe()
    {
        return $this->belongsTo(TechnicalRecipe::class, 'recipe_id');
    }

    // Agronomist responsible
    public function agronomist()
    {
        return $this->belongsTo(User::class, 'responsible_agronomist');
    }

    // User who applied the order
    public function applier()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    // Farms assigned to this order (many-to-many)
    public function farms()
    {
        return $this->belongsToMany(Location::class, 'technical_order_farms', 'technical_order_id', 'farm_id')
            ->withTimestamps()
            ->using(TechnicalOrderFarm::class);
    }

    // Technical order products
    public function technicalOrderProducts()
    {
        return $this->hasMany(TechnicalOrderProduct::class, 'technical_order_id');
    }

    // Products in this order (through technical_order_products)
    public function products()
    {
        return $this->belongsToMany(Product::class, 'technical_order_products', 'technical_order_id', 'product_id')
            ->withPivot(['brand_id', 'quantity', 'unit', 'observations']);
    }

    // Product outputs associated with this order
    public function productOutputs()
    {
        return $this->hasMany(ProductOutput::class, 'technical_order_id');
    }

    // Inventory movements related to this order (polymorphic)
    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'related_document', 'related_document_type', 'related_document_id');
    }
}
