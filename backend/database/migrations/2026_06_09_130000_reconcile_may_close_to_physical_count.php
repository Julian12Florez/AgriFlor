<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Location;
use App\Models\User;
use App\Models\InventoryMovement;

/**
 * Cierre de mayo (una sola vez): cuadra el Inventario Mensual de mayo al CONTEO
 * FÍSICO del cliente (Excel "SUPER conteo final 28 de mayo"), para los productos
 * que aún diferían DESPUÉS de re-fechar los movimientos (errores reales de captura:
 * cantidades mal digitadas, salidas/remanentes no registrados, saldo inicial errado).
 *
 * Por cada producto con valor objetivo:
 *  - Calcula el cierre de mayo actual (neto de movimientos en bodega <= 31-may) y crea,
 *    si difiere, un MOVIMIENTO de corrección fechado 28-may (entry/exit) por la diferencia,
 *    etiquetado como conciliación a conteo físico → el reporte de mayo queda = Excel.
 *  - Si el producto NO tiene actividad en junio, corrige además el stock real (tabla
 *    inventory) al valor del conteo, manteniendo consistencia movimientos↔inventario.
 *
 * Idempotente: los deltas se calculan en vivo (al re-ejecutar, la diferencia es 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        $targets = [
            '1047' => 105.0, '1066' => 56.3, '1081' => 1770.0, '1082' => 11777.0, '1097' => 19.54,
            '1098' => 376.0, '1124' => 0.0, '1125' => 1850.0, '1155' => 12.75, '1184' => 3300.0,
            '1241' => 18075.0, '1399' => 480.0, '1521' => 2575.0, '1537' => 34145.5, '1540' => 239.05,
            '1679' => 194476.0, '2020' => 250.0, '2050' => 37250.05, '2051' => 50.0, '2284' => 34025.7,
            '2295' => 0.0, '2452' => 228.8, '2459' => 116.25, '2861' => 2010.0, '2935' => 5000.0,
            '2946' => 45.53, '5107' => 28.39, '8541' => 28.7,
        ];

        $bodega = Location::where('name', 'BODEGA PRINCIPAL')->first();
        $admin = User::where('email', 'admin@agriflor.com')->first();
        if (!$bodega || !$admin) {
            return; // entorno sin estos datos: no-op
        }
        $mayEnd = '2026-05-31 23:59:59';
        $corrDate = '2026-05-28 12:00:00';

        foreach ($targets as $code => $target) {
            $product = Product::where('product_code', (string) $code)->first();
            if (!$product) {
                continue;
            }

            // Cierre de mayo actual en bodega (neto de movimientos <= 31-may)
            $cierre = (float) (InventoryMovement::where('product_id', $product->id)
                ->where('location_id', $bodega->id)
                ->where('created_at', '<=', $mayEnd)
                ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE 0 END) - SUM(CASE WHEN type IN ('exit','application') THEN quantity ELSE 0 END) as s")
                ->value('s') ?? 0);

            $gap = round($target - $cierre, 2);
            if (abs($gap) > 0.01) {
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
                    'observations' => 'Corrección de cierre mayo - conciliación con conteo físico (SUPER 28-may)',
                    'created_at' => $corrDate,
                ]);
            }

            // Si NO hay actividad en junio, corrige también el stock real (consistencia)
            $hasJune = InventoryMovement::where('product_id', $product->id)
                ->where('location_id', $bodega->id)
                ->where('created_at', '>=', '2026-06-01 00:00:00')
                ->exists();
            if (!$hasJune) {
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
                        'batch_number' => 'CORR-CONTEO-MAYO',
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
        // Corrección de cierre: elimina solo los movimientos de conciliación creados.
        DB::table('inventory_movements')
            ->where('observations', 'Corrección de cierre mayo - conciliación con conteo físico (SUPER 28-may)')
            ->delete();
        DB::table('inventory')->where('batch_number', 'CORR-CONTEO-MAYO')->delete();
    }
};
