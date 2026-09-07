<?php

namespace App\Console\Commands;

use App\Services\FarmBackfill\FarmBackfillApplier;
use App\Services\FarmBackfill\FarmBackfillPlan;
use App\Services\FarmBackfill\FarmBackfillPlanner;
use App\Services\FarmBackfill\FarmBackfillResult;
use App\Services\FarmBackfill\PlannedMovement;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Reparación de las entradas que nunca se escribieron en las fincas.
 *
 * Entre el 21-ago-2026 (commit 0ea931b, que metió 'technical_order' en
 * OutputType::DIRECT_CONSUMPTION_CODES) y la corrección de sep-2026, cada
 * despacho a finca descargó la bodega sin acreditar el destino. Quedaron 184
 * movimientos `exit` sin contrapartida en 16 fincas. Este comando escribe las
 * entradas que faltan con la FECHA ORIGINAL de cada salida y netea, con su
 * contrapartida, lo que la finca ya devolvió por la vía de las compras
 * ficticias al proveedor de remanentes.
 *
 * MODOS
 * -----
 * `--dry-run` no escribe una sola fila: imprime el informe completo (por finca,
 * producto, cantidad, unidad y fecha, con totales POR UNIDAD y por mes) que se
 * revisa con bodega antes de autorizar.
 *
 * Sin `--dry-run` se ejecuta la corrida REAL, que además:
 *   · aborta si hay UN solo bloqueo (todo-o-nada),
 *   · aborta si ya hay marcas de una corrida previa (salvo `--force`),
 *   · pide confirmación por consola (salvo `--yes`),
 *   · respalda `inventory` de las fincas afectadas ANTES de tocar nada,
 *   · recalcula el plan DENTRO de la transacción y verifica el resultado.
 *
 * `--revertir` deshace una corrida: borra los movimientos marcados y repone el
 * `inventory` de las fincas desde el respaldo.
 *
 * ENSAYO EN SECO CONTRA LA COPIA DE PRODUCCIÓN
 * --------------------------------------------
 *   docker exec agriflor-app php artisan inventario:reponer-entradas-finca --dry-run
 *   docker exec agriflor-app php artisan inventario:reponer-entradas-finca --yes
 *   docker exec agriflor-app php artisan inventory:ledger-audit
 *   docker exec agriflor-app php artisan inventario:reponer-entradas-finca --revertir
 *
 * Las totales NUNCA se suman entre unidades: 72.185,66 kg, 1.341,80 L, 11.400 g
 * y 11.000 cm son cuatro cifras, no una de 95.927 (ver
 * DIAGNOSTICO_INVENTARIO_20260907.md, §2.A).
 */
class BackfillFarmEntries extends Command
{
    protected $signature = 'inventario:reponer-entradas-finca
        {--corte=2026-07-31 : Corte duro. No se escribe NADA con fecha igual o anterior}
        {--tipo=* : Códigos de output_type a reparar (por defecto technical_order)}
        {--proveedor-remanente= : Proveedor de las compras ficticias de devolución}
        {--origen-remanente=* : Asigna finca a una devolución sin origen: PUR-XXXX:<finca> o PUR-XXXX:OMITIR}
        {--dry-run : Simula y NO escribe nada en la base de datos}
        {--detalle=40 : Cuántas líneas de detalle imprimir (0 = todas)}
        {--yes : Aplica sin pedir confirmación interactiva}
        {--force : Permite re-ejecutar aunque existan marcas de una corrida previa}
        {--revertir : Deshace la última corrida usando el respaldo}';

    protected $description = 'Repone las entradas de kardex y los lotes que nunca se escribieron en las fincas, con la fecha original de cada salida y neteando las devoluciones ya registradas como compras ficticias';

    public function handle(FarmBackfillPlanner $planner, FarmBackfillApplier $applier): int
    {
        try {
            if ($this->option('revertir')) {
                return $this->revert($applier);
            }

            $cutoff = $this->cutoffDate();
            $codes = $this->outputCodes();
            $supplier = $this->returnSupplier();
            $dryRun = (bool) $this->option('dry-run');

            $this->line(
                'Corte duro: ' . $cutoff->toDateString()
                . '  ·  Tipos: ' . implode(', ', $codes)
                . '  ·  Devoluciones: ' . $supplier
                . '  ·  Modo: ' . ($dryRun ? 'SIMULACIÓN' : 'APLICACIÓN REAL')
            );

            $plan = $planner->plan($cutoff, $codes, $supplier, $this->originOverrides());

            $this->reportPlan($plan, $cutoff);

            return $dryRun
                ? $this->simulate($plan)
                : $this->apply($planner, $applier, $plan, $cutoff, $codes, $supplier);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    // ---------------------------------------------------------------- modos

    private function simulate(FarmBackfillPlan $plan): int
    {
        if ($plan->isBlocked()) {
            $this->warn(
                '  La corrida real se detendría por ' . count($plan->blockers)
                . ' bloqueo(s). En --dry-run sólo se reportan.'
            );
        }

        $this->info('SIMULACIÓN: no se escribió ni una fila. Repita sin --dry-run para aplicar.');

        return self::SUCCESS;
    }

    /**
     * Corrida REAL. Toda comprobación capaz de abortar se ejecuta ANTES de la
     * primera escritura; lo que no se puede comprobar de antemano se verifica
     * dentro de la transacción, donde un fallo se revierte solo.
     *
     * @param  array<int, string>  $codes
     */
    private function apply(
        FarmBackfillPlanner $planner,
        FarmBackfillApplier $applier,
        FarmBackfillPlan $plan,
        CarbonImmutable $cutoff,
        array $codes,
        string $supplier,
    ): int {
        $this->assertNotBlocked($plan);
        $this->assertNotAlreadyApplied($planner, (bool) $this->option('force'));

        if ($plan->isEmpty()) {
            $this->info('No hay nada que reponer: todas las salidas a finca tienen su entrada.');

            return self::SUCCESS;
        }

        if (! $this->confirmApplication($plan)) {
            $this->warn('Cancelado por el operador: no se escribió nada.');

            return self::SUCCESS;
        }

        $archived = $applier->prepareBackupTables((bool) $this->option('force'));

        $result = $applier->apply(
            $plan,
            fn () => $planner->plan($cutoff, $codes, $supplier, $this->originOverrides()),
            $cutoff,
            $archived,
        );

        $this->reportResult($result);

        return self::SUCCESS;
    }

    private function revert(FarmBackfillApplier $applier): int
    {
        $this->warn('=== REVERSIÓN ===');
        $this->line('  Base de datos: ' . DB::connection()->getDatabaseName());

        if (! $this->option('yes') && ! $this->confirm('¿Deshacer la reparación y reponer el respaldo?', false)) {
            $this->warn('Cancelado: no se tocó nada.');

            return self::SUCCESS;
        }

        $undone = $applier->revert();

        $this->info(sprintf(
            'Reversión completa: %s movimiento(s) borrados, %s fila(s) de inventory restauradas en %s finca(s). '
            . 'El kardex vuelve a cuadrar con el físico.',
            number_format($undone['movimientos']),
            number_format($undone['filas_restauradas']),
            number_format($undone['fincas']),
        ));

        return self::SUCCESS;
    }

    // ------------------------------------------------------------- opciones

    private function cutoffDate(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) $this->option('corte'))->startOfDay();
        } catch (Throwable) {
            throw new RuntimeException('La fecha de --corte no es válida: ' . $this->option('corte'));
        }
    }

    /** @return array<int, string> */
    private function outputCodes(): array
    {
        $codes = array_values(array_filter((array) $this->option('tipo')));

        if ($codes === []) {
            return FarmBackfillPlanner::DEFAULT_OUTPUT_CODES;
        }

        $unknown = array_diff($codes, DB::table('output_types')->pluck('code')->all());

        if ($unknown !== []) {
            throw new RuntimeException('Códigos de --tipo que no existen en output_types: ' . implode(', ', $unknown));
        }

        return $codes;
    }

    /**
     * Un nombre explícito que no existe es un dedazo y se para: se estaría
     * corriendo sin netear nada por una errata. El nombre por defecto que no
     * existe es sólo una base sin devoluciones ficticias, que es lo normal en
     * una instalación limpia; se avisa y se sigue.
     */
    private function returnSupplier(): string
    {
        $explicit = trim((string) $this->option('proveedor-remanente'));
        $supplier = $explicit !== '' ? $explicit : FarmBackfillPlanner::DEFAULT_RETURN_SUPPLIER;

        if (DB::table('suppliers')->where('name', $supplier)->exists()) {
            return $supplier;
        }

        if ($explicit !== '') {
            throw new RuntimeException(
                'No existe ningún proveedor llamado "' . $explicit . '". Compruebe el nombre: si se corre con '
                . 'uno equivocado, las devoluciones NO se netean y la finca queda contada dos veces.'
            );
        }

        $this->warn(
            '  No existe el proveedor "' . $supplier . '": esta base no tiene devoluciones registradas como '
            . 'compra ficticia, así que no hay nada que netear.'
        );

        return $supplier;
    }

    /**
     * `--origen-remanente=PUR-2026-402555:Uva` o `…:OMITIR`.
     *
     * Existe porque PUR-2026-402555 llegó a producción sin
     * `origin_location_id`: nadie sabe de qué finca vino. Adivinarlo sería
     * exactamente el error que este comando trata de no cometer, así que se
     * exige que alguien lo diga por escrito.
     *
     * @return array<string, ?string> order_number → location_id, o null para omitir
     */
    private function originOverrides(): array
    {
        $overrides = [];

        foreach ((array) $this->option('origen-remanente') as $raw) {
            $parts = explode(':', (string) $raw, 2);

            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new RuntimeException(
                    'Formato inválido en --origen-remanente="' . $raw . '". Use PUR-XXXX:<finca> o PUR-XXXX:OMITIR.'
                );
            }

            [$order, $target] = [trim($parts[0]), trim($parts[1])];

            if (strtoupper($target) === 'OMITIR') {
                $overrides[$order] = null;

                continue;
            }

            $location = DB::table('locations')
                ->where(fn ($query) => $query->where('name', $target)->orWhere('id', $target))
                ->first();

            if ($location === null) {
                throw new RuntimeException('No existe ninguna ubicación llamada o con id "' . $target . '".');
            }

            if ($location->type !== 'farm') {
                throw new RuntimeException(
                    'La ubicación "' . $location->name . '" no es una finca (type=' . $location->type
                    . '): una devolución de remanente sólo puede venir de una finca.'
                );
            }

            $overrides[$order] = (string) $location->id;
        }

        return $overrides;
    }

    // ------------------------------------------------------------- informes

    private function reportPlan(FarmBackfillPlan $plan, CarbonImmutable $cutoff): void
    {
        $this->newLine();
        $this->info('=== ENTRADAS A REPONER EN FINCA ===');
        $this->totalsTable(FarmBackfillPlan::totalsByUnit($plan->entries));

        $this->newLine();
        $this->info('=== POR MES CONTABLE (agosto y septiembre siguen abiertos) ===');
        foreach (FarmBackfillPlan::totalsByMonthAndUnit($plan->entries) as $month => $totals) {
            $this->line('  ' . $month . ':');
            foreach ($totals as $unit => $total) {
                $this->line(sprintf(
                    '      %-6s %10s  en %s movimiento(s)',
                    $unit,
                    number_format($total['cantidad'], 2),
                    number_format($total['movimientos']),
                ));
            }
        }

        $this->newLine();
        $this->info('=== POR FINCA ===');
        $rows = [];
        foreach (FarmBackfillPlan::byFarm($plan->entries) as $farm => $totals) {
            foreach ($totals as $unit => $total) {
                $rows[] = [$farm, $unit, number_format($total['cantidad'], 2), number_format($total['movimientos'])];
            }
        }
        $this->table(['Finca', 'Unidad', 'Cantidad', 'Movimientos'], $rows);

        $this->reportDetail($plan);
        $this->reportNettings($plan);
        $this->reportResiduals($plan);
        $this->reportAlerts($plan, $cutoff);
    }

    private function reportDetail(FarmBackfillPlan $plan): void
    {
        $limit = (int) $this->option('detalle');
        $detail = FarmBackfillPlan::detail($plan->entries);
        $shown = $limit > 0 ? array_slice($detail, 0, $limit) : $detail;

        $this->newLine();
        $this->info('=== DETALLE (finca · producto · cantidad · unidad · fecha · documento) ===');
        $this->table(
            ['Finca', 'Producto', 'Cantidad', 'Unidad', 'Fecha kardex', 'Documento'],
            array_map(fn (array $row) => [
                $row['finca'],
                mb_strimwidth($row['producto'], 0, 32, '…'),
                number_format($row['cantidad'], 2),
                $row['unidad'],
                $row['fecha'],
                $row['documento'],
            ], $shown),
        );

        if (count($shown) < count($detail)) {
            $this->line('  ... y ' . (count($detail) - count($shown))
                . ' línea(s) más (use --detalle=0 para verlas todas).');
        }
    }

    private function reportNettings(FarmBackfillPlan $plan): void
    {
        $this->newLine();
        $this->info('=== NETEO DE LAS DEVOLUCIONES YA REGISTRADAS COMO COMPRA ===');

        if ($plan->nettings === []) {
            $this->line('  No hay devoluciones que netear.');

            return;
        }

        $this->line(
            '  Se escribe la SALIDA que falta en la finca por cada devolución que entró a bodega como compra'
        );
        $this->line(
            '  ficticia. No se resta de la entrada: si entrada y salida de bodega dejaran de ser iguales, la'
        );
        $this->line('  celda "Variación" del informe mensual —la que se concilia contra contabilidad— dejaría de ser 0.');
        $this->newLine();

        $this->table(
            ['Finca', 'Producto', 'Cantidad', 'Unidad', 'Fecha kardex', 'Compra'],
            array_map(fn (PlannedMovement $movement) => [
                $movement->locationName,
                mb_strimwidth($movement->productName, 0, 32, '…'),
                number_format($movement->quantity, 2),
                $movement->unit,
                $movement->movementDate,
                $movement->documentLabel,
            ], $plan->nettings),
        );

        $this->totalsTable(FarmBackfillPlan::totalsByUnit($plan->nettings), 'A descontar de la finca');
    }

    private function reportResiduals(FarmBackfillPlan $plan): void
    {
        if ($plan->residuals === []) {
            return;
        }

        $this->newLine();
        $this->warn('=== DEVOLUCIONES QUE NO SE PUEDEN NETEAR DEL TODO ===');
        $this->line(
            '  La finca devolvió más de lo que su saldo respalda, incluso después de la reposición. Recortar el'
        );
        $this->line(
            '  neteo es deliberado: forzarlo dejaría la finca en negativo. El residuo queda sin netear y la finca'
        );
        $this->line('  queda sobrevalorada en esa cantidad hasta el conteo físico de cierre.');

        $this->table(
            ['Finca', 'Producto', 'Unidad', 'Devuelto', 'Neteado', 'Residuo', 'Compra'],
            array_map(fn (array $row) => [
                $row['finca'],
                mb_strimwidth($row['producto'], 0, 28, '…'),
                $row['unidad'],
                number_format($row['devuelto'], 2),
                number_format($row['neteado'], 2),
                number_format($row['residuo'], 2),
                $row['documento'],
            ], $plan->residuals),
        );
    }

    private function reportAlerts(FarmBackfillPlan $plan, CarbonImmutable $cutoff): void
    {
        $this->newLine();
        $this->info('=== PRE-FLIGHT ===');
        $this->line('  Corte duro respetado: nada con fecha <= ' . $cutoff->toDateString() . '.');

        foreach ($plan->warnings as $warning) {
            $this->warn('  aviso · ' . $warning);
        }

        if ($plan->blockers === []) {
            $this->line('  Sin bloqueos.');

            return;
        }

        foreach ($plan->blockers as $blocker) {
            $this->error('  BLOQUEA · ' . $blocker);
        }
    }

    /**
     * @param  array<string, array{movimientos: int, cantidad: float}>  $totals
     */
    private function totalsTable(array $totals, string $label = 'A reponer'): void
    {
        if ($totals === []) {
            $this->line('  Nada.');

            return;
        }

        $this->table(
            ['Unidad', $label, 'Movimientos'],
            array_map(
                fn (string $unit, array $total) => [
                    $unit,
                    number_format($total['cantidad'], 2),
                    number_format($total['movimientos']),
                ],
                array_keys($totals),
                array_values($totals),
            ),
        );
        $this->line('  Los totales van POR UNIDAD a propósito: kg, L, g y cm no se suman entre sí.');
    }

    private function reportResult(FarmBackfillResult $result): void
    {
        $this->newLine();
        $this->info('=== REPARACIÓN APLICADA ===');
        $this->table(['Concepto', 'Valor'], [
            ['Entradas de kardex creadas', number_format($result->entryMovements)],
            ['Salidas de neteo creadas', number_format($result->nettingMovements)],
            ['Lotes de inventory creados', number_format($result->inventoryRowsCreated)],
            ['Lotes de inventory fusionados', number_format($result->inventoryRowsUpdated)],
            ['Filas de inventory respaldadas', number_format($result->inventoryRowsBackedUp)],
            ['Residuos sin netear', number_format(count($result->applied->residuals))],
        ]);

        foreach ($result->archivedBackups as $table => $archive) {
            $this->line("  Respaldo previo archivado: {$table} → {$archive}");
        }

        $this->info(sprintf(
            '  VERIFICACIÓN OK: %s comprobación(es) — cada entrada cuadra con su salida de bodega, '
            . 'kardex y físico siguen sin un solo par divergente, no hay saldos negativos y la BODEGA NO SE MOVIÓ.',
            number_format($result->checksRun),
        ));
        $this->line('  Para deshacer: php artisan inventario:reponer-entradas-finca --revertir');
    }

    // ------------------------------------------------------------- bloqueos

    private function assertNotBlocked(FarmBackfillPlan $plan): void
    {
        if (! $plan->isBlocked()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'PRE-FLIGHT BLOQUEANTE: %d problema(s) sin resolver. No se escribió NADA. '
            . 'Corrija el origen (ver las líneas "BLOQUEA" de arriba) y repita el --dry-run.',
            count($plan->blockers),
        ));
    }

    private function assertNotAlreadyApplied(FarmBackfillPlanner $planner, bool $force): void
    {
        $marks = $planner->previousRunMarks();

        if ($marks['entradas'] === 0 && $marks['neteos'] === 0) {
            return;
        }

        $detail = sprintf('%d entrada(s) y %d neteo(s) marcados', $marks['entradas'], $marks['neteos']);

        if (! $force) {
            throw new RuntimeException(
                'La reparación YA se aplicó en esta base de datos: ' . $detail . '. No se escribió nada. '
                . 'La corrida es idempotente (no repondría lo ya repuesto), pero se detiene igual para que '
                . 'nadie la ejecute dos veces por descuido. Use --force si de verdad quiere completar lo que falte.'
            );
        }

        $this->warn('  --force: hay una corrida previa (' . $detail . '); sólo se repondrá lo que siga faltando.');
    }

    private function confirmApplication(FarmBackfillPlan $plan): bool
    {
        $connection = config('database.default');

        $this->newLine();
        $this->warn('=== SE VA A ESCRIBIR EN LA BASE DE DATOS ===');
        $this->line('  Base de datos       : ' . DB::connection()->getDatabaseName()
            . ' @ ' . config("database.connections.{$connection}.host"));
        $this->line('  Entradas en finca   : ' . number_format(count($plan->entries)));
        $this->line('  Salidas de neteo    : ' . number_format(count($plan->nettings)));
        $this->line('  Bodega              : NO se toca (se verifica dentro de la transacción)');
        $this->line('  Fechas              : la original de cada salida, nunca hoy');
        $this->line('  Respaldo            : inventory de las fincas afectadas → inventory_farm_backfill_backup');

        if ($this->option('yes')) {
            $this->line('  --yes: se aplica sin confirmación interactiva.');

            return true;
        }

        return $this->confirm('¿Aplicar la reparación con estos números?', false);
    }
}
