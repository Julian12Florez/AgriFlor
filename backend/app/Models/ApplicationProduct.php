<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApplicationProduct extends Model
{
    use HasUuids;

    protected $table = 'application_products';

    protected $fillable = [
        'application_id',
        'product_id',
        'brand_id',
        'quantity',
        'unit',
        'applied_area',
        'area_unit',
        'dosage',
        'dosage_unit',
        'reception_id',
        'observations',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'applied_area' => 'decimal:2',
        'dosage' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Aplicación a la que pertenece este producto
     */
    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * Producto aplicado
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Marca del producto
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Recepción de origen (trazabilidad)
     */
    public function reception()
    {
        return $this->belongsTo(Reception::class, 'reception_id');
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Calcular la dosis si no está definida
     * Dosis = Cantidad / Área Aplicada
     */
    public function calculateDosage(): ?float
    {
        if ($this->applied_area && $this->applied_area > 0) {
            return round($this->quantity / $this->applied_area, 4);
        }
        return null;
    }

    /**
     * Obtener la dosis formateada con su unidad
     */
    public function getFormattedDosage(): string
    {
        if ($this->dosage && $this->dosage_unit) {
            return number_format($this->dosage, 2) . ' ' . $this->dosage_unit;
        }

        $calculatedDosage = $this->calculateDosage();
        if ($calculatedDosage) {
            $unit = $this->unit && $this->area_unit ? "{$this->unit}/{$this->area_unit}" : '';
            return number_format($calculatedDosage, 2) . ' ' . $unit;
        }

        return 'N/A';
    }

    /**
     * Obtener el área aplicada formateada con su unidad
     */
    public function getFormattedArea(): string
    {
        if ($this->applied_area) {
            $unit = $this->area_unit ?? 'ha';
            return number_format($this->applied_area, 2) . ' ' . $unit;
        }
        return 'N/A';
    }
}
