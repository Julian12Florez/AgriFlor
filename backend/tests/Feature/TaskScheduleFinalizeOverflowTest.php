<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\TaskCatalog;
use App\Models\TaskDailyLog;
use App\Models\TaskSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finalizar una programación con un rendimiento mayor a 9.999 %.
 *
 * En producción (30-sep-2026) PROG-0046 de Roble 1 tenía 314 jornales
 * presupuestados y 3 reales: rendimiento 10.466,67 %. La columna era
 * DECIMAL(6,2) (máximo 9.999,99) y finalizar respondía error 500 tres veces
 * seguidas: la tarea no se podía cerrar.
 */
class TaskScheduleFinalizeOverflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_finaliza_aunque_el_rendimiento_pase_de_9999_por_ciento(): void
    {
        $admin = User::create([
            'name' => 'Admin Finalizar',
            'email' => 'finalizar_' . uniqid() . '@agriflor.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $finca = Location::create(['name' => 'Roble 1', 'type' => 'farm', 'status' => 'active', 'total_workers' => 20]);
        $tarea = TaskCatalog::create(['code' => 'TSK-ROBLE', 'name' => 'Plateo', 'unit' => 'arbol', 'reference_yield' => 2, 'active' => true]);

        $programacion = TaskSchedule::create([
            'code' => 'PROG-0046',
            'task_catalog_id' => $tarea->id,
            'location_id' => $finca->id,
            'total_quantity' => 624,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(150)->toDateString(),
            'working_days' => 157,
            'planned_persons' => 2,
            'budgeted_jornales' => 314,
            'accumulated_pct' => 80,
            'real_jornales' => 0,
            'status' => 'en_progreso',
            'created_by' => $admin->id,
        ]);
        TaskDailyLog::create([
            'task_schedule_id' => $programacion->id,
            'log_date' => now()->subDay()->toDateString(),
            'registered_at' => now(),
            'mode' => 'programada',
            'advance_pct_today' => 80,
            'accumulated_snapshot_pct' => 80,
            'persons_today' => 3,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api')
            ->postJson("/api/performance/schedules/{$programacion->id}/finalize")
            ->assertOk();

        $programacion->refresh();
        $this->assertSame('completada', $programacion->status);
        $this->assertEquals(10466.67, (float) $programacion->final_performance_pct);
    }
}
