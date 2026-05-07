<?php

namespace Database\Seeders;

use App\Models\TaskCatalog;
use Illuminate\Database\Seeder;

class TaskCatalogSeeder extends Seeder
{
    /**
     * 28 tareas precargadas basadas en las labores reales del cultivo de
     * aguacate Hass en AgriFlor.
     */
    public function run(): void
    {
        $tasks = [
            ['code' => 'TA-001', 'name' => 'Siembra', 'unit' => 'arbol', 'category' => 'Establecimiento', 'reference_yield' => 50],
            ['code' => 'TA-002', 'name' => 'Podas', 'unit' => 'arbol', 'category' => 'Mantenimiento', 'reference_yield' => 80],
            ['code' => 'TA-003', 'name' => 'Plateo con Azadon', 'unit' => 'arbol', 'category' => 'Mantenimiento', 'reference_yield' => 60],
            ['code' => 'TA-004', 'name' => 'Plateo con Herbicida', 'unit' => 'arbol', 'category' => 'Mantenimiento', 'reference_yield' => 120],
            ['code' => 'TA-005', 'name' => 'Cicatrizacion', 'unit' => 'arbol', 'category' => 'Mantenimiento', 'reference_yield' => 100],
            ['code' => 'TA-006', 'name' => 'Control de Arrieras', 'unit' => 'hectarea', 'category' => 'Fitosanitario', 'reference_yield' => 2],
            ['code' => 'TA-007', 'name' => 'Encalada', 'unit' => 'arbol', 'category' => 'Nutricion', 'reference_yield' => 90],
            ['code' => 'TA-008', 'name' => 'Guadaña', 'unit' => 'hectarea', 'category' => 'Mantenimiento', 'reference_yield' => 1.5],
            ['code' => 'TA-009', 'name' => 'Machete', 'unit' => 'hectarea', 'category' => 'Mantenimiento', 'reference_yield' => 1],
            ['code' => 'TA-010', 'name' => 'Herbicida', 'unit' => 'hectarea', 'category' => 'Mantenimiento', 'reference_yield' => 3],
            ['code' => 'TA-011', 'name' => 'Aplicacion Materia Organica', 'unit' => 'arbol', 'category' => 'Nutricion', 'reference_yield' => 70],
            ['code' => 'TA-012', 'name' => 'Edafica', 'unit' => 'arbol', 'category' => 'Nutricion', 'reference_yield' => 85],
            ['code' => 'TA-013', 'name' => 'Tapada de Abono', 'unit' => 'arbol', 'category' => 'Nutricion', 'reference_yield' => 110],
            ['code' => 'TA-014', 'name' => 'Foliar', 'unit' => 'hectarea', 'category' => 'Nutricion', 'reference_yield' => 2],
            ['code' => 'TA-015', 'name' => 'Drench', 'unit' => 'arbol', 'category' => 'Nutricion', 'reference_yield' => 75],
            ['code' => 'TA-016', 'name' => 'Fitosanitaria', 'unit' => 'hectarea', 'category' => 'Fitosanitario', 'reference_yield' => 2],
            ['code' => 'TA-017', 'name' => 'Tratamiento Enfermedades', 'unit' => 'arbol', 'category' => 'Fitosanitario', 'reference_yield' => 50],
            ['code' => 'TA-018', 'name' => 'Control Plagas', 'unit' => 'hectarea', 'category' => 'Fitosanitario', 'reference_yield' => 2.5],
            ['code' => 'TA-019', 'name' => 'Monitoreo', 'unit' => 'hectarea', 'category' => 'Monitoreo', 'reference_yield' => 5],
            ['code' => 'TA-020', 'name' => 'Cosecha', 'unit' => 'arbol', 'category' => 'Cosecha', 'reference_yield' => 40],
            ['code' => 'TA-021', 'name' => 'Post Cosecha', 'unit' => 'arbol', 'category' => 'Cosecha', 'reference_yield' => 100],
            ['code' => 'TA-022', 'name' => 'Rectificar Hoyos', 'unit' => 'arbol', 'category' => 'Establecimiento', 'reference_yield' => 30],
            ['code' => 'TA-023', 'name' => 'Vias', 'unit' => 'metro', 'category' => 'Infraestructura', 'reference_yield' => 50],
            ['code' => 'TA-024', 'name' => 'Cercos', 'unit' => 'metro', 'category' => 'Infraestructura', 'reference_yield' => 40],
            ['code' => 'TA-025', 'name' => 'Inyectar', 'unit' => 'arbol', 'category' => 'Fitosanitario', 'reference_yield' => 120],
            ['code' => 'TA-026', 'name' => 'Drenajes', 'unit' => 'metro', 'category' => 'Infraestructura', 'reference_yield' => 30],
            ['code' => 'TA-027', 'name' => 'Fertilizacion Quimica', 'unit' => 'arbol', 'category' => 'Nutricion', 'reference_yield' => 95],
            ['code' => 'TA-028', 'name' => 'Riego', 'unit' => 'hectarea', 'category' => 'Riego', 'reference_yield' => 3],
        ];

        foreach ($tasks as $task) {
            TaskCatalog::firstOrCreate(
                ['code' => $task['code']],
                array_merge($task, ['active' => true])
            );
        }
    }
}
