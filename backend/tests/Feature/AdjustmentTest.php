<?php

namespace Tests\Feature;

use App\Models\Adjustment;
use App\Models\AdjustmentReason;
use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\PackagingUnit;
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
     *
     * El producto se crea con `base_unit = 'kg'` (vía un `BaseUnit` real, porque
     * `products.base_unit` tiene FK a `base_units.symbol`) para que 'kg' sea una
     * unidad válida en los payloads de los tests: desde el fix round 1,
     * StoreAdjustmentRequest valida `unit` contra la unidad base efectiva del
     * producto (o sus PackagingUnit asociadas).
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

        BaseUnit::firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramos', 'description' => 'Unidad de masa', 'status' => 'active']
        );

        $product = Product::create([
            'name' => 'Producto Test',
            'brand_id' => $brand->id,
            'active_ingredient' => 'Glifosato',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
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
     * Usuario con un rol restringido por ubicación (supervisor|farm), sin
     * ninguna ubicación administrada por defecto — cada test asigna las
     * ubicaciones (o ninguna) según lo que quiera probar.
     */
    private function createRestrictedUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Test',
            'email' => $role . '_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'active',
        ]);
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

        // Siembra un lote real y un movimiento real para poder comparar
        // CANTIDADES antes/después, no solo conteos: un bug que sume/reste
        // sobre un lote existente sin crear filas nuevas pasaría inadvertido
        // si solo se comparan conteos.
        $inventory = Inventory::create([
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $fixtures['destination']->id,
            'batch_number' => 'LOTE-EXISTENTE',
            'quantity' => 50,
            'unit' => 'kg',
            'unit_price' => 10,
            'total_value' => 500,
            'status' => 'good',
        ]);

        $movement = InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $fixtures['destination']->id,
            'quantity' => 50,
            'unit' => 'kg',
            'movement_date' => now()->toDateString(),
            'unit_price' => 10,
            'total_price' => 500,
            'responsible_user' => $fixtures['requester']->id,
        ]);

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

        $inventory->refresh();
        $this->assertEquals(50.00, (float) $inventory->quantity);
        $this->assertEquals(500.00, (float) $inventory->total_value);

        $movement->refresh();
        $this->assertEquals(50.00, (float) $movement->quantity);

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

    public function test_store_entry_rejects_origin_location(): void
    {
        $fixtures = $this->createCatalogFixtures();
        // Entrada válida salvo por traer también un origin_location_id, que
        // no le corresponde (solo destino).
        $payload = $this->validEntryPayload($fixtures, [
            'origin_location_id' => $fixtures['origin']->id,
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('origin_location_id');
    }

    public function test_store_exit_rejects_destination_location(): void
    {
        $fixtures = $this->createCatalogFixtures();
        // Salida válida salvo por traer también un destination_location_id,
        // que no le corresponde (solo origen).
        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'exit',
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['destination']->id,
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('destination_location_id');
    }

    public function test_store_rejects_unit_not_valid_for_product(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $payload = $this->validEntryPayload($fixtures, ['unit' => 'banana']);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('unit');
    }

    public function test_store_accepts_unit_matching_a_packaging_unit_of_the_product(): void
    {
        $fixtures = $this->createCatalogFixtures();

        $packagingUnit = PackagingUnit::create([
            'name' => 'Saco',
            'base_quantity' => 25,
            'base_unit' => 'kg',
        ]);
        $fixtures['product']->packagingUnits()->attach($packagingUnit->id);

        $payload = $this->validEntryPayload($fixtures, ['unit' => 'Saco']);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(201)->assertJsonPath('data.unit', 'Saco');
    }

    public function test_store_rejects_reason_direction_mismatch_with_type(): void
    {
        $fixtures = $this->createCatalogFixtures();

        $exitOnlyReason = AdjustmentReason::create([
            'code' => 'motivo_exit_' . uniqid(),
            'name' => 'Motivo solo salida',
            'direction' => 'exit',
            'active' => true,
        ]);

        $payload = $this->validEntryPayload($fixtures, ['reason_id' => $exitOnlyReason->id]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('reason_id');
    }

    public function test_store_rejects_unit_over_max_length(): void
    {
        $fixtures = $this->createCatalogFixtures();
        // 300 'a' NO es una unidad válida para el producto (base_unit='kg', sin
        // packaging unit llamada así), así que el error de
        // validateUnitBelongsToProduct() dispararía igual sin la regla max:255,
        // dejando el test verde aunque se quitara max:255. Se asevera
        // explícitamente que el mensaje de max:255 está presente entre los
        // errores de `unit` (puede haber más de uno), para que el test SÍ
        // falle si esa regla se elimina.
        $payload = $this->validEntryPayload($fixtures, ['unit' => str_repeat('a', 300)]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('unit');
        $this->assertContains(
            'La unidad no puede exceder 255 caracteres.',
            $response->json('errors.unit'),
            'El error de max:255 debe estar presente explícitamente, no solo el de "unidad no corresponde al producto".'
        );
    }

    public function test_store_rejects_batch_number_over_max_length(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $payload = $this->validEntryPayload($fixtures, [
            'quantity_mode' => 'absolute',
            'batch_number' => str_repeat('a', 300),
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors('batch_number');
    }

    public function test_store_rejects_array_values_for_reference_ids(): void
    {
        $fixtures = $this->createCatalogFixtures();

        // Antes del fix, un array con un UUID EXISTENTE pasaba la regla
        // `exists` (que acepta arrays) y sólo reventaba en el INSERT (500).
        $idsByField = [
            'reason_id' => $fixtures['reason']->id,
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
        ];

        foreach ($idsByField as $field => $realId) {
            $payload = $this->validEntryPayload($fixtures, [$field => [$realId]]);

            $response = $this->actingAs($fixtures['requester'], 'api')
                ->postJson('/api/adjustments', $payload);

            $response->assertStatus(422)->assertJsonValidationErrors($field);
        }
    }

    public function test_store_ignores_mass_assignment_of_protected_fields(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $otherUser = $this->createRestrictedUser('supervisor');

        $payload = $this->validEntryPayload($fixtures, [
            'status' => 'approved',
            'responsible_user' => $otherUser->id,
            'adjustment_number' => 'AJU-HACKED-0001',
        ]);

        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.responsible_user', $fixtures['requester']->id);

        $this->assertDatabaseMissing('adjustments', ['adjustment_number' => 'AJU-HACKED-0001']);
        $this->assertDatabaseHas('adjustments', ['responsible_user' => $fixtures['requester']->id, 'status' => 'pending']);
    }

    public function test_store_denies_restricted_role_creating_in_foreign_location(): void
    {
        $fixtures = $this->createCatalogFixtures();
        // 'farm' sin ninguna ubicación administrada: cualquier ubicación es ajena.
        $farmUser = $this->createRestrictedUser('farm');

        $payload = $this->validEntryPayload($fixtures); // destination = bodega que farmUser no administra

        $response = $this->actingAs($farmUser, 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('adjustments', ['responsible_user' => $farmUser->id]);
    }

    public function test_store_allows_restricted_role_creating_in_own_location(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $farmUser = $this->createRestrictedUser('farm');

        $ownWarehouse = Location::create([
            'name' => 'Bodega Propia',
            'type' => 'warehouse',
            'status' => 'active',
            'responsible_user_id' => $farmUser->id,
        ]);

        $payload = $this->validEntryPayload($fixtures, ['destination_location_id' => $ownWarehouse->id]);

        $response = $this->actingAs($farmUser, 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(201)->assertJsonPath('data.responsible_user', $farmUser->id);
    }

    public function test_store_exit_denies_restricted_role_with_foreign_origin(): void
    {
        // Fix round 2/5: confirma que exit NO se aflojó (a diferencia de
        // transfer) al corregir la regresión de deniedLocationMessage.
        $fixtures = $this->createCatalogFixtures();
        $farmUser = $this->createRestrictedUser('farm');

        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'exit',
            'origin_location_id' => $fixtures['origin']->id, // ajena
        ]);

        $response = $this->actingAs($farmUser, 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(403);
    }

    public function test_store_transfer_allows_when_only_origin_is_managed(): void
    {
        // Fix round 2/5: caso de negocio central que la regla estricta original
        // bloqueaba — "devolver producto de mi finca a la bodega central"
        // (origen propio, destino ajeno).
        $fixtures = $this->createCatalogFixtures();
        $farmUser = $this->createRestrictedUser('farm');

        $ownFarm = Location::create([
            'name' => 'Finca Propia',
            'type' => 'farm',
            'status' => 'active',
            'responsible_user_id' => $farmUser->id,
        ]);

        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'transfer',
            'origin_location_id' => $ownFarm->id,
            'destination_location_id' => $fixtures['destination']->id, // ajena
        ]);

        $response = $this->actingAs($farmUser, 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(201);

        // El solicitante debe verla en su index (por origin, además de por
        // responsible_user).
        $ids = collect(
            $this->actingAs($farmUser, 'api')
                ->getJson('/api/adjustments')
                ->assertStatus(200)
                ->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($response->json('data.id')));
    }

    public function test_store_transfer_allows_when_only_destination_is_managed(): void
    {
        // Fix round 2/5: simétrico — "traer producto de la bodega central a mi
        // finca" (destino propio, origen ajeno).
        $fixtures = $this->createCatalogFixtures();
        $farmUser = $this->createRestrictedUser('farm');

        $ownWarehouse = Location::create([
            'name' => 'Bodega Propia',
            'type' => 'warehouse',
            'status' => 'active',
            'responsible_user_id' => $farmUser->id,
        ]);

        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'transfer',
            'origin_location_id' => $fixtures['origin']->id, // ajena
            'destination_location_id' => $ownWarehouse->id,
        ]);

        $response = $this->actingAs($farmUser, 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(201);
    }

    public function test_store_transfer_denies_when_both_locations_are_foreign(): void
    {
        $fixtures = $this->createCatalogFixtures();
        // 'farm' sin ninguna ubicación administrada: origen y destino son ajenos.
        $farmUser = $this->createRestrictedUser('farm');

        $payload = array_merge($this->baseAdjustmentAttributes($fixtures), [
            'type' => 'transfer',
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['destination']->id,
        ]);

        $response = $this->actingAs($farmUser, 'api')
            ->postJson('/api/adjustments', $payload);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('adjustments', ['responsible_user' => $farmUser->id]);
    }

    public function test_index_isolates_by_location_for_restricted_roles(): void
    {
        $fixtures = $this->createCatalogFixtures();

        $supervisor = $this->createRestrictedUser('supervisor');

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

    public function test_index_shows_own_requests_even_outside_managed_locations(): void
    {
        $fixtures = $this->createCatalogFixtures();

        // Supervisor SIN ninguna ubicación administrada (no hay Location con
        // responsible_user_id = su id): la única vía de visibilidad posible
        // es ser el solicitante (responsible_user), que es justo lo que este
        // test verifica (orWhere('responsible_user', ...) en applyLocationScope).
        $supervisor = $this->createRestrictedUser('supervisor');

        $foreignLocation = Location::create([
            'name' => 'Finca Ajena',
            'type' => 'farm',
            'status' => 'active',
        ]);

        $ownRequestOutsideScope = Adjustment::create(array_merge($this->baseAdjustmentAttributes($fixtures), [
            'adjustment_number' => Adjustment::generateAdjustmentNumber(),
            'type' => 'exit',
            'origin_location_id' => $foreignLocation->id,
            'responsible_user' => $supervisor->id,
        ]));

        $ids = collect(
            $this->actingAs($supervisor, 'api')
                ->getJson('/api/adjustments')
                ->assertStatus(200)
                ->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($ownRequestOutsideScope->id));
    }

    public function test_show_denies_access_to_adjustment_outside_managed_locations(): void
    {
        $fixtures = $this->createCatalogFixtures();

        $supervisor = $this->createRestrictedUser('supervisor');

        $ownLocation = Location::create([
            'name' => 'Finca Supervisor',
            'type' => 'farm',
            'status' => 'active',
            'responsible_user_id' => $supervisor->id,
        ]);

        $foreignLocation = Location::create([
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

        $foreignAdjustment = Adjustment::create(array_merge($this->baseAdjustmentAttributes($fixtures), [
            'adjustment_number' => Adjustment::generateAdjustmentNumber(),
            'type' => 'exit',
            'origin_location_id' => $foreignLocation->id,
            'responsible_user' => $fixtures['requester']->id,
        ]));

        // IDOR verificado: antes del fix, GET /api/adjustments/{foreignAdjustment}
        // devolvía 200 con el registro completo (incluidas notes) aunque index()
        // no lo mostrara.
        $this->actingAs($supervisor, 'api')
            ->getJson("/api/adjustments/{$foreignAdjustment->id}")
            ->assertStatus(403);

        $this->actingAs($supervisor, 'api')
            ->getJson("/api/adjustments/{$ownAdjustment->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $ownAdjustment->id);
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
