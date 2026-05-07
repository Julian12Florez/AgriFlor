<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaskCatalog extends Model
{
    use HasUuids;

    protected $table = 'task_catalog';

    protected $fillable = [
        'code',
        'name',
        'unit',
        'category',
        'category_id',
        'description',
        'reference_yield',
        'active',
        'override_sobrepaso_pct',
        'override_alto_pct',
        'override_medio_pct',
        'override_k_factor',
        'created_by',
    ];

    protected $casts = [
        'reference_yield' => 'decimal:2',
        'active' => 'boolean',
        'override_sobrepaso_pct' => 'decimal:2',
        'override_alto_pct' => 'decimal:2',
        'override_medio_pct' => 'decimal:2',
        'override_k_factor' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function categoryRelation()
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    public function schedules()
    {
        return $this->hasMany(TaskSchedule::class, 'task_catalog_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Devuelve los umbrales efectivos: propios si existen overrides,
     * o los globales del sistema.
     */
    public function effectiveThresholds(): array
    {
        $globals = PerformanceSettings::current();

        return [
            'sobrepaso' => $this->override_sobrepaso_pct ?? $globals->global_sobrepaso_pct,
            'alto' => $this->override_alto_pct ?? $globals->global_alto_pct,
            'medio' => $this->override_medio_pct ?? $globals->global_medio_pct,
            'k_factor' => $this->override_k_factor ?? $globals->global_k_factor,
        ];
    }
}
