<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductOutput extends Model
{
    use HasUuids;

    protected $table = 'product_outputs';

    /**
     * Enable timestamps - table now has both created_at and updated_at (INC-004 fix)
     * updated_at was added in migration 2025_12_13_081401
     */
    public $timestamps = true;

    protected $fillable = [
        'output_number',
        'output_type_id',
        'technical_order_id',
        'output_date',
        'origin_location_id',
        'destination_location_id',
        'status',
        'total_cost',
        'observations',
        'responsible_user',
    ];

    protected $casts = [
        'output_date' => 'date',
        'status' => 'string',
        'total_cost' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Generar numero de salida automatico
     * Formato: SAL-YYYYMMDD-XXXX
     */
    public static function generateOutputNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "SAL-{$date}-";

        $lastOutput = self::where('output_number', 'like', "{$prefix}%")
            ->orderBy('output_number', 'desc')
            ->first();

        if ($lastOutput) {
            $lastNumber = (int) substr($lastOutput->output_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }

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

    // Relationships

    public function technicalOrder()
    {
        return $this->belongsTo(TechnicalOrder::class, 'technical_order_id');
    }

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

    // Output products
    public function outputProducts()
    {
        return $this->hasMany(OutputProduct::class, 'output_id');
    }

    // Reception (1:1 relationship - an output has one reception)
    public function reception()
    {
        return $this->hasOne(Reception::class, 'source_id')
            ->where('source_type', 'output');
    }

    // Inventory movements related to this output (polymorphic)
    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'related_document', 'related_document_type', 'related_document_id');
    }

    // Output type
    public function outputType()
    {
        return $this->belongsTo(OutputType::class, 'output_type_id');
    }

    // Farm lots associated with this output (many-to-many)
    public function farmLots()
    {
        return $this->belongsToMany(FarmLot::class, 'output_farm_lots', 'product_output_id', 'farm_lot_id')
            ->using(OutputFarmLot::class)
            ->withTimestamps();
    }

    /**
     * Aplicaciones registradas a partir de esta salida (solo para tipo consumo)
     * Permite rastrear que productos se aplicaron en que lotes desde esta salida
     */
    public function applications()
    {
        return $this->hasMany(Application::class, 'product_output_id');
    }
}
