<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasUuids;

    protected $table = 'locations';

    /**
     * Disable automatic timestamps since table only has created_at (INC-001 fix)
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
        'type',
        'municipality',
        'address',
        'coordinates_lat',
        'coordinates_lng',
        'responsible_user_id',
        'status',
        'total_workers',
    ];

    protected $casts = [
        'type' => 'string',
        'coordinates_lat' => 'decimal:8',
        'coordinates_lng' => 'decimal:8',
        'status' => 'string',
        'total_workers' => 'integer',
        'created_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWarehouses($query)
    {
        return $query->where('type', 'warehouse');
    }

    public function scopeFarms($query)
    {
        return $query->where('type', 'farm');
    }

    // Relationships

    /**
     * User responsible for this location (encargado)
     * INC-001: Relacion con User para vincular encargado
     */
    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    // Purchases with this location as destination
    public function purchasesAsDestination()
    {
        return $this->hasMany(Purchase::class, 'destination_location_id');
    }

    // Product outputs with this location as origin
    public function outputsAsOrigin()
    {
        return $this->hasMany(ProductOutput::class, 'origin_location_id');
    }

    // Product outputs with this location as destination
    public function outputsAsDestination()
    {
        return $this->hasMany(ProductOutput::class, 'destination_location_id');
    }

    // Receptions with this location as origin
    public function receptionsAsOrigin()
    {
        return $this->hasMany(Reception::class, 'origin_location_id');
    }

    // Receptions with this location as destination
    public function receptionsAsDestination()
    {
        return $this->hasMany(Reception::class, 'destination_location_id');
    }

    // Technical orders assigned to this farm (many-to-many)
    public function technicalOrders()
    {
        return $this->belongsToMany(TechnicalOrder::class, 'technical_order_farms', 'farm_id', 'technical_order_id')
            ->withTimestamps()
            ->using(TechnicalOrderFarm::class);
    }

    // Inventory in this location
    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'location_id');
    }

    // Inventory movements in this location
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'location_id');
    }

    // Alerts related to this location
    public function alerts()
    {
        return $this->hasMany(Alert::class, 'location_id');
    }

    // Farm lots (only for farms)
    public function farmLots()
    {
        return $this->hasMany(FarmLot::class, 'location_id');
    }
}
