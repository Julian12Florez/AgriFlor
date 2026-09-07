<?php

namespace App\Services\FarmBackfill;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Arma el plan de reparación de las entradas que nunca se escribieron en las
 * fincas (DIAGNOSTICO_INVENTARIO_20260907.md, §2.A y §3.2 "si se hace backfill").
 *
 * El plan se construye SIEMPRE leyendo la base, nunca de un archivo ni de una
 * lista fija: se usa igual en el pre-flight y —recalculado— dentro de la
 * transacción, que es la única idempotencia que de verdad protege.
 *
 * CANDIDATOS
 * ----------
 * Movimientos `exit` de una recepción de SALIDA cuyo tipo está en la lista de
 * códigos a reparar (por defecto 'technical_order') y para los que NO existe la
 * entrada emparejada en el destino. El predicado de "no existe" usa el MISMO
 * grano que el informe mensual de bodega
 * ({@see \App\Http\Controllers\Api\InventoryController::directConsumptionExitsByDestination}):
 * documento + producto + ubicación de destino. Así, en cuanto la entrada se
 * escribe, el `whereNotExists` de ese informe descarta el documento solo y la
 * matriz de envíos lo recoge por la entrada: no hay doble conteo ni hay que
 * tocar el informe.
 *
 * CORTE DURO
 * ----------
 * Nada con `movement_date <= corte` entra al plan (D1: julio y anteriores están
 * conciliados y re-baselineados). El filtro va en la consulta Y se vuelve a
 * comprobar como bloqueo, porque es el invariante más caro de violar.
 *
 * NETEO DE LAS COMPRAS FICTICIAS
 * ------------------------------
 * Mientras la finca no pudo custodiar nada, el cliente devolvió remanentes
 * registrándolos como compras a un proveedor inventado ("REMANENTES FINCA").
 * Esas compras YA acreditaron la bodega y NO descargaron la finca. Si se
 * repusieran las entradas sin más, lo devuelto quedaría contado dos veces (una
 * en bodega y otra en la finca).
 *
 * El neteo NO se hace restando de la entrada. Se hace escribiendo la
 * CONTRAPARTIDA que falta: una salida en la finca, con la fecha de kardex de la
 * entrada de bodega con la que forma pareja. Restar de la entrada rompería el
 * informe de bodega, porque su celda "Enviado a finca X" y su "Variación" se
 * calculan comparando el `exit` de bodega contra la ENTRADA de la finca: si
 * dejaran de ser iguales, la variación —la columna que se concilia contra
 * contabilidad— dejaría de ser 0 por el importe neteado.
 *
 * TOPE DEL NETEO
 * --------------
 * La contrapartida se recorta al saldo que la finca tiene de verdad después de
 * la reposición (saldo de kardex actual + entradas repuestas). Hay devoluciones
 * de producto que la finca recibió ANTES del re-baseline del 31-jul, que puso
 * todas las fincas en 0: contra eso no hay nada que netear, y forzarlo dejaría
 * saldos negativos. Lo que no cabe se reporta como residuo y NO se escribe.
 */
final class FarmBackfillPlanner
{
    /**
     * Marca de idempotencia. Va al principio de `observations` de todo lo que
     * escribe la reparación y lleva el UUID del movimiento del que se deriva.
     *
     * Se usa el guion y no el guion bajo a propósito: en un LIKE de SQL el `_`
     * es comodín de un carácter, y la detección tiene que ser exacta.
     */
    public const MARK_PREFIX = '[BACKFILL-FINCA origen-mov=';

    /** Códigos de salida que se reparan si no se pasa `--tipo`. */
    public const DEFAULT_OUTPUT_CODES = ['technical_order'];

    /** Proveedor ficticio por el que entraron las devoluciones. */
    public const DEFAULT_RETURN_SUPPLIER = 'REMANENTES FINCA';

    private const RECEPTION_DOCUMENT_TYPE = 'App\Models\Reception';

    private const PURCHASE_DOCUMENT_TYPE = 'App\Models\Purchase';

    /** decimal(10,2): por debajo de esto no hay cantidad que escribir. */
    private const EPSILON = 0.005;

    /**
     * Unidades de LONGITUD. Ninguna existencia se mide así: cuando aparecen es
     * un error de catálogo (BORNEO 11 SC, un líquido, está en 'cm' porque el
     * cliente escribe cm por cm³ = mL). El backfill NO las corrige — sólo avisa.
     */
    private const SUSPECT_UNITS = ['cm', 'm', 'mm'];

    /**
     * @param  array<int, string>  $outputCodes
     * @param  array<string, ?string>  $originOverrides  order_number → location_id, o null para omitir el neteo
     */
    public function plan(
        CarbonImmutable $cutoff,
        array $outputCodes,
        string $returnSupplier,
        array $originOverrides = [],
    ): FarmBackfillPlan {
        $blockers = [];
        $warnings = [];

        $this->assertPartialGroups($cutoff, $outputCodes, $blockers);

        $entries = $this->buildEntries($cutoff, $outputCodes, $warnings);
        [$nettings, $residuals] = $this->buildNettings(
            $cutoff,
            $returnSupplier,
            $entries,
            $originOverrides,
            $blockers,
            $warnings,
        );

        foreach (array_merge($entries, $nettings) as $movement) {
            if ($movement->movementDate <= $cutoff->toDateString()) {
                $blockers[] = sprintf(
                    'CORTE DURO violado: %s · %s · %s quedaría fechado el %s, en el periodo cerrado (<= %s).',
                    $movement->locationName,
                    $movement->productName,
                    $movement->documentLabel,
                    $movement->movementDate,
                    $cutoff->toDateString(),
                );
            }
        }

        return new FarmBackfillPlan($entries, $nettings, $residuals, $blockers, $warnings);
    }

    /**
     * ¿Ya corrió la reparación en esta base? Cuenta los movimientos marcados.
     *
     * @return array{entradas: int, neteos: int}
     */
    public function previousRunMarks(): array
    {
        $marked = DB::table('inventory_movements')
            ->where('observations', 'like', self::MARK_PREFIX . '%')
            ->selectRaw("SUM(type = 'entry') as entradas, SUM(type = 'exit') as neteos")
            ->first();

        return [
            'entradas' => (int) ($marked->entradas ?? 0),
            'neteos' => (int) ($marked->neteos ?? 0),
        ];
    }

    // ------------------------------------------------------------- entradas

    /**
     * Una entrada por cada `exit` huérfano. Copia cantidad, unidad, FECHA,
     * precio y responsable del movimiento origen: nada se recalcula, porque
     * recalcular es exactamente lo que haría que agosto cerrara distinto.
     *
     * @param  array<int, string>  $outputCodes
     * @param  array<int, string>  $warnings
     * @return array<int, PlannedMovement>
     */
    private function buildEntries(CarbonImmutable $cutoff, array $outputCodes, array &$warnings): array
    {
        $rows = DB::table('inventory_movements as m')
            ->join('receptions as r', function ($join) {
                $join->on('r.id', '=', 'm.related_document_id')->where('r.source_type', '=', 'output');
            })
            ->join('product_outputs as po', 'po.id', '=', 'r.source_id')
            ->join('output_types as ot', 'ot.id', '=', 'po.output_type_id')
            ->join('locations as l', 'l.id', '=', 'r.destination_location_id')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'm.brand_id')
            // El vencimiento sale de `reception_items`, que es de donde lo saca
            // el propio ReceptionController al crear la entrada. Hay 40 grupos
            // (recepción, producto, marca) con más de una fila, así que el JOIN
            // multiplicaría: el GROUP BY por `m.id` lo colapsa y se toma el
            // vencimiento MÁS PRÓXIMO, la misma regla que
            // InventoryService::earlierDate() al mezclar existencias (quedarse
            // con el más lejano rejuvenecería mercancía por vencer y la sacaría
            // de las alertas).
            ->leftJoin('reception_items as ri', function ($join) {
                $join->on('ri.reception_id', '=', 'r.id')
                    ->on('ri.product_id', '=', 'm.product_id')
                    ->on('ri.brand_id', '=', 'm.brand_id');
            })
            ->where('m.type', 'exit')
            ->where('m.related_document_type', self::RECEPTION_DOCUMENT_TYPE)
            ->whereIn('ot.code', $outputCodes)
            ->where('m.movement_date', '>', $cutoff->toDateString())
            ->whereNotExists(function ($exists) {
                $exists->selectRaw('1')
                    ->from('inventory_movements as e')
                    ->whereColumn('e.related_document_id', 'm.related_document_id')
                    ->whereColumn('e.product_id', 'm.product_id')
                    ->whereColumn('e.location_id', 'r.destination_location_id')
                    ->where('e.type', 'entry');
            })
            ->orderBy('m.movement_date')
            ->orderBy('m.created_at')
            ->orderBy('m.id')
            ->selectRaw(
                'm.id, m.product_id, m.brand_id, m.quantity, m.unit, m.movement_date, m.unit_price, '
                . 'm.responsible_user, m.observations, r.id as reception_id, '
                . 'r.destination_location_id, l.name as location_name, l.type as location_type, '
                . 'p.name as product_name, p.product_code, p.base_unit, '
                . 'COALESCE(b.name, \'Sin Marca\') as brand_name, po.output_number, '
                . 'MIN(ri.expiration_date) as expiration_date'
            )
            ->groupBy(
                'm.id', 'm.product_id', 'm.brand_id', 'm.quantity', 'm.unit', 'm.movement_date',
                'm.unit_price', 'm.responsible_user', 'm.observations', 'r.id',
                'r.destination_location_id', 'l.name', 'l.type', 'p.name', 'p.product_code',
                'p.base_unit', 'b.name', 'po.output_number',
            )
            ->get();

        $entries = [];
        $unitMismatches = [];
        $suspectUnits = [];
        $nonFarm = [];

        foreach ($rows as $row) {
            if ($row->location_type !== 'farm') {
                $nonFarm[$row->location_name] = true;
            }

            if ($row->base_unit !== $row->unit) {
                $unitMismatches[$row->product_name . ': movimiento en ' . $row->unit
                    . ', catálogo en ' . $row->base_unit] = true;
            }

            if (in_array($row->unit, self::SUSPECT_UNITS, true)) {
                $suspectUnits[$row->product_name . ' en "' . $row->unit . '"'] = true;
            }

            $entries[] = new PlannedMovement(
                PlannedMovement::KIND_ENTRY,
                (string) $row->id,
                'entry',
                (string) $row->product_id,
                (string) ($row->product_code ?? ''),
                (string) $row->product_name,
                (string) $row->brand_id,
                (string) $row->brand_name,
                (string) $row->destination_location_id,
                (string) $row->location_name,
                round((float) $row->quantity, 2),
                // La unidad se copia TAL CUAL del movimiento origen. El catálogo
                // tiene BORNEO 11 SC en 'cm' (es un líquido: 'cm' es el cm³ del
                // cliente), pero inventory, el kardex y la línea base de julio
                // usan 'cm' de forma consistente. "Corregirla" aquí crearía un
                // lote en otra unidad al lado del BASE-JUL-2026 de la finca y
                // rompería la conversión del FIFO. El catálogo se arregla en su
                // propio ticket; el backfill sólo reproduce lo que ya existe.
                (string) $row->unit,
                substr((string) $row->movement_date, 0, 10),
                $row->expiration_date === null ? null : substr((string) $row->expiration_date, 0, 10),
                round((float) $row->unit_price, 2),
                (string) $row->responsible_user,
                (string) $row->reception_id,
                self::RECEPTION_DOCUMENT_TYPE,
                (string) ($row->output_number ?? $row->reception_id),
                $this->batchNumberFor((string) $row->reception_id, (string) $row->observations),
            );
        }

        foreach (array_keys($unitMismatches) as $mismatch) {
            $warnings[] = 'Unidad del movimiento distinta de la del catálogo (se respeta la del movimiento) — ' . $mismatch;
        }

        foreach (array_keys($suspectUnits) as $suspect) {
            $warnings[] = 'Unidad de LONGITUD en un inventario: ' . $suspect . '. Es un error de CATÁLOGO '
                . '(el cliente escribe "cm" por cm³ = mL), no del backfill, y se copia tal cual: cambiarla aquí '
                . 'dejaría el lote nuevo en una unidad distinta a la del BASE-JUL-2026 que la finca ya tiene y '
                . 'rompería la conversión del FIFO. Corríjase en products.base_unit, en su propio ticket.';
        }

        foreach (array_keys($nonFarm) as $name) {
            $warnings[] = 'Destino que NO es de tipo finca: ' . $name . '. Se repone igual, pero revíselo.';
        }

        return $entries;
    }

    /**
     * Lote físico destino, idéntico al que habría creado
     * {@see \App\Http\Controllers\Api\ReceptionController::createEntryMovement}:
     * `REC-{8 primeros del uuid de la recepción}-{nº de lote de la recepción}`.
     *
     * El número de lote se recupera del texto del `exit` ("... lote #2 ..."),
     * que es el único sitio donde quedó. Si no se puede leer se usa 1, que es
     * el caso de 182 de los 184 movimientos afectados.
     */
    private function batchNumberFor(string $receptionId, string $observations): string
    {
        $batch = 1;

        if (preg_match('/lote #(\d+)/u', $observations, $matches) === 1) {
            $batch = (int) $matches[1];
        }

        return 'REC-' . substr($receptionId, 0, 8) . '-' . $batch;
    }

    // --------------------------------------------------------------- neteo

    /**
     * Contrapartidas en finca de las devoluciones registradas como compras
     * ficticias posteriores al corte.
     *
     * @param  array<int, PlannedMovement>  $entries
     * @param  array<string, ?string>  $originOverrides
     * @param  array<int, string>  $blockers
     * @param  array<int, string>  $warnings
     * @return array{0: array<int, PlannedMovement>, 1: array<int, array<string, mixed>>}
     */
    private function buildNettings(
        CarbonImmutable $cutoff,
        string $returnSupplier,
        array $entries,
        array $originOverrides,
        array &$blockers,
        array &$warnings,
    ): array {
        $rows = DB::table('inventory_movements as m')
            ->join('receptions as r', function ($join) {
                $join->on('r.id', '=', 'm.related_document_id')->where('r.source_type', '=', 'purchase');
            })
            ->join('purchases as pu', 'pu.id', '=', 'r.source_id')
            ->join('suppliers as s', 's.id', '=', 'pu.supplier_id')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'm.brand_id')
            ->leftJoin('locations as origen', 'origen.id', '=', 'pu.origin_location_id')
            ->where('m.type', 'entry')
            ->where('m.related_document_type', self::RECEPTION_DOCUMENT_TYPE)
            ->where('s.name', $returnSupplier)
            ->where('pu.status', '!=', 'cancelled')
            ->where('m.movement_date', '>', $cutoff->toDateString())
            ->orderBy('m.movement_date')
            ->orderBy('m.created_at')
            ->orderBy('m.id')
            ->selectRaw(
                'm.id, m.product_id, m.brand_id, m.quantity, m.unit, m.movement_date, m.unit_price, '
                . 'm.responsible_user, pu.id as purchase_id, pu.order_number, pu.origin_location_id, '
                . 'origen.name as origin_name, p.name as product_name, p.product_code, '
                . 'COALESCE(b.name, \'Sin Marca\') as brand_name'
            )
            ->get();

        if ($rows->isEmpty()) {
            return [[], []];
        }

        // Fechado a propósito: la capacidad se mide al día del neteo.
        $deltas = $this->datedDeltas($entries);
        $nettings = [];
        $residuals = [];
        $alreadyNetted = $this->alreadyNettedSourceIds();

        foreach ($rows as $row) {
            if (isset($alreadyNetted[(string) $row->id])) {
                continue;
            }

            $farmId = $this->resolveReturnOrigin($row, $originOverrides, $blockers, $warnings);

            if ($farmId === null) {
                continue;
            }

            $key = $row->product_id . '|' . $row->brand_id . '|' . $farmId;
            $fechaNeteo = substr((string) $row->movement_date, 0, 10);
            $available = $this->balanceAt($deltas, $key, $fechaNeteo);
            $wanted = round((float) $row->quantity, 2);
            $taken = round(min($wanted, max($available, 0.0)), 2);

            if ($taken > self::EPSILON) {
                // Se anota con su fecha: los neteos posteriores ven este consumo,
                // y los anteriores no. Las filas vienen ordenadas por movement_date.
                $deltas[$key][] = ['d' => $fechaNeteo, 'q' => -$taken];

                $nettings[] = new PlannedMovement(
                    PlannedMovement::KIND_NETTING,
                    (string) $row->id,
                    'exit',
                    (string) $row->product_id,
                    (string) ($row->product_code ?? ''),
                    (string) $row->product_name,
                    (string) $row->brand_id,
                    (string) $row->brand_name,
                    (string) $farmId,
                    (string) ($row->origin_name ?? $this->locationName($farmId)),
                    $taken,
                    (string) $row->unit,
                    // MISMA fecha de kardex que la entrada de bodega con la que
                    // forma pareja: es el invariante que la migración
                    // 2026_07_30_120000_realign_transfer_entry_movement_dates
                    // dejó por escrito para las dos patas de un traslado.
                    substr((string) $row->movement_date, 0, 10),
                    null,
                    round((float) $row->unit_price, 2),
                    (string) $row->responsible_user,
                    (string) $row->purchase_id,
                    // El documento es la COMPRA, no su recepción, y por dos
                    // motivos. Primero, es lo cierto: el producto salió de la
                    // finca amparado por PUR-…. Segundo,
                    // InventoryController::farmExitsOutsideOutputs excluye del
                    // informe por finca todo `exit` cuyo documento termine en
                    // 'Reception' (asume que la columna "Remanente" ya lo
                    // restó), y esta devolución NO está en esa columna porque no
                    // es un product_output: marcarla como Reception la haría
                    // invisible y la finca reportaría de más.
                    self::PURCHASE_DOCUMENT_TYPE,
                    (string) $row->order_number,
                    null,
                );
            }

            $residual = round($wanted - $taken, 2);

            if ($residual > self::EPSILON) {
                $residuals[] = [
                    'finca' => (string) ($row->origin_name ?? $this->locationName($farmId)),
                    'producto' => (string) $row->product_name,
                    'unidad' => (string) $row->unit,
                    'documento' => (string) $row->order_number,
                    'devuelto' => $wanted,
                    'neteado' => $taken,
                    'residuo' => $residual,
                    'motivo' => 'La finca no tiene saldo que respalde la devolución ni siquiera '
                        . 'después de la reposición (producto recibido antes del re-baseline del '
                        . $cutoff->toDateString() . ', que dejó todas las fincas en 0).',
                ];
            }
        }

        return [$nettings, $residuals];
    }

    /**
     * Saldo con el que cada triple producto + marca + finca puede respaldar una
     * devolución: lo que el kardex de la finca ya tiene MÁS lo que la
     * reposición le va a acreditar.
     *
     * @param  array<int, PlannedMovement>  $entries
     * @param  \Illuminate\Support\Collection<int, object>  $returnRows
     * @return array<string, float>
     */
    /**
     * Saldos FECHADOS por triple (producto|marca|finca), para poder preguntar
     * cuánto tenía la finca A UNA FECHA y no solo al final de todo.
     *
     * Medir la capacidad del neteo con el saldo TOTAL cerraba un mes en negativo:
     * Villa / BOROZINCO FOLIAR recibió sus 60 L el 03-sep, pero la devolución que
     * se le netea está fechada el 28-ago. Con el saldo total la capacidad daba 60
     * y el neteo pasaba; al corte del 31-ago la finca quedaba en −10,00 L, en el
     * mes que se concilia contra contabilidad. Con el saldo a la fecha, la
     * capacidad del 28-ago es 0 y la devolución entera se va a residuo.
     *
     * @param  array<int, PlannedMovement>  $entries
     * @return array<string, array<int, array{d: string, q: float}>>
     */
    private function datedDeltas(array $entries): array
    {
        $deltas = [];

        foreach ($entries as $entry) {
            $deltas[$entry->tripleKey()][] = [
                'd' => substr($entry->movementDate, 0, 10),
                'q' => $entry->quantity,
            ];
        }

        $ledger = DB::table('inventory_movements')
            ->select('product_id', 'brand_id', 'location_id', 'movement_date')
            ->selectRaw("SUM(CASE WHEN type = 'entry' THEN quantity ELSE -quantity END) as saldo")
            ->whereIn('location_id', function ($query) {
                $query->select('id')->from('locations')->where('type', 'farm');
            })
            ->groupBy('product_id', 'brand_id', 'location_id', 'movement_date')
            ->get();

        foreach ($ledger as $row) {
            $key = $row->product_id . '|' . $row->brand_id . '|' . $row->location_id;
            $deltas[$key][] = [
                'd' => substr((string) $row->movement_date, 0, 10),
                'q' => (float) $row->saldo,
            ];
        }

        return $deltas;
    }

    /**
     * Saldo del triple contando SOLO lo que ya había ocurrido en `$date`.
     *
     * @param  array<string, array<int, array{d: string, q: float}>>  $deltas
     */
    private function balanceAt(array $deltas, string $key, string $date): float
    {
        $total = 0.0;

        foreach ($deltas[$key] ?? [] as $delta) {
            if ($delta['d'] <= $date) {
                $total += $delta['q'];
            }
        }

        return round($total, 2);
    }

    /**
     * Finca de la que salió la devolución. Se toma de `purchases.origin_location_id`;
     * si viene vacío NO se adivina: se bloquea la corrida y se pide una
     * asignación explícita con `--origen-remanente`.
     *
     * @param  array<string, ?string>  $overrides
     * @param  array<int, string>  $blockers
     * @param  array<int, string>  $warnings
     */
    private function resolveReturnOrigin(
        object $row,
        array $overrides,
        array &$blockers,
        array &$warnings,
    ): ?string {
        $order = (string) $row->order_number;

        if (array_key_exists($order, $overrides)) {
            if ($overrides[$order] === null) {
                $message = 'Devolución ' . $order . ' declarada SIN finca (--origen-remanente="' . $order
                    . ':OMITIR"): no se netea, así que la finca de la que salió queda sobrevalorada en lo que '
                    . 'esa compra devolvió, hasta el conteo físico de cierre.';

                if (! in_array($message, $warnings, true)) {
                    $warnings[] = $message;
                }

                return null;
            }

            return $overrides[$order];
        }

        if ($row->origin_location_id !== null) {
            return (string) $row->origin_location_id;
        }

        // Un bloqueo por COMPRA, no por línea: PUR-2026-402555 tiene 13
        // productos y repetir el mismo mensaje 13 veces esconde los demás.
        $message = 'La devolución ' . $order . ' no dice de qué finca vino (purchases.origin_location_id NULL) '
            . 'y sin eso no se puede netear. Indique la finca con --origen-remanente="' . $order
            . ':<nombre-o-id-de-finca>", o acepte dejarla sin netear con --origen-remanente="' . $order . ':OMITIR".';

        if (! in_array($message, $blockers, true)) {
            $blockers[] = $message;
        }

        return null;
    }

    /**
     * UUIDs de entradas de bodega que ya tienen su contrapartida escrita por una
     * corrida anterior. Es la idempotencia del neteo: a diferencia de las
     * entradas, aquí no hay un "no existe la pareja" estructural del que tirar.
     *
     * @return array<string, bool>
     */
    private function alreadyNettedSourceIds(): array
    {
        $marked = DB::table('inventory_movements')
            ->where('observations', 'like', self::MARK_PREFIX . '%')
            ->where('type', 'exit')
            ->pluck('observations');

        $ids = [];

        foreach ($marked as $observations) {
            if (preg_match('/origen-mov=([0-9a-f-]{36})/i', (string) $observations, $matches) === 1) {
                $ids[strtolower($matches[1])] = true;
            }
        }

        return $ids;
    }

    private function locationName(string $locationId): string
    {
        return (string) (DB::table('locations')->where('id', $locationId)->value('name') ?? $locationId);
    }

    // ------------------------------------------------------------- bloqueos

    /**
     * Grupos documento + producto + destino en los que UNAS marcas tienen
     * entrada y otras no.
     *
     * Hoy no existe ninguno (medido en producción), pero si apareciera, el
     * informe de bodega ya estaría roto para ese documento: su `whereNotExists`
     * lo excluiría entero mientras la matriz de envíos sólo contaría las marcas
     * que sí tienen entrada. Reponer parcialmente ahí no lo arregla, así que se
     * para y se mira.
     *
     * @param  array<int, string>  $outputCodes
     * @param  array<int, string>  $blockers
     */
    private function assertPartialGroups(CarbonImmutable $cutoff, array $outputCodes, array &$blockers): void
    {
        $groups = DB::table('inventory_movements as m')
            ->join('receptions as r', function ($join) {
                $join->on('r.id', '=', 'm.related_document_id')->where('r.source_type', '=', 'output');
            })
            ->join('product_outputs as po', 'po.id', '=', 'r.source_id')
            ->join('output_types as ot', 'ot.id', '=', 'po.output_type_id')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->join('locations as l', 'l.id', '=', 'r.destination_location_id')
            ->where('m.type', 'exit')
            ->where('m.related_document_type', self::RECEPTION_DOCUMENT_TYPE)
            ->whereIn('ot.code', $outputCodes)
            ->where('m.movement_date', '>', $cutoff->toDateString())
            ->groupBy('r.id', 'm.product_id', 'r.destination_location_id', 'po.output_number', 'p.name', 'l.name')
            ->havingRaw('huerfano > 0 AND huerfano < COUNT(*)')
            ->selectRaw(
                'po.output_number, p.name as product_name, l.name as location_name, '
                . 'SUM(CASE WHEN NOT EXISTS ('
                . '  SELECT 1 FROM inventory_movements e'
                . '  WHERE e.related_document_id = m.related_document_id'
                . '    AND e.product_id = m.product_id AND e.brand_id = m.brand_id'
                . '    AND e.location_id = r.destination_location_id AND e.type = \'entry\''
                . ') THEN 1 ELSE 0 END) as huerfano'
            )
            ->get();

        foreach ($groups as $group) {
            $blockers[] = sprintf(
                'Documento %s · %s · %s tiene marcas con entrada y marcas sin ella. '
                . 'El informe de bodega ya está descuadrado para ese documento; repóngalo a mano.',
                $group->output_number,
                $group->product_name,
                $group->location_name,
            );
        }
    }
}
