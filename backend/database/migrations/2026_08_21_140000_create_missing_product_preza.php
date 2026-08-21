<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea el producto PREZA (código 1107), que falta en el catálogo.
 *
 * El inventario de julio del cliente le asigna 24 L (valorizados en $9.060.000 a
 * $377.500/L), pero el producto nunca se creó en `products`. Sin él, el re-baseline
 * no puede escribir esas existencias y las deja fuera en silencio.
 *
 * Datos tomados de los archivos del cliente:
 *   - INVENTARIO JULIO FINAL.xlsx  → grupo INSECTICIDA, unidad LITRO, saldo 24
 *   - Valoración de inventarios.xlsx → categoría "Acaricidas e insecticidas", $377.500
 *
 * Se sigue el patrón de los demás insecticidas del catálogo (base_unit 'L', iva 0,
 * marca "Sin Marca"). El principio activo queda vacío porque el cliente no lo
 * reporta; se completa desde la pantalla de productos cuando se conozca.
 *
 * Idempotente: si el código ya existe, no hace nada.
 */
return new class extends Migration
{
    private const CODE = '1107';

    public function up(): void
    {
        if (DB::table('products')->where('product_code', self::CODE)->exists()) {
            return;
        }

        $categoryId = DB::table('categories')
            ->where('name', 'like', '%nsecticida%')
            ->value('id');
        $brandId = DB::table('brands')->where('name', 'Sin Marca')->value('id');
        $adminId = DB::table('users')->where('email', 'admin@agriflor.com')->value('id');

        if (!$brandId || !$adminId) {
            return; // entorno sin datos base: no-op
        }

        DB::table('products')->insert([
            'id' => (string) Str::uuid(),
            'product_code' => self::CODE,
            'name' => 'PREZA',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'base_unit' => 'L',
            'iva' => 0,
            'active_ingredient' => '',
            'min_stock' => 0,
            'status' => 'active',
            'created_by' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Solo se borra si nunca se usó, para no romper movimientos ni existencias.
        $id = DB::table('products')->where('product_code', self::CODE)->value('id');
        if (!$id) {
            return;
        }

        $enUso = DB::table('inventory_movements')->where('product_id', $id)->exists()
            || DB::table('inventory')->where('product_id', $id)->exists();

        if (!$enUso) {
            DB::table('products')->where('id', $id)->delete();
        }
    }
};
