<?php

namespace App\Console\Commands;

use App\Services\Rebaseline\FileBalance;
use App\Services\Rebaseline\InventoryFileData;
use App\Services\Rebaseline\PlanRow;
use App\Services\Rebaseline\PriceFileData;
use App\Services\Rebaseline\RebaselineApplier;
use App\Services\Rebaseline\RebaselineResult;
use App\Services\Rebaseline\RebaselineText;
use App\Services\Rebaseline\RebaselineWorkbookReader;
use App\Services\Rebaseline\SimulationWorkbookWriter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Re-baseline del inventario al 31-jul-2026 (reglas v3 aprobadas).
 *
 * Carga el inventario de julio del cliente como línea base oficial, dejando
 * intactos los movimientos de agosto. Por cada triple producto + marca +
 * ubicación:
 *
 *   J   = saldo de apertura según el archivo
 *         · BODEGA: hoja INVENTARIOS, columna "INVENTARIO FINAL"
 *         · FINCAS: SIEMPRE 0 (el archivo no trae stock de finca; la hoja
 *           REMANENTES no se lee — ver RebaselineWorkbookReader)
 *         · Excepción: si con esa apertura el saldo quedaría negativo (la
 *           ubicación consumió en agosto más de lo que abre), J = |A| para que
 *           cierre exactamente en 0
 *         · El archivo da UNA sola cifra por producto+ubicación, sin marca.
 *           Cuando hay varias marcas, cada una recibe primero lo mínimo que
 *           necesita para no quedar negativa y el resto se asigna a la marca
 *           principal (ver allocateOpening())
 *   K31 = saldo del kardex al corte   (movement_date <= corte)
 *   A   = delta de agosto             (movement_date >  corte)
 *   P   = stock físico actual         (tabla inventory)
 *
 * y la aplicación real escribe:
 *   1) UN movimiento al kardex fechado al corte, por (J − K31)
 *   2) el stock físico FIJADO a (J + A), lote BASE-JUL-2026, al precio del archivo
 *
 * MODOS
 * -----
 * `--dry-run` no escribe una sola fila: produce el Excel de simulación que se
 * revisa con bodega y contabilidad.
 *
 * Sin `--dry-run` se ejecuta la corrida REAL, que además de lo anterior:
 *   · aborta si hay UNA sola fila con alerta bloqueante (todo-o-nada),
 *   · aborta si ya hay marcas de una corrida previa (salvo `--force`),
 *   · pide confirmación por consola (salvo `--yes`),
 *   · respalda `inventory`, los movimientos de ajuste y `adjustments` en tablas
 *     `*_rebaseline_backup` ANTES de tocar nada,
 *   · borra TODOS los ajustes y sus movimientos,
 *   · recalcula K31, A y P DENTRO de la transacción (nunca reutiliza los
 *     números del pre-flight) y verifica el resultado contra el archivo.
 *
 * La mecánica de la escritura vive en `RebaselineApplier`; aquí quedan la
 * lectura de los archivos, el plan, el pre-flight y el reporte.
 */
class RebaselineInventory extends Command
{
    protected $signature = 'inventario:rebaseline
        {--inventario= : Ruta del Excel INVENTARIO JULIO FINAL.xlsx}
        {--valoracion= : Ruta del Excel Valoración de inventarios.xlsx}
        {--corte=2026-07-31 : Fecha de corte de la línea base}
        {--dry-run : Simula y NO escribe nada en la base de datos}
        {--salida= : Ruta del Excel de simulación a generar}
        {--responsable= : Email o id del usuario que queda como responsable de los ajustes (por defecto, el admin más antiguo)}
        {--yes : Aplica sin pedir confirmación interactiva (ejecución desatendida)}
        {--force : Permite re-ejecutar la aplicación real aunque ya existan marcas de una corrida previa}';

    protected $description = 'Re-baseline del inventario a la fecha de corte con los archivos del cliente (--dry-run simula, sin la opción aplica)';

    /** Tolerancia para comparar decimal(10,2) sin que el redondeo genere ruido. */
    private const EPSILON = 0.0001;

    public function handle(
        RebaselineWorkbookReader $reader,
        SimulationWorkbookWriter $writer,
        RebaselineApplier $applier,
    ): int {
        try {
            $cutoff = $this->cutoffDate();
            $warehouse = $this->warehouseLocation();
            $dryRun = (bool) $this->option('dry-run');

            $this->line(
                'Corte: ' . $cutoff->toDateString()
                . '  ·  Bodega: ' . $warehouse->name
                . '  ·  Modo: ' . ($dryRun ? 'SIMULACIÓN' : 'APLICACIÓN REAL')
            );

            $file = $reader->readInventory($this->requiredPath('inventario'), $warehouse->name);
            $prices = $reader->readPrices($this->requiredPath('valoracion'));

            $rows = $this->buildPlan($file, $prices, $cutoff);

            return $dryRun
                ? $this->simulate($writer, $rows, $file, $prices, $cutoff)
                : $this->apply($applier, $writer, $rows, $file, $prices, $cutoff);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<int, PlanRow>  $rows
     */
    private function simulate(
        SimulationWorkbookWriter $writer,
        array $rows,
        InventoryFileData $file,
        PriceFileData $prices,
        CarbonImmutable $cutoff,
    ): int {
        $output = $this->outputPath($cutoff);

        $writer->write($output, $rows, $this->summaryEntries($rows, $file, $prices, $cutoff), $this->alertCounts($rows));

        $this->reportPlan($rows, $file, $prices);
        $this->reportFileWarnings($file, $prices);
        $this->info('Excel de simulación generado en: ' . $output);

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- opciones

    private function cutoffDate(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) $this->option('corte'))->startOfDay();
        } catch (Throwable) {
            throw new RuntimeException('La fecha de --corte no es válida: ' . $this->option('corte'));
        }
    }

    private function requiredPath(string $option): string
    {
        $path = (string) $this->option($option);

        if ($path === '') {
            throw new RuntimeException("Falta la opción --{$option} con la ruta del Excel.");
        }

        return $path;
    }

    private function outputPath(CarbonImmutable $cutoff): string
    {
        $path = (string) $this->option('salida');

        if ($path !== '') {
            return $path;
        }

        $prefix = $this->option('dry-run') ? 'simulacion' : 'aplicacion';

        return storage_path("app/rebaseline/{$prefix}_rebaseline_" . $cutoff->format('Ymd') . '.xlsx');
    }

    // ------------------------------------------------------ estado del sistema

    private function warehouseLocation(): object
    {
        $warehouse = DB::table('locations')->where('type', 'warehouse')->first();

        if ($warehouse === null) {
            throw new RuntimeException('No existe ninguna ubicación de tipo "warehouse" en la base de datos.');
        }

        return $warehouse;
    }

    /**
     * Estado actual por triple producto + marca + ubicación.
     *
     * El universo es la UNIÓN de `inventory` (lo que hay físicamente) y de
     * `inventory_movements` (lo que dice el libro): hay triples que existen solo
     * en una de las dos tablas y todos deben quedar en la simulación.
     *
     * @return array<string, array{physical: float, value: float, ledger_cutoff: float, august: float, product_id: string, brand_id: string, location_id: string}>
     */
    private function systemState(CarbonImmutable $cutoff): array
    {
        $state = [];

        foreach ($this->physicalStockRows() as $row) {
            $key = $this->registerTriple($state, $row);
            $state[$key]['physical'] = round((float) $row->physical, 2);
            $state[$key]['value'] = round((float) $row->value, 2);
        }

        foreach ($this->ledgerRows($cutoff) as $row) {
            $key = $this->registerTriple($state, $row);
            $state[$key]['ledger_cutoff'] = round((float) $row->ledger_cutoff, 2);
            $state[$key]['august'] = round((float) $row->august, 2);
        }

        return $state;
    }

    /**
     * Asegura que el triple exista en el mapa de estado y devuelve su clave.
     *
     * @param  array<string, array<string, mixed>>  $state
     */
    private function registerTriple(array &$state, object $row): string
    {
        $key = $this->tripleKey($row->product_id, $row->brand_id, $row->location_id);

        $state[$key] ??= [
            'product_id' => $row->product_id,
            'brand_id' => $row->brand_id,
            'location_id' => $row->location_id,
            'physical' => 0.0,
            'value' => 0.0,
            'ledger_cutoff' => 0.0,
            'august' => 0.0,
        ];

        return $key;
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function physicalStockRows()
    {
        return DB::table('inventory')
            ->select('product_id', 'brand_id', 'location_id')
            ->selectRaw('SUM(quantity) as physical')
            ->selectRaw('SUM(COALESCE(total_value, 0)) as value')
            ->groupBy('product_id', 'brand_id', 'location_id')
            ->get();
    }

    /**
     * Kardex partido por la fecha de corte. Las entradas suman y todo lo demás
     * (exit, transfer, application) resta, igual que en `inventory:ledger-audit`.
     *
     * Un movimiento sin `movement_date` se considera anterior al corte: se
     * absorbe en la apertura en vez de desaparecer del cálculo.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function ledgerRows(CarbonImmutable $cutoff)
    {
        $signed = "CASE WHEN type = 'entry' THEN quantity ELSE -quantity END";
        $date = $cutoff->toDateString();

        return DB::table('inventory_movements')
            ->select('product_id', 'brand_id', 'location_id')
            ->selectRaw("SUM(CASE WHEN movement_date <= ? OR movement_date IS NULL THEN ({$signed}) ELSE 0 END) as ledger_cutoff", [$date])
            ->selectRaw("SUM(CASE WHEN movement_date > ? THEN ({$signed}) ELSE 0 END) as august", [$date])
            ->groupBy('product_id', 'brand_id', 'location_id')
            ->get();
    }

    /** @return array<string, object> código de producto → fila de products */
    private function productsByCode(array $products): array
    {
        $indexed = [];

        foreach ($products as $product) {
            if ($product->product_code !== null && $product->product_code !== '') {
                $indexed[trim($product->product_code)] = $product;
            }
        }

        return $indexed;
    }

    /** @return array<string, object> */
    private function productsById(): array
    {
        return DB::table('products')
            ->select('id', 'product_code', 'name', 'base_unit', 'brand_id')
            ->get()
            ->keyBy('id')
            ->all();
    }

    /** @return array<string, object> */
    private function locationsById(): array
    {
        return DB::table('locations')->select('id', 'name', 'type')->get()->keyBy('id')->all();
    }

    /** @return array<string, object> nombre normalizado → fila de locations */
    private function locationsByName(array $locations): array
    {
        $indexed = [];

        foreach ($locations as $location) {
            $indexed[RebaselineText::normalize($location->name)] = $location;
        }

        return $indexed;
    }

    /** @return array<string, string> */
    private function brandNames(): array
    {
        return DB::table('brands')->pluck('name', 'id')->all();
    }

    private function tripleKey(string $productId, string $brandId, string $locationId): string
    {
        return $productId . '|' . $brandId . '|' . $locationId;
    }

    // -------------------------------------------------------------- plan (J/A)

    /**
     * @return array<int, PlanRow>
     */
    private function buildPlan(InventoryFileData $file, PriceFileData $prices, CarbonImmutable $cutoff): array
    {
        $context = [
            'products_by_id' => $this->productsById(),
            'locations_by_id' => $this->locationsById(),
            'brands' => $this->brandNames(),
            'file' => $file,
            'prices' => $prices,
        ];
        $context['products_by_code'] = $this->productsByCode($context['products_by_id']);
        $context['locations_by_name'] = $this->locationsByName($context['locations_by_id']);

        $state = $this->systemState($cutoff);
        $consumed = [];

        $rows = $this->rowsFromSystem($state, $context, $consumed);
        $rows = array_merge($rows, $this->rowsOnlyInFile($context, $consumed));

        usort($rows, $this->rowOrder());

        return $rows;
    }

    /**
     * Una fila por cada triple que ya existe en el sistema.
     *
     * @param  array<string, array<string, mixed>>  $state
     * @param  array<string, mixed>  $context
     * @param  array<string, bool>  $consumed  claves del archivo ya asignadas
     * @return array<int, PlanRow>
     */
    private function rowsFromSystem(array $state, array $context, array &$consumed): array
    {
        $rows = [];

        foreach ($this->groupByProductAndLocation($state) as $brandStates) {
            $sample = reset($brandStates);
            $product = $context['products_by_id'][$sample['product_id']] ?? null;
            $location = $context['locations_by_id'][$sample['location_id']] ?? null;
            $balance = $this->fileBalanceFor($context['file'], $product, $location);

            if ($balance !== null) {
                $consumed[$balance->key()] = true;
            }

            $allocations = $this->allocateOpening($brandStates, $balance?->quantity ?? 0.0, $location);
            $multiBrand = count($brandStates) > 1;

            foreach ($brandStates as $brandId => $tripleState) {
                $rows[] = $this->buildRow(
                    $context,
                    $product,
                    (string) ($context['brands'][$brandId] ?? 'Sin Marca'),
                    $location,
                    $location?->name ?? 'Ubicación desconocida',
                    $tripleState,
                    $balance,
                    $multiBrand,
                    $allocations[$brandId],
                    (string) $brandId,
                );
            }
        }

        return $rows;
    }

    /**
     * Filas que solo existen en el archivo: producto + ubicación con saldo de
     * julio pero sin ningún rastro en el sistema (ni lote ni movimiento).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, bool>  $consumed
     * @return array<int, PlanRow>
     */
    private function rowsOnlyInFile(array $context, array $consumed): array
    {
        $rows = [];

        foreach ($context['file']->nonZeroBalances() as $balance) {
            if (isset($consumed[$balance->key()])) {
                continue;
            }

            $product = $context['products_by_code'][$balance->productCode] ?? null;
            $location = $context['locations_by_name'][RebaselineText::normalize($balance->locationLabel)] ?? null;
            $brandId = (string) ($product->brand_id ?? '');
            $brandStates = [$brandId => $this->emptyState()];
            $allocations = $this->allocateOpening($brandStates, $balance->quantity, $location);

            $rows[] = $this->buildRow(
                $context,
                $product,
                (string) ($context['brands'][$brandId] ?? 'Sin Marca'),
                $location,
                $location?->name ?? $balance->locationLabel,
                $brandStates[$brandId],
                $balance,
                false,
                $allocations[$brandId],
                $brandId,
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $state
     * @return array<string, array<string, array<string, mixed>>> "producto|ubicación" → marca → estado
     */
    private function groupByProductAndLocation(array $state): array
    {
        $groups = [];

        foreach ($state as $triple) {
            $groups[$triple['product_id'] . '|' . $triple['location_id']][$triple['brand_id']] = $triple;
        }

        return $groups;
    }

    /**
     * Marca "principal" de un producto + ubicación: la que recibe el resto de
     * la apertura tras proteger a todas las demás (ver allocateOpening()). Es
     * la de mayor stock físico y, a igualdad, la de mayor movimiento en el
     * libro (desempate final por brand_id para que sea determinista).
     *
     * @param  array<string, array<string, mixed>>  $brandStates
     */
    private function brandThatReceivesOpening(array $brandStates): string
    {
        $best = null;
        $bestScore = null;

        foreach ($brandStates as $brandId => $triple) {
            $score = [$triple['physical'], abs($triple['ledger_cutoff']), $brandId];

            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $best = (string) $brandId;
            }
        }

        return (string) $best;
    }

    private function fileBalanceFor(InventoryFileData $file, ?object $product, ?object $location): ?FileBalance
    {
        if ($product === null || $location === null || ($product->product_code ?? '') === '') {
            return null;
        }

        return $file->balanceFor(trim($product->product_code), $location->name);
    }

    /** @return array<string, mixed> */
    private function emptyState(): array
    {
        return ['physical' => 0.0, 'value' => 0.0, 'ledger_cutoff' => 0.0, 'august' => 0.0];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $tripleState
     * @param  array{opening: float, original: float, alerts: array<int, string>}  $allocation
     */
    private function buildRow(
        array $context,
        ?object $product,
        string $brandName,
        ?object $location,
        string $locationName,
        array $tripleState,
        ?FileBalance $balance,
        bool $multiBrand,
        array $allocation,
        ?string $brandId = null,
    ): PlanRow {
        $august = (float) $tripleState['august'];
        $physical = (float) $tripleState['physical'];
        $value = (float) $tripleState['value'];

        $productCode = $balance?->productCode ?? trim((string) ($product->product_code ?? ''));
        $filePrice = $context['prices']->priceFor($productCode);

        $alerts = array_merge(
            $allocation['alerts'],
            $this->diagnose($context, $product, $location, $balance, $productCode, $multiBrand, $tripleState),
            $this->priceAlerts($filePrice, $allocation['opening'] + $august),
        );

        return new PlanRow(
            $product->id ?? null,
            $productCode,
            (string) ($product->name ?? $balance?->productName ?? 'Producto desconocido'),
            $brandName,
            $locationName,
            $this->unitFor($product, $balance),
            $allocation['opening'],
            round($allocation['original'], 2),
            (float) $tripleState['ledger_cutoff'],
            $august,
            $physical,
            $filePrice,
            $physical > self::EPSILON ? round($value / $physical, 2) : null,
            $value,
            array_values(array_unique($alerts)),
            // Las tres llaves del triple: la simulación no las usa, la
            // aplicación real no puede escribir sin ellas.
            $brandId !== null && $brandId !== '' ? $brandId : null,
            $location->id ?? null,
        );
    }

    /**
     * Reparto de la apertura (J) entre las marcas de un producto + ubicación.
     *
     * El archivo del cliente da UNA sola cifra por producto + ubicación
     * (`$jTotal`), sin distinguir marca:
     *
     *   1. Cada marca recibe primero el mínimo que necesita para no quedar
     *      negativa dado su propio delta de agosto: `max(0, -A_marca)`.
     *   2. Lo que sobra (`$jTotal` − suma de mínimos) se asigna completo a la
     *      marca principal (brandThatReceivesOpening()).
     *   3. Si los mínimos exceden `$jTotal`, no se resta nada: cada marca se
     *      queda con su propio mínimo y la diferencia queda "reconocida por
     *      encima del archivo" (PlanRow::recognizedAboveFile()), igual que en
     *      la excepción de fincas que cierran en cero — que es, de hecho, el
     *      mismo caso: las fincas siempre llegan aquí con `$jTotal = 0`
     *      (ver RebaselineWorkbookReader), así que toda marca con delta de
     *      agosto negativo cae siempre en esta rama y cierra exactamente en 0.
     *
     * Con esta construcción ninguna marca puede quedar con apertura + agosto
     * negativos: SALDO_NEGATIVO deja de ser alcanzable.
     *
     * @param  array<string, array<string, mixed>>  $brandStates
     * @return array<string, array{opening: float, original: float, alerts: array<int, string>}>
     */
    private function allocateOpening(array $brandStates, float $jTotal, ?object $location): array
    {
        $jTotal = round($jTotal, 2);
        $minimums = [];
        $sumMinimums = 0.0;

        foreach ($brandStates as $brandId => $triple) {
            $minimum = max(0.0, round(-(float) $triple['august'], 2));
            $minimums[$brandId] = $minimum;
            $sumMinimums = round($sumMinimums + $minimum, 2);
        }

        $principal = $this->brandThatReceivesOpening($brandStates);
        $shortfall = round($sumMinimums - $jTotal, 2) > self::EPSILON;
        $remainder = $shortfall ? 0.0 : round($jTotal - $sumMinimums, 2);
        $isFarm = ($location->type ?? null) === 'farm';

        $allocations = [];

        foreach ($minimums as $brandId => $minimum) {
            $opening = round($minimum + ($brandId === $principal ? $remainder : 0.0), 2);
            $original = $brandId === $principal ? $jTotal : 0.0;

            $allocations[$brandId] = [
                'opening' => $opening,
                'original' => $original,
                'alerts' => $isFarm && $minimum > self::EPSILON ? [PlanRow::ALERT_FARM_CLOSES_AT_ZERO] : [],
            ];
        }

        return $allocations;
    }

    /**
     * Pre-flight de la fila: producto sin catálogo, ubicación sin match, unidad
     * distinta a la base y productos del sistema ausentes del archivo.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $tripleState
     * @return array<int, string>
     */
    private function diagnose(
        array $context,
        ?object $product,
        ?object $location,
        ?FileBalance $balance,
        string $productCode,
        bool $multiBrand,
        array $tripleState,
    ): array {
        $alerts = [];

        if ($product === null) {
            $alerts[] = PlanRow::ALERT_PRODUCT_NOT_IN_CATALOG;
        }

        if ($location === null) {
            $alerts[] = PlanRow::ALERT_LOCATION_NOT_FOUND;
        }

        if ($multiBrand) {
            $alerts[] = PlanRow::ALERT_MULTIPLE_BRANDS;
        }

        if ($product !== null && ! $context['file']->hasProduct($productCode) && $this->hasActivity($tripleState)) {
            $alerts[] = PlanRow::ALERT_MISSING_FROM_FILE;
        }

        return array_merge($alerts, $this->unitAlerts($context, $product, $productCode, $balance));
    }

    /**
     * Regla dura: NUNCA se convierten unidades. Si la unidad del archivo no es
     * la unidad base del producto, la fila es una falla dura y se reporta.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function unitAlerts(array $context, ?object $product, string $productCode, ?FileBalance $balance): array
    {
        if ($product === null || ! $context['file']->hasProduct($productCode)) {
            return [];
        }

        $label = $balance !== null && $balance->unitLabel !== ''
            ? $balance->unitLabel
            : ($context['file']->unitLabels[$productCode] ?? '');

        $symbol = RebaselineText::unitSymbol($label);

        if ($symbol === null) {
            return [PlanRow::ALERT_UNKNOWN_UNIT];
        }

        return $symbol === $product->base_unit ? [] : [PlanRow::ALERT_UNIT_MISMATCH];
    }

    /** @return array<int, string> */
    private function priceAlerts(?float $filePrice, float $target): array
    {
        return $filePrice === null && abs($target) > self::EPSILON
            ? [PlanRow::ALERT_NO_FILE_PRICE]
            : [];
    }

    /** @param array<string, mixed> $tripleState */
    private function hasActivity(array $tripleState): bool
    {
        return abs((float) $tripleState['physical']) > self::EPSILON
            || abs((float) $tripleState['ledger_cutoff']) > self::EPSILON
            || abs((float) $tripleState['august']) > self::EPSILON;
    }

    private function unitFor(?object $product, ?FileBalance $balance): string
    {
        return (string) ($product->base_unit ?? $balance?->unitLabel ?? '');
    }

    private function rowOrder(): callable
    {
        return static fn (PlanRow $a, PlanRow $b) => [$a->locationName, $a->productName, $a->brandName]
            <=> [$b->locationName, $b->productName, $b->brandName];
    }

    // ------------------------------------------------------------- resultados

    /**
     * @param  array<int, PlanRow>  $rows
     * @return array<string, int>
     */
    private function alertCounts(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            foreach ($row->alerts as $alert) {
                $counts[$alert] = ($counts[$alert] ?? 0) + 1;
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @param  array<int, PlanRow>  $rows
     * @return array<int, array{concepto: string, valor: mixed}>
     */
    private function summaryEntries(array $rows, InventoryFileData $file, PriceFileData $prices, CarbonImmutable $cutoff): array
    {
        $totals = $this->totals($rows);

        return [
            ['concepto' => 'Fecha de corte', 'valor' => $cutoff->toDateString()],
            ['concepto' => 'Modo', 'valor' => $this->option('dry-run') ? 'SIMULACIÓN (no se escribió nada)' : 'APLICACIÓN'],
            ['concepto' => 'Productos en el archivo de inventario', 'valor' => count($file->productNames)],
            ['concepto' => 'Productos con precio en la valoración', 'valor' => count($prices->prices)],
            ['concepto' => 'Cantidad total del archivo (bodega + fincas)', 'valor' => $file->totalQuantity()],
            ['concepto' => 'Cantidad total de la valoración (contable, solo bodega)', 'valor' => $prices->totalQuantity()],
            ['concepto' => 'Triples producto+marca+ubicación simulados', 'valor' => count($rows)],
            ['concepto' => 'Triples con cambios', 'valor' => $totals['con_cambios']],
            ['concepto' => 'Triples bloqueantes para la corrida real', 'valor' => $totals['bloqueados']],
            ['concepto' => 'Suma J (apertura al corte)', 'valor' => $totals['j']],
            ['concepto' => 'Suma K31 (kardex al corte)', 'valor' => $totals['k31']],
            ['concepto' => 'Suma A (delta de agosto)', 'valor' => $totals['a']],
            ['concepto' => 'Suma P (stock físico actual)', 'valor' => $totals['p']],
            ['concepto' => 'Suma físico objetivo (J + A)', 'valor' => $totals['objetivo']],
            ['concepto' => 'Movimientos de kardex a escribir', 'valor' => $totals['movimientos']],
            ['concepto' => 'Unidades a sumar al kardex (entradas)', 'valor' => $totals['kardex_entradas']],
            ['concepto' => 'Unidades a restar del kardex (salidas)', 'valor' => $totals['kardex_salidas']],
            ['concepto' => 'Fincas que cierran en 0 por excepción (filas)', 'valor' => $totals['fincas_en_cero']],
            ['concepto' => 'Apertura asignada a esas fincas (J)', 'valor' => $totals['unidades_fincas_en_cero']],
            ['concepto' => 'Unidades reconocidas por encima del archivo', 'valor' => $totals['reconocidas_sobre_archivo']],
            ['concepto' => 'Valorización actual', 'valor' => $totals['valor_actual']],
            ['concepto' => 'Valorización con la nueva base', 'valor' => $totals['valor_nuevo']],
            ['concepto' => 'Delta de valorización', 'valor' => $totals['delta_valor']],
        ];
    }

    /**
     * @param  array<int, PlanRow>  $rows
     * @return array<string, float|int>
     */
    private function totals(array $rows): array
    {
        $totals = array_fill_keys([
            'j', 'k31', 'a', 'p', 'objetivo', 'kardex_entradas', 'kardex_salidas',
            'valor_actual', 'valor_nuevo', 'unidades_fincas_en_cero', 'reconocidas_sobre_archivo',
        ], 0.0);
        $totals += array_fill_keys(['con_cambios', 'bloqueados', 'movimientos', 'fincas_en_cero'], 0);

        foreach ($rows as $row) {
            $movement = $row->ledgerMovement();

            $totals['j'] += $row->fileBalance;
            $totals['k31'] += $row->ledgerAtCutoff;
            $totals['a'] += $row->augustDelta;
            $totals['p'] += $row->physicalStock;
            $totals['objetivo'] += $row->physicalTarget();
            $totals['valor_actual'] += $row->currentValue;
            $totals['valor_nuevo'] += $row->targetValue();
            $totals['con_cambios'] += $row->hasChanges() ? 1 : 0;
            $totals['bloqueados'] += $row->isBlocked() ? 1 : 0;
            $totals['movimientos'] += abs($movement) > self::EPSILON ? 1 : 0;
            $totals['kardex_entradas'] += max($movement, 0);
            $totals['kardex_salidas'] += min($movement, 0);

            $totals['reconocidas_sobre_archivo'] += $row->recognizedAboveFile();

            if ($row->hasAlert(PlanRow::ALERT_FARM_CLOSES_AT_ZERO)) {
                $totals['fincas_en_cero']++;
                $totals['unidades_fincas_en_cero'] += $row->fileBalance;
            }
        }

        $totals['delta_valor'] = $totals['valor_nuevo'] - $totals['valor_actual'];

        return array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $totals);
    }

    /**
     * @param  array<int, PlanRow>  $rows
     */
    private function reportPlan(array $rows, InventoryFileData $file, PriceFileData $prices): void
    {
        $totals = $this->totals($rows);

        $this->newLine();
        $this->info('=== ARCHIVOS DEL CLIENTE ===');
        $this->line('  Productos en el archivo de inventario : ' . count($file->productNames));
        $this->line('  Cantidad total del archivo            : ' . number_format($file->totalQuantity(), 2));
        $this->line('  Productos con precio en la valoración : ' . count($prices->prices));

        $this->newLine();
        $this->info('=== PLAN DE RE-BASELINE (SIMULACIÓN) ===');
        $this->table(['Concepto', 'Valor'], [
            ['Triples producto+marca+ubicación', number_format(count($rows))],
            ['Triples con cambios', number_format($totals['con_cambios'])],
            ['Movimientos de kardex a escribir', number_format($totals['movimientos'])],
            ['Suma J (apertura)', number_format($totals['j'], 2)],
            ['Suma K31 (kardex al corte)', number_format($totals['k31'], 2)],
            ['Suma A (agosto)', number_format($totals['a'], 2)],
            ['Suma P (físico actual)', number_format($totals['p'], 2)],
            ['Suma físico objetivo (J+A)', number_format($totals['objetivo'], 2)],
            ['Kardex: unidades a sumar', number_format($totals['kardex_entradas'], 2)],
            ['Kardex: unidades a restar', number_format($totals['kardex_salidas'], 2)],
            ['Fincas que cierran en 0 (filas)', number_format($totals['fincas_en_cero'])],
            ['Apertura asignada a esas fincas (J)', number_format($totals['unidades_fincas_en_cero'], 2)],
            ['Unidades reconocidas por encima del archivo', number_format($totals['reconocidas_sobre_archivo'], 2)],
            ['Valorización actual', number_format($totals['valor_actual'], 2)],
            ['Valorización nueva', number_format($totals['valor_nuevo'], 2)],
            ['Delta de valorización', number_format($totals['delta_valor'], 2)],
        ]);

        $this->reportAlerts($rows, $totals);
    }

    /**
     * @param  array<int, PlanRow>  $rows
     * @param  array<string, float|int>  $totals
     */
    private function reportAlerts(array $rows, array $totals): void
    {
        $counts = $this->alertCounts($rows);

        $this->info('=== PRE-FLIGHT ===');

        if ($counts === []) {
            $this->line('  Sin alertas.');

            return;
        }

        $this->table(
            ['Alerta', 'Filas', 'Bloquea la corrida real'],
            array_map(
                fn (string $alert, int $count) => [
                    $alert,
                    number_format($count),
                    in_array($alert, PlanRow::BLOCKING_ALERTS, true) ? 'SÍ' : 'no',
                ],
                array_keys($counts),
                array_values($counts),
            ),
        );

        if ($totals['bloqueados'] > 0) {
            $this->warn(
                "  {$totals['bloqueados']} fila(s) bloquearían la aplicación real. "
                . 'En --dry-run solo se reportan; revisar la columna "alerta" del Excel.'
            );
            $this->listBlockedRows($rows);
        }
    }

    /**
     * @param  array<int, PlanRow>  $rows
     */
    private function listBlockedRows(array $rows, int $limit = 15): void
    {
        $blocked = array_values(array_filter($rows, fn (PlanRow $row) => $row->isBlocked()));

        $this->table(
            ['Código', 'Producto', 'Ubicación', 'J', 'A', 'J+A', 'Alerta'],
            array_map(fn (PlanRow $row) => [
                $row->productCode,
                mb_strimwidth($row->productName, 0, 32, '…'),
                $row->locationName,
                number_format($row->fileBalance, 2),
                number_format($row->augustDelta, 2),
                number_format($row->physicalTarget(), 2),
                $row->alertLabel(),
            ], array_slice($blocked, 0, $limit)),
        );

        if (count($blocked) > $limit) {
            $this->line('  ... y ' . (count($blocked) - $limit) . ' más (ver el Excel).');
        }
    }

    private function reportFileWarnings(InventoryFileData $file, PriceFileData $prices): void
    {
        $warnings = array_merge($file->warnings, $prices->warnings);

        if ($warnings === []) {
            return;
        }

        $this->info('=== AVISOS DE LECTURA DE LOS ARCHIVOS ===');

        foreach ($warnings as $warning) {
            $this->line('  · ' . $warning);
        }

        $this->newLine();
    }

    // ------------------------------------------------------- aplicación real

    /**
     * Corrida REAL. El orden de los pasos es el que la hace todo-o-nada: cada
     * comprobación capaz de abortar se ejecuta ANTES de la primera escritura, y
     * lo que no se puede comprobar de antemano se verifica dentro de la
     * transacción, donde un fallo se revierte solo.
     *
     * @param  array<int, PlanRow>  $rows  plan del pre-flight (se recalcula dentro de la transacción)
     */
    private function apply(
        RebaselineApplier $applier,
        SimulationWorkbookWriter $writer,
        array $rows,
        InventoryFileData $file,
        PriceFileData $prices,
        CarbonImmutable $cutoff,
    ): int {
        $force = (bool) $this->option('force');

        $this->reportPlan($rows, $file, $prices);
        $this->reportFileWarnings($file, $prices);

        $this->assertPreflightIsClean($rows);
        $this->assertNotAlreadyApplied($applier, $force);

        $user = $this->responsibleUser();
        $this->line('  Responsable de los ajustes: ' . $user->name . ' <' . $user->email . '>');

        $this->reportUnwritableRows($rows);

        if (! $this->confirmApplication($rows)) {
            $this->warn('Cancelado por el operador: no se escribió nada.');

            return self::SUCCESS;
        }

        $archived = $applier->prepareBackupTables($force);

        $result = $applier->apply(
            $cutoff,
            $rows,
            fn () => $this->buildPlan($file, $prices, $cutoff),
            (string) $user->id,
            $archived,
        );

        $this->reportResult($result, $cutoff);
        $this->writeAppliedWorkbook($writer, $result, $file, $prices, $cutoff);

        return self::SUCCESS;
    }

    /**
     * Pre-flight bloqueante, todo-o-nada: UNA sola fila con alerta bloqueante
     * detiene la corrida entera sin escribir nada.
     *
     * @param  array<int, PlanRow>  $rows
     */
    private function assertPreflightIsClean(array $rows): void
    {
        $blocked = array_values(array_filter($rows, fn (PlanRow $row) => $row->isBlocked()));

        if ($blocked === []) {
            return;
        }

        $this->listBlockedRows($rows);

        throw new RuntimeException(sprintf(
            'PRE-FLIGHT BLOQUEANTE: %d fila(s) con alertas de la lista %s. No se escribió NADA. '
            . 'Corrija el origen del problema (unidad del producto, ubicación sin match o saldo negativo) '
            . 'y vuelva a ejecutar el --dry-run.',
            count($blocked),
            implode(', ', PlanRow::BLOCKING_ALERTS),
        ));
    }

    /**
     * Idempotencia (reglas v3, punto 6): si ya hay ajustes REBASE-JUL26- o lotes
     * BASE-JUL-2026, la línea base ya se aplicó y repetirla la duplicaría.
     */
    private function assertNotAlreadyApplied(RebaselineApplier $applier, bool $force): void
    {
        $marks = $applier->previousRunMarks();

        if ($marks['ajustes'] === 0 && $marks['lotes'] === 0) {
            return;
        }

        $detail = sprintf(
            '%d ajuste(s) con el prefijo %s y %d lote(s) %s',
            $marks['ajustes'],
            RebaselineApplier::ADJUSTMENT_PREFIX,
            $marks['lotes'],
            RebaselineApplier::BASELINE_BATCH,
        );

        if (! $force) {
            throw new RuntimeException(
                'La línea base YA se aplicó en esta base de datos: ' . $detail . '. '
                . 'No se escribió nada. Use --force solo si sabe que quiere rehacerla '
                . '(con --force se borran los ajustes de la corrida anterior y se reconstruye desde cero).'
            );
        }

        $this->warn('  --force: se encontró una corrida previa (' . $detail . '); se rehará desde cero.');
    }

    /**
     * Filas del archivo que la corrida NO puede escribir porque el producto no
     * está en el catálogo (reglas v3, punto 7: hay que crearlo antes). No son
     * bloqueantes, pero tienen que verse: si se callaran, el archivo diría una
     * cosa y el sistema otra sin que nadie se enterara.
     *
     * @param  array<int, PlanRow>  $rows
     */
    private function reportUnwritableRows(array $rows): void
    {
        $unwritable = array_values(array_filter(
            $rows,
            fn (PlanRow $row) => ! $row->isWritable() && $row->hasChanges(),
        ));

        if ($unwritable === []) {
            return;
        }

        $this->newLine();
        $this->warn(sprintf(
            '  %d fila(s) del archivo NO se van a escribir porque el producto no está en el catálogo '
            . '(reglas v3, punto 7: hay que crearlo antes de la carga):',
            count($unwritable),
        ));

        $this->table(
            ['Código', 'Producto', 'Ubicación', 'J', 'Alerta'],
            array_map(fn (PlanRow $row) => [
                $row->productCode,
                mb_strimwidth($row->productName, 0, 32, '…'),
                $row->locationName,
                number_format($row->fileBalance, 2),
                $row->alertLabel(),
            ], $unwritable),
        );
    }

    /**
     * Resumen + confirmación interactiva. `--yes` la salta para ejecución
     * desatendida, pero el resumen se imprime igual: es lo que queda en el log
     * del despliegue como constancia de con qué números se aplicó.
     *
     * @param  array<int, PlanRow>  $rows
     */
    private function confirmApplication(array $rows): bool
    {
        $totals = $this->totals($rows);
        $connection = config('database.default');

        $this->newLine();
        $this->warn('=== SE VA A ESCRIBIR EN LA BASE DE DATOS ===');
        $this->line('  Base de datos          : ' . DB::connection()->getDatabaseName()
            . ' @ ' . config("database.connections.{$connection}.host"));
        $this->line('  Triples a procesar     : ' . number_format(count($rows)));
        $this->line('  Movimientos de kardex  : ' . number_format($totals['movimientos']));
        $this->line('  Ajustes existentes     : se BORRAN todos (cabeceras y movimientos)');
        $this->line('  Stock físico           : se FIJA a J + A en el lote ' . RebaselineApplier::BASELINE_BATCH);
        $this->line('  Valorización           : ' . number_format($totals['valor_actual'], 2)
            . ' → ' . number_format($totals['valor_nuevo'], 2)
            . '  (' . number_format($totals['delta_valor'], 2) . ')');

        if ($this->option('yes')) {
            $this->line('  --yes: se aplica sin confirmación interactiva.');

            return true;
        }

        return $this->confirm('¿Aplicar el re-baseline con estos números?', false);
    }

    /**
     * Usuario al que se atribuyen los ajustes de la línea base.
     *
     * `responsible_user` es NOT NULL en `adjustments` y en
     * `inventory_movements`, así que se resuelve ANTES de abrir la transacción:
     * descubrir que no hay usuario a mitad de la escritura solo serviría para
     * provocar un rollback.
     */
    private function responsibleUser(): object
    {
        $wanted = trim((string) $this->option('responsable'));

        if ($wanted !== '') {
            $user = DB::table('users')
                ->select('id', 'name', 'email')
                ->where(fn ($query) => $query->where('email', $wanted)->orWhere('id', $wanted))
                ->first();

            if ($user === null) {
                throw new RuntimeException("No existe ningún usuario con email o id \"{$wanted}\".");
            }

            return $user;
        }

        $user = DB::table('users')
            ->select('users.id', 'users.name', 'users.email')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.name', 'admin')
            ->orderBy('users.created_at')
            ->first();

        if ($user === null) {
            throw new RuntimeException(
                'No hay ningún usuario con rol admin al que atribuir los ajustes; '
                . 'indique uno con --responsable=correo@dominio.'
            );
        }

        return $user;
    }

    /**
     * Excel de la corrida REAL: se genera con el plan RECALCULADO (el que de
     * verdad se aplicó), no con el del pre-flight. Va después del commit y en su
     * propio try: un fallo escribiendo un archivo no debe hacer parecer que la
     * corrida falló, porque los datos ya están escritos y verificados.
     */
    private function writeAppliedWorkbook(
        SimulationWorkbookWriter $writer,
        RebaselineResult $result,
        InventoryFileData $file,
        PriceFileData $prices,
        CarbonImmutable $cutoff,
    ): void {
        $output = $this->outputPath($cutoff);

        try {
            $writer->write(
                $output,
                $result->appliedRows,
                $this->summaryEntries($result->appliedRows, $file, $prices, $cutoff),
                $this->alertCounts($result->appliedRows),
            );

            $this->info('Excel de la corrida aplicada: ' . $output);
        } catch (Throwable $exception) {
            $this->warn(
                'El re-baseline se aplicó y verificó correctamente, pero no se pudo escribir el Excel '
                . 'de respaldo en ' . $output . ': ' . $exception->getMessage()
            );
        }
    }

    private function reportResult(RebaselineResult $result, CarbonImmutable $cutoff): void
    {
        $this->newLine();
        $this->info('=== RE-BASELINE APLICADO ===');
        $this->table(['Concepto', 'Valor'], [
            ['Fecha de corte', $cutoff->toDateString()],
            ['Triples procesados', number_format($result->triplesProcessed)],
            ['Movimientos de kardex creados', number_format($result->movementsCreated)],
            ['Ajustes creados (' . RebaselineApplier::ADJUSTMENT_PREFIX . '####)', number_format($result->adjustmentsCreated)],
            ['Ajustes anteriores borrados', number_format($result->oldAdjustmentsDeleted)],
            ['Movimientos de ajuste borrados', number_format($result->oldAdjustmentMovementsDeleted)],
            ['Filas de inventory respaldadas', number_format($result->inventoryRowsBackedUp)],
            ['Filas de inventory eliminadas', number_format($result->inventoryRowsDeleted)],
            ['Filas de inventory creadas (' . RebaselineApplier::BASELINE_BATCH . ')', number_format($result->inventoryRowsCreated)],
            ['Triples que quedan en 0 (sin lote)', number_format($result->triplesEmptied)],
            ['Filas sin precio en el archivo (costo conservado)', number_format($result->rowsWithoutPrice)],
            ['Triples aparecidos al recalcular', number_format($result->triplesAppearedAfterReplan)],
            ['Filas del archivo omitidas (producto sin catálogo)', number_format(count($result->skippedRows))],
            ['Valorización antes', number_format($result->valueBefore, 2)],
            ['Valorización después', number_format($result->valueAfter, 2)],
            ['Delta de valorización', number_format($result->valueDelta(), 2)],
        ]);

        foreach ($result->archivedBackups as $table => $archive) {
            $this->line("  Respaldo previo archivado: {$table} → {$archive}");
        }

        $this->info(sprintf(
            '  VERIFICACIÓN OK: %s comprobación(es) contra el archivo (kardex al corte = J, '
            . 'kardex hoy = J + A, físico = J + A) más la revisión global de saldos negativos.',
            number_format($result->checksRun),
        ));
    }
}
