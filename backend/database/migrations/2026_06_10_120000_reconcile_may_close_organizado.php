<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Location;
use App\Models\User;
use App\Models\InventoryMovement;

/**
 * Cierre de mayo v2 (una sola vez): el cliente envió una versión "organizada" del
 * conteo físico (INVENTARIO MAYO ... organizado.xlsx) que ajusta 12 productos
 * respecto al archivo anterior ya conciliado. Esta migración lleva el Inventario
 * Mensual de mayo a coincidir con esos nuevos valores.
 *
 * Por cada producto afectado:
 *  - Calcula el cierre de mayo actual (neto de movimientos en bodega <= 31-may) y crea,
 *    si difiere, un MOVIMIENTO de ajuste fechado 28-may (entry/exit) por la diferencia,
 *    etiquetado como conciliación al conteo organizado → el reporte de mayo queda = Excel.
 *  - Si el producto NO tiene actividad en junio, corrige además el stock real (tabla
 *    inventory) al valor del conteo, manteniendo consistencia movimientos↔inventario.
 *    Si tiene actividad en junio (p.ej. ACUAFIN MALATHION), NO se toca el stock real.
 *
 * Idempotente: los deltas se calculan en vivo (al re-ejecutar, la diferencia es 0).
 * Reversible: antes de tocar la tabla inventory se respaldan las filas afectadas en
 * inventory_corr_org_backup; down() elimina los movimientos/inventario de ESTA
 * conciliación y RESTAURA el inventario previo (incluida la conciliación anterior v1).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Respaldo de las filas de inventory que vamos a reemplazar (reversibilidad).
        DB::statement('CREATE TABLE IF NOT EXISTS inventory_corr_org_backup LIKE inventory');
        // Solo los 12 productos que el archivo "organizado" cambió respecto al anterior.
        $targets = [
            '1081' => 1920.0, '1097' => 20.04, '1098' => 383.0, '1521' => 5200.0,
            '1537' => 34550.0, '2030' => 182976.0, '2050' => 37825.05, '2051' => 65.0,
            '2452' => 233.6, '2935' => 2500.0, '2973' => 122.51, '3078' => 535.2,
        ];

        $bodega = Location::where('name', 'BODEGA PRINCIPAL')->first();
        $admin = User::where('email', 'admin@agriflor.com')->first();
        if (!$bodega || !$admin) {
            return; // entorno sin estos datos: no-op
        }
        $mayEnd = '2026-05-31 23:59:59';
        $corrDate = '2026-05-28 12:00:00';
        $obs = 'Ajuste cierre mayo - conciliación con conteo físico organizado (28-may)';

        foreach ($targets as $code => $target) {
            $product = Product::where('product_code', (string) $code)->first();
            if (!$product) {
                continue;
            }

            // Fórmula IDÉNTICA a monthlyReport::final_stock (incluye 'transfer').
            $cierre = (float) (InventoryMovement::where('product_id', $product->id)
                ->where('location_id', $bodega->id)
                ->where('created_at', '<=', $mayEnd)
                ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE 0 END) - SUM(CASE WHEN type IN ('exit','transfer','application') THEN quantity ELSE 0 END) as s")
                ->value('s') ?? 0);

            $gap = round($target - $cierre, 2);
            if (abs($gap) <= 0.01) {
                continue; // ya coincide: nada que hacer (no toca inventario)
            }

            // Insert crudo: created_at debe quedar en mayo (Eloquent lo sobreescribe con now()).
            // La tabla NO tiene columna updated_at. brand_id es NOT NULL.
            DB::table('inventory_movements')->insert([
                'id' => (string) Str::uuid(),
                'type' => $gap > 0 ? 'entry' : 'exit',
                'product_id' => $product->id,
                'brand_id' => $product->brand_id,
                'location_id' => $bodega->id,
                'quantity' => abs($gap),
                'unit' => $product->base_unit,
                'unit_price' => 0,
                'total_price' => 0,
                'responsible_user' => $admin->id,
                'observations' => $obs,
                'created_at' => $corrDate,
            ]);

            // Si NO hay actividad en junio, corrige también el stock real (consistencia).
            $hasJune = InventoryMovement::where('product_id', $product->id)
                ->where('location_id', $bodega->id)
                ->where('created_at', '>=', '2026-06-01 00:00:00')
                ->exists();
            if (!$hasJune) {
                // Respaldar las filas antes de reemplazarlas (restaurables en down()).
                DB::statement(
                    'INSERT IGNORE INTO inventory_corr_org_backup SELECT * FROM inventory WHERE product_id = ? AND location_id = ?',
                    [$product->id, $bodega->id]
                );
                DB::table('inventory')
                    ->where('product_id', $product->id)
                    ->where('location_id', $bodega->id)
                    ->delete();
                if ($target > 0) {
                    DB::table('inventory')->insert([
                        'id' => (string) Str::uuid(),
                        'product_id' => $product->id,
                        'brand_id' => $product->brand_id,
                        'location_id' => $bodega->id,
                        'batch_number' => 'CORR-MAYO-ORG',
                        'quantity' => $target,
                        'unit' => $product->base_unit,
                        'status' => 'good',
                        'created_at' => $corrDate,
                        'updated_at' => $corrDate,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('inventory_movements')
            ->where('observations', 'Ajuste cierre mayo - conciliación con conteo físico organizado (28-may)')
            ->delete();
        // Quitar las filas insertadas por esta migración y restaurar las originales.
        DB::table('inventory')->where('batch_number', 'CORR-MAYO-ORG')->delete();
        if (DB::getSchemaBuilder()->hasTable('inventory_corr_org_backup')) {
            DB::statement('INSERT IGNORE INTO inventory SELECT * FROM inventory_corr_org_backup');
            DB::statement('DELETE FROM inventory_corr_org_backup');
        }
    }
};
