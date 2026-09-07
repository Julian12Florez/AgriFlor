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
     * El producto sale de la bodega y se APLICA EN CAMPO (al cultivo) en el acto:
     * la finca nunca lo custodia como existencia, así que crearle una entrada de
     * kardex le inventa stock que nadie va a descargar nunca.
     *
     * - 'consumption' : consumo declarado sobre lotes de cultivo. Se aplica el
     *   mismo día; no queda nada que devolver.
     *
     * Estos códigos SIGUEN descargando la bodega (movimiento `exit`) y quedan
     * trazables a la finca por el documento de salida y la recepción.
     *
     * Los demás códigos ('technical_order', 'transfer', 'remanente',
     * 'free_request') SÍ acreditan stock: mueven producto entre ubicaciones que
     * lo custodian de verdad (el remanente, además, lo devuelve a la bodega y
     * tiene que sumar).
     *
     * POR QUÉ 'technical_order' YA NO ESTÁ AQUÍ (sep-2026)
     * ----------------------------------------------------
     * Se agregó el 21-ago-2026 para matar un bug real de stock fantasma, pero la
     * cura resultó peor: la orden técnica es el 398 de 400 de las salidas a finca,
     * así que la finca dejó de recibir absolutamente todo. En 18 días quedaron
     * 184 movimientos sin contrapartida (72.185 kg + 1.341,8 L + 11.400 g +
     * 11.000 cm³ en 16 fincas), la finca no podía mover producto a sus lotes ni
     * devolver remanentes, y el cliente terminó registrando las devoluciones como
     * compras a un proveedor inventado ("REMANENTES FINCA").
     *
     * La orden técnica DESPACHA a la finca; no la consume. Lo que se manda hoy se
     * aplica durante las semanas siguientes y lo que sobra vuelve como remanente.
     * El descargo de la finca lo dan el remanente y el conteo físico mensual, que
     * es el proceso real del cliente (el que produjo INVENTARIO JULIO FINAL.xlsx).
     *
     * Si mañana entra un código nuevo, se agrega AQUÍ y en ningún otro sitio.
     *
     * @var array<int, string>
     */
    public const DIRECT_CONSUMPTION_CODES = ['consumption'];

    /**
     * LISTA HISTÓRICA CONGELADA. No gobierna comportamiento: solo informes que
     * leen el pasado.
     *
     * Entre el 21-ago-2026 y la corrección de sep-2026, las salidas
     * 'technical_order' descargaron la bodega sin acreditar la finca. Esos
     * movimientos ya escritos NUNCA van a tener una entrada emparejada.
     *
     * El informe mensual de bodega los atribuye por documento a su finca destino
     * ({@see \App\Http\Controllers\Api\InventoryController::directConsumptionExitsByDestination}).
     * Si ese informe usara la lista de comportamiento — que ya no incluye
     * 'technical_order' — esos movimientos dejarían de atribuirse y caerían
     * enteros a la celda "Variación": CALFOS/septiembre pasaría de variación 0 a
     * −18.000 y "Enviado a Breva" de 18.000 a 0.
     *
     * Por eso son DOS listas y no una. Esta no debe crecer nunca más: describe un
     * período cerrado del pasado. Si el backfill de reparación llega a escribir
     * las entradas que faltan, el `whereNotExists` del informe las descartará solo.
     *
     * @var array<int, string>
     */
    public const HISTORICAL_NO_ENTRY_CODES = ['consumption', 'technical_order'];

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
