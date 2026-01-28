<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Location;
use App\Models\OutputProduct;
use App\Models\Product;
use App\Models\ProductOutput;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductOutputSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        $this->command->info('Clearing existing outputs and receptions...');
        \DB::table('reception_items')->delete();
        \DB::table('receptions')->delete();
        \DB::table('output_products')->delete();
        \DB::table('product_outputs')->delete();

        // Get necessary data
        $warehouse = Location::where('type', 'warehouse')->first();
        $farms = Location::where('type', 'farm')->limit(3)->get();
        $products = Product::limit(10)->get();
        $brands = Brand::all();
        $admin = User::where('role', 'admin')->first();

        if (!$warehouse || $farms->count() < 2 || $products->count() < 5 || !$admin) {
            $this->command->error('Required data not found. Please seed locations, products, brands, and users first.');
            return;
        }

        $this->command->info('Creating product outputs and pending receptions...');

        // Create 5 product outputs with their receptions
        for ($i = 1; $i <= 5; $i++) {
            $destinationFarm = $farms->random();
            $outputNumber = 'OUT-' . date('Y') . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);

            // Create product output
            $output = ProductOutput::create([
                'output_number' => $outputNumber,
                'technical_order_id' => null,
                'output_date' => now()->subDays(rand(1, 15)),
                'origin_location_id' => $warehouse->id,
                'destination_location_id' => $destinationFarm->id,
                'status' => $i <= 3 ? 'partial' : 'pending', // First 3 partial, rest pending
                'total_cost' => 0, // Will be calculated
                'observations' => $i <= 2 ? 'Envío urgente para aplicación técnica' : null,
                'responsible_user' => $admin->id,
            ]);

            // Add 2-4 products to each output
            $numProducts = rand(2, 4);
            $selectedProducts = $products->random($numProducts);
            $totalCost = 0;

            foreach ($selectedProducts as $product) {
                $brand = $brands->random();
                $quantityRequested = rand(5, 50);
                $quantityDelivered = $output->status === 'completed' ? $quantityRequested : rand(0, (int)($quantityRequested * 0.8));
                $unitCost = rand(5000, 50000);

                // Determine unit - use kg for fertilizers, L for liquids, units for others
                $unit = 'kg'; // Default
                if ($product->category === 'fertilizer') {
                    $unit = 'kg';
                } elseif (in_array($product->category, ['pesticide', 'herbicide', 'fungicide'])) {
                    $unit = 'L';
                }

                OutputProduct::create([
                    'output_id' => $output->id,
                    'product_id' => $product->id,
                    'brand_id' => $brand->id,
                    'quantity_requested' => $quantityRequested,
                    'quantity_delivered' => $quantityDelivered,
                    'unit' => $unit,
                    'batch_number' => 'BATCH-' . strtoupper(substr(md5($product->id . time()), 0, 8)),
                    'expiration_date' => now()->addMonths(rand(6, 24)),
                ]);

                $totalCost += $quantityDelivered * $unitCost;
            }

            // Update total cost
            $output->update(['total_cost' => $totalCost]);

            // Create reception for outputs that are not completed
            if ($output->status !== 'completed') {
                $receptionNumber = 'REC-' . date('Y') . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);

                $reception = Reception::create([
                    'reception_number' => $receptionNumber,
                    'source_id' => $output->id,
                    'source_type' => 'output',
                    'origin_location_id' => $warehouse->id,
                    'destination_location_id' => $destinationFarm->id,
                    'shipment_date' => $output->output_date,
                    'status' => $i % 2 === 0 ? 'pending' : 'partial', // Alternate between pending and partial
                    'total_expected' => 0, // Will be calculated
                    'total_received' => 0, // Will be calculated
                    'completion_percentage' => 0, // Will be calculated
                    'responsible_user' => $admin->id,
                    'observations' => null,
                ]);

                $totalExpected = 0;
                $totalReceived = 0;

                // Create reception items based on output products
                $outputProducts = $output->outputProducts()->get();
                foreach ($outputProducts as $outputProduct) {
                    $quantityExpected = $outputProduct->quantity_delivered;
                    // For partial status, some items are received, for pending, none
                    $quantityReceived = $reception->status === 'partial' ? rand(0, (int)($quantityExpected * 0.6)) : 0;

                    ReceptionItem::create([
                        'reception_id' => $reception->id,
                        'product_id' => $outputProduct->product_id,
                        'brand_id' => $outputProduct->brand_id,
                        'quantity_expected' => $quantityExpected,
                        'quantity_received' => $quantityReceived,
                        'unit' => $outputProduct->unit,
                    ]);

                    $totalExpected += $quantityExpected;
                    $totalReceived += $quantityReceived;
                }

                // Calculate completion percentage
                $completionPercentage = $totalExpected > 0 ? ($totalReceived / $totalExpected) * 100 : 0;

                // Update reception totals
                $reception->update([
                    'total_expected' => $totalExpected,
                    'total_received' => $totalReceived,
                    'completion_percentage' => round($completionPercentage, 2),
                ]);

                $this->command->info("Created output {$outputNumber} with reception {$receptionNumber} ({$reception->status})");
            }
        }

        $this->command->info('Product outputs and receptions seeded successfully!');
    }
}
