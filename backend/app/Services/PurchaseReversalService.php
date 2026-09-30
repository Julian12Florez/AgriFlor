<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Reception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Elimina una compra —también una YA RECIBIDA— revirtiendo lo que metió al
 * inventario.
 *
 * POR QUÉ EXISTE
 * ==============
 * El 30-sep-2026 el cliente pidió borrar PUR-2026-402555, un remanente de finca
 * registrado como compra a "REMANENTES FINCA". La app no podía: el borrado de
 * compras solo aceptaba el estado "ordered", que no existe desde diciembre de
 * 2025. Hubo que hacerlo con SQL a mano, y no bastaba con borrar filas: de los 12
 * productos, 3 ya se habían despachado a fincas desde el lote de esa compra.
 *
 * QUÉ HACE, POR CADA PRODUCTO+MARCA DE CADA RECEPCIÓN DE LA COMPRA
 * ===============================================================
 *  - Borra la entrada del kardex que escribió la recepción.
 *  - Borra el lote físico que creó (REC-xxxxxxxx-N) con lo que le quede.
 *  - Lo que YA salió de ese lote hacia otras ubicaciones se descuenta de los
 *    demás lotes de la misma ubicación, en orden FIFO — el mismo de cualquier
 *    salida. Así kardex y físico bajan exactamente lo mismo y siguen cuadrando.
 * Y luego borra la recepción y la compra.
 *
 * QUÉ BLOQUEA (sin tocar nada)
 * ============================
 *  - Una entrada fechada en un mes cerrado (config inventory.closed_period_until).
 *  - Que el kardex quede negativo algún día desde la entrada: quitar una entrada
 *    fechada D baja el saldo de D y de todos los días siguientes (la regla de
 *    HistoricalStockService).
 *  - Que no haya en otros lotes con qué cubrir lo ya despachado.
 *  - Que el stock que queda no cubra lo reservado por salidas en tránsito.
 *  - Un lote de la compra citado por una salida que aún no se completa.
 *  - Una aplicación que apunte a la recepción.
 */
class PurchaseReversalService
{
    private const EPSILON = 0.01;

    /** Estados de salida que todavía van a usar el lote que citan. */
    private const SALIDAS_VIVAS = ['pending', 'approved', 'in_transit', 'partial'];

    public function __construct(
        private InventoryService $inventoryService,
        private HistoricalStockService $historico,
        private CommittedStockService $comprometido,
    ) {
    }

    /**
     * Qué pasaría si se elimina la compra, sin escribir nada.
     *
     * @return array{can_reverse: bool, blockers: array<int, string>, lines: array<int, array<string, mixed>>, receptions: array<int, string>}
     */
    public function plan(Purchase $purchase): array
    {
        $blockers = [];
        $lines = [];
        $receptions = $this->recepciones($purchase);
        $corte = (string) config('inventory.closed_period_until');

        foreach ($receptions as $reception) {
            $prefijo = $this->prefijoDeLote($reception);
            $movimientos = $this->movimientosDe($reception);

            $noEntradas = $movimientos->where('type', '!=', 'entry')->count();
            if ($noEntradas > 0) {
                $blockers[] = "La recepción {$reception->reception_number} tiene {$noEntradas} movimiento(s) que no son entradas: no es una recepción de compra normal y no se revierte automáticamente.";
            }

            $enTransito = DB::table('output_products as op')
                ->join('product_outputs as po', 'po.id', '=', 'op.output_id')
                ->where('op.batch_number', 'like', $prefijo . '%')
                ->whereIn('po.status', self::SALIDAS_VIVAS)
                ->pluck('po.output_number')
                ->unique()
                ->values();
            if ($enTransito->isNotEmpty()) {
                $blockers[] = 'Un lote de esta compra va en salidas que aún no se completan ('
                    . $enTransito->implode(', ') . '). Complételas o anúlelas primero.';
            }

            $aplicaciones = DB::table('application_products')->where('reception_id', $reception->id)->count();
            if ($aplicaciones > 0) {
                $blockers[] = "La recepción {$reception->reception_number} tiene {$aplicaciones} aplicación(es) asociada(s).";
            }

            foreach ($movimientos->where('type', 'entry')->groupBy(fn ($m) => $m->product_id . '|' . $m->brand_id . '|' . $m->location_id) as $grupo) {
                $lines[] = $this->linea($reception, $grupo, $prefijo, $corte, $blockers);
            }
        }

        return [
            'can_reverse' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'lines' => $lines,
            'receptions' => $receptions->pluck('reception_number')->all(),
        ];
    }

    /**
     * Elimina la compra y revierte su inventario. Todo o nada.
     *
     * @throws ValidationException si algo lo impide (422, nada cambia)
     */
    public function reverse(Purchase $purchase, string $motivo, string $usuario): array
    {
        $archivos = [];

        $resumen = DB::transaction(function () use ($purchase, $motivo, $usuario, &$archivos) {
            $purchase = Purchase::lockForUpdate()->findOrFail($purchase->id);

            // Se bloquean los lotes de lo que toca la compra y se vuelve a
            // planificar DENTRO de la transacción: si alguien despachó entre la
            // vista previa y la confirmación, se decide sobre el estado real.
            $this->bloquearLotes($purchase);
            $plan = $this->plan($purchase);

            if (!$plan['can_reverse']) {
                throw ValidationException::withMessages(['purchase' => $plan['blockers']]);
            }

            // El motivo queda en la traza de auditoría de la compra (evento
            // 'updated'), y el borrado deja su propio evento 'deleted'.
            $purchase->update([
                'observations' => trim(($purchase->observations ?? '') . "\n"
                    . '[ELIMINADA ' . now()->format('Y-m-d H:i') . " por {$usuario}] {$motivo}"),
            ]);

            foreach ($this->recepciones($purchase) as $reception) {
                $prefijo = $this->prefijoDeLote($reception);
                $movimientos = $this->movimientosDe($reception);

                foreach ($movimientos->groupBy(fn ($m) => $m->product_id . '|' . $m->brand_id . '|' . $m->location_id) as $grupo) {
                    $primero = $grupo->first();
                    $entradaBase = $this->enBase($grupo, $primero->product_id);
                    $lotes = $this->lotesDeLaRecepcion($primero, $prefijo);
                    $quedaBase = $this->lotesEnBase($lotes, $primero->product_id);

                    Inventory::whereIn('id', $lotes->pluck('id'))->delete();

                    $yaSalio = $entradaBase - $quedaBase;
                    if ($yaSalio > self::EPSILON) {
                        $this->inventoryService->reduceInventoryFIFO(
                            $primero->product_id,
                            $primero->brand_id,
                            $primero->location_id,
                            $yaSalio,
                            Product::find($primero->product_id)?->base_unit ?? $primero->unit,
                        );
                    }
                }

                InventoryMovement::whereIn('id', $movimientos->pluck('id'))->delete();

                $batchIds = DB::table('reception_batches')->where('reception_id', $reception->id)->pluck('id');
                DB::table('reception_batch_items')->whereIn('batch_id', $batchIds)->delete();
                DB::table('reception_batches')->where('reception_id', $reception->id)->delete();
                DB::table('reception_items')->where('reception_id', $reception->id)->delete();
                $reception->delete();
            }

            $archivos = $purchase->attachments()->pluck('file_path')->all();
            $purchase->attachments()->delete();
            $purchase->purchaseItems()->delete();
            $purchase->delete();

            return [
                'order_number' => $purchase->order_number,
                'receptions' => $plan['receptions'],
                'lines' => $plan['lines'],
            ];
        });

        // Los archivos se borran después del commit: si fallara el borrado en
        // disco, la base ya quedó consistente.
        foreach ($archivos as $ruta) {
            try {
                Storage::disk('public')->delete($ruta);
            } catch (\Throwable $e) {
                \Log::warning('No se pudo borrar un adjunto de una compra eliminada', ['path' => $ruta, 'error' => $e->getMessage()]);
            }
        }

        \Log::info('Compra eliminada con reversión de inventario', [
            'order_number' => $resumen['order_number'],
            'usuario' => $usuario,
            'motivo' => $motivo,
            'recepciones' => $resumen['receptions'],
        ]);

        return $resumen;
    }

    // ------------------------------------------------------------------

    /**
     * Una línea de la vista previa (producto+marca+ubicación de una recepción),
     * y los bloqueos que encuentre.
     */
    private function linea(Reception $reception, Collection $grupo, string $prefijo, string $corte, array &$blockers): array
    {
        $primero = $grupo->first();
        $producto = Product::find($primero->product_id);
        $nombre = $producto?->name ?? 'Producto';
        $unidad = $producto?->base_unit ?? $primero->unit;

        $entradaBase = $this->enBase($grupo, $primero->product_id);
        $lotes = $this->lotesDeLaRecepcion($primero, $prefijo);
        $quedaBase = $this->lotesEnBase($lotes, $primero->product_id);
        $yaSalio = max(0.0, $entradaBase - $quedaBase);

        $fisicoTotal = $this->lotesEnBase(
            Inventory::where('product_id', $primero->product_id)
                ->where('brand_id', $primero->brand_id)
                ->where('location_id', $primero->location_id)
                ->where('quantity', '>', 0)
                ->get(),
            $primero->product_id
        );
        $otrosLotes = $fisicoTotal - $quedaBase;
        $reservado = $this->comprometido->committedQuantity(
            $primero->location_id,
            $primero->product_id,
            (string) $primero->brand_id
        );

        $motivos = [];
        $fechaMinima = $grupo->min(fn ($m) => substr((string) $m->movement_date, 0, 10));

        if ($corte !== '' && $fechaMinima <= $corte) {
            $motivos[] = sprintf('entró el %s, en un mes cerrado (corte %s)', $this->fecha($fechaMinima), $this->fecha($corte));
        }

        if ($yaSalio > self::EPSILON && $otrosLotes + self::EPSILON < $yaSalio) {
            $motivos[] = sprintf(
                'ya salieron %s %s de su lote y en otros lotes solo hay %s %s para cubrirlo',
                $this->num($yaSalio), $unidad, $this->num($otrosLotes), $unidad
            );
        }

        $saldoMinimo = $this->historico->saldoMinimoDesde(
            $primero->product_id,
            $primero->brand_id,
            $primero->location_id,
            $fechaMinima,
            $grupo->pluck('id')->all(),
        );
        if ($saldoMinimo < -self::EPSILON) {
            $motivos[] = sprintf(
                'el kardex quedaría en %s %s en alguna fecha desde el %s',
                $this->num($saldoMinimo), $unidad, $this->fecha($fechaMinima)
            );
        }

        $fisicoDespues = $fisicoTotal - $entradaBase;
        if ($reservado > self::EPSILON && $fisicoDespues + self::EPSILON < $reservado) {
            $motivos[] = sprintf(
                'hay %s %s reservados por salidas en tránsito y quedarían %s %s',
                $this->num($reservado), $unidad, $this->num(max(0, $fisicoDespues)), $unidad
            );
        }

        foreach ($motivos as $motivo) {
            $blockers[] = "{$nombre}: {$motivo}.";
        }

        return [
            'reception_number' => $reception->reception_number,
            'product_id' => $primero->product_id,
            'product_name' => $nombre,
            'brand_name' => DB::table('brands')->where('id', $primero->brand_id)->value('name') ?? 'Sin marca',
            'location_name' => DB::table('locations')->where('id', $primero->location_id)->value('name'),
            'unit' => $unidad,
            'movement_date' => $fechaMinima,
            'entered' => round($entradaBase, 2),
            'still_in_lot' => round($quedaBase, 2),
            'already_dispatched' => round($yaSalio, 2),
            'ok' => $motivos === [],
            'problems' => $motivos,
        ];
    }

    /** @return Collection<int, Reception> */
    private function recepciones(Purchase $purchase): Collection
    {
        return Reception::where('source_type', 'purchase')
            ->where('source_id', $purchase->id)
            ->get();
    }

    /** @return Collection<int, InventoryMovement> */
    private function movimientosDe(Reception $reception): Collection
    {
        return InventoryMovement::where('related_document_id', $reception->id)
            ->where('related_document_type', 'App\\Models\\Reception')
            ->get();
    }

    /** Mismo formato que ReceptionController::createEntryMovement. */
    private function prefijoDeLote(Reception $reception): string
    {
        return 'REC-' . substr($reception->id, 0, 8) . '-';
    }

    private function lotesDeLaRecepcion(InventoryMovement $m, string $prefijo): Collection
    {
        return Inventory::where('product_id', $m->product_id)
            ->where('brand_id', $m->brand_id)
            ->where('location_id', $m->location_id)
            ->where('batch_number', 'like', $prefijo . '%')
            ->get();
    }

    private function bloquearLotes(Purchase $purchase): void
    {
        foreach ($this->recepciones($purchase) as $reception) {
            foreach ($this->movimientosDe($reception) as $m) {
                Inventory::where('product_id', $m->product_id)
                    ->where('brand_id', $m->brand_id)
                    ->where('location_id', $m->location_id)
                    ->lockForUpdate()
                    ->get();
            }
        }
    }

    private function enBase(Collection $movimientos, string $productId): float
    {
        return (float) $movimientos->sum(
            fn ($m) => $this->inventoryService->toBaseUnit((float) $m->quantity, (string) $m->unit, $productId)
        );
    }

    private function lotesEnBase(Collection $lotes, string $productId): float
    {
        return (float) $lotes->sum(
            fn ($l) => $this->inventoryService->toBaseUnit((float) $l->quantity, (string) $l->unit, $productId)
        );
    }

    private function num(float $n): string
    {
        return number_format($n, 2, ',', '.');
    }

    private function fecha(string $f): string
    {
        return \Carbon\CarbonImmutable::parse($f)->format('d/m/Y');
    }
}
