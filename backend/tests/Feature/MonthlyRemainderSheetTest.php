<?php

namespace Tests\Feature;

use App\Exports\MonthlyRemainderSheet;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\OutputProduct;
use App\Models\OutputType;
use App\Models\Product;
use App\Models\ProductOutput;
use App\Models\Reception;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hoja REMANENTES (segunda hoja del Excel del Informe de Inventario Mensual).
 *
 * El cliente concilia a mano una hoja con una columna por finca y una fila por
 * producto: cuánto DEVOLVIÓ cada finca a la bodega en el mes. Este archivo fija
 * ese contrato:
 *
 *  - el formato exacto de encabezados (17 fincas en el orden del archivo del
 *    cliente, entre los 4 campos de producto y el total de la fila);
 *  - que la cifra se mida sobre lo que REALMENTE entró a la bodega (kardex),
 *    filtrando por `movement_date` y no por `created_at` ni por
 *    `product_outputs.output_date`;
 *  - que solo cuenten las salidas de tipo `remanente`;
 *  - que la celda quede VACÍA (null), no en 0, cuando la finca no devolvió nada.
 */
class MonthlyRemainderSheetTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT_MONTH = 4;
    private const REPORT_YEAR = 2026;
    private const IN_MONTH = '2026-04-15';
    private const PREVIOUS_MONTH = '2026-03-15';

    /**
     * @return array<string, mixed>
     */
    private function createFixtures(): array
    {
        // El export vive tras `permission:export_reports`; el rol con
        // has_full_access lo satisface (ver Role::hasPermission).
        $adminRole = \App\Models\Role::create([
            'name' => 'admin_rem_' . uniqid(),
            'display_name' => 'Administrador',
            'has_full_access' => true,
            'excluded_modules' => [],
        ]);

        $admin = User::create([
            'name' => 'Admin Remanentes',
            'email' => 'admin_hoja_remanentes_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $brand = Brand::create(['name' => 'Marca Rem ' . uniqid(), 'status' => 'active']);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $category = Category::create([
            'name' => 'Fertilizante',
            'slug' => 'fertilizante-' . uniqid(),
            'status' => 'active',
        ]);

        // Códigos deliberadamente fuera de orden alfabético-lexicográfico
        // ('90' > '1000' como texto) para probar el orden natural por código.
        $productA = $this->makeProduct($admin, $brand, $category, 'ZZZ PRODUCTO', '1000');
        $productB = $this->makeProduct($admin, $brand, $category, 'AAA PRODUCTO', '90');
        $productSinRemanente = $this->makeProduct($admin, $brand, $category, 'SIN REMANENTE', '2000');

        $bodega = Location::create(['name' => 'BODEGA PRINCIPAL', 'type' => 'warehouse', 'status' => 'active']);

        // Nombres con la tilde/mayúsculas del maestro real: la hoja los resuelve
        // por nombre normalizado contra sus encabezados ('ALQUERÍA', 'MANSIÓN').
        $alqueria = Location::create(['name' => 'Alquería', 'type' => 'farm', 'status' => 'active']);
        $mansion = Location::create(['name' => 'Mansión', 'type' => 'farm', 'status' => 'active']);

        // Finca que NO es columna de la hoja del cliente.
        $laboratorio = Location::create(['name' => 'Laboratorio', 'type' => 'farm', 'status' => 'active']);

        $remanenteType = OutputType::firstOrCreate(
            ['code' => 'remanente'],
            ['name' => 'Remanente', 'requires_lots' => false, 'status' => 'active']
        );

        $transferType = OutputType::firstOrCreate(
            ['code' => 'transfer'],
            ['name' => 'Traslado', 'requires_lots' => false, 'status' => 'active']
        );

        return compact(
            'admin', 'brand', 'category', 'productA', 'productB', 'productSinRemanente',
            'bodega', 'alqueria', 'mansion', 'laboratorio', 'remanenteType', 'transferType'
        );
    }

    private function makeProduct(User $admin, Brand $brand, Category $category, string $name, string $code): Product
    {
        return Product::create([
            'name' => $name,
            'product_code' => $code,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'active_ingredient' => 'N/A',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);
    }

    /**
     * Devolución recepcionada: salida (finca -> bodega) + recepción + la ENTRADA
     * de kardex en la bodega, que es lo que mide la hoja.
     */
    private function seedReceivedOutput(
        array $fixtures,
        OutputType $type,
        Product $product,
        Location $origin,
        float $quantity,
        string $movementDate
    ): void {
        $output = ProductOutput::create([
            'output_number' => ProductOutput::generateOutputNumber(),
            'output_type_id' => $type->id,
            'output_date' => $movementDate,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $fixtures['bodega']->id,
            'status' => 'completed',
            'total_cost' => $quantity * 10,
            'responsible_user' => $fixtures['admin']->id,
        ]);

        OutputProduct::create([
            'output_id' => $output->id,
            'product_id' => $product->id,
            'brand_id' => $fixtures['brand']->id,
            'quantity_requested' => $quantity,
            'quantity_delivered' => $quantity,
            'unit' => 'kg',
        ]);

        $reception = Reception::create([
            'reception_number' => 'REC-REM-' . strtoupper(substr(uniqid(), -8)),
            'source_id' => $output->id,
            'source_type' => 'output',
            'origin_location_id' => $origin->id,
            'destination_location_id' => $fixtures['bodega']->id,
            'status' => 'completed',
            'total_expected' => $quantity,
            'total_received' => $quantity,
            'completion_percentage' => 100,
            'responsible_user' => $fixtures['admin']->id,
        ]);

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $product->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $fixtures['bodega']->id,
            'quantity' => $quantity,
            'unit' => 'kg',
            'movement_date' => $movementDate,
            'unit_price' => 10,
            'total_price' => $quantity * 10,
            'related_document_id' => $reception->id,
            'related_document_type' => 'App\Models\Reception',
            'responsible_user' => $fixtures['admin']->id,
            'observations' => 'Recepción de ' . $type->code,
        ]);
    }

    private function sheet(array $fixtures): MonthlyRemainderSheet
    {
        return new MonthlyRemainderSheet(self::REPORT_MONTH, self::REPORT_YEAR, $fixtures['bodega']->id);
    }

    /** @test */
    public function encabezados_replican_el_formato_del_cliente(): void
    {
        $fixtures = $this->createFixtures();

        $this->assertSame(
            [
                'Codigo', 'Grupo Insumo', 'Producto', 'Unidad Medida',
                'ALQUERÍA', 'BREVA', 'CIRUELO', 'ESPÁRRAGOS', 'LA PALMA', 'VENTA',
                'MANSIÓN', 'MELON', 'NARANJOS', 'ROBLE 1', 'ROBLE 2', 'SALADEROS',
                'TORONJAS 2', 'TORONJAS', 'UVA', 'VILLA', 'ARANDANOS',
                'INVENTARIO FINAL',
            ],
            $this->sheet($fixtures)->headings()
        );

        $this->assertSame('REMANENTES', $this->sheet($fixtures)->title());
        $this->assertCount(17, MonthlyRemainderSheet::FARM_HEADINGS);
    }

    /** @test */
    public function ubica_cada_devolucion_en_la_columna_de_su_finca_y_totaliza_la_fila(): void
    {
        $fixtures = $this->createFixtures();

        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productA'], $fixtures['alqueria'], 10.5, self::IN_MONTH);
        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productA'], $fixtures['mansion'], 4.5, self::IN_MONTH);
        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productB'], $fixtures['mansion'], 2.0, self::IN_MONTH);

        $rows = $this->sheet($fixtures)->array();
        $headings = $this->sheet($fixtures)->headings();

        $this->assertCount(2, $rows, 'Solo los productos con remanente en el mes.');

        // Orden NATURAL por código: '90' antes que '1000'.
        $this->assertSame('90', $rows[0][0]);
        $this->assertSame('1000', $rows[1][0]);

        $productA = $rows[1];
        $this->assertSame('Fertilizante', $productA[1]);
        $this->assertSame('ZZZ PRODUCTO', $productA[2]);
        $this->assertSame('kg', $productA[3]);
        $this->assertSame(10.5, $productA[array_search('ALQUERÍA', $headings, true)]);
        $this->assertSame(4.5, $productA[array_search('MANSIÓN', $headings, true)]);
        $this->assertSame(15.0, $productA[array_search('INVENTARIO FINAL', $headings, true)]);

        // Cada fila cuadra con la suma de sus celdas de finca.
        foreach ($rows as $row) {
            $farmCells = array_slice($row, 4, count(MonthlyRemainderSheet::FARM_HEADINGS));
            $this->assertEqualsWithDelta(
                array_sum(array_map(fn ($v) => (float) $v, $farmCells)),
                (float) end($row),
                0.005
            );
        }
    }

    /** @test */
    public function la_finca_que_no_devolvio_deja_la_celda_vacia_no_en_cero(): void
    {
        $fixtures = $this->createFixtures();

        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productA'], $fixtures['alqueria'], 3.0, self::IN_MONTH);

        $rows = $this->sheet($fixtures)->array();
        $headings = $this->sheet($fixtures)->headings();

        $this->assertCount(1, $rows);
        $this->assertSame(3.0, $rows[0][array_search('ALQUERÍA', $headings, true)]);
        $this->assertNull($rows[0][array_search('MANSIÓN', $headings, true)]);
        $this->assertNull($rows[0][array_search('UVA', $headings, true)]);
    }

    /** @test */
    public function ignora_traslados_otros_meses_y_fincas_fuera_de_la_plantilla(): void
    {
        $fixtures = $this->createFixtures();

        // Sí cuenta: remanente, dentro del mes, desde una finca de la plantilla.
        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productA'], $fixtures['alqueria'], 7.0, self::IN_MONTH);

        // No cuenta: no es remanente.
        $this->seedReceivedOutput($fixtures, $fixtures['transferType'], $fixtures['productB'], $fixtures['alqueria'], 100.0, self::IN_MONTH);

        // No cuenta: remanente de otro mes (se filtra por movement_date).
        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productB'], $fixtures['mansion'], 50.0, self::PREVIOUS_MONTH);

        // No cuenta: finca que no es columna de la hoja del cliente.
        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productB'], $fixtures['laboratorio'], 25.0, self::IN_MONTH);

        $rows = $this->sheet($fixtures)->array();

        $this->assertCount(1, $rows);
        $this->assertSame('1000', $rows[0][0]);
        $this->assertSame(7.0, (float) end($rows[0]));
    }

    /** @test */
    public function sin_remanentes_en_el_mes_la_hoja_queda_solo_con_encabezados(): void
    {
        $fixtures = $this->createFixtures();

        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productA'], $fixtures['alqueria'], 9.0, self::PREVIOUS_MONTH);

        $this->assertSame([], $this->sheet($fixtures)->array());
        $this->assertCount(22, $this->sheet($fixtures)->headings());
    }

    /** @test */
    public function el_export_a_excel_entrega_dos_hojas_movimientos_y_remanentes(): void
    {
        $fixtures = $this->createFixtures();

        $sheets = (new \App\Exports\MonthlyInventoryWorkbookExport(
            [],
            [],
            (string) self::REPORT_MONTH,
            (string) self::REPORT_YEAR,
            $fixtures['bodega']->id
        ))->sheets();

        $this->assertCount(2, $sheets);
        $this->assertInstanceOf(\App\Exports\MonthlyInventoryExport::class, $sheets[0]);
        $this->assertInstanceOf(MonthlyRemainderSheet::class, $sheets[1]);
        $this->assertSame('MOVIMIENTOS ABRIL', $sheets[0]->title());
        $this->assertSame('REMANENTES', $sheets[1]->title());
    }

    /** @test */
    public function el_endpoint_de_export_descarga_un_libro_con_las_dos_hojas(): void
    {
        $fixtures = $this->createFixtures();

        $this->seedReceivedOutput($fixtures, $fixtures['remanenteType'], $fixtures['productA'], $fixtures['alqueria'], 12.0, self::IN_MONTH);

        $response = $this->actingAs($fixtures['admin'], 'api')->get(
            '/api/reports/monthly-inventory/export-excel'
            . '?month=' . self::REPORT_MONTH
            . '&year=' . self::REPORT_YEAR
            . '&location_id=' . $fixtures['bodega']->id
        );

        $response->assertStatus(200);

        // Excel::download devuelve un BinaryFileResponse: el .xlsx ya está en
        // disco, se copia para leerlo antes de que el framework lo borre.
        $this->assertInstanceOf(
            \Symfony\Component\HttpFoundation\BinaryFileResponse::class,
            $response->baseResponse
        );

        $path = tempnam(sys_get_temp_dir(), 'rem') . '.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $path);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        @unlink($path);

        $this->assertSame(['MOVIMIENTOS ABRIL', 'REMANENTES'], $spreadsheet->getSheetNames());

        $remanentes = $spreadsheet->getSheetByName('REMANENTES');
        $this->assertSame('Codigo', $remanentes->getCell('A1')->getValue());
        $this->assertSame('ALQUERÍA', $remanentes->getCell('E1')->getValue());
        $this->assertSame('INVENTARIO FINAL', $remanentes->getCell('V1')->getValue());
        $this->assertEqualsWithDelta(12.0, (float) $remanentes->getCell('E2')->getValue(), 0.005);
        $this->assertEqualsWithDelta(12.0, (float) $remanentes->getCell('V2')->getValue(), 0.005);
        $this->assertNull($remanentes->getCell('K2')->getValue(), 'MANSIÓN sin devolución debe quedar vacía.');
    }
}
