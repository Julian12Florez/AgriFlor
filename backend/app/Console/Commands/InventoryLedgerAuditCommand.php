<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría del libro de inventario (PR-C / E5).
 *
 * Por cada combinación producto + ubicación compara:
 *   - El stock FÍSICO: SUM(inventory.quantity) — lo que hay en lotes y se
 *     puede despachar.
 *   - El saldo CONTABLE derivado del kardex: SUM(entry) − SUM(exit, transfer,
 *     application) en inventory_movements.
 *
 * Y reporta las combinaciones cuya diferencia absoluta supera el umbral.
 *
 * Por qué existe: el cliente reportó que "el disponible no cuadra con el
 * kardex". La causa no es un error de cálculo — son correcciones contables de
 * cierre de mayo que se escribieron directamente en inventory_movements sin
 * crear el lote físico correspondiente. Ese dato NO se repara en este PR: la
 * reparación está bloqueada esperando un conteo físico del cliente. Este
 * comando es la prueba objetiva de "0 divergencias" que debe pasar el día que
 * esos datos se reparen; hasta entonces documenta el estado real.
 *
 * Todas las combinaciones se traen con UNA sola consulta (UNION ALL de dos
 * agregados agrupados por producto + ubicación): no hay N+1 sin importar
 * cuántos productos o ubicaciones existan.
 */
class InventoryLedgerAuditCommand extends Command
{
    /**
     * Umbral de diferencia (en unidad base) a partir del cual una combinación
     * se considera divergente. 0.01 absorbe residuos de punto flotante de los
     * `decimal(10,2)` de inventory/inventory_movements sin ocultar diferencias
     * reales.
     */
    private const THRESHOLD = 0.01;

    protected $signature = 'inventory:ledger-audit
        {--json : Emite el resultado en formato JSON en lugar de tabla}
        {--product= : Filtra por un product_id específico}
        {--location= : Filtra por un location_id específico}';

    protected $description = 'Compara el stock físico (inventory) contra el saldo del kardex (inventory_movements) por producto + ubicación y reporta las divergencias';

    public function handle(): int
    {
        $rows = $this->runAudit();

        $analyzed = $rows->count();
        $divergent = $rows
            ->filter(fn (array $row) => abs($row['difference']) > self::THRESHOLD)
            ->sortByDesc(fn (array $row) => abs($row['difference']))
            ->values();

        if ($this->option('json')) {
            $this->line(json_encode([
                'analyzed' => $analyzed,
                'divergent_count' => $divergent->count(),
                'threshold' => self::THRESHOLD,
                'divergences' => $divergent->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $divergent->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Combinaciones producto + ubicación analizadas: {$analyzed}");
        $this->newLine();

        if ($divergent->isEmpty()) {
            $this->info('Sin divergencias: el stock físico cuadra con el saldo del kardex en todas las combinaciones.');

            return self::SUCCESS;
        }

        $this->warn(
            "Se encontraron {$divergent->count()} combinación(es) con divergencia "
            . '(|diferencia| > ' . self::THRESHOLD . '):'
        );
        $this->newLine();

        $this->table(
            ['Código', 'Producto', 'Ubicación', 'Físico', 'Saldo kardex', 'Diferencia'],
            $divergent->map(fn (array $row) => [
                $row['product_code'] ?? '-',
                $row['product_name'],
                $row['location_name'],
                number_format($row['physical_quantity'], 2),
                number_format($row['ledger_balance'], 2),
                number_format($row['difference'], 2),
            ])->all()
        );

        $this->newLine();
        $this->line('Suma de diferencias: ' . number_format($divergent->sum('difference'), 2));
        $this->newLine();
        $this->comment(
            'Nota: esta divergencia NO se repara con este comando. Corresponde a '
            . 'movimientos contables de cierre sin lote físico; la reparación queda '
            . 'pendiente de un conteo físico del cliente.'
        );

        return self::FAILURE;
    }

    /**
     * Trae, en una sola consulta, el físico y el saldo contable de TODAS las
     * combinaciones producto + ubicación que tengan inventario y/o movimientos.
     *
     * @return Collection<int, array{
     *     product_id: string,
     *     product_code: string|null,
     *     product_name: string,
     *     location_id: string,
     *     location_name: string,
     *     base_unit: string|null,
     *     physical_quantity: float,
     *     ledger_balance: float,
     *     difference: float
     * }>
     */
    private function runAudit(): Collection
    {
        $combined = DB::raw('(
                SELECT product_id, location_id, SUM(quantity) AS physical, 0 AS ledger_balance
                FROM inventory
                GROUP BY product_id, location_id

                UNION ALL

                SELECT product_id, location_id, 0 AS physical,
                    SUM(CASE WHEN type = \'entry\' THEN quantity ELSE 0 END)
                    - SUM(CASE WHEN type IN (\'exit\', \'transfer\', \'application\') THEN quantity ELSE 0 END) AS ledger_balance
                FROM inventory_movements
                GROUP BY product_id, location_id
            ) AS combined');

        $query = DB::table($combined)
            ->leftJoin('products', 'products.id', '=', 'combined.product_id')
            ->leftJoin('locations', 'locations.id', '=', 'combined.location_id')
            ->select([
                'combined.product_id',
                'combined.location_id',
                'products.product_code',
                DB::raw("COALESCE(products.name, 'Producto eliminado') as product_name"),
                DB::raw("COALESCE(locations.name, 'Ubicación eliminada') as location_name"),
                'products.base_unit',
                DB::raw('SUM(combined.physical) as physical_quantity'),
                DB::raw('SUM(combined.ledger_balance) as ledger_balance'),
            ])
            ->groupBy(
                'combined.product_id',
                'combined.location_id',
                'products.product_code',
                'products.name',
                'locations.name',
                'products.base_unit'
            );

        if ($productId = $this->option('product')) {
            $query->where('combined.product_id', $productId);
        }

        if ($locationId = $this->option('location')) {
            $query->where('combined.location_id', $locationId);
        }

        return $query->get()->map(function ($row) {
            $physical = round((float) $row->physical_quantity, 4);
            $ledger = round((float) $row->ledger_balance, 4);

            return [
                'product_id' => $row->product_id,
                'product_code' => $row->product_code,
                'product_name' => $row->product_name,
                'location_id' => $row->location_id,
                'location_name' => $row->location_name,
                'base_unit' => $row->base_unit,
                'physical_quantity' => $physical,
                'ledger_balance' => $ledger,
                'difference' => round($physical - $ledger, 4),
            ];
        });
    }
}
