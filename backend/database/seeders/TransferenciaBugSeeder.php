<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\User;
use App\Models\OutputType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TransferenciaBugSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n=== INICIANDO SEEDER PARA INVESTIGACIÓN DE BUG DE TRANSFERENCIAS ===\n\n";

        // 1. Crear ubicaciones
        $bodegaA = Location::create([
            'name' => 'Bodega A - Test',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        $fincaB = Location::create([
            'name' => 'Finca B - Test',
            'type' => 'farm',
            'status' => 'active',
        ]);

        echo "✅ Ubicaciones creadas:\n";
        echo "   - Bodega A ID: {$bodegaA->id}\n";
        echo "   - Finca B ID: {$fincaB->id}\n\n";

        // 2. Crear usuario de prueba si no existe
        $user = User::firstOrCreate(
            ['email' => 'test@agriflor.com'],
            [
                'name' => 'Usuario Test',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        echo "✅ Usuario de prueba:\n";
        echo "   - Email: test@agriflor.com\n";
        echo "   - Password: password123\n";
        echo "   - User ID: {$user->id}\n\n";

        // 3. Crear producto y marca
        $marca = Brand::create([
            'name' => 'Marca Test',
            'status' => 'active',
        ]);

        $producto = Product::create([
            'name' => 'Fertilizante Test NPK',
            'brand_id' => $marca->id,
            'category' => 'fertilizante',
            'base_unit' => 'kg',
            'active_ingredient' => 'NPK 15-15-15',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        echo "✅ Producto y Marca creados:\n";
        echo "   - Producto ID: {$producto->id}\n";
        echo "   - Producto: {$producto->name}\n";
        echo "   - Marca ID: {$marca->id}\n\n";

        // 4. Crear inventario inicial en Bodega A
        $inventario = Inventory::create([
            'product_id' => $producto->id,
            'brand_id' => $marca->id,
            'location_id' => $bodegaA->id,
            'batch_number' => 'LOTE-TEST-001',
            'quantity' => 100.00,
            'unit' => 'kg',
            'expiration_date' => now()->addMonths(6),
            'unit_price' => 50.00,
            'total_value' => 5000.00,
            'status' => 'good',
        ]);

        echo "✅ Inventario inicial creado:\n";
        echo "   - Inventario ID: {$inventario->id}\n";
        echo "   - Cantidad: 100 kg\n";
        echo "   - Valor: $5,000.00\n";
        echo "   - Ubicación: Bodega A\n\n";

        // 5. Verificar que exista el tipo de salida "transfer"
        $transferType = OutputType::firstOrCreate(
            ['code' => 'transfer'],
            [
                'name' => 'Transferencia',
                'description' => 'Transferencia entre ubicaciones',
                'requires_destination' => true,
            ]
        );

        echo "✅ Tipo de salida verificado:\n";
        echo "   - Output Type ID: {$transferType->id}\n";
        echo "   - Tipo: {$transferType->name}\n\n";

        echo "=== RESUMEN DE IDs PARA PRUEBAS ===\n";
        echo "BODEGA_A_ID={$bodegaA->id}\n";
        echo "FINCA_B_ID={$fincaB->id}\n";
        echo "PRODUCTO_ID={$producto->id}\n";
        echo "MARCA_ID={$marca->id}\n";
        echo "USER_ID={$user->id}\n";
        echo "OUTPUT_TYPE_TRANSFER_ID={$transferType->id}\n\n";

        echo "=== SEEDER COMPLETADO EXITOSAMENTE ===\n\n";
        echo "📝 PRÓXIMOS PASOS:\n";
        echo "1. Login con test@agriflor.com / password123\n";
        echo "2. Crear salida de transferencia de 60 kg\n";
        echo "3. Aprobar la salida\n";
        echo "4. Crear recepción de 60 kg\n";
        echo "5. Verificar si quedan 40 kg en Bodega A\n\n";
    }
}
