<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\Brand;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AdjustmentReasonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_reasons_seeded(): void
    {
        $this->seed(AdjustmentReasonSeeder::class);

        $this->assertDatabaseHas('adjustment_reasons', ['code' => 'compra_doble', 'direction' => 'exit']);
        $this->assertSame(10, AdjustmentReason::count());
    }

    private function makeAdjustment(array $overrides = []): Adjustment
    {
        $reason = AdjustmentReason::create([
            'code' => 'motivo_test_' . uniqid(),
            'name' => 'Motivo de prueba',
            'direction' => 'any',
            'active' => true,
        ]);

        $requester = User::create([
            'name' => 'Solicitante Test',
            'email' => 'solicitante_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $brand = Brand::create([
            'name' => 'Marca Test ' . uniqid(),
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Producto Test',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'created_by' => $requester->id,
        ]);

        $destination = Location::create([
            'name' => 'Bodega Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        return Adjustment::create(array_merge([
            'adjustment_number' => Adjustment::generateAdjustmentNumber(),
            'type' => 'entry',
            'reason_id' => $reason->id,
            'notes' => 'Ajuste de prueba',
            'product_id' => $product->id,
            'brand_id' => $brand->id,
            'unit' => 'kg',
            'quantity_mode' => 'delta',
            'quantity' => 10,
            'destination_location_id' => $destination->id,
            'movement_date' => now()->toDateString(),
            'responsible_user' => $requester->id,
        ], $overrides));
    }

    public function test_generate_number_format(): void
    {
        $number = Adjustment::generateAdjustmentNumber();

        $this->assertMatchesRegularExpression('/^AJU-\d{8}-\d{4}$/', $number);
    }

    public function test_generate_number_increments_sequence(): void
    {
        $first = $this->makeAdjustment();

        $second = Adjustment::generateAdjustmentNumber();

        $firstSequence = (int) substr($first->adjustment_number, -4);
        $secondSequence = (int) substr($second, -4);

        $this->assertSame($firstSequence + 1, $secondSequence);
    }

    public function test_adjustment_is_auditable(): void
    {
        // El paquete owen-it/laravel-auditing desactiva la auditoria en consola
        // (config('audit.console') = false por defecto); phpunit corre en consola,
        // asi que la habilitamos solo para esta prueba.
        config(['audit.console' => true]);

        $adjustment = $this->makeAdjustment();

        $this->assertGreaterThanOrEqual(1, $adjustment->audits()->count());
    }

    public function test_adjustment_relationships(): void
    {
        $adjustment = $this->makeAdjustment();

        $this->assertInstanceOf(AdjustmentReason::class, $adjustment->reason);
        $this->assertInstanceOf(Product::class, $adjustment->product);
        $this->assertInstanceOf(Brand::class, $adjustment->brand);
        $this->assertInstanceOf(Location::class, $adjustment->destinationLocation);
        $this->assertInstanceOf(User::class, $adjustment->requester);
        $this->assertNull($adjustment->originLocation);
        $this->assertNull($adjustment->approver);
    }
}
