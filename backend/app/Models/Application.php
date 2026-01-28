<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Application extends Model
{
    use HasUuids;

    protected $table = 'applications';

    protected $fillable = [
        'application_number',
        'origin_location_id',
        'farm_lot_id',
        'product_output_id', // Relacion con salida tipo consumo (opcional)
        'application_date',
        'applied_by',
        'status',
        'application_type',
        'observations',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'application_date' => 'date',
        'status' => 'string',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope para filtrar por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para aplicaciones pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para aplicaciones aprobadas
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope para aplicaciones canceladas
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope para filtrar por ubicación origen
     */
    public function scopeByOriginLocation($query, $locationId)
    {
        return $query->where('origin_location_id', $locationId);
    }

    /**
     * Scope para filtrar por lote de finca
     */
    public function scopeByFarmLot($query, $farmLotId)
    {
        return $query->where('farm_lot_id', $farmLotId);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('application_date', [$startDate, $endDate]);
    }

    /**
     * Scope para filtrar por tipo de aplicación
     */
    public function scopeByType($query, $type)
    {
        return $query->where('application_type', $type);
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Ubicación origen de donde provienen los productos
     */
    public function originLocation()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    /**
     * Lote de finca donde se aplican los productos
     */
    public function farmLot()
    {
        return $this->belongsTo(FarmLot::class, 'farm_lot_id');
    }

    /**
     * Salida de productos de donde provino esta aplicacion (para consumos)
     * Solo aplica cuando la aplicacion se origina desde una salida tipo consumo
     */
    public function productOutput()
    {
        return $this->belongsTo(ProductOutput::class, 'product_output_id');
    }

    /**
     * Usuario que aplicó los productos
     */
    public function appliedByUser()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Usuario que aprobó la aplicación
     */
    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Usuario que canceló la aplicación
     */
    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Productos aplicados en esta aplicación
     */
    public function applicationProducts()
    {
        return $this->hasMany(ApplicationProduct::class, 'application_id');
    }

    /**
     * Movimientos de inventario relacionados con esta aplicación
     */
    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'relatedDocument', 'related_document_type', 'related_document_id');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Generar número de aplicación automático
     * Formato: APP-YYYYMMDD-XXXX
     */
    public static function generateApplicationNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "APP-{$date}-";

        // Obtener el último número del día
        $lastApplication = self::where('application_number', 'like', "{$prefix}%")
            ->orderBy('application_number', 'desc')
            ->first();

        if ($lastApplication) {
            $lastNumber = intval(substr($lastApplication->application_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }

    /**
     * Verificar si la aplicación puede ser aprobada
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'pending' && $this->applicationProducts()->count() > 0;
    }

    /**
     * Verificar si la aplicación puede ser cancelada
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending']);
    }

    /**
     * Verificar si la aplicación puede ser editada
     */
    public function canBeEdited(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Obtener el total de productos aplicados
     */
    public function getTotalProductsCount(): int
    {
        return $this->applicationProducts()->count();
    }

    /**
     * Obtener el área total aplicada
     */
    public function getTotalAreaApplied(): float
    {
        return $this->applicationProducts()->sum('applied_area') ?? 0;
    }
}
