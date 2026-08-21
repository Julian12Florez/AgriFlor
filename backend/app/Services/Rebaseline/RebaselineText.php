<?php

namespace App\Services\Rebaseline;

use Illuminate\Support\Str;

/**
 * Normalización de texto para el re-baseline de inventario.
 *
 * Los Excel del cliente traen los nombres con acentos, mayúsculas irregulares,
 * espacios finales ("VILLA ", "INVENTARIO FINAL ") y dobles espacios. La base de
 * datos los guarda con acentos y capitalización de título ("Alquería"). Todo el
 * casado (encabezados, ubicaciones, unidades) se hace sobre la forma normalizada
 * para que esas diferencias cosméticas no rompan el match.
 */
final class RebaselineText
{
    /**
     * Etiquetas de unidad del archivo → símbolo de `products.base_unit`.
     *
     * Las llaves están normalizadas. Conviven "KILOGRAMOS" y "KILOS" en el mismo
     * archivo: ambas son kg.
     */
    private const UNIT_SYMBOLS = [
        'LITRO' => 'L',
        'LITROS' => 'L',
        'KILOGRAMO' => 'kg',
        'KILOGRAMOS' => 'kg',
        'KILO' => 'kg',
        'KILOS' => 'kg',
        'GRAMO' => 'g',
        'GRAMOS' => 'g',
        'MILILITRO' => 'mL',
        'MILILITROS' => 'mL',
        'CENTIMETRO' => 'cm',
        'CENTIMETROS' => 'cm',
        'UNIDAD' => 'unidades',
        'UNIDADES' => 'unidades',
    ];

    /**
     * Mayúsculas, sin acentos, sin espacios repetidos y sin espacios en los bordes.
     */
    public static function normalize(?string $value): string
    {
        $ascii = Str::ascii((string) $value);
        $collapsed = preg_replace('/\s+/u', ' ', $ascii) ?? '';

        return mb_strtoupper(trim($collapsed));
    }

    /**
     * Traduce la etiqueta de unidad del archivo al símbolo usado por el sistema.
     * Devuelve null si la etiqueta está vacía o no se reconoce.
     */
    public static function unitSymbol(?string $label): ?string
    {
        return self::UNIT_SYMBOLS[self::normalize($label)] ?? null;
    }

    /**
     * Convierte el valor de la celda de código a la misma forma que
     * `products.product_code`: sin el ".0" que Excel agrega a los códigos leídos
     * como número y sin NINGÚN espacio.
     *
     * Se eliminan todos los espacios, no solo los de los bordes: el archivo trae
     * los códigos 1584 y 1137 con un espacio duro (U+00A0) que `trim()` no toca
     * y que los dejaría sin casar con `products.product_code`.
     */
    public static function productCode(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return (string) preg_replace('/[\s\x{00A0}]+/u', '', (string) $value);
    }
}
