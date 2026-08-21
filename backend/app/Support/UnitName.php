<?php

namespace App\Support;

/**
 * Única fuente del nombre largo de una unidad de medida en los PDF.
 *
 * En la base las unidades se guardan abreviadas (`L`, `kg`, `g`, `mL`, `cm`,
 * `unidades`) porque así las validan `products.base_unit`,
 * `packaging_units.base_unit` y `output_products.unit` contra
 * `base_units.symbol`. En los documentos impresos el cliente quiere el nombre
 * completo y en singular: «Litro», «Kilogramo», «Gramo», «Mililitro»,
 * «Centímetro», «Unidad».
 *
 * Se resuelve aquí y no en cada plantilla Blade por tres razones:
 *
 *  - Hay dos consumidores con formas distintas: la remisión recibe un array ya
 *    armado por `ProductOutputController::exportRemisionPdf()`, mientras que la
 *    orden de compra recibe modelos Eloquent y arma la unidad dentro de la
 *    vista. Un método privado del controlador no serviría para la segunda.
 *  - Las cuatro plantillas de remisión siguen siendo tontas: imprimen
 *    `$item['unidad']` sin saber de mapeos, igual que hoy.
 *  - Es el mismo criterio que ya sigue `CompanyInfo` para el membrete de estos
 *    mismos PDF: un archivo en `App\Support` como fuente única.
 *
 * No se leen los nombres de la tabla `base_units` a propósito: ahí están en
 * plural y sin tildes («Litros», «Centimetros»), que no es lo que se pidió, y
 * obligaría a una consulta extra por documento.
 */
class UnitName
{
    /**
     * Claves ya normalizadas por `normalize()`: minúsculas, sin tildes y sin
     * signos de puntuación. Cubre las abreviaturas reales de la base y las
     * variantes que se escriben a mano (`LT`, `KILOGRAMOS`, `UND`, `un.`…).
     */
    private const MAP = [
        // Volumen
        'l' => 'Litro',
        'lt' => 'Litro',
        'lts' => 'Litro',
        'ltr' => 'Litro',
        'ltrs' => 'Litro',
        'litro' => 'Litro',
        'litros' => 'Litro',
        'ml' => 'Mililitro',
        'mls' => 'Mililitro',
        'mililitro' => 'Mililitro',
        'mililitros' => 'Mililitro',
        'gal' => 'Galón',
        'gl' => 'Galón',
        'galon' => 'Galón',
        'galones' => 'Galón',

        // Masa
        'kg' => 'Kilogramo',
        'kgs' => 'Kilogramo',
        'kilo' => 'Kilogramo',
        'kilos' => 'Kilogramo',
        'kilogramo' => 'Kilogramo',
        'kilogramos' => 'Kilogramo',
        'g' => 'Gramo',
        'gr' => 'Gramo',
        'grs' => 'Gramo',
        'grm' => 'Gramo',
        'grms' => 'Gramo',
        'gramo' => 'Gramo',
        'gramos' => 'Gramo',
        'lb' => 'Libra',
        'lbs' => 'Libra',
        'libra' => 'Libra',
        'libras' => 'Libra',
        'ton' => 'Tonelada',
        'tons' => 'Tonelada',
        'tonelada' => 'Tonelada',
        'toneladas' => 'Tonelada',

        // Longitud
        'cm' => 'Centímetro',
        'cms' => 'Centímetro',
        'centimetro' => 'Centímetro',
        'centimetros' => 'Centímetro',
        'mm' => 'Milímetro',
        'mms' => 'Milímetro',
        'milimetro' => 'Milímetro',
        'milimetros' => 'Milímetro',
        'm' => 'Metro',
        'mt' => 'Metro',
        'mts' => 'Metro',
        'metro' => 'Metro',
        'metros' => 'Metro',

        // Conteo
        'u' => 'Unidad',
        'un' => 'Unidad',
        'und' => 'Unidad',
        'unds' => 'Unidad',
        'ud' => 'Unidad',
        'uds' => 'Unidad',
        'unid' => 'Unidad',
        'unids' => 'Unidad',
        'unidad' => 'Unidad',
        'unidades' => 'Unidad',
    ];

    /**
     * Nombre completo y en singular de la unidad.
     *
     * Devuelve `null` sólo si no había unidad, para que la plantilla siga
     * decidiendo su propio marcador (`{{ $item['unidad'] ?? '-' }}`). Si la
     * unidad existe pero no está mapeada se devuelve tal cual vino: en un
     * documento de despacho es peor imprimir un guion que una abreviatura.
     */
    public static function full(?string $unit): ?string
    {
        $raw = trim((string) $unit);

        if ($raw === '') {
            return null;
        }

        return self::MAP[self::normalize($raw)] ?? $raw;
    }

    /**
     * Minúsculas, sin tildes y sin nada que no sea letra o dígito, para que
     * `mL`, `ML`, `ml.` y `Mililitros` caigan todos en la misma clave.
     *
     * Se usa `mb_strtolower` antes de quitar tildes porque `Á` y `á` deben
     * colapsar en la misma letra.
     */
    private static function normalize(string $value): string
    {
        $lower = mb_strtolower($value, 'UTF-8');

        $withoutAccents = strtr($lower, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]/u', '', $withoutAccents) ?? '';
    }
}
