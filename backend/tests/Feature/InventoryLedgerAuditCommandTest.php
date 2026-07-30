<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * PR-C / C-2: el comando `inventory:ledger-audit` es la prueba objetiva de
 * "0 divergencias" que debe pasar el día que se repare el descuadre de E5
 * (correcciones contables de cierre de mayo escritas en el kardex sin lote
 * físico). Este PR NO repara datos: solo prueba que el comando detecta la
 * divergencia cuando existe y no reporta nada cuando no existe.
 */
class InventoryLedgerAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCatalog(): array
    {
        $requester = User::create([
            'name' => 'Auditor Test',
            'email' => 'auditor_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create([
            'name' => 'Marca Test ' . uniqid(),
            'status' => 'active',
        ]);

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Auditoria Test',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $requester->id,
        ]);

        $location = Location::create([
            'name' => 'Bodega Auditoria Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        return compact('requester', 'brand', 'product', 'location');
    }

    /**
     * Siembra una divergencia conocida (físico 100, kardex 150 -> diferencia
     * -50) y comprueba que el comando la detecta y falla (exit code != 0).
     */
    public function test_detects_a_seeded_divergence(): void
    {
        ['requester' => $requester, 'brand' => $brand, 'product' => $product, 'location' => $location] = $this->makeCatalog();

        Inventory::create([
            'product_id' => $product->id,
            'brand_id' => $brand->id,
            'location_id' => $location->id,
            'batch_number' => 'LOTE-1',
            'quantity' => 100,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_value' => 1000,
            'status' => 'good',
        ]);

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $product->id,
            'brand_id' => $brand->id,
            'location_id' => $location->id,
            'quantity' => 150,
            'unit' => 'kg',
            'movement_date' => now()->toDateString(),
            'unit_price' => 10,
            'total_price' => 1500,
            'responsible_user' => $requester->id,
        ]);

        $exitCode = Artisan::call('inventory:ledger-audit', [
            '--json' => true,
            '--product' => $product->id,
            '--location' => $location->id,
        ]);

        $output = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exitCode, 'El comando debe fallar cuando hay divergencias');
        $this->assertSame(1, $output['divergent_count']);
        $this->assertSame($product->id, $output['divergences'][0]['product_id']);
        $this->assertEqualsWithDelta(100.0, $output['divergences'][0]['physical_quantity'], 0.001);
        $this->assertEqualsWithDelta(150.0, $output['divergences'][0]['ledger_balance'], 0.001);
        $this->assertEqualsWithDelta(-50.0, $output['divergences'][0]['difference'], 0.001);
    }

    /**
     * Cuando el físico y el kardex cuadran (misma cantidad en ambos lados),
     * el comando no debe reportar ninguna divergencia y debe terminar en
     * éxito (exit code 0).
     */
    public function test_reports_nothing_when_balanced(): void
    {
        ['requester' => $requester, 'brand' => $brand, 'product' => $product, 'location' => $location] = $this->makeCatalog();

        Inventory::create([
            'product_id' => $product->id,
            'brand_id' => $brand->id,
            'location_id' => $location->id,
            'batch_number' => 'LOTE-1',
            'quantity' => 100,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_value' => 1000,
            'status' => 'good',
        ]);

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $product->id,
            'brand_id' => $brand->id,
            'location_id' => $location->id,
            'quantity' => 100,
            'unit' => 'kg',
            'movement_date' => now()->toDateString(),
            'unit_price' => 10,
            'total_price' => 1000,
            'responsible_user' => $requester->id,
        ]);

        $exitCode = Artisan::call('inventory:ledger-audit', [
            '--json' => true,
            '--product' => $product->id,
            '--location' => $location->id,
        ]);

        $output = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, $output['divergent_count']);
        $this->assertSame([], $output['divergences']);
    }

    /**
     * La salida en modo tabla (default, sin --json) también debe reflejar el
     * estado "sin divergencias" de forma legible.
     */
    public function test_table_output_reports_no_divergences_message(): void
    {
        ['requester' => $requester, 'brand' => $brand, 'product' => $product, 'location' => $location] = $this->makeCatalog();

        Inventory::create([
            'product_id' => $product->id,
            'brand_id' => $brand->id,
            'location_id' => $location->id,
            'batch_number' => 'LOTE-1',
            'quantity' => 42,
            'unit' => 'kg',
            'unit_price' => 5,
            'total_value' => 210,
            'status' => 'good',
        ]);

        InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $product->id,
            'brand_id' => $brand->id,
            'location_id' => $location->id,
            'quantity' => 42,
            'unit' => 'kg',
            'movement_date' => now()->toDateString(),
            'unit_price' => 5,
            'total_price' => 210,
            'responsible_user' => $requester->id,
        ]);

        $this->artisan('inventory:ledger-audit', ['--product' => $product->id, '--location' => $location->id])
            ->expectsOutputToContain('Sin divergencias')
            ->assertExitCode(0);
    }
}
