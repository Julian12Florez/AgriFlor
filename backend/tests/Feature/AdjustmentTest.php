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
use App\Services\InventoryService;
use Database\Seeders\AdjustmentReasonSeeder;
use Illuminate\Database\QueryException;
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
        return $this->createUserWithRole($role);
    }

    private function createUserWithRole(string $role): User
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

    // ------------------------------------------------------------------
    // Task 6: approve() — la aprobación aplica el stock (solo admin)
    // ------------------------------------------------------------------

    /**
     * Solicitud en estado 'pending' creada directamente (sin pasar por el
     * endpoint) para que cada test de aprobación fije exactamente el escenario
     * que necesita. Por defecto: entrada delta de 10 kg a la bodega destino.
     */
    private function makePendingAdjustment(array $fixtures, array $overrides = []): Adjustment
    {
        return Adjustment::create(array_merge($this->baseAdjustmentAttributes($fixtures), [
            'adjustment_number' => Adjustment::generateAdjustmentNumber(),
            'type' => 'entry',
            'destination_location_id' => $fixtures['destination']->id,
            'unit_price' => 5,
            'status' => 'pending',
            'responsible_user' => $fixtures['requester']->id,
            // Fecha distinta de hoy a propósito: el movimiento debe heredar la
            // fecha REAL del ajuste, no la de la aprobación. Con la fecha de hoy
            // (la que trae baseAdjustmentAttributes) esa aserción no discriminaría.
            'movement_date' => now()->subMonth()->toDateString(),
        ], $overrides));
    }

    /**
     * Lote real en `inventory` (unidad base kg) para probar reducciones FIFO,
     * modo absoluto y costeo del traslado.
     */
    private function seedBatch(
        array $fixtures,
        string $locationId,
        string $batchNumber,
        float $quantity,
        float $unitPrice = 10.0,
        ?string $expirationDate = null
    ): Inventory {
        return Inventory::create([
            'product_id' => $fixtures['product']->id,
            'brand_id' => $fixtures['brand']->id,
            'location_id' => $locationId,
            'batch_number' => $batchNumber,
            'quantity' => $quantity,
            'unit' => 'kg',
            'expiration_date' => $expirationDate,
            'unit_price' => $unitPrice,
            'total_value' => $quantity * $unitPrice,
            'status' => 'good',
        ]);
    }

    /**
     * Presentación (PackagingUnit) asociada al producto: 1 {name} = {baseQuantity} kg.
     */
    private function attachPackagingUnit(array $fixtures, string $name, float $baseQuantity): PackagingUnit
    {
        $packagingUnit = PackagingUnit::create([
            'name' => $name,
            'base_quantity' => $baseQuantity,
            'base_unit' => 'kg',
        ]);

        $fixtures['product']->packagingUnits()->attach($packagingUnit->id);

        return $packagingUnit;
    }

    private function approve(User $user, Adjustment $adjustment)
    {
        return $this->actingAs($user, 'api')
            ->putJson("/api/adjustments/{$adjustment->id}/approve");
    }

    private function reject(User $user, Adjustment $adjustment, ?string $rejectionReason = 'Motivo de rechazo de prueba')
    {
        return $this->actingAs($user, 'api')
            ->putJson("/api/adjustments/{$adjustment->id}/reject", [
                'rejection_reason' => $rejectionReason,
            ]);
    }

    private function cancel(User $user, Adjustment $adjustment)
    {
        return $this->actingAs($user, 'api')
            ->putJson("/api/adjustments/{$adjustment->id}/cancel");
    }

    /**
     * Existencia total (kg) del producto/marca del ajuste en una ubicación.
     */
    private function stockAt(array $fixtures, string $locationId): float
    {
        return (float) Inventory::where('product_id', $fixtures['product']->id)
            ->where('brand_id', $fixtures['brand']->id)
            ->where('location_id', $locationId)
            ->sum('quantity');
    }

    private function movementsOf(Adjustment $adjustment)
    {
        return InventoryMovement::where('related_document_id', $adjustment->id)->get();
    }

    public function test_approve_requires_admin_role(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $adjustment = $this->makePendingAdjustment($fixtures);

        $this->approve($this->createUserWithRole('warehouse'), $adjustment)->assertStatus(403);
        $this->approve($this->createUserWithRole('supervisor'), $adjustment)->assertStatus(403);

        // Ningún intento denegado puede haber tocado el inventario.
        $this->assertDatabaseHas('adjustments', ['id' => $adjustment->id, 'status' => 'pending']);
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(0, Inventory::count());

        $this->approve($this->createUserWithRole('admin'), $adjustment)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_approve_entry_delta_increases_stock_and_records_movement(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');
        $adjustment = $this->makePendingAdjustment($fixtures, ['quantity' => 10, 'unit_price' => 5]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        $batch = Inventory::where('location_id', $fixtures['destination']->id)->first();
        $this->assertNotNull($batch);
        // Sin batch_number en la solicitud, el lote queda trazable al documento.
        $this->assertSame('AJU-' . substr($adjustment->id, 0, 8), $batch->batch_number);
        $this->assertEqualsWithDelta(10, (float) $batch->quantity, 0.01);
        $this->assertSame('kg', $batch->unit);
        $this->assertEqualsWithDelta(5, (float) $batch->unit_price, 0.01);

        $movements = $this->movementsOf($adjustment);
        $this->assertCount(1, $movements);

        $movement = $movements->first();
        $this->assertSame('entry', $movement->type);
        // El enum de inventory_movements NO tiene 'adjustment'; y el tipo de
        // documento debe guardarse con el FQCN (no el alias del morph map),
        // que es contra lo que comparan los informes.
        $this->assertSame('App\Models\Adjustment', $movement->related_document_type);
        $this->assertSame($fixtures['destination']->id, $movement->location_id);
        $this->assertEqualsWithDelta(10, (float) $movement->quantity, 0.01);
        $this->assertSame('kg', $movement->unit);
        $this->assertSame($admin->id, $movement->responsible_user);
        $this->assertEqualsWithDelta(50, (float) $movement->total_price, 0.01);
        $this->assertSame(
            $adjustment->movement_date->toDateString(),
            $movement->movement_date->toDateString()
        );
        // Palabra load-bearing: monthlyReport clasifica la columna "Aumentos"
        // buscando 'aumento'/'ajuste positivo' en las observaciones.
        $this->assertStringContainsStringIgnoringCase('aumento', $movement->observations);

        $adjustment->refresh();
        $this->assertSame('approved', $adjustment->status);
        $this->assertSame($admin->id, $adjustment->approved_by);
        $this->assertNotNull($adjustment->approved_at);
        $this->assertEqualsWithDelta(10, (float) $adjustment->quantity_base, 0.01);
    }

    public function test_approve_exit_delta_reduces_stock_fifo(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        // FIFO ordena por vencimiento: LOTE-A sale primero.
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-A', 3, 8, '2030-01-01');
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-B', 7, 10, '2031-01-01');

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity' => 4,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => null,
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        $this->assertEqualsWithDelta(6, $this->stockAt($fixtures, $fixtures['origin']->id), 0.01);
        $this->assertDatabaseMissing('inventory', [
            'location_id' => $fixtures['origin']->id,
            'batch_number' => 'LOTE-A',
        ]);
        $remaining = Inventory::where('batch_number', 'LOTE-B')->first();
        $this->assertEqualsWithDelta(6, (float) $remaining->quantity, 0.01);

        $movements = $this->movementsOf($adjustment);
        $this->assertCount(1, $movements);

        $movement = $movements->first();
        $this->assertSame('exit', $movement->type);
        $this->assertSame($fixtures['origin']->id, $movement->location_id);
        $this->assertEqualsWithDelta(4, (float) $movement->quantity, 0.01);
        $this->assertSame('App\Models\Adjustment', $movement->related_document_type);
        // Valorado al costo REAL que consumió FIFO: 3 kg @8 + 1 kg @10 = 34
        // (34/4 = 8.5). NO al promedio de toda la ubicación (9.4 → 37.6), que
        // daría de baja 3.6 de valor que el inventario nunca tuvo aquí.
        $this->assertEqualsWithDelta(8.5, (float) $movement->unit_price, 0.01);
        $this->assertEqualsWithDelta(34, (float) $movement->total_price, 0.01);
        $this->assertStringContainsStringIgnoringCase('disminuc', $movement->observations);
    }

    public function test_approve_exit_insufficient_stock_fails_and_keeps_everything_intact(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-A', 3);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity' => 10,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => null,
        ]);

        $response = $this->approve($admin, $adjustment)->assertStatus(422);

        $message = $response->json('message');
        $this->assertStringContainsString('Producto Test', $message);
        $this->assertStringContainsString('10', $message);
        $this->assertStringContainsString('3', $message);

        $this->assertEqualsWithDelta(3, $this->stockAt($fixtures, $fixtures['origin']->id), 0.01);
        $this->assertCount(0, $this->movementsOf($adjustment));

        $adjustment->refresh();
        $this->assertSame('pending', $adjustment->status);
        $this->assertNull($adjustment->approved_by);
        $this->assertNull($adjustment->quantity_base);
    }

    public function test_approve_transfer_moves_stock_from_origin_to_destination(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-ORIGEN', 10, 8);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 4,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['destination']->id,
            'unit_price' => null,
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        $this->assertEqualsWithDelta(6, $this->stockAt($fixtures, $fixtures['origin']->id), 0.01);
        $this->assertEqualsWithDelta(4, $this->stockAt($fixtures, $fixtures['destination']->id), 0.01);

        // Un traslado reubica la mercancía, no la revalúa: el lote destino
        // hereda el costo del origen (8), no un precio nuevo.
        $destinationBatch = Inventory::where('location_id', $fixtures['destination']->id)->first();
        $this->assertEqualsWithDelta(8, (float) $destinationBatch->unit_price, 0.01);

        $movements = $this->movementsOf($adjustment);
        $this->assertCount(2, $movements);

        $exit = $movements->firstWhere('type', 'exit');
        $entry = $movements->firstWhere('type', 'entry');
        $this->assertNotNull($exit);
        $this->assertNotNull($entry);
        $this->assertSame($fixtures['origin']->id, $exit->location_id);
        $this->assertSame($fixtures['destination']->id, $entry->location_id);
        $this->assertEqualsWithDelta(4, (float) $exit->quantity, 0.01);
        $this->assertEqualsWithDelta(4, (float) $entry->quantity, 0.01);

        // Decisión documentada: la pata de SALIDA de un traslado no lleva las
        // palabras 'disminución'/'ajuste negativo' porque monthlyReport ya
        // contabiliza esa salida en la matriz de envíos (total_shipped, vía
        // related_document_id compartido); marcarla además como disminución la
        // restaría DOS veces y descuadraría el informe.
        $this->assertStringNotContainsStringIgnoringCase('disminuc', $exit->observations);
        $this->assertStringNotContainsStringIgnoringCase('negativ', $exit->observations);
        // La pata de ENTRADA sí es un aumento para la ubicación destino, y
        // ningún otro concepto del informe la explica.
        $this->assertStringContainsStringIgnoringCase('aumento', $entry->observations);
    }

    public function test_approve_transfer_preserves_total_inventory_value(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        // Valor total antes: 3*8 + 7*10 = 94.
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-A', 3, 8, '2030-01-01');
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-B', 7, 10, '2031-01-01');

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 4,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['destination']->id,
            'unit_price' => null,
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        // Un traslado NO crea ni destruye valor: mueve 3@8 + 1@10 = 34 de costo
        // real. Con el promedio de la ubicación (9.4) el destino se acreditaría
        // a 37.6 y el inventario total pasaría a 97.6: 3.6 de la nada.
        $this->assertEqualsWithDelta(94, (float) Inventory::sum('total_value'), 0.01);
        $this->assertEqualsWithDelta(60, (float) Inventory::where('location_id', $fixtures['origin']->id)->sum('total_value'), 0.01);

        $destinationBatch = Inventory::where('location_id', $fixtures['destination']->id)->sole();
        $this->assertEqualsWithDelta(4, (float) $destinationBatch->quantity, 0.01);
        $this->assertEqualsWithDelta(8.5, (float) $destinationBatch->unit_price, 0.01);
        $this->assertEqualsWithDelta(34, (float) $destinationBatch->total_value, 0.01);

        $exit = $this->movementsOf($adjustment)->firstWhere('type', 'exit');
        $entry = $this->movementsOf($adjustment)->firstWhere('type', 'entry');
        $this->assertEqualsWithDelta(34, (float) $exit->total_price, 0.01);
        $this->assertEqualsWithDelta(34, (float) $entry->total_price, 0.01);
    }

    public function test_approve_transfer_from_zero_cost_batch_preserves_value(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        // Costo 0 legítimo (producto donado / carga inicial sin valorar).
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-SIN-COSTO', 10, 0);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 4,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['destination']->id,
            'unit_price' => 5, // debe ignorarse: el costo consumido SÍ se conoce, y es 0
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        // Costo 0 es un costo CONOCIDO: tomarlo por "desconocido" y sustituirlo
        // por el unit_price del ajuste acreditaría el destino a 5/kg y el
        // inventario total pasaría de 0 a 20.
        $this->assertEqualsWithDelta(0, (float) Inventory::sum('total_value'), 0.01);

        $destinationBatch = Inventory::where('location_id', $fixtures['destination']->id)->sole();
        $this->assertEqualsWithDelta(4, (float) $destinationBatch->quantity, 0.01);
        $this->assertEqualsWithDelta(0, (float) $destinationBatch->unit_price, 0.01);

        foreach ($this->movementsOf($adjustment) as $movement) {
            $this->assertEqualsWithDelta(0, (float) $movement->unit_price, 0.01);
            $this->assertEqualsWithDelta(0, (float) $movement->total_price, 0.01);
        }
    }

    public function test_approve_exit_from_zero_cost_batch_records_zero_value(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-SIN-COSTO', 10, 0);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity' => 4,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => 5, // idem: no puede valorar una baja de stock sin costo
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        // El kardex no puede dar de baja 20 de un inventario que valía 0.
        $movement = $this->movementsOf($adjustment)->sole();
        $this->assertEqualsWithDelta(0, (float) $movement->unit_price, 0.01);
        $this->assertEqualsWithDelta(0, (float) $movement->total_price, 0.01);
        $this->assertEqualsWithDelta(0, (float) Inventory::sum('total_value'), 0.01);
    }

    public function test_approve_transfer_keeps_nearest_expiration_when_merging_batches(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $nearExpiry = now()->addDays(10)->toDateString();
        // Mismo número de lote en ambas ubicaciones: la entrada se fusiona con
        // el lote que YA existe en el destino.
        $this->seedBatch($fixtures, $fixtures['destination']->id, 'L1', 5, 8, $nearExpiry);
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'L1', 5, 8, '2031-01-01');

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 3,
            'batch_number' => 'L1',
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['destination']->id,
            'unit_price' => null,
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        // La fusión NO puede rejuvenecer el lote del destino: se queda con la
        // fecha más próxima de las dos, y con el status que le corresponde.
        $destinationBatch = Inventory::where('location_id', $fixtures['destination']->id)->sole();
        $this->assertEqualsWithDelta(8, (float) $destinationBatch->quantity, 0.01);
        $this->assertSame($nearExpiry, $destinationBatch->expiration_date->toDateString());
        $this->assertSame('near_expiry', $destinationBatch->status);
    }

    public function test_approve_transfer_inherits_expiration_date_from_consumed_batches(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $nearExpiry = now()->addDays(10)->toDateString();
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-PROXIMO', 5, 8, $nearExpiry);
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-LEJANO', 5, 8, '2031-01-01');

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'transfer',
            'quantity' => 3,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => $fixtures['destination']->id,
            'unit_price' => null,
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        // El producto no rejuvenece al cambiar de bodega: el lote destino hereda
        // el vencimiento del lote consumido (el más próximo si fueran varios),
        // con lo que además conserva su prioridad en el FIFO del destino.
        $destinationBatch = Inventory::where('location_id', $fixtures['destination']->id)->sole();
        $this->assertSame($nearExpiry, $destinationBatch->expiration_date->toDateString());
        $this->assertSame('near_expiry', $destinationBatch->status);
    }

    public function test_approve_absolute_entry_sets_batch_to_target_quantity(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $this->seedBatch($fixtures, $fixtures['destination']->id, 'LOTE-CONTEO', 6, 5);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'quantity_mode' => 'absolute',
            'quantity' => 10,
            'batch_number' => 'LOTE-CONTEO',
            'unit_price' => 5,
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        $batch = Inventory::where('batch_number', 'LOTE-CONTEO')->first();
        $this->assertEqualsWithDelta(10, (float) $batch->quantity, 0.01);

        // El movimiento registra el DELTA aplicado (10 - 6), no el absoluto.
        $movement = $this->movementsOf($adjustment)->sole();
        $this->assertSame('entry', $movement->type);
        $this->assertEqualsWithDelta(4, (float) $movement->quantity, 0.01);

        $adjustment->refresh();
        $this->assertEqualsWithDelta(4, (float) $adjustment->quantity_base, 0.01);
    }

    public function test_approve_absolute_entry_rejects_target_below_current_stock(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $this->seedBatch($fixtures, $fixtures['destination']->id, 'LOTE-CONTEO', 10, 5);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'quantity_mode' => 'absolute',
            'quantity' => 4,
            'batch_number' => 'LOTE-CONTEO',
            'unit_price' => 5,
        ]);

        $response = $this->approve($admin, $adjustment)->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('salida', $response->json('message'));

        $this->assertEqualsWithDelta(10, $this->stockAt($fixtures, $fixtures['destination']->id), 0.01);
        $this->assertCount(0, $this->movementsOf($adjustment));
        $this->assertSame('pending', $adjustment->refresh()->status);
    }

    public function test_approve_absolute_exit_sets_batch_to_target_quantity(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-CONTEO', 10, 5);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity_mode' => 'absolute',
            'quantity' => 4,
            'batch_number' => 'LOTE-CONTEO',
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => null,
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        $batch = Inventory::where('batch_number', 'LOTE-CONTEO')->first();
        $this->assertEqualsWithDelta(4, (float) $batch->quantity, 0.01);

        $movement = $this->movementsOf($adjustment)->sole();
        $this->assertSame('exit', $movement->type);
        $this->assertEqualsWithDelta(6, (float) $movement->quantity, 0.01);
    }

    public function test_approve_absolute_exit_rejects_target_above_current_stock(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-CONTEO', 4, 5);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity_mode' => 'absolute',
            'quantity' => 10,
            'batch_number' => 'LOTE-CONTEO',
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => null,
        ]);

        $response = $this->approve($admin, $adjustment)->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('entrada', $response->json('message'));

        $this->assertEqualsWithDelta(4, $this->stockAt($fixtures, $fixtures['origin']->id), 0.01);
        $this->assertSame('pending', $adjustment->refresh()->status);
    }

    public function test_approve_entry_in_packaging_unit_converts_to_base_only_once(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');
        $this->attachPackagingUnit($fixtures, 'Bulto', 50);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'unit' => 'Bulto',
            'quantity' => 10,
            'unit_price' => 2, // por unidad base (kg)
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        // 10 Bultos * 50 kg = 500 kg. Con doble conversión serían 25.000 kg.
        $batch = Inventory::where('location_id', $fixtures['destination']->id)->first();
        $this->assertEqualsWithDelta(500, (float) $batch->quantity, 0.01);
        $this->assertSame('kg', $batch->unit);

        $movement = $this->movementsOf($adjustment)->sole();
        $this->assertEqualsWithDelta(10, (float) $movement->quantity, 0.01);
        $this->assertSame('Bulto', $movement->unit);
        // total = 500 kg * 2; unit_price se expresa en la unidad del movimiento
        // para conservar el invariante total = cantidad * precio unitario.
        $this->assertEqualsWithDelta(1000, (float) $movement->total_price, 0.01);
        $this->assertEqualsWithDelta(100, (float) $movement->unit_price, 0.01);

        $this->assertEqualsWithDelta(500, (float) $adjustment->refresh()->quantity_base, 0.01);
    }

    public function test_approve_exit_in_packaging_unit_converts_to_base_only_once(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');
        $this->attachPackagingUnit($fixtures, 'Bulto', 50);

        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-KG', 600, 3);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'unit' => 'Bulto',
            'quantity' => 10,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => null,
        ]);

        // Con doble conversión pediría 10*50*50 = 25.000 kg y devolvería 422.
        $this->approve($admin, $adjustment)->assertStatus(200);

        $this->assertEqualsWithDelta(100, $this->stockAt($fixtures, $fixtures['origin']->id), 0.01);

        $movement = $this->movementsOf($adjustment)->sole();
        $this->assertSame('exit', $movement->type);
        $this->assertEqualsWithDelta(10, (float) $movement->quantity, 0.01);
        $this->assertSame('Bulto', $movement->unit);
    }

    public function test_approve_twice_does_not_duplicate_stock(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');
        $adjustment = $this->makePendingAdjustment($fixtures, ['quantity' => 10, 'unit_price' => 5]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        $this->approve($admin, $adjustment)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue procesada.');

        $this->assertEqualsWithDelta(10, $this->stockAt($fixtures, $fixtures['destination']->id), 0.01);
        $this->assertCount(1, $this->movementsOf($adjustment));
    }

    public function test_approve_absolute_exit_to_zero_empties_the_batch(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-CONTEO', 7, 5);

        // Conteo físico que da cero: el lote debe quedar en 0 (dado de baja).
        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity_mode' => 'absolute',
            'quantity' => 0,
            'batch_number' => 'LOTE-CONTEO',
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => null,
        ]);

        $this->approve($admin, $adjustment)->assertStatus(200);

        $this->assertEqualsWithDelta(0, $this->stockAt($fixtures, $fixtures['origin']->id), 0.01);

        $movement = $this->movementsOf($adjustment)->sole();
        $this->assertSame('exit', $movement->type);
        $this->assertEqualsWithDelta(7, (float) $movement->quantity, 0.01);
    }

    public function test_approve_does_not_leak_technical_error_details(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');
        $adjustment = $this->makePendingAdjustment($fixtures);

        // Un fallo técnico (no una regla de negocio) no puede devolverle al
        // cliente el mensaje crudo: una QueryException lleva dentro el SQL y sus
        // bindings.
        $this->app->bind(InventoryService::class, fn () => new class extends InventoryService {
            public function addStock(
                string $productId,
                string $brandId,
                string $locationId,
                float $quantityInBase,
                float $unitPrice,
                string $batchNumber,
                ?string $expirationDate = null
            ): void {
                throw new QueryException(
                    'mysql',
                    'insert into `inventory` (`columna_secreta`) values (?)',
                    ['dato-sensible'],
                    new \PDOException('SQLSTATE[42S22]: Column not found')
                );
            }
        });

        $response = $this->approve($admin, $adjustment)->assertStatus(500);

        $message = $response->json('message');
        $this->assertStringNotContainsString('insert into', $message);
        $this->assertStringNotContainsString('dato-sensible', $message);
        $this->assertStringNotContainsString('SQLSTATE', $message);

        $this->assertSame('pending', $adjustment->refresh()->status);
        $this->assertCount(0, $this->movementsOf($adjustment));
    }

    public function test_store_accepts_zero_quantity_only_in_absolute_mode(): void
    {
        $fixtures = $this->createCatalogFixtures();

        // Absoluto: 0 es legítimo ("el conteo físico dio cero").
        $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $this->validEntryPayload($fixtures, [
                'quantity_mode' => 'absolute',
                'batch_number' => 'LOTE-CONTEO',
                'quantity' => 0,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.quantity', 0);

        // Delta: mover 0 no ajusta nada.
        $response = $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $this->validEntryPayload($fixtures, ['quantity' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        $this->assertContains('La cantidad debe ser mayor a 0.', $response->json('errors.quantity'));

        // Negativo: nunca, en ningún modo.
        $this->actingAs($fixtures['requester'], 'api')
            ->postJson('/api/adjustments', $this->validEntryPayload($fixtures, [
                'quantity_mode' => 'absolute',
                'batch_number' => 'LOTE-CONTEO',
                'quantity' => -1,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    // ------------------------------------------------------------------
    // Task 7: reject() (solo admin) y cancel() (solo el solicitante)
    // ------------------------------------------------------------------

    public function test_reject_by_admin_sets_rejected_and_keeps_stock_intact(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-A', 5, 8);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity' => 3,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => null,
        ]);

        $inventoryCountBefore = Inventory::count();
        $movementCountBefore = InventoryMovement::count();
        $batchQuantityBefore = (float) Inventory::where('batch_number', 'LOTE-A')->value('quantity');

        $this->reject($admin, $adjustment, 'No corresponde a un ajuste real')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'No corresponde a un ajuste real');

        $adjustment->refresh();
        $this->assertSame('rejected', $adjustment->status);
        $this->assertSame('No corresponde a un ajuste real', $adjustment->rejection_reason);

        // El rechazo NO debe tocar el inventario en absoluto.
        $this->assertSame($inventoryCountBefore, Inventory::count());
        $this->assertSame($movementCountBefore, InventoryMovement::count());
        $this->assertSame(0, $movementCountBefore);
        $this->assertEqualsWithDelta(
            $batchQuantityBefore,
            (float) Inventory::where('batch_number', 'LOTE-A')->value('quantity'),
            0.01
        );
    }

    public function test_reject_requires_rejection_reason(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');
        $adjustment = $this->makePendingAdjustment($fixtures);

        $this->actingAs($admin, 'api')
            ->putJson("/api/adjustments/{$adjustment->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');

        $this->assertSame('pending', $adjustment->refresh()->status);
    }

    public function test_reject_denied_for_non_admin_roles(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $adjustment = $this->makePendingAdjustment($fixtures);

        $this->reject($this->createUserWithRole('warehouse'), $adjustment)->assertStatus(403);
        $this->reject($this->createUserWithRole('supervisor'), $adjustment)->assertStatus(403);

        $this->assertSame('pending', $adjustment->refresh()->status);
    }

    public function test_reject_of_already_processed_adjustment_fails(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $approved = $this->makePendingAdjustment($fixtures);
        $this->approve($admin, $approved)->assertStatus(200);

        $this->reject($admin, $approved, 'Motivo tardío')
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue procesada.');

        $alreadyRejected = $this->makePendingAdjustment($fixtures);
        $this->reject($admin, $alreadyRejected, 'Primer rechazo')->assertStatus(200);
        $this->reject($admin, $alreadyRejected, 'Segundo rechazo')
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue procesada.');
    }

    public function test_reject_then_approve_fails_and_keeps_stock_intact(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');
        $adjustment = $this->makePendingAdjustment($fixtures);

        $this->reject($admin, $adjustment, 'No corresponde')->assertStatus(200);

        $this->approve($admin, $adjustment)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue procesada.');

        $this->assertSame(0, Inventory::count());
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame('rejected', $adjustment->refresh()->status);
    }

    public function test_cancel_by_requester_sets_cancelled_and_keeps_stock_intact(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $this->seedBatch($fixtures, $fixtures['origin']->id, 'LOTE-A', 5, 8);

        $adjustment = $this->makePendingAdjustment($fixtures, [
            'type' => 'exit',
            'quantity' => 3,
            'origin_location_id' => $fixtures['origin']->id,
            'destination_location_id' => null,
            'unit_price' => null,
        ]);

        $inventoryCountBefore = Inventory::count();
        $movementCountBefore = InventoryMovement::count();
        $batchQuantityBefore = (float) Inventory::where('batch_number', 'LOTE-A')->value('quantity');

        $this->cancel($fixtures['requester'], $adjustment)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');

        $adjustment->refresh();
        $this->assertSame('cancelled', $adjustment->status);

        $this->assertSame($inventoryCountBefore, Inventory::count());
        $this->assertSame($movementCountBefore, InventoryMovement::count());
        $this->assertEqualsWithDelta(
            $batchQuantityBefore,
            (float) Inventory::where('batch_number', 'LOTE-A')->value('quantity'),
            0.01
        );
    }

    public function test_cancel_denied_for_other_users_including_admin(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $adjustment = $this->makePendingAdjustment($fixtures);

        // Otro admin (no el solicitante) no puede cancelar la solicitud ajena.
        $this->cancel($this->createUserWithRole('admin'), $adjustment)->assertStatus(403);
        $this->cancel($this->createUserWithRole('warehouse'), $adjustment)->assertStatus(403);

        $this->assertSame('pending', $adjustment->refresh()->status);
    }

    public function test_cancel_of_non_pending_adjustment_fails(): void
    {
        $fixtures = $this->createCatalogFixtures();
        $admin = $this->createUserWithRole('admin');

        $approved = $this->makePendingAdjustment($fixtures);
        $this->approve($admin, $approved)->assertStatus(200);
        $this->cancel($fixtures['requester'], $approved)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue procesada.');

        $rejected = $this->makePendingAdjustment($fixtures);
        $this->reject($admin, $rejected, 'Motivo')->assertStatus(200);
        $this->cancel($fixtures['requester'], $rejected)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue procesada.');

        $cancelled = $this->makePendingAdjustment($fixtures);
        $this->cancel($fixtures['requester'], $cancelled)->assertStatus(200);
        $this->cancel($fixtures['requester'], $cancelled)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue procesada.');
    }
}
