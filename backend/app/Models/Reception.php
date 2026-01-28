<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Reception extends Model
{
    use HasUuids;

    protected $table = 'receptions';

    protected $fillable = [
        'reception_number',
        'source_id',
        'source_type',
        'origin_location_id',
        'destination_location_id',
        'shipment_date',
        'status',
        'total_expected',
        'total_received',
        'completion_percentage',
        'responsible_user',
        'observations',
    ];

    protected $casts = [
        'source_type' => 'string',
        'shipment_date' => 'date',
        'status' => 'string',
        'total_expected' => 'decimal:2',
        'total_received' => 'decimal:2',
        'completion_percentage' => 'decimal:2',
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
        return $query->whereIn('status', ['pending', 'partial']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeBySourceType($query, $type)
    {
        return $query->where('source_type', $type);
    }

    // Relationships

    // Polymorphic relationship to source (Purchase or ProductOutput)
    // DISABLED: morphTo causes SQL errors trying to add source_type to source tables
    // Use getSource() helper method in controller instead
    /*
    public function source()
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }
    */

    // Alternative specific relationships for easier querying
    // DISABLED: These add where('source_type') to Purchase/ProductOutput tables which don't have that column
    /*
    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'source_id')
            ->where('source_type', 'purchase');
    }

    public function productOutput()
    {
        return $this->belongsTo(ProductOutput::class, 'source_id')
            ->where('source_type', 'output');
    }
    */

    public function originLocation()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user');
    }

    // Reception items
    public function receptionItems()
    {
        return $this->hasMany(ReceptionItem::class, 'reception_id');
    }

    // Reception batches
    public function receptionBatches()
    {
        return $this->hasMany(ReceptionBatch::class, 'reception_id');
    }

    // Inventory movements related to this reception (polymorphic)
    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'related_document', 'related_document_type', 'related_document_id');
    }
}
