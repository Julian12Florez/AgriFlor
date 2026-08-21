<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CALCIO MICRONIZADO (`product_code = '1135'`): la unidad base del catálogo dice
 * `kg` y TODOS sus datos están en litros. Es un error de captura en la ficha del
 * producto, no un error en las cantidades.
 *
 * EVIDENCIA (medida sobre una copia de producción, 21-ago-2026):
 *
 *   1. Los 5 lotes de `inventory` del producto están en `L`:
 *        REC-a25e3f7f-1  100.00 L · REC-a219dd4d-1  280.00 L
 *        REC-a2549f8f-1   40.00 L · REC-a254a06d-1  140.00 L
 *        REC-a25e3ec3-1  120.00 L
 *      `SELECT DISTINCT unit` sobre esos lotes devuelve una sola fila: `L`.
 *
 *   2. Los 16 `inventory_movements` del producto están en `L`:
 *        SELECT DISTINCT unit FROM inventory_movements m
 *        JOIN products p ON p.id = m.product_id WHERE p.product_code = '1135';
 *      → una sola fila: `L`.
 *
 *   3. El archivo del cliente (`INVENTARIO JULIO FINAL.xlsx`, hoja INVENTARIOS)
 *      trae el producto con "Unidad Medida" = LITRO.
 *
 * O sea: catálogo `kg` contra recepciones, kardex, stock y archivo del cliente
 * en `L`. La única fuente discordante es la etiqueta del catálogo.
 *
 * POR QUÉ IMPORTA: el re-baseline de julio (`inventario:rebaseline`) aplica la
 * regla dura "NUNCA se convierten unidades" (reglas v3, punto 1). Con la unidad
 * mal capturada, la fila de este producto levanta la alerta bloqueante
 * `UNIDAD_NO_COINCIDE` y aborta la corrida entera. Corregir la etiqueta deja el
 * pre-flight sin bloqueantes SIN tocar una sola cantidad.
 *
 * ALCANCE: se escribe EXCLUSIVAMENTE `products.base_unit`. No se tocan
 * cantidades, ni lotes, ni movimientos, ni precios: los números ya eran litros,
 * lo único mal era cómo se llamaban.
 *
 * IDEMPOTENTE: el WHERE exige `base_unit = 'kg'`, así que una segunda pasada no
 * afecta ninguna fila. Y si alguien ya lo corrigió a mano, la migración no hace
 * nada en vez de pisar el dato.
 */
return new class extends Migration
{
    private const PRODUCT_CODE = '1135';

    private const WRONG_UNIT = 'kg';

    private const CORRECT_UNIT = 'L';

    public function up(): void
    {
        $affected = DB::table('products')
            ->where('product_code', self::PRODUCT_CODE)
            ->where('base_unit', self::WRONG_UNIT)
            ->update(['base_unit' => self::CORRECT_UNIT]);

        $this->report('up', $affected);
    }

    public function down(): void
    {
        $affected = DB::table('products')
            ->where('product_code', self::PRODUCT_CODE)
            ->where('base_unit', self::CORRECT_UNIT)
            ->update(['base_unit' => self::WRONG_UNIT]);

        $this->report('down', $affected);
    }

    /**
     * Una migración correctiva que no dice cuánto reparó es indistinguible de una
     * que no reparó nada.
     */
    private function report(string $direction, int $affected): void
    {
        \Log::info('fix_calcio_micronizado_base_unit', [
            'direccion' => $direction,
            'product_code' => self::PRODUCT_CODE,
            'filas' => $affected,
        ]);

        if (app()->environment('testing')) {
            return;
        }

        fwrite(STDOUT, sprintf(
            '  CALCIO MICRONIZADO (%s): base_unit %s → %s · filas=%d%s',
            self::PRODUCT_CODE,
            $direction === 'up' ? self::WRONG_UNIT : self::CORRECT_UNIT,
            $direction === 'up' ? self::CORRECT_UNIT : self::WRONG_UNIT,
            $affected,
            PHP_EOL,
        ));
    }
};
