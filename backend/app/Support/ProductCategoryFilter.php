<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Filtro "Categoría" de los informes de inventario: Fertilizante, Insecticida,
 * Fungicida... (parámetro `category_id`).
 *
 * Vive en un solo sitio porque lo comparten 9 consultas y 8 descargas. Si cada
 * una lo implementara por su cuenta, la pantalla y su Excel acabarían filtrando
 * distinto — que es justo lo que pasaba con el Inventario Mensual, cuyo filtro
 * era del navegador y la descarga lo ignoraba.
 *
 * Se aplica en el servidor y no en el navegador: varias de estas listas vienen
 * paginadas, y filtrar en pantalla solo filtraría la página que se ve.
 *
 * Sin `$request` explícito lee la petición en curso: las clases de exportación
 * (app/Exports) arman su propia consulta dentro de la misma petición HTTP.
 */
final class ProductCategoryFilter
{
    /** Id de la categoría pedida, o null si no se filtra (ausente o vacío). */
    public static function id(?Request $request = null): ?string
    {
        $id = ($request ?? request())->input('category_id');

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    /**
     * Para consultas que ya están sobre `products` o tienen un join a ella.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function onProducts($query, string $column = 'products.category_id', ?Request $request = null)
    {
        if ($id = self::id($request)) {
            $query->where($column, $id);
        }

        return $query;
    }

    /**
     * Para consultas Eloquent que llegan al producto por una relación
     * (Inventory, InventoryMovement).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function throughRelation($query, string $relation = 'product', ?Request $request = null)
    {
        if ($id = self::id($request)) {
            $query->whereHas($relation, fn ($q) => $q->where('category_id', $id));
        }

        return $query;
    }
}
