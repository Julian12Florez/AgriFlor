<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanTransactionalData extends Command
{
    protected $signature = 'db:clean-transactional {--force : Skip confirmation}';
    protected $description = 'Limpia datos transaccionales manteniendo datos maestros (productos, ubicaciones, usuarios, etc.)';

    public function handle()
    {
        $this->warn('========================================');
        $this->warn('LIMPIEZA DE DATOS TRANSACCIONALES');
        $this->warn('========================================');
        $this->newLine();

        // Tables to clean in order (respecting foreign keys - children first)
        $tablesToClean = [
            // Level 4: Deepest children
            'reception_batch_attachments',
            'reception_batch_items',
            'purchase_attachments',

            // Level 3: Children of children
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

            // System
            'alerts',
        ];

        $this->info('Tablas que se limpiarán:');
        foreach ($tablesToClean as $table) {
            $count = DB::table($table)->count();
            $this->line("  - {$table}: {$count} registros");
        }

        $this->newLine();
        $this->info('Datos maestros que se CONSERVARÁN:');
        $masterTables = [
            'users', 'roles', 'permissions', 'role_permission',
            'products', 'brands', 'base_units', 'packaging_units', 'product_packaging_units',
            'suppliers', 'supplier_contacts',
            'locations', 'farm_lots',
            'output_types',
            'technical_recipes', 'recipe_products',
        ];
        foreach ($masterTables as $table) {
            $count = DB::table($table)->count();
            $this->line("  - {$table}: {$count} registros (se mantienen)");
        }

        $this->newLine();

        if (!$this->option('force')) {
            if (!$this->confirm('¿Estás seguro de que deseas eliminar TODOS los datos transaccionales?')) {
                $this->info('Operación cancelada.');
                return 0;
            }
        }

        $this->newLine();
        $this->info('Limpiando datos transaccionales...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tablesToClean as $table) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("  ✓ {$table}: {$count} registros eliminados");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->newLine();
            $this->info('========================================');
            $this->info('LIMPIEZA COMPLETADA EXITOSAMENTE');
            $this->info('========================================');
            $this->newLine();

            // Verify
            $this->info('Verificación post-limpieza:');
            foreach ($tablesToClean as $table) {
                $count = DB::table($table)->count();
                $status = $count === 0 ? '✓' : '✗';
                $this->line("  {$status} {$table}: {$count} registros");
            }

            $this->newLine();
            $this->info('Datos maestros intactos:');
            foreach ($masterTables as $table) {
                $count = DB::table($table)->count();
                $this->line("  ✓ {$table}: {$count} registros");
            }

        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->error('Error durante la limpieza: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
