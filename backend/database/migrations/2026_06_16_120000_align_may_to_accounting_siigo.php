<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cierre de mayo — ALINEAR A CONTABILIDAD (Siigo).
 *
 * El contador comparó Siigo vs Agriflor (archivo Comparacion_Contab_vs_Agriflor_Mayo)
 * y detectó: (a) Agriflor no separó el SALDO INICIAL de meses anteriores —lo cargó como
 * entradas de mayo, inflando "Compras"— y (b) 4 productos cuyo Inventario Final difería
 * del de Siigo. El cliente confirmó que CONTABILIDAD ES LA FUENTE CORRECTA.
 *
 * Esta migración deja el Inventario Mensual de mayo = Siigo, por producto:
 *  1. Re-fecha los movimientos "Ajuste inicial - Inventario final (importación)" de la
 *     bodega de MAYO → 30-ABR (conserva la hora). Así el saldo de arranque pasa a ser
 *     el "Inv. Inicial" de mayo y deja de inflar "Compras". NO cambia el Inv. Final.
 *  2. Ajusta el Inv. Inicial (neto ≤ 30-abr) exactamente al valor de Siigo (ajuste 30-abr).
 *  3. Ajusta el Inv. Final (neto ≤ 31-may) exactamente al valor de Siigo (ajuste 28-may).
 *  4. Para los productos cuyo Final cambió y sin actividad en junio, corrige el stock real
 *     (tabla inventory) al final de Siigo.
 *
 * Idempotente (deltas en vivo) y reversible (down() restaura fechas, borra los ajustes y
 * restaura el inventario desde los respaldos).
 */
return new class extends Migration
{
    public function up(): void
    {
        // code => [inv_inicial_siigo, inv_final_siigo]
        $targets = [
            '0141'=>[3.5,3.5], '1000'=>[0.82,0.82], '1003'=>[68.21,68.21], '1005'=>[1.2,1.2], '1009'=>[123.18,35.98], '1012'=>[0.79,0.79],
            '1015'=>[19.53,19.53], '1017'=>[0.2,0.2], '1019'=>[160.5,160.5], '1021'=>[46.53,46.53], '1023'=>[8.4,8.4], '1029'=>[386.55,382.55],
            '1032'=>[19.84,19.84], '1034'=>[60.0,60.0], '1035'=>[18.8,18.8], '1037'=>[1.8,1.8], '1038'=>[0.54,0.54], '1040'=>[300.0,300.0],
            '1042'=>[356.0,599.9], '1047'=>[14.0,105.0], '1048'=>[50250.0,50250.0], '1051'=>[0.96,0.79], '1060'=>[2.07,2.07], '1065'=>[1.59,1.59],
            '1066'=>[0.5,56.3], '1067'=>[12.49,12.49], '1071'=>[6.0,6.0], '1075'=>[3.5,3.5], '1080'=>[27.86,27.86], '1081'=>[1990.0,1920.0],
            '1082'=>[12879.0,11711.0], '1083'=>[15.69,15.69], '1087'=>[8.0,3.7], '1088'=>[22.78,22.53], '1097'=>[50.5,20.04], '1098'=>[496.38,383.0],
            '1114'=>[3.0,3.0], '1125'=>[0.0,1850.0], '1132'=>[0.0,8.0], '1153'=>[12312.5,12312.5], '1155'=>[15.0,12.75], '1184'=>[59.8,3300.0],
            '1223'=>[7.12,7.12], '1229'=>[29.7,29.7], '1241'=>[20162.0,18075.0], '1293'=>[2.2,2.2], '1298'=>[2535.0,1785.0], '1308'=>[21.15,21.15],
            '1335'=>[40.0,40.0], '1379'=>[0.11,0.11], '1390'=>[200.0,127.9], '1399'=>[110.0,480.0], '1400'=>[105.0,105.0], '1401'=>[11.0,11.0],
            '1438'=>[600.0,600.0], '1503'=>[12.0,12.0], '1509'=>[4892.0,4792.0], '1511'=>[7.0,7.0], '1512'=>[20.0,0.0], '1513'=>[1.78,1.78],
            '1514'=>[3.0,3.0], '1521'=>[2625.0,2575.0], '1523'=>[39.5,39.5], '1537'=>[4852.5,34550.0], '1538'=>[13.0,13.0], '1540'=>[345.05,239.05],
            '1574'=>[0.7,0.7], '1589'=>[43.7,47.5], '1592'=>[248686.0,295501.0], '1596'=>[1150.0,1150.0], '1676'=>[3295.0,3295.0], '1679'=>[6549.4,6549.4],
            '1750'=>[11.0,1.1], '1758'=>[3604.3,3604.3], '1759'=>[24.6,24.6], '1894'=>[3.0,3.0], '1899'=>[21.0,21.0], '1959'=>[2028.2,1878.2],
            '1960'=>[161.6,180.9], '1961'=>[11.0,11.0], '2002'=>[21.0,21.0], '2013'=>[29.5,30.5], '2019'=>[12387.0,12387.0], '2020'=>[500.0,250.0],
            '2029'=>[275.0,275.0], '2030'=>[72576.0,182976.0], '2031'=>[39.38,34.38], '2035'=>[7.78,7.78], '2050'=>[16650.05,37825.05], '2051'=>[168.0,65.0],
            '2078'=>[100.65,100.65], '2079'=>[3.88,3.88], '2084'=>[3.83,3.83], '2101'=>[6.0,6.0], '2125'=>[4905.0,4880.0], '2131'=>[1.8,1.8],
            '2134'=>[114.3,123.8], '2151'=>[16144.0,16144.0], '2153'=>[1.9,1.9], '2160'=>[250.0,250.0], '2161'=>[2.89,2.89], '2167'=>[10.0,10.0],
            '2174'=>[21.0,21.0], '2187'=>[100.0,100.0], '2222'=>[10.0,10.0], '2224'=>[0.5,0.5], '2225'=>[28.68,28.68], '2284'=>[25.7,34025.7],
            '2285'=>[108.91,108.91], '2295'=>[11.0,0.0], '2346'=>[50.0,50.0], '2413'=>[146.8,129.0], '2415'=>[0.89,0.89], '2433'=>[27.9,27.9],
            '2434'=>[3.7,0.0], '2440'=>[29386.0,29386.0], '2451'=>[5.85,5.85], '2452'=>[1.3,233.6], '2454'=>[24.0,24.0], '2456'=>[5.57,5.57],
            '2459'=>[43.3,116.25], '2480'=>[1050.0,1050.0], '2482'=>[33.0,33.0], '2541'=>[73.6,0.0], '2542'=>[2.98,2.98], '2543'=>[3.0,3.6],
            '2548'=>[2249.0,2261.9], '2550'=>[24070.0,25570.0], '2554'=>[26.7,26.7], '2555'=>[7.1,7.1], '2570'=>[146.5,146.5], '2595'=>[9615.0,11451.0],
            '2660'=>[1.19,1.19], '2717'=>[298.0,265.23], '2734'=>[2.0,2.0], '2780'=>[22315.0,22315.0], '2781'=>[2.0,2.0], '2782'=>[0.38,0.38],
            '2806'=>[0.6,0.6], '2822'=>[310.0,251.1], '2823'=>[25.0,27.0], '2827'=>[575.0,575.0], '2848'=>[1.33,1.33], '2859'=>[88.88,88.88],
            '2861'=>[410.0,2370.0], '2866'=>[1.9,1.9], '2867'=>[126.0,126.0], '2875'=>[6.0,6.0], '2891'=>[40.6,40.6], '2892'=>[2.02,2.02],
            '2935'=>[5000.0,2500.0], '2939'=>[960.0,960.0], '2942'=>[8.0,8.0], '2943'=>[87.75,70.35], '2946'=>[44.53,45.53], '2968'=>[3.5,3.5],
            '2969'=>[0.78,0.78], '2970'=>[75.65,31.25], '2971'=>[7.95,7.95], '2973'=>[44.4,122.51], '2975'=>[443.3,443.3], '2976'=>[158.0,158.0],
            '3078'=>[605.9,535.2], '3092'=>[91.0,91.0], '3093'=>[19.0,19.0], '3094'=>[1000.0,1000.0], '3143'=>[0.0,126.0], '3153'=>[1.0,1.0],
            '3156'=>[100.08,100.08], '3157'=>[38.0,38.0], '3215'=>[7.0,7.0], '3286'=>[16.0,16.0], '3295'=>[3.0,3.0], '3296'=>[54.5,54.5],
            '3391'=>[9.05,9.05], '3640'=>[1.0,1.0], '3661'=>[216.0,204.0], '4300'=>[24.0,24.0], '4301'=>[11.1,11.1], '4302'=>[24.07,24.07],
            '4390'=>[3.17,3.17], '4484'=>[6.2,6.2], '4599'=>[0.8,0.8], '4658'=>[41.0,38.0], '4774'=>[2.8,2.8], '4775'=>[0.96,0.96],
            '4912'=>[1.72,1.72], '5086'=>[90.0,90.0], '5107'=>[28.55,28.39], '5278'=>[5.1,5.1], '5424'=>[94.0,94.0], '5809'=>[0.3,0.3],
            '6247'=>[3.58,3.58], '6418'=>[14.0,14.0], '6473'=>[7.45,7.45], '6475'=>[18.0,18.0], '6477'=>[46.0,46.0], '6479'=>[72.0,72.0],
            '6613'=>[9.0,9.0], '6651'=>[159.7,162.1], '6677'=>[12.54,12.54], '7678'=>[6250.0,4580.0], '7819'=>[17.7,17.7], '7912'=>[40.28,40.28],
            '8050'=>[6.19,6.19], '8217'=>[274.2,274.2], '8536'=>[13.0,13.0], '8537'=>[60.0,40.0], '8538'=>[13.5,13.5], '8541'=>[1.5,28.7],
            '8748'=>[58.05,58.05], '8749'=>[38.8,38.8], '9596'=>[1.0,1.0], '9712'=>[10.0,10.0], '9713'=>[17.0,17.0], '9715'=>[13.2,13.2],
            '9716'=>[3990.0,3990.0], '9717'=>[4.1,4.1], '9945'=>[8.0,8.0],
        ];

        $bodega = DB::table('locations')->where('name', 'BODEGA PRINCIPAL')->value('id');
        $admin = DB::table('users')->where('email', 'admin@agriflor.com')->value('id');
        if (!$bodega || !$admin) {
            return;
        }
        $aprDate = '2026-04-30';
        $mayEnd = '2026-05-31 23:59:59';
        $corrDateApr = '2026-04-30 12:00:00';
        $corrDateMay = '2026-05-28 12:00:00';
        $OBS_INI = 'Saldo inicial contable (Siigo) - carga abril';
        $OBS_FIN = 'Ajuste a cierre contable mayo (Siigo)';

        // ---- 0) Respaldo y re-fecha de "Ajuste inicial" de mayo -> 30-abr (solo bodega) ----
        DB::statement("CREATE TABLE IF NOT EXISTS inventory_movements_ajuste_backup (
            movement_id CHAR(36) PRIMARY KEY, original_created_at TIMESTAMP NULL,
            backed_up_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP)");
        DB::statement("INSERT IGNORE INTO inventory_movements_ajuste_backup (movement_id, original_created_at)
            SELECT id, created_at FROM inventory_movements
            WHERE location_id = ? AND type='entry' AND related_document_type IS NULL
              AND observations LIKE 'Ajuste inicial%'
              AND created_at BETWEEN '2026-05-01 00:00:00' AND '2026-05-31 23:59:59'", [$bodega]);
        DB::statement("UPDATE inventory_movements
            SET created_at = TIMESTAMP(?, TIME(created_at))
            WHERE location_id = ? AND type='entry' AND related_document_type IS NULL
              AND observations LIKE 'Ajuste inicial%'
              AND created_at BETWEEN '2026-05-01 00:00:00' AND '2026-05-31 23:59:59'", [$aprDate, $bodega]);

        // Respaldo de inventory para reversibilidad (solo se llenará al corregir stock).
        DB::statement('CREATE TABLE IF NOT EXISTS inventory_contab_backup LIKE inventory');

        foreach ($targets as $code => [$ini, $fin]) {
            $product = DB::table('products')->where('product_code', (string) $code)->first();
            if (!$product) {
                // El contador puede traer ceros a la izquierda (p.ej. '0141' = '141').
                $alt = ltrim((string) $code, '0');
                if ($alt !== '' && $alt !== (string) $code) {
                    $product = DB::table('products')->where('product_code', $alt)->first();
                }
            }
            if (!$product) {
                continue;
            }
            $brand = $product->brand_id;
            $unit = $product->base_unit;

            $netApr = (float) (DB::table('inventory_movements')
                ->where('product_id', $product->id)->where('location_id', $bodega)
                ->where('created_at', '<=', '2026-04-30 23:59:59')
                ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE 0 END) - SUM(CASE WHEN type IN ('exit','transfer','application') THEN quantity ELSE 0 END) as s")
                ->value('s') ?? 0);
            $netMay = (float) (DB::table('inventory_movements')
                ->where('product_id', $product->id)->where('location_id', $bodega)
                ->where('created_at', '<=', $mayEnd)
                ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE 0 END) - SUM(CASE WHEN type IN ('exit','transfer','application') THEN quantity ELSE 0 END) as s")
                ->value('s') ?? 0);
            $finalChanged = abs($netMay - $fin) > 0.5;

            // 1) Inicial (≤30-abr) = Siigo
            $gapIni = round($ini - $netApr, 2);
            if (abs($gapIni) > 0.01) {
                $this->insertMov($gapIni, $product->id, $brand, $bodega, $unit, $admin, $OBS_INI, $corrDateApr);
            }

            // 2) Final (≤31-may) = Siigo (recalcula tras el ajuste de abril)
            $netMay2 = (float) (DB::table('inventory_movements')
                ->where('product_id', $product->id)->where('location_id', $bodega)
                ->where('created_at', '<=', $mayEnd)
                ->selectRaw("SUM(CASE WHEN type='entry' THEN quantity ELSE 0 END) - SUM(CASE WHEN type IN ('exit','transfer','application') THEN quantity ELSE 0 END) as s")
                ->value('s') ?? 0);
            $gapFin = round($fin - $netMay2, 2);
            if (abs($gapFin) > 0.01) {
                $this->insertMov($gapFin, $product->id, $brand, $bodega, $unit, $admin, $OBS_FIN, $corrDateMay);
            }

            // 3) Stock real (solo si el final cambió y sin actividad en junio)
            if ($finalChanged) {
                $hasJune = DB::table('inventory_movements')
                    ->where('product_id', $product->id)->where('location_id', $bodega)
                    ->where('created_at', '>=', '2026-06-01 00:00:00')->exists();
                if (!$hasJune) {
                    DB::statement('INSERT IGNORE INTO inventory_contab_backup SELECT * FROM inventory WHERE product_id = ? AND location_id = ?', [$product->id, $bodega]);
                    DB::table('inventory')->where('product_id', $product->id)->where('location_id', $bodega)->delete();
                    if ($fin > 0) {
                        DB::table('inventory')->insert([
                            'id' => (string) Str::uuid(), 'product_id' => $product->id, 'brand_id' => $brand,
                            'location_id' => $bodega, 'batch_number' => 'CORR-CONTAB-MAY', 'quantity' => $fin,
                            'unit' => $unit, 'status' => 'good', 'created_at' => $corrDateMay, 'updated_at' => $corrDateMay,
                        ]);
                    }
                }
            }
        }
    }

    private function insertMov($gap, $productId, $brand, $bodega, $unit, $admin, $obs, $date): void
    {
        DB::table('inventory_movements')->insert([
            'id' => (string) Str::uuid(),
            'type' => $gap > 0 ? 'entry' : 'exit',
            'product_id' => $productId,
            'brand_id' => $brand,
            'location_id' => $bodega,
            'quantity' => abs($gap),
            'unit' => $unit,
            'unit_price' => 0,
            'total_price' => 0,
            'responsible_user' => $admin,
            'observations' => $obs,
            'created_at' => $date,
        ]);
    }

    public function down(): void
    {
        DB::table('inventory_movements')->whereIn('observations', [
            'Saldo inicial contable (Siigo) - carga abril',
            'Ajuste a cierre contable mayo (Siigo)',
        ])->delete();

        // Restaurar fechas de los "Ajuste inicial" re-fechados.
        if (DB::getSchemaBuilder()->hasTable('inventory_movements_ajuste_backup')) {
            DB::statement("UPDATE inventory_movements im
                JOIN inventory_movements_ajuste_backup b ON b.movement_id = im.id
                SET im.created_at = b.original_created_at");
            DB::statement('DELETE FROM inventory_movements_ajuste_backup');
        }
        // Restaurar inventario.
        if (DB::getSchemaBuilder()->hasTable('inventory_contab_backup')) {
            DB::table('inventory')->where('batch_number', 'CORR-CONTAB-MAY')->delete();
            DB::statement('INSERT IGNORE INTO inventory SELECT * FROM inventory_contab_backup');
            DB::statement('DELETE FROM inventory_contab_backup');
        }
    }
};
