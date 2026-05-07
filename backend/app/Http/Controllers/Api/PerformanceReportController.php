<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskCatalog;
use App\Models\TaskSchedule;
use App\Models\TaskDailyLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceReportController extends Controller
{
    /**
     * Reporte 1: Rendimiento por Tarea
     * Muestra distribucion de niveles (sobrepaso/alto/medio/bajo) para tareas completadas.
     */
    public function performanceByTask(Request $request): JsonResponse
    {
        $request->validate([
            'task_catalog_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $query = TaskSchedule::with(['task', 'location', 'lot'])
            ->where('status', 'completada')
            ->whereNotNull('final_level')
            ->whereDate('completed_at', '>=', $request->date_from)
            ->whereDate('completed_at', '<=', $request->date_to)
            ->forUser(auth()->user());

        if ($request->filled('task_catalog_id')) {
            $query->where('task_catalog_id', $request->task_catalog_id);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        $schedules = $query->orderBy('completed_at')->get();

        // Distribucion por nivel
        $distribution = [
            'sobrepaso' => $schedules->where('final_level', 'sobrepaso')->count(),
            'alto' => $schedules->where('final_level', 'alto')->count(),
            'medio' => $schedules->where('final_level', 'medio')->count(),
            'bajo' => $schedules->where('final_level', 'bajo')->count(),
        ];

        // Promedio general
        $avgPerformance = $schedules->avg('final_performance_pct');

        // Detalle por tarea
        $byTask = $schedules->groupBy('task_catalog_id')->map(function ($group) {
            $task = $group->first()->task;
            return [
                'taskId' => $task->id,
                'taskName' => $task->name,
                'taskCode' => $task->code,
                'count' => $group->count(),
                'avgPerformance' => round($group->avg('final_performance_pct'), 1),
                'distribution' => [
                    'sobrepaso' => $group->where('final_level', 'sobrepaso')->count(),
                    'alto' => $group->where('final_level', 'alto')->count(),
                    'medio' => $group->where('final_level', 'medio')->count(),
                    'bajo' => $group->where('final_level', 'bajo')->count(),
                ],
            ];
        })->values();

        // Tabla detallada
        $details = $schedules->map(fn($s) => [
            'code' => $s->code,
            'taskName' => $s->task->name,
            'locationName' => $s->location->name,
            'lotName' => $s->lot?->name,
            'completedAt' => $s->completed_at->format('Y-m-d'),
            'budgetedJornales' => $s->budgeted_jornales,
            'realJornales' => $s->real_jornales,
            'performancePct' => $s->final_performance_pct,
            'level' => $s->final_level,
            'isAdHoc' => $s->is_ad_hoc,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'totalCompleted' => $schedules->count(),
                    'avgPerformance' => round($avgPerformance, 1),
                    'distribution' => $distribution,
                ],
                'byTask' => $byTask,
                'details' => $details,
            ],
        ]);
    }

    /**
     * Reporte 2: Cumplimiento de Programacion
     * Compara jornales planificados vs reales, programados vs ad-hoc.
     */
    public function compliance(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
            'location_id' => ['nullable', 'uuid'],
        ]);

        $query = TaskSchedule::with(['task', 'location', 'lot'])
            ->whereIn('status', ['completada', 'en_progreso', 'cancelada'])
            ->where('start_date', '<=', $request->date_to)
            ->where(function ($q) use ($request) {
                $q->where('end_date', '>=', $request->date_from)
                  ->orWhereNotNull('completed_at');
            })
            ->forUser(auth()->user());

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        $schedules = $query->get();

        $programmed = $schedules->where('is_ad_hoc', false);
        $adHoc = $schedules->where('is_ad_hoc', true);

        $totalBudgeted = $programmed->sum('budgeted_jornales');
        $totalReal = $programmed->sum('real_jornales');
        $adHocJornales = $adHoc->sum('real_jornales');

        $programmedRows = $programmed->map(fn($s) => [
            'code' => $s->code,
            'taskName' => $s->task->name,
            'locationName' => $s->location->name,
            'lotName' => $s->lot?->name,
            'totalQuantity' => $s->total_quantity,
            'accumulatedPct' => $s->accumulated_pct,
            'budgetedJornales' => $s->budgeted_jornales,
            'realJornales' => $s->real_jornales,
            'variation' => $s->budgeted_jornales > 0
                ? round((($s->real_jornales - $s->budgeted_jornales) / $s->budgeted_jornales) * 100, 1)
                : 0,
            'status' => $s->status,
        ])->values();

        $adHocRows = $adHoc->map(fn($s) => [
            'code' => $s->code,
            'taskName' => $s->task->name,
            'locationName' => $s->location->name,
            'lotName' => $s->lot?->name,
            'realJornales' => $s->real_jornales,
            'adHocMotive' => $s->ad_hoc_motive,
            'status' => $s->status,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'totalBudgetedJornales' => $totalBudgeted,
                    'totalRealProgrammed' => $totalReal,
                    'totalAdHocJornales' => $adHocJornales,
                    'totalConsumed' => $totalReal + $adHocJornales,
                    'variationPct' => $totalBudgeted > 0
                        ? round((($totalReal - $totalBudgeted) / $totalBudgeted) * 100, 1) : 0,
                ],
                'programmed' => $programmedRows,
                'adHoc' => $adHocRows,
            ],
        ]);
    }

    /**
     * Reporte 3: Gantt Visual
     * Devuelve datos para un diagrama Gantt de programaciones.
     */
    public function gantt(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
            'location_id' => ['nullable', 'uuid'],
            'task_catalog_id' => ['nullable', 'uuid'],
        ]);

        $query = TaskSchedule::with(['task', 'location', 'lot'])
            ->where('start_date', '<=', $request->date_to)
            ->where('end_date', '>=', $request->date_from)
            ->forUser(auth()->user());

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('task_catalog_id')) {
            $query->where('task_catalog_id', $request->task_catalog_id);
        }

        $schedules = $query->orderBy('location_id')->orderBy('start_date')->get();

        $bars = $schedules->map(fn($s) => [
            'id' => $s->id,
            'code' => $s->code,
            'taskName' => $s->task->name,
            'locationName' => $s->location->name,
            'lotName' => $s->lot?->name,
            'startDate' => $s->start_date->format('Y-m-d'),
            'endDate' => $s->end_date->format('Y-m-d'),
            'accumulatedPct' => (float) $s->accumulated_pct,
            'status' => $s->status,
            'finalLevel' => $s->final_level,
            'isAdHoc' => $s->is_ad_hoc,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'bars' => $bars,
                'dateRange' => [
                    'from' => $request->date_from,
                    'to' => $request->date_to,
                ],
            ],
        ]);
    }

    /**
     * Reporte 4: Eficiencia Comparativa entre Fincas
     * Compara rendimiento promedio de la misma tarea entre varias fincas.
     */
    public function farmComparison(Request $request): JsonResponse
    {
        $request->validate([
            'task_catalog_id' => ['required', 'uuid'],
            'location_ids' => ['required', 'array', 'min:2'],
            'location_ids.*' => ['uuid'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
        ]);

        $schedules = TaskSchedule::with(['location'])
            ->where('task_catalog_id', $request->task_catalog_id)
            ->where('status', 'completada')
            ->whereIn('location_id', $request->location_ids)
            ->whereDate('completed_at', '>=', $request->date_from)
            ->whereDate('completed_at', '<=', $request->date_to)
            ->forUser(auth()->user())
            ->get();

        $task = TaskCatalog::find($request->task_catalog_id);

        $byFarm = $schedules->groupBy('location_id')->map(function ($group) {
            $loc = $group->first()->location;
            return [
                'locationId' => $loc->id,
                'locationName' => $loc->name,
                'count' => $group->count(),
                'avgPerformance' => round($group->avg('final_performance_pct'), 1),
                'maxPerformance' => round($group->max('final_performance_pct'), 1),
                'minPerformance' => round($group->min('final_performance_pct'), 1),
                'distribution' => [
                    'sobrepaso' => $group->where('final_level', 'sobrepaso')->count(),
                    'alto' => $group->where('final_level', 'alto')->count(),
                    'medio' => $group->where('final_level', 'medio')->count(),
                    'bajo' => $group->where('final_level', 'bajo')->count(),
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'taskName' => $task?->name,
                'taskUnit' => $task?->unit,
                'farms' => $byFarm,
            ],
        ]);
    }

    /**
     * Reporte 5: Proyeccion de Completamiento
     * Para cada tarea en progreso, estima cuando se completara.
     */
    public function projection(Request $request): JsonResponse
    {
        $query = TaskSchedule::with(['task', 'location', 'lot'])
            ->where('status', 'en_progreso')
            ->forUser(auth()->user());

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        $schedules = $query->get();

        $projections = $schedules->map(function ($s) {
            // Calcular ritmo diario promedio real
            $daysElapsed = max(1, $s->start_date->diffInDaysFiltered(
                fn(Carbon $date) => $date->dayOfWeek !== Carbon::SUNDAY,
                now()
            ));
            $dailyRate = $s->accumulated_pct > 0
                ? $s->accumulated_pct / $daysElapsed : 0;

            $remaining = 100 - $s->accumulated_pct;
            $daysRemaining = $dailyRate > 0 ? (int) ceil($remaining / $dailyRate) : null;

            // Proyectar fecha de fin (saltando domingos)
            $projectedEnd = null;
            if ($daysRemaining !== null) {
                $projectedEnd = now()->copy();
                $count = 0;
                while ($count < $daysRemaining) {
                    $projectedEnd->addDay();
                    if ($projectedEnd->dayOfWeek !== Carbon::SUNDAY) {
                        $count++;
                    }
                }
            }

            $diffDays = $projectedEnd
                ? $projectedEnd->startOfDay()->diffInDays($s->end_date->startOfDay(), false)
                : null;

            $alert = 'en_tiempo';
            if ($diffDays !== null) {
                if ($diffDays < -3) $alert = 'atrasada';
                elseif ($diffDays < 0) $alert = 'riesgo';
                else $alert = 'adelantada';
            }

            return [
                'code' => $s->code,
                'taskName' => $s->task->name,
                'locationName' => $s->location->name,
                'lotName' => $s->lot?->name,
                'accumulatedPct' => (float) $s->accumulated_pct,
                'dailyRate' => round($dailyRate, 1),
                'daysRemaining' => $daysRemaining,
                'plannedEndDate' => $s->end_date->format('Y-m-d'),
                'projectedEndDate' => $projectedEnd?->format('Y-m-d'),
                'diffDays' => $diffDays ? (int) $diffDays : null,
                'alert' => $alert,
            ];
        })->sortBy('alert')->values();

        return response()->json([
            'success' => true,
            'data' => $projections,
        ]);
    }
}
