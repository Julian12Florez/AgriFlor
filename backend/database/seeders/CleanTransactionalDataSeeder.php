<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanTransactionalDataSeeder extends Seeder
{
    /**
     * Clean all transactional data while keeping master data intact.
     * This allows for fresh testing with proper master data.
     */
    public function run(): void
    {
        $this->command->info('🧹 Cleaning transactional data...');

        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // 1. Clean inventory movements (must be first due to foreign keys)
            $this->command->info('  → Cleaning inventory_movements...');
            DB::table('inventory_movements')->truncate();

            // 2. Clean inventory
            $this->command->info('  → Cleaning inventory...');
            DB::table('inventory')->truncate();

            // 3. Clean receptions
            $this->command->info('  → Cleaning receptions...');
            DB::table('receptions')->truncate();

            // 4. Clean output products
            $this->command->info('  → Cleaning output_products...');
            DB::table('output_products')->truncate();

            // 5. Clean product outputs
            $this->command->info('  → Cleaning product_outputs...');
            DB::table('product_outputs')->truncate();

            // 6. Clean purchase products (if exists)
            if (DB::getSchemaBuilder()->hasTable('purchase_products')) {
                $this->command->info('  → Cleaning purchase_products...');
                DB::table('purchase_products')->truncate();
            }

            // 7. Clean purchases (if exists)
            if (DB::getSchemaBuilder()->hasTable('purchases')) {
                $this->command->info('  → Cleaning purchases...');
                DB::table('purchases')->truncate();
            }

            // 8. Clean technical order products (if exists)
            if (DB::getSchemaBuilder()->hasTable('technical_order_products')) {
                $this->command->info('  → Cleaning technical_order_products...');
                DB::table('technical_order_products')->truncate();
            }

            // 9. Clean technical orders (if exists)
            if (DB::getSchemaBuilder()->hasTable('technical_orders')) {
                $this->command->info('  → Cleaning technical_orders...');
                DB::table('technical_orders')->truncate();
            }

            // 10. Clean recipe products (if exists)
            if (DB::getSchemaBuilder()->hasTable('recipe_products')) {
                $this->command->info('  → Cleaning recipe_products...');
                DB::table('recipe_products')->truncate();
            }

            // 11. Clean recipes (if exists)
            if (DB::getSchemaBuilder()->hasTable('recipes')) {
                $this->command->info('  → Cleaning recipes...');
                DB::table('recipes')->truncate();
            }

            $this->command->info('✅ Transactional data cleaned successfully!');
            $this->command->newLine();
            $this->command->info('📋 Master data (users, products, brands, etc.) has been preserved.');

        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
