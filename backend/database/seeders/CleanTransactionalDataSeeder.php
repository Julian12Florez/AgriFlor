<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanTransactionalDataSeeder extends Seeder
{
    /**
     * Clean all transactional data while keeping master data intact.
     * This allows for fresh testing with proper master data.
     *
     * Tables cleaned (in dependency order - children first):
     *   Level 4: reception_batch_attachments, reception_batch_items, purchase_attachments
     *   Level 3: reception_batches, reception_items, purchase_items, output_products,
     *            output_farm_lots, application_products, technical_order_farms,
     *            technical_order_products
     *   Level 2: inventory_movements, inventory, applications, receptions,
     *            product_outputs, purchases, technical_orders
     *   Level 1: alerts
     *
     * Tables preserved (master data):
     *   users, roles, permissions, role_permission,
     *   products, brands, base_units, packaging_units, product_packaging_units,
     *   suppliers, supplier_contacts,
     *   locations, farm_lots,
     *   output_types,
     *   technical_recipes, recipe_products
     */
    public function run(): void
    {
        $this->command->info('Cleaning transactional data...');

        $tablesToClean = [
            // Level 4: Deepest children
            'reception_batch_attachments',
            'reception_batch_items',
            'purchase_attachments',

            // Level 3: Children of main transactional tables
            'reception_batches',
            'reception_items',
            'purchase_items',
            'output_products',
            'output_farm_lots',
            'application_products',
            'technical_order_farms',
            'technical_order_products',

            // Level 2: Main transactional tables
            'inventory_movements',
            'inventory',
            'applications',
            'receptions',
            'product_outputs',
            'purchases',
            'technical_orders',

            // Level 1: System tables
            'alerts',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tablesToClean as $table) {
                if (Schema::hasTable($table)) {
                    $count = DB::table($table)->count();
                    DB::table($table)->truncate();
                    $this->command->info("  Cleaned {$table}: {$count} records removed");
                }
            }

            $this->command->info('Transactional data cleaned successfully!');
            $this->command->newLine();
            $this->command->info('Master data (users, products, brands, locations, suppliers, etc.) preserved.');
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
