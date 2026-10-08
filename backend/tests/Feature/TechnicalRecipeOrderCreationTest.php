<?php

namespace Tests\Feature;

use App\Models\BaseUnit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\TechnicalOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Crear recetas y órdenes técnicas de punta a punta, por el API.
 *
 * En producción hay 0 recetas y 0 órdenes técnicas. Al revisar la auditoría
 * (7-oct-2026) apareció por qué: `technical_order_farms` solo tiene
 * `created_at`, pero la relación `TechnicalOrder::farms()` pedía
 * `withTimestamps()`, así que asociar las fincas a la orden intentaba escribir
 * `updated_at` y la orden no se podía crear.
 */
class TechnicalRecipeOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $producto;
    private Brand $marca;
    private Location $finca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Agrónomo Admin',
            'email' => 'tecnico_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        BaseUnit::create(['name' => 'Kilogramos', 'symbol' => 'kg', 'status' => 'active']);
        $this->marca = Brand::create(['name' => 'Marca Técnica', 'status' => 'active']);
        $categoria = Category::create(['name' => 'Fertilizante', 'slug' => 'fertilizante', 'status' => 'active']);
        $this->producto = Product::create([
            'name' => 'Producto Técnico',
            'brand_id' => $this->marca->id,
            'category_id' => $categoria->id,
            'active_ingredient' => 'x',
            'min_stock' => 0,
            'status' => 'active',
            'base_unit' => 'kg',
            'created_by' => $this->admin->id,
        ]);
        $this->finca = Location::create(['name' => 'Finca Técnica', 'type' => 'farm', 'status' => 'active']);
    }

    public function test_se_crea_una_receta_tecnica(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/technical-recipes', [
            'name' => 'Receta de prueba',
            'category' => 'fertilization',
            'products' => [[
                'product_id' => $this->producto->id,
                'brand_id' => $this->marca->id,
                'quantity' => 2,
                'unit' => 'kg',
            ]],
        ])->assertCreated();
    }

    public function test_se_crea_una_orden_tecnica_con_sus_fincas(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/technical-orders', [
            'order_number' => 'OT-PRUEBA-001',
            'scheduled_date' => now()->toDateString(),
            'farm_ids' => [$this->finca->id],
            'responsible_agronomist' => $this->admin->id,
            'products' => [[
                'product_id' => $this->producto->id,
                'brand_id' => $this->marca->id,
                'quantity' => 3,
                'unit' => 'kg',
            ]],
        ])->assertCreated();

        $orden = TechnicalOrder::where('order_number', 'OT-PRUEBA-001')->firstOrFail();
        $this->assertSame([$this->finca->id], $orden->farms()->pluck('locations.id')->all());
    }
}
