<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton: solo existe un registro con id=1 que contiene los umbrales
 * globales del modulo de Rendimiento.
 */
class PerformanceSettings extends Model
{
    protected $table = 'performance_settings';

    protected $fillable = [
        'global_sobrepaso_pct',
        'global_alto_pct',
        'global_medio_pct',
        'global_k_factor',
    ];

    protected $casts = [
        'global_sobrepaso_pct' => 'decimal:2',
        'global_alto_pct' => 'decimal:2',
        'global_medio_pct' => 'decimal:2',
        'global_k_factor' => 'decimal:2',
    ];

    /**
     * Obtiene el singleton de configuracion global.
     */
    public static function current(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'global_sobrepaso_pct' => 130,
                'global_alto_pct' => 100,
                'global_medio_pct' => 80,
                'global_k_factor' => 3,
            ]
        );
    }
}
