<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Brand;
use App\Models\PackagingUnit;
use App\Models\User;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener primera marca (usar primera creada)
        $firstBrand = Brand::first();

        // Obtener unidades de empaque
        $bulto = PackagingUnit::where('name', 'Bulto')->first();
        $saco = PackagingUnit::where('name', 'Saco')->first();
        $kg = PackagingUnit::where('name', 'Kilogramo')->first();
        $mediaUnidad = PackagingUnit::where('name', 'Media Unidad')->first();
        $galon = PackagingUnit::where('name', 'Galon')->first();
        $litro = PackagingUnit::where('name', 'Litro')->first();
        $medioLitro = PackagingUnit::where('name', 'Medio Litro')->first();

        // Obtener primer usuario admin para created_by
        $adminUser = User::where('role', 'admin')->first();

        // Producto 1: NPK 15-15-15
        $npk = Product::create([
            'name' => 'NPK 15-15-15',
            'brand_id' => $firstBrand->id,
            'category' => 'fertilizante',
            'base_unit' => 'kg',
            'active_ingredient' => 'NPK',
            'min_stock' => 500,
            'description' => 'Fertilizante completo balanceado con nitrogeno, fosforo y potasio',
            'status' => 'active',
            'created_by' => $adminUser->id,
        ]);

        $npk->packagingUnits()->attach([$bulto->id, $saco->id, $kg->id]);

        // Producto 2: Urea 46%
        $urea = Product::create([
            'name' => 'Urea 46%',
            'brand_id' => $firstBrand->id,
            'category' => 'fertilizante',
            'base_unit' => 'kg',
            'active_ingredient' => 'Urea',
            'min_stock' => 300,
            'description' => 'Fertilizante nitrogenado de alta concentracion',
            'status' => 'active',
            'created_by' => $adminUser->id,
        ]);

        $urea->packagingUnits()->attach([$bulto->id, $saco->id, $kg->id]);

        // Producto 3: Clorpirifos 48%
        $clorpirifos = Product::create([
            'name' => 'Clorpirifos 48%',
            'brand_id' => $firstBrand->id,
            'category' => 'pesticida',
            'base_unit' => 'L',
            'active_ingredient' => 'Clorpirifos',
            'min_stock' => 50,
            'description' => 'Insecticida organofosforado de amplio espectro',
            'status' => 'active',
            'created_by' => $adminUser->id,
        ]);

        $clorpirifos->packagingUnits()->attach([$galon->id, $litro->id, $medioLitro->id]);

        // Producto 4: Glifosato 48% SL
        $glifosato = Product::create([
            'name' => 'Glifosato 48% SL',
            'brand_id' => $firstBrand->id,
            'category' => 'herbicida',
            'base_unit' => 'L',
            'active_ingredient' => 'Glifosato',
            'min_stock' => 100,
            'description' => 'Herbicida sistemico no selectivo',
            'status' => 'active',
            'created_by' => $adminUser->id,
        ]);

        $glifosato->packagingUnits()->attach([$galon->id, $litro->id, $medioLitro->id]);

        // Producto 5: Mancozeb 80% WP
        $mancozeb = Product::create([
            'name' => 'Mancozeb 80% WP',
            'brand_id' => $firstBrand->id,
            'category' => 'fungicida',
            'base_unit' => 'kg',
            'active_ingredient' => 'Mancozeb',
            'min_stock' => 75,
            'description' => 'Fungicida protectante de contacto',
            'status' => 'active',
            'created_by' => $adminUser->id,
        ]);

        $mancozeb->packagingUnits()->attach([$saco->id, $mediaUnidad->id, $kg->id]);

        // Producto 6: Abono Foliar Completo
        $abonoFoliar = Product::create([
            'name' => 'Abono Foliar Completo',
            'brand_id' => $firstBrand->id,
            'category' => 'fertilizante',
            'base_unit' => 'L',
            'active_ingredient' => 'Micronutrientes',
            'min_stock' => 60,
            'description' => 'Fertilizante foliar con micronutrientes esenciales',
            'status' => 'active',
            'created_by' => $adminUser->id,
        ]);

        $abonoFoliar->packagingUnits()->attach([$galon->id, $litro->id, $medioLitro->id]);

        // Producto 7: Imidacloprid 35% SC
        $imidacloprid = Product::create([
            'name' => 'Imidacloprid 35% SC',
            'brand_id' => $firstBrand->id,
            'category' => 'pesticida',
            'base_unit' => 'L',
            'active_ingredient' => 'Imidacloprid',
            'min_stock' => 40,
            'description' => 'Insecticida sistemico de amplio espectro',
            'status' => 'active',
            'created_by' => $adminUser->id,
        ]);

        $imidacloprid->packagingUnits()->attach([$galon->id, $litro->id]);

        // Producto 8: Azufre Mojable 80%
        $azufre = Product::create([
            'name' => 'Azufre Mojable 80%',
            'brand_id' => $firstBrand->id,
            'category' => 'fungicida',
            'base_unit' => 'kg',
            'active_ingredient' => 'Azufre',
            'min_stock' => 200,
            'description' => 'Fungicida y acaricida de contacto',
            'status' => 'active',
            'created_by' => $adminUser->id,
        ]);

        $azufre->packagingUnits()->attach([$bulto->id, $saco->id, $kg->id]);
    }
}
