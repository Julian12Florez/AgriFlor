<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Adjustment extends Model implements AuditableContract
{
    use HasUuids, Auditable;

    protected $table = 'adjustments';

    protected $fillable = [
        'adjustment_number',
        'type',
        'reason_id',
        'notes',
        'product_id',
        'brand_id',
        'unit',
        'quantity_mode',
        'quantity',
        'quantity_base',
        'origin_location_id',
        'destination_location_id',
        'batch_number',
        'unit_price',
        'movement_date',
        'status',
        'responsible_user',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_base' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'movement_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Generar numero de ajuste automatico
     * Formato: AJU-YYYYMMDD-XXXX
     */
    public static function generateAdjustmentNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "AJU-{$date}-";

        $lastAdjustment = self::where('adjustment_number', 'like', "{$prefix}%")
            ->orderBy('adjustment_number', 'desc')
            ->first();

        if ($lastAdjustment) {
            $lastNumber = (int) substr($lastAdjustment->adjustment_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }

    // Relationships

    public function reason()
    {
        return $this->belongsTo(AdjustmentReason::class, 'reason_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function originLocation()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'responsible_user');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
