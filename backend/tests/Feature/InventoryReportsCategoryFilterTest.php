<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los informes de inventario se pueden filtrar por categoría del producto
 * (Fertilizante, Insecticida, Fungicida...).
 *
 * Pedido del cliente (7-oct-2026). Solo "Inventario Mensual" tenía el filtro, y
 * lo hacía en el navegador: su descarga a Excel lo ignoraba. Aquí el filtro se
 * aplica EN EL SERVIDOR con el parámetro `category_id`, porque varias de estas
 * listas vienen paginadas (filtrar en el navegador solo filtraría la página que
 * se ve) y para que pantalla, totales y descargas digan lo mismo.
 */
class InventoryReportsCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private const MES = '2026-09';

    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = $this->fixtures();
    }

    // ------------------------------------------------------------------
    // Cada informe, filtrado, solo trae la categoría pedida
    // ------------------------------------------------------------------

    public function test_inventario_actual(): void
    {
        $filas = $this->pedir('/api/inventory?per_page=100&category_id=' . $this->f['fertilizante']->id)->json('data');

        $this->assertSame([$this->f['urea']->id], $this->productos($filas));
    }

    public function test_inventario_y_kardex(): void
    {
        $filas = $this->pedir('/api/inventory/kardex?category_id=' . $this->f['fertilizante']->id)->json('data');

        $this->assertSame([$this->f['urea']->id], $this->productos($filas));
    }

    public function test_movimientos_de_inventario(): void
    {
        $filas = $this->pedir('/api/inventory/movements?per_page=100&category_id=' . $this->f['fertilizante']->id)->json('data');

        $this->assertNotEmpty($filas);
        $this->assertSame([$this->f['urea']->id], $this->productos($filas));
    }

    /** El consolidado filtra los movimientos Y sus totales y agrupaciones. */
    public function test_analisis_consolidado_filtra_tambien_los_totales(): void
    {
        $r = $this->pedir('/api/inventory/movements/report?start_date=2026-09-01&end_date=2026-09-30&category_id=' . $this->f['fertilizante']->id)->json();

        $this->assertSame([$this->f['urea']->id], $this->productos($r['movements']));
        $this->assertSame([$this->f['urea']->id], $this->productos($r['by_product']));
        $this->assertSame(count($r['movements']), $r['summary']['total_movements']);
    }

    public function test_inventario_mensual(): void
    {
        $filas = $this->pedir('/api/inventory/monthly-report?month=9&year=2026&location_id=' . $this->f['bodega']->id
            . '&category_id=' . $this->f['fertilizante']->id)->json('data.products');

        $this->assertSame([$this->f['urea']->id], $this->productos($filas));
    }

    public function test_inventario_mensual_por_finca(): void
    {
        $filas = $this->pedir('/api/inventory/farm-monthly-report?month=9&year=2026&location_id=' . $this->f['finca']->id
            . '&category_id=' . $this->f['fertilizante']->id)->json('data.products');

        $this->assertSame([$this->f['urea']->id], $this->productos($filas));
    }

    public function test_entradas_por_finca(): void
    {
        $filas = $this->pedir('/api/inventory/farm-entries-report?location_id=' . $this->f['finca']->id
            . '&category_id=' . $this->f['fertilizante']->id)->json('data.products');

        $this->assertSame([$this->f['urea']->id], $this->productos($filas));
    }

    public function test_listado_por_fecha(): void
    {
        $filas = $this->pedir('/api/inventory/product-listing?date=2026-09-30&location_id=' . $this->f['bodega']->id
            . '&category_id=' . $this->f['fertilizante']->id)->json('data.products');

        $this->assertSame(['Urea'], collect($filas)->pluck('product_name')->unique()->values()->all());
    }

    // ------------------------------------------------------------------
    // Sin filtro, todo sigue igual
    // ------------------------------------------------------------------

    public function test_sin_filtro_cada_informe_trae_las_dos_categorias(): void
    {
        $ambos = [$this->f['urea']->id, $this->f['lannate']->id];
        sort($ambos);

        $this->assertSame($ambos, $this->productos($this->pedir('/api/inventory?per_page=100')->json('data')));
        $this->assertSame($ambos, $this->productos($this->pedir('/api/inventory/kardex')->json('data')));
        $this->assertSame($ambos, $this->productos($this->pedir('/api/inventory/movements?per_page=100')->json('data')));
        $this->assertSame($ambos, $this->productos(
            $this->pedir('/api/inventory/monthly-report?month=9&year=2026&location_id=' . $this->f['bodega']->id)->json('data.products')
        ));
        $this->assertSame($ambos, $this->productos(
            $this->pedir('/api/inventory/farm-entries-report?location_id=' . $this->f['finca']->id)->json('data.products')
        ));
    }

    /** Un filtro vacío ("") es lo mismo que no filtrar: es lo que manda un selector limpio. */
    public function test_el_filtro_vacio_no_filtra(): void
    {
        $filas = $this->pedir('/api/inventory/kardex?category_id=')->json('data');

        $this->assertCount(2, $filas);
    }

    // ------------------------------------------------------------------
    // Las descargas salen con el mismo filtro que la pantalla
    // ------------------------------------------------------------------

    /**
     * @dataProvider descargas
     */
    public function test_la_descarga_a_excel_respeta_el_filtro(string $ruta): void
    {
        $url = str_replace(
            ['{bodega}', '{categoria}'],
            [$this->f['bodega']->id, $this->f['fertilizante']->id],
            $ruta
        );

        $texto = $this->textoDelExcel($url);

        $this->assertStringContainsString('Urea', $texto, 'El producto de la categoría pedida tiene que salir.');
        $this->assertStringNotContainsString('Lannate', $texto, 'El de otra categoría no puede salir en la descarga.');
    }

    public static function descargas(): array
    {
        return [
            'stock actual' => ['/api/reports/stock/export-excel?category_id={categoria}'],
            'inventario y kardex' => ['/api/reports/kardex-list/export-excel?category_id={categoria}'],
            'movimientos' => ['/api/reports/movements/export-excel?start_date=2026-09-01&end_date=2026-09-30&category_id={categoria}'],
            'inventario mensual' => ['/api/reports/monthly-inventory/export-excel?month=9&year=2026&location_id={bodega}&category_id={categoria}'],
            'listado por fecha' => ['/api/reports/product-listing/export-excel?date=2026-09-30&location_id={bodega}&category_id={categoria}'],
        ];
    }

    // ------------------------------------------------------------------

    private function pedir(string $uri)
    {
        return $this->actingAs($this->f['admin'], 'api')->getJson($uri);
    }

    /** Ids de producto distintos, ordenados, de una lista de filas. */
    private function productos(?array $filas): array
    {
        $ids = collect($filas ?? [])->pluck('product_id')->unique()->values()->all();
        sort($ids);

        return $ids;
    }

    /** Todo el texto de todas las hojas del .xlsx descargado. */
    private function textoDelExcel(string $url): string
    {
        $response = $this->actingAs($this->f['admin'], 'api')->get($url);
        $response->assertStatus(200);

        $path = tempnam(sys_get_temp_dir(), 'cat') . '.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $path);
        $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        @unlink($path);

        $texto = '';
        foreach ($libro->getAllSheets() as $hoja) {
            foreach ($hoja->toArray() as $fila) {
                $texto .= implode(' | ', array_map(fn ($c) => (string) $c, $fila)) . "\n";
            }
        }

        return $texto;
    }

    private function fixtures(): array
    {
        // Las descargas viven tras `permission:export_reports`; un rol con
        // has_full_access lo satisface (ver Role::hasPermission).
        $rolAdmin = \App\Models\Role::create([
            'name' => 'admin_cat_' . uniqid(),
            'display_name' => 'Administrador',
            'has_full_access' => true,
            'excluded_modules' => [],
        ]);

        $admin = User::create([
            'name' => 'Admin Categorias',
            'email' => 'cat_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'role_id' => $rolAdmin->id,
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(['symbol' => 'kg'], ['name' => 'Kilogramos', 'description' => 'Masa', 'status' => 'active']);

        $brand = Brand::create(['name' => 'Marca Cat ' . uniqid(), 'status' => 'active']);
        $fertilizante = Category::create(['name' => 'Fertilizante', 'slug' => 'fertilizante', 'status' => 'active']);
        $insecticida = Category::create(['name' => 'Insecticida', 'slug' => 'insecticida', 'status' => 'active']);
        $bodega = Location::create(['name' => 'Bodega Cat', 'type' => 'warehouse', 'status' => 'active']);
        $finca = Location::create(['name' => 'Finca Cat', 'type' => 'farm', 'status' => 'active']);

        $producto = fn (string $nombre, Category $categoria) => Product::create([
            'name' => $nombre,
            'brand_id' => $brand->id,
            'category_id' => $categoria->id,
            'active_ingredient' => 'x',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $admin->id,
        ]);
        $urea = $producto('Urea', $fertilizante);
        $lannate = $producto('Lannate', $insecticida);

        foreach ([$urea, $lannate] as $p) {
            foreach ([$bodega, $finca] as $ubicacion) {
                InventoryMovement::create([
                    'type' => 'entry',
                    'product_id' => $p->id,
                    'brand_id' => $brand->id,
                    'location_id' => $ubicacion->id,
                    'quantity' => 100,
                    'unit' => 'kg',
                    'movement_date' => self::MES . '-10',
                    'unit_price' => 10,
                    'total_price' => 1000,
                    'responsible_user' => $admin->id,
                    'observations' => 'fixture',
                ]);
                Inventory::create([
                    'product_id' => $p->id,
                    'brand_id' => $brand->id,
                    'location_id' => $ubicacion->id,
                    'batch_number' => 'LOTE-' . substr($p->id, 0, 6) . '-' . substr($ubicacion->id, 0, 4),
                    'quantity' => 100,
                    'unit' => 'kg',
                    'unit_price' => 10,
                    'total_value' => 1000,
                    'status' => 'good',
                ]);
            }
        }

        return compact('admin', 'brand', 'fertilizante', 'insecticida', 'bodega', 'finca', 'urea', 'lannate');
    }
}
