<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FarmLot;
use App\Models\Location;

class FarmLotDemoSeeder extends Seeder
{
    /**
     * Seed demo lots with capacity data for all active farms.
     * Idempotent: updates existing lots, creates new ones only if missing.
     */
    public function run(): void
    {
        $farms = Location::where('type', 'farm')
            ->where('status', 'active')
            ->get();

        $created = 0;
        $updated = 0;

        foreach ($farms as $farm) {
            $existingLots = FarmLot::where('location_id', $farm->id)->get();

            if ($existingLots->count() > 0) {
                // Update existing lots with capacity values
                foreach ($existingLots as $lot) {
                    $lot->update([
                        'total_trees'        => rand(200, 800),
                        'area_hectares'      => rand(100, 500) / 100,  // 1.00 - 5.00
                        'total_cubic_meters'  => rand(2000, 10000) / 100, // 20.00 - 100.00
                        'total_linear_meters' => rand(10000, 50000) / 100, // 100.00 - 500.00
                    ]);
                    $updated++;
                }
            } else {
                // Create 1-2 lots for farms without any
                $numLots = rand(1, 2);
                for ($i = 1; $i <= $numLots; $i++) {
                    FarmLot::firstOrCreate(
                        [
                            'location_id' => $farm->id,
                            'name'        => "Lote {$i}",
                        ],
                        [
                            'area'                => rand(100, 500) / 100,
                            'area_unit'           => 'hectares',
                            'total_trees'         => rand(200, 800),
                            'area_hectares'       => rand(100, 500) / 100,
                            'total_cubic_meters'  => rand(2000, 10000) / 100,
                            'total_linear_meters' => rand(10000, 50000) / 100,
                            'description'         => "Lote de aguacate - {$farm->name}",
                            'status'              => 'active',
                        ]
                    );
                    $created++;
                }
            }
        }

        $this->command->info("FarmLotDemoSeeder: {$created} lots created, {$updated} lots updated across {$farms->count()} farms.");
    }
}
