<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pruebas de InventoryService::addStock (aumento de stock con costo promedio ponderado).
 *
 * Product/Brand/Location no usan HasFactory en este proyecto: los registros se
 * crean con Model::create([...]) directo (mismo patron que AdjustmentTest).
 */
class InventoryServiceAddStockTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    private Product $product;

    private Brand $brand;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryService();

        $user = User::create([
            'name' => 'Usuario Test',
            'email' => 'stock_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        BaseUnit::create([
            'name' => 'Kilogramos',
            'symbol' => 'kg',
            'description' => 'Unidad de masa',
            'status' => 'active',
        ]);

        $this->brand = Brand::create([
            'name' => 'Marca Test ' . uniqid(),
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'name' => 'Producto Test',
            'brand_id' => $this->brand->id,
            'base_unit' => 'kg',
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->location = Location::create([
            'name' => 'Bodega Test ' . uniqid(),
            'type' => 'warehouse',
            'status' => 'active',
        ]);
    }

    /**
     * Ejecuta addStock dentro de una transaccion (patron obligatorio del proyecto).
     */
    private function addStock(
        float $quantityInBase,
        float $unitPrice,
        string $batchNumber,
        ?string $expirationDate = null
    ): void {
        DB::transaction(function () use ($quantityInBase, $unitPrice, $batchNumber, $expirationDate) {
            $this->service->addStock(
                $this->product->id,
                $this->brand->id,
                $this->location->id,
                $quantityInBase,
                $unitPrice,
                $batchNumber,
                $expirationDate
            );
        });
    }

    private function batch(string $batchNumber): ?Inventory
    {
        return Inventory::where('product_id', $this->product->id)
            ->where('brand_id', $this->brand->id)
            ->where('location_id', $this->location->id)
            ->where('batch_number', $batchNumber)
            ->first();
    }

    public function test_creates_batch_when_it_does_not_exist(): void
    {
        $this->addStock(10, 5.0, 'AJU-x');

        $this->assertDatabaseHas('inventory', [
            'batch_number' => 'AJU-x',
            'quantity' => 10,
            'unit_price' => 5.0,
        ]);

        $inventory = $this->batch('AJU-x');
        $this->assertNotNull($inventory);
        $this->assertSame('kg', $inventory->unit);
        $this->assertSame('good', $inventory->status);
        $this->assertEqualsWithDelta(50.0, (float) $inventory->total_value, 0.01);
        $this->assertNull($inventory->expiration_date);
    }

    public function test_weighted_average_price_when_batch_exists(): void
    {
        // 10 @ 5.0 + 10 @ 7.0 => 20 @ 6.0
        $this->addStock(10, 5.0, 'AJU-x');
        $this->addStock(10, 7.0, 'AJU-x');

        $inventory = $this->batch('AJU-x');

        $this->assertNotNull($inventory);
        $this->assertEqualsWithDelta(20, (float) $inventory->quantity, 0.01);
        $this->assertEqualsWithDelta(6.0, (float) $inventory->unit_price, 0.01);
        $this->assertEqualsWithDelta(120.0, (float) $inventory->total_value, 0.01);
        $this->assertSame(1, Inventory::where('batch_number', 'AJU-x')->count());
    }

    public function test_does_not_mix_different_batches(): void
    {
        $this->addStock(10, 5.0, 'AJU-x');
        $this->addStock(4, 20.0, 'AJU-y');

        $x = $this->batch('AJU-x');
        $y = $this->batch('AJU-y');

        $this->assertEqualsWithDelta(10, (float) $x->quantity, 0.01);
        $this->assertEqualsWithDelta(5.0, (float) $x->unit_price, 0.01);
        $this->assertEqualsWithDelta(4, (float) $y->quantity, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $y->unit_price, 0.01);
        $this->assertSame(2, Inventory::count());
    }

    public function test_supports_zero_unit_price(): void
    {
        $this->addStock(10, 0.0, 'AJU-z');

        $inventory = $this->batch('AJU-z');
        $this->assertEqualsWithDelta(10, (float) $inventory->quantity, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $inventory->unit_price, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $inventory->total_value, 0.01);

        // Un ingreso posterior con precio si debe promediar contra el lote en 0
        $this->addStock(10, 4.0, 'AJU-z');

        $inventory->refresh();
        $this->assertEqualsWithDelta(20, (float) $inventory->quantity, 0.01);
        $this->assertEqualsWithDelta(2.0, (float) $inventory->unit_price, 0.01);
    }

    public function test_rejects_non_positive_quantity(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('La cantidad a ingresar debe ser mayor a 0.');

        $this->addStock(0, 5.0, 'AJU-invalid');
    }

    /**
     * Crea un lote con cantidad corrupta (negativa) saltandose el servicio,
     * para poder ejercitar la guarda de increaseBatch().
     */
    private function makeCorruptBatch(string $batchNumber, float $quantity): Inventory
    {
        return Inventory::create([
            'product_id' => $this->product->id,
            'brand_id' => $this->brand->id,
            'location_id' => $this->location->id,
            'batch_number' => $batchNumber,
            'quantity' => $quantity,
            'unit' => 'kg',
            'unit_price' => 5.0,
            'total_value' => $quantity * 5.0,
            'status' => 'good',
        ]);
    }

    private function captureThrowable(callable $callback): ?\Throwable
    {
        try {
            $callback();
        } catch (\Throwable $throwable) {
            return $throwable;
        }

        return null;
    }

    public function test_does_not_divide_by_zero_when_resulting_quantity_is_zero(): void
    {
        // -10 (dato corrupto) + 10 => 0 : sin la guarda esto seria un DivisionByZeroError fatal.
        $batch = $this->makeCorruptBatch('AJU-zero', -10);

        $thrown = $this->captureThrowable(fn () => $this->addStock(10, 7.0, 'AJU-zero'));

        $this->assertNotNull($thrown, 'Se esperaba una excepcion controlada.');
        $this->assertNotInstanceOf(\DivisionByZeroError::class, $thrown);
        $this->assertInstanceOf(\Exception::class, $thrown);
        $this->assertStringContainsString('cantidad no positiva', $thrown->getMessage());

        // El lote no se toca: la operacion se rechaza completa.
        $batch->refresh();
        $this->assertEqualsWithDelta(-10, (float) $batch->quantity, 0.01);
        $this->assertEqualsWithDelta(5.0, (float) $batch->unit_price, 0.01);
    }

    public function test_rejects_when_resulting_quantity_stays_negative(): void
    {
        // -30 + 10 => -20 : nunca se persiste stock negativo.
        $batch = $this->makeCorruptBatch('AJU-neg', -30);

        $thrown = $this->captureThrowable(fn () => $this->addStock(10, 7.0, 'AJU-neg'));

        $this->assertNotNull($thrown, 'Se esperaba una excepcion controlada.');
        $this->assertInstanceOf(\Exception::class, $thrown);
        $this->assertStringContainsString('cantidad no positiva', $thrown->getMessage());

        $batch->refresh();
        $this->assertEqualsWithDelta(-30, (float) $batch->quantity, 0.01);
    }

    public function test_keeps_expiration_date_and_marks_expired_batches(): void
    {
        $this->addStock(10, 5.0, 'AJU-exp', now()->subDay()->toDateString());

        $inventory = $this->batch('AJU-exp');

        $this->assertNotNull($inventory->expiration_date);
        $this->assertSame('expired', $inventory->status);
    }
}
