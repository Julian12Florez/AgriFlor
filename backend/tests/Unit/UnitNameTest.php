<?php

namespace Tests\Unit;

use App\Support\UnitName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Las remisiones y la orden de compra deben imprimir el nombre completo de la
 * unidad ("Litro"), no la abreviatura con la que se guarda ("L").
 */
class UnitNameTest extends TestCase
{
    /**
     * Estas son las abreviaturas que existen de verdad en la base
     * (`output_products.unit`, `products.base_unit`, `packaging_units.base_unit`).
     */
    public static function abreviaturasRealesProvider(): array
    {
        return [
            ['L', 'Litro'],
            ['kg', 'Kilogramo'],
            ['g', 'Gramo'],
            ['mL', 'Mililitro'],
            ['cm', 'Centímetro'],
            ['unidades', 'Unidad'],
        ];
    }

    #[Test]
    #[DataProvider('abreviaturasRealesProvider')]
    public function traduce_las_abreviaturas_que_existen_en_la_base(string $abreviatura, string $esperado): void
    {
        $this->assertSame($esperado, UnitName::full($abreviatura));
    }

    public static function variantesProvider(): array
    {
        return [
            ['LT', 'Litro'],
            ['LITRO', 'Litro'],
            ['Litros', 'Litro'],
            ['KILOGRAMOS', 'Kilogramo'],
            ['Kg.', 'Kilogramo'],
            ['GR', 'Gramo'],
            ['ML', 'Mililitro'],
            ['mL.', 'Mililitro'],
            ['CENTÍMETROS', 'Centímetro'],
            ['UND', 'Unidad'],
            ['UN', 'Unidad'],
            ['  unidad  ', 'Unidad'],
        ];
    }

    #[Test]
    #[DataProvider('variantesProvider')]
    public function es_tolerante_a_mayusculas_tildes_plurales_y_puntuacion(string $variante, string $esperado): void
    {
        $this->assertSame($esperado, UnitName::full($variante));
    }

    #[Test]
    public function una_unidad_desconocida_se_imprime_tal_cual(): void
    {
        // En un documento de despacho es peor perder el dato que mostrar una
        // unidad sin traducir.
        $this->assertSame('quintal', UnitName::full('quintal'));
        $this->assertSame('BULTO ORGANICO', UnitName::full('BULTO ORGANICO'));
    }

    #[Test]
    public function sin_unidad_devuelve_null_para_que_la_plantilla_ponga_su_marcador(): void
    {
        $this->assertNull(UnitName::full(null));
        $this->assertNull(UnitName::full(''));
        $this->assertNull(UnitName::full('   '));
    }
}
