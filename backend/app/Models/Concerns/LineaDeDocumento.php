<?php

namespace App\Models\Concerns;

use App\Support\Auditoria\AuditoriaDeLaPeticion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Línea de un documento auditado (producto de una compra, de una salida, de
 * una recepción…). La línea no deja registro propio: lo que cambia en ella se
 * ve en la foto del DOCUMENTO, con producto, marca, cantidad y unidad tal como
 * estaban en ese momento.
 *
 * Por qué no un registro por línea: los controladores editan borrando TODAS
 * las líneas y creándolas de nuevo, así que serían "eliminó 10, creó 10" aunque
 * no haya cambiado nada. La foto del documento compara antes y después y dice
 * solo lo que de verdad cambió.
 *
 * El modelo declara a qué documento pertenece:
 *
 *     public const DOCUMENTO_AUDITADO = ['purchase', 'purchase_id'];  // [alias, columna]
 */
trait LineaDeDocumento
{
    public static function bootLineaDeDocumento(): void
    {
        if (!AuditoriaDeLaPeticion::activa()) {
            return;
        }

        [$alias, $columna] = static::DOCUMENTO_AUDITADO;

        $antes = fn (Model $linea) => AuditoriaDeLaPeticion::actual()
            ->recordarAntesDe($alias, $linea->getRawOriginal($columna) ?? $linea->getAttribute($columna));
        $despues = fn (Model $linea) => AuditoriaDeLaPeticion::actual()
            ->documentoTocado($alias, $linea->getAttribute($columna));

        static::creating($antes);
        static::updating($antes);
        static::deleting($antes);
        static::created($despues);
        static::updated($despues);
        static::deleted($despues);

        // `$compra->purchaseItems()->delete()` borra en lote, sin eventos: se
        // intercepta el delete de la consulta para tomar la foto ANTES.
        static::addGlobalScope('auditoria-de-lineas', new class($alias, $columna) implements Scope {
            public function __construct(private readonly string $alias, private readonly string $columna)
            {
            }

            public function apply(Builder $builder, Model $model): void
            {
            }

            public function extend(Builder $builder): void
            {
                $builder->onDelete(function (Builder $consulta) {
                    // La foto es un extra: si no se puede tomar, se borra igual.
                    $documentos = [];
                    try {
                        $documentos = (clone $consulta)->toBase()->distinct()->pluck($this->columna)->filter()->all();
                    } catch (\Throwable $e) {
                        report($e);
                    }

                    foreach ($documentos as $id) {
                        AuditoriaDeLaPeticion::actual()->recordarAntesDe($this->alias, $id);
                    }

                    $borradas = $consulta->toBase()->delete();

                    foreach ($documentos as $id) {
                        AuditoriaDeLaPeticion::actual()->documentoTocado($this->alias, $id);
                    }

                    return $borradas;
                });
            }
        });
    }
}
