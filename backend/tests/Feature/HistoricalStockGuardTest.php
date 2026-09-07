<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\HistoricalStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El stock NUNCA puede quedar negativo, tampoco al registrar salidas del mes
 * pasado.
 *
 * El sistema validaba las salidas contra la existencia de HOY. Con una salida
 * fechada hoy eso es correcto; con una RETROFECHADA no, porque insertar una
 * salida con fecha D no baja solo el saldo de ese día: baja el de D y el de todos
 * los días siguientes.
 *
 * Caso medido en un ensayo contra copia de producción: se sacaron 1.500 kg de
 * CALFOS de Breva fechados el 22 y el 02 de agosto, de un producto cuya única
 * entrada a esa finca está fechada el 1 de septiembre. El sistema lo aceptó sin
 * una sola advertencia porque HOY el saldo era +17.000. Resultado: el kardex de
 * Breva al 31/08 quedó en −1.500,00 kg. El stock de hoy seguía correcto y
 * `inventory` no tenía negativos, así que ningún chequeo de "¿hay algo en rojo?"
 * lo detectaba: el daño estaba en la foto histórica de agosto, que es justo la
 * que se entrega a Contabilidad.
 */
class HistoricalStockGuardTest extends TestCase
{
    use RefreshDatabase;

    private HistoricalStockService $historico;
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();

        $this->historico = app(HistoricalStockService::class);
        $this->f = $this->fixtures();
    }

    // ------------------------------------------------------------------
    // 1. El caso exacto de producción
    // ------------------------------------------------------------------

    /**
     * El producto llega a la finca el 1 de septiembre. Sacarlo con fecha de
     * agosto tiene que dar 0 disponible, aunque hoy haya 18.000 kg.
     */
    public function test_no_se_puede_sacar_con_fecha_anterior_a_la_entrada(): void
    {
        $this->movimiento('entry', 18000, '2026-09-01');

        // Hoy sí hay existencia.
        $this->assertEqualsWithDelta(18000, $this->disponible('2026-09-07'), 0.01);

        // Pero en agosto no había NADA.
        $this->assertEqualsWithDelta(
            0,
            $this->disponible('2026-08-22'),
            0.01,
            'El 22 de agosto ese producto todavía no había llegado a la finca.'
        );

        $this->assertFalse(
            $this->historico->alcanza(
                $this->f['product']->id,
                $this->f['brand']->id,
                $this->f['finca']->id,
                '2026-08-22',
                1500,
            ),
            'Sacar 1.500 kg con fecha 22-ago dejaría agosto en −1.500: debe rechazarse.'
        );
    }

    // ------------------------------------------------------------------
    // 2. Lo que hace insuficiente mirar solo el saldo del día
    // ------------------------------------------------------------------

    /**
     * El punto fino: no basta con que HAYA saldo el día de la salida. Insertar la
     * salida baja TODOS los días siguientes, así que lo que manda es el MÍNIMO de
     * la línea de tiempo desde esa fecha en adelante.
     *
     * Aquí el 10 de agosto hay 2.000, pero el 15 una salida deja el saldo en 100.
     * Sacar 1.500 con fecha 10 dejaría el 15 en −1.400.
     */
    public function test_mira_el_minimo_futuro_no_solo_el_saldo_de_ese_dia(): void
    {
        $this->movimiento('entry', 2000, '2026-08-01');
        $this->movimiento('exit', 1900, '2026-08-15');

        // El día 10 el saldo es 2.000...
        $this->assertEqualsWithDelta(
            100,
            $this->disponible('2026-08-10'),
            0.01,
            '...pero solo se pueden sacar 100: el resto ya se gastó el día 15.'
        );

        $this->assertFalse(
            $this->historico->alcanza(
                $this->f['product']->id,
                $this->f['brand']->id,
                $this->f['finca']->id,
                '2026-08-10',
                1500,
            ),
            'Sacar 1.500 el día 10 dejaría el día 15 en −1.400.'
        );

        $this->assertTrue(
            $this->historico->alcanza(
                $this->f['product']->id,
                $this->f['brand']->id,
                $this->f['finca']->id,
                '2026-08-10',
                100,
            ),
            'Sacar exactamente 100 sí cabe.'
        );
    }

    // ------------------------------------------------------------------
    // 3. Lo que NO debe estorbar
    // ------------------------------------------------------------------

    /** Una salida normal, con fecha posterior a la entrada, pasa sin ruido. */
    public function test_deja_pasar_una_salida_legitima_retrofechada(): void
    {
        $this->movimiento('entry', 500, '2026-08-05');

        $this->assertTrue(
            $this->historico->alcanza(
                $this->f['product']->id,
                $this->f['brand']->id,
                $this->f['finca']->id,
                '2026-08-20',
                500,
            ),
            'El producto estaba desde el 5 de agosto: sacarlo el 20 es legítimo.'
        );
    }

    /** El borde exacto: sacar justo lo que hay. */
    public function test_permite_sacar_exactamente_el_saldo(): void
    {
        $this->movimiento('entry', 250, '2026-08-01');

        $this->assertTrue(
            $this->historico->alcanza(
                $this->f['product']->id,
                $this->f['brand']->id,
                $this->f['finca']->id,
                '2026-08-10',
                250,
            )
        );

        $this->assertFalse(
            $this->historico->alcanza(
                $this->f['product']->id,
                $this->f['brand']->id,
                $this->f['finca']->id,
                '2026-08-10',
                250.5,
            ),
            'Medio kilo de más ya deja el mes en negativo.'
        );
    }

    /**
     * Si el histórico YA está en rojo por datos anteriores a esta validación, la
     * respuesta es 0, no un número negativo que luego se compare mal.
     */
    public function test_un_historico_ya_negativo_responde_cero(): void
    {
        $this->movimiento('exit', 100, '2026-08-01');

        $this->assertEqualsWithDelta(0, $this->disponible('2026-08-05'), 0.01);
        $this->assertFalse(
            $this->historico->alcanza(
                $this->f['product']->id,
                $this->f['brand']->id,
                $this->f['finca']->id,
                '2026-08-05',
                1,
            )
        );
    }

    /** Cada ubicación lleva su propia cuenta: el saldo de una no financia a otra. */
    public function test_no_mezcla_ubicaciones(): void
    {
        $this->movimiento('entry', 1000, '2026-08-01', $this->f['bodega']->id);

        $this->assertEqualsWithDelta(
            0,
            $this->disponible('2026-08-10'),
            0.01,
            'La bodega tiene 1.000, pero la finca no tiene nada.'
        );
    }

    // ------------------------------------------------------------------

    private function disponible(string $fecha, ?string $locationId = null): float
    {
        return $this->historico->disponibleALaFecha(
            $this->f['product']->id,
            $this->f['brand']->id,
            $locationId ?? $this->f['finca']->id,
            $fecha,
        );
    }

    private function movimiento(string $tipo, float $cantidad, string $fecha, ?string $locationId = null): void
    {
        InventoryMovement::create([
            'product_id' => $this->f['product']->id,
            'brand_id' => $this->f['brand']->id,
            'location_id' => $locationId ?? $this->f['finca']->id,
            'type' => $tipo,
            'quantity' => $cantidad,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_price' => $cantidad * 10,
            'movement_date' => $fecha,
            'responsible_user' => $this->f['admin']->id,
            'observations' => 'fixture',
        ]);
    }

    private function fixtures(): array
    {
        $admin = User::create([
            'name' => 'Admin Historico',
            'email' => 'admin_hist_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create(['name' => 'Marca Hist ' . uniqid(), 'status' => 'active']);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Historico',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Fosforo',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);

        return [
            'admin' => $admin,
            'brand' => $brand,
            'product' => $product,
            'bodega' => Location::create(['name' => 'Bodega Hist', 'type' => 'warehouse', 'status' => 'active']),
            'finca' => Location::create(['name' => 'Finca Hist', 'type' => 'farm', 'status' => 'active']),
        ];
    }
}
