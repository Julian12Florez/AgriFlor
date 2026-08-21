<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OutputType extends Model
{
    use HasUuids;

    protected $table = 'output_types';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
        'description',
        'requires_lots',
        'status',
    ];

    protected $casts = [
        'requires_lots' => 'boolean',
        'status' => 'string',
        'created_at' => 'datetime',
    ];

    /**
     * FUENTE ÚNICA DE VERDAD: códigos de salida que NO acreditan stock al destino.
     *
     * El producto sale de la bodega y se APLICA EN CAMPO (al cultivo): la finca
     * nunca lo custodia como existencia, así que crearle una entrada de kardex
     * le inventa stock que nadie va a descargar nunca.
     *
     * - 'consumption'     : consumo declarado sobre lotes de cultivo.
     * - 'technical_order' : orden técnica. Se agregó aquí porque acreditar la finca
     *   había acumulado 420.823 unidades fantasma (327 salidas, 425.142 unidades)
     *   a jul-2026: producto aplicado al cultivo que el sistema seguía mostrando
     *   como existencia en la finca.
     *
     * Estos códigos SIGUEN descargando la bodega (movimiento `exit`) y quedan
     * trazables a la finca por el documento de salida y la recepción.
     *
     * Los demás códigos ('transfer', 'remanente', 'free_request') SÍ acreditan
     * stock: mueven producto entre ubicaciones que lo custodian de verdad
     * (el remanente, además, lo devuelve a la bodega y tiene que sumar).
     *
     * Si mañana entra un código nuevo, se agrega AQUÍ y en ningún otro sitio.
     *
     * @var array<int, string>
     */
    public const DIRECT_CONSUMPTION_CODES = ['consumption', 'technical_order'];

    /**
     * ¿Esta salida se consume en campo y por tanto NO acredita stock al destino?
     *
     * Acepta null (salida sin tipo o tipo borrado) y responde false: ante la duda,
     * el comportamiento seguro es el de un traslado normal, que sí deja rastro de
     * la entrada y puede corregirse, en vez de perder producto en silencio.
     */
    public static function esConsumoDirecto(?string $code): bool
    {
        return $code !== null && in_array($code, self::DIRECT_CONSUMPTION_CODES, true);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRequiringLots($query)
    {
        return $query->where('requires_lots', true);
    }

    // Relationships

    // Product outputs of this type
    public function productOutputs()
    {
        return $this->hasMany(ProductOutput::class, 'output_type_id');
    }
}
