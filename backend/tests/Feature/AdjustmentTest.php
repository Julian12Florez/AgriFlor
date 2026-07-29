<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
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

    // ------------------------------------------------------------------
    // Task A: API de creación y lectura (StoreAdjustmentRequest,
    // AdjustmentResource, AdjustmentController::index/show/store/reasons)
    // ------------------------------------------------------------------

    /**
     * Catálogo compartido (reason, product, brand, origin/destination locations,
     * un usuario 'admin' como solicitante) para armar payloads de /api/adjustments
     * sin acoplar cada test a la creación completa de un Adjustment.
     */
    private function createCatalogFixtures(): array
    {
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

        $reason = AdjustmentReason::create([
            'code' => 'motivo_test_' . uniqid(),
            'name' => 'Motivo de prueba',
            'direction' => 'any',
            'active' => true,
        ]);

        $origin = Location::create([
            'name' => 'Finca Origen Test',
            'type' => 'farm',
            'status' => 'active',
        ]);

        $destination = Location::create([
            'name' => 'Bodega Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        return compact('requester', 'brand', 'product', 'reason', 'origin', 'destination');
    }

    /**
     * Campos comunes a cualquier payload de /api/adjustments, sin `type` ni
     * ubicaciones (cada test agrega lo que su caso necesita).
     */
    private function baseAdjustmentAttributes(array $fixtures): array
    {
        return [
            'reason_id' => $fixtures['reason']->id,
            'notes' => 'Ajuste de prueba',
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'unit' => 'kg',
            'quantity_mode' => 'delta',
            'quantity' => 10,
            'movement_date' => now()->toDateString(),
        ];
    }

    private function validEntryPayload(array $fixtures, array $overrides = []): array
    {
        return array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'entry',
            'destination_location_id' => $fixtures['destination']->id,
            'unit_price' => 5.5,
        ], $overrides);
    }

    public function test_store_requires_type(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $payload = $this->validEntryPayload($fixtures);
        unset($payload['type']);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_store_creates_pending_entry_without_touching_inventory(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $payload = $this->validEntryPayload($fixtures);

        $inventoryBefore = Inventory::count();
        $movementsBefore = InventoryMovement::count();

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.responsible_user', $fixtures['requester']->id)
            ->assertJsonPath('data.type', 'entry');

        $this->assertSame($inventoryBefore, Inventory::count());
        $this->assertSame($movementsBefore, InventoryMovement::count());
        $this->assertDatabaseHas('adjustments', [
            'type' => 'entry',
            'status' => 'pending',
            'responsible_user' => $fixtures['requester']->id,
        ]);
    }

    public function test_store_exit_requires_origin_location(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'exit',
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('origin_location_id');
    }

    public function test_store_transfer_requires_different_origin_and_destination(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'transfer',
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['origin']->id,
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('destination_location_id');
    }

    public function test_store_absolute_mode_requires_batch_number(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'entry',
            'quantity_mode' => 'absolute',
            'destination_location_id' => $fixtures['destination']->id,
            'unit_price' => 5.5,
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('batch_number');
    }

    public function test_store_transfer_does_not_allow_absolute_mode(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'transfer',
            'quantity_mode' => 'absolute',
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['destination']->id,
            'batch_number' => 'LOTE-001',
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('quantity_mode');
    }

    public function test_index_isolates_by_location_for_restricted_roles(): void
    {
        $fixtures = $this->createCatalogFixtures();

        $supervisor = User::create([
            'name' => 'Supervisor Test',
            'email' => 'supervisor_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'supervisor',
            'status' => 'active',
        ]);

        // Finca de la que el supervisor es responsable (managedLocationIds).
        $ownLocation = Location::create([
            'name' => 'Finca Supervisor',
            'type' => 'farm',
            'status' => 'active',
            'responsible_user_id' => $supervisor->id,
        ]);

        $otherLocation = Location::create([
            'name' => 'Finca Ajena',
            'type' => 'farm',
            'status' => 'active',
        ]);

        $ownAdjustment = Adjustment::create(array_merge($this->baseAdjustmentAttributes($fixtures), [
            'adjustment_number' => Adjustment::generateAdjustmentNumber(),
            'type' => 'exit',
            'origin_location_id' => $ownLocation->id,
            'responsible_user' => $fixtures['requester']->id,
        ]));

        $otherAdjustment = Adjustment::create(array_merge($this->baseAdjustmentAttributes($fixtures), [
            'adjustment_number' => Adjustment::generateAdjustmentNumber(),
            'type' => 'exit',
            'origin_location_id' => $otherLocation->id,
            'responsible_user' => $fixtures['requester']->id,
        ]));

        $supervisorIds = collect(
            $this->actingAs($supervisor, 'api')
                ->getJson('/api/adjustments')
                ->assertStatus(200)
                ->json('data')
        )->pluck('id');

        $this->assertTrue($supervisorIds->contains($ownAdjustment->id));
        $this->assertFalse($supervisorIds->contains($otherAdjustment->id));

        $adminIds = collect(
            $this->actingAs($fixtures['requester'], 'api')
                ->getJson('/api/adjustments')
                ->assertStatus(200)
                ->json('data')
        )->pluck('id');

        $this->assertTrue($adminIds->contains($ownAdjustment->id));
        $this->assertTrue($adminIds->contains($otherAdjustment->id));
    }

    public function test_reasons_endpoint_returns_active_reasons(): void
    {
        $fixtures = $this->createCatalogFixtures();

        $inactiveReason = AdjustmentReason::create([
            'code' => 'motivo_inactivo_' . uniqid(),
            'name' => 'Motivo inactivo',
            'direction' => 'any',
            'active' => false,
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->getJson('/api/adjustment-reasons');

        $response->assertStatus(200);

        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains($fixtures['reason']->code));
        $this->assertFalse($codes->contains($inactiveReason->code));
    }
}
