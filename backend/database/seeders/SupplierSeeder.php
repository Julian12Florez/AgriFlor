<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\SupplierContact;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Proveedor 1: Agro Sur S.A.S.
        $supplier1 = Supplier::create([
            'name' => 'Agro Sur S.A.S.',
            'nit' => '900123456-1',
            'address' => 'Calle 50 #45-30, Medellin',
            'city' => 'Medellin',
            'phone' => '604-3001234',
            'email' => 'ventas@agrosur.com',
            'payment_terms' => '30 dias',
            'status' => 'active',
        ]);

        SupplierContact::create([
            'supplier_id' => $supplier1->id,
            'name' => 'Laura Mendoza',
            'position' => 'Gerente Comercial',
            'phone' => '3001234567',
            'email' => 'laura@agrosur.com',
        ]);

        // Proveedor 2: Insumos del Campo Ltda
        $supplier2 = Supplier::create([
            'name' => 'Insumos del Campo Ltda',
            'nit' => '890234567-2',
            'address' => 'Carrera 43A #12-50, Rionegro',
            'city' => 'Rionegro',
            'phone' => '604-5671234',
            'email' => 'info@insumoscampo.com',
            'payment_terms' => '15 dias',
            'status' => 'active',
        ]);

        SupplierContact::create([
            'supplier_id' => $supplier2->id,
            'name' => 'Miguel Angel Ruiz',
            'position' => 'Vendedor',
            'phone' => '3109876543',
            'email' => 'miguel@insumoscampo.com',
        ]);

        // Proveedor 3: Distribuidora Agricola Nacional
        $supplier3 = Supplier::create([
            'name' => 'Distribuidora Agricola Nacional',
            'nit' => '800345678-3',
            'address' => 'Avenida 80 #35-20, Medellin',
            'city' => 'Medellin',
            'phone' => '604-2501234',
            'email' => 'contacto@distrinacional.com',
            'payment_terms' => '45 dias',
            'status' => 'active',
        ]);

        SupplierContact::create([
            'supplier_id' => $supplier3->id,
            'name' => 'Sandra Ospina',
            'position' => 'Jefe de Ventas',
            'phone' => '3157654321',
            'email' => 'sandra@distrinacional.com',
        ]);
    }
}
