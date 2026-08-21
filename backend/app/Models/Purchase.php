<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Purchase extends Model implements AuditableContract
{
    use HasUuids, Auditable;

    protected $table = 'purchases';

    /**
     * Enable timestamps - table now has both created_at and updated_at (INC-003 fix)
     * updated_at was added in migration 2025_12_13_081400
     */
    public $timestamps = true;

    protected $fillable = [
        'order_number',
        'company_id',
        'supplier_id',
        'origin_location_id',
        'destination_location_id',
        'purchase_date',
        'expected_delivery',
        'status',
        'subtotal',
        'tax',
        'total',
        'observations',
        'created_by',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'expected_delivery' => 'date',
        'status' => 'string',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'received_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    // Relationships

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Empresa emisora del documento (membrete del PDF).
     * Nullable en BD: las compras anteriores al módulo de empresas no la tienen.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function originLocation()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // Purchase items
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }

    // Attachments
    public function attachments()
    {
        return $this->hasMany(PurchaseAttachment::class, 'purchase_id');
    }

    // Reception (1:1 relationship - a purchase has one reception)
    public function reception()
    {
        return $this->hasOne(Reception::class, 'source_id')
            ->where('source_type', 'purchase');
    }

    // Inventory movements related to this purchase (polymorphic)
    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'related_document', 'related_document_type', 'related_document_id');
    }
}
