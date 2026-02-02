<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyAssignment;
use App\Models\Worker;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class LiquidationAnalyticsController extends Controller
{
    /**
     * FUN-001: Costos Laborales por Periodo
     */
    public function laborCosts(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'grouping' => ['nullable', 'in:weekly,biweekly,monthly'],
            'worker_id' => ['nullable', 'uuid'],
            'task_id' => ['nullable', 'uuid'],
        ], [
            'start_date.required' => 'La fecha de inicio es requerida',
            'end_date.required' => 'La fecha de fin es requerida',
        ]);

        $grouping = $request->get('grouping', 'monthly');

        $groupExpression = match ($grouping) {
            'weekly' => "CONCAT(YEAR(date), '-S', LPAD(WEEK(date, 1), 2, '0'))",
            'biweekly' => "CONCAT(DATE_FORMAT(date, '%Y-%m'), IF(DAY(date) <= 15, '-Q1', '-Q2'))",
            'monthly' => "DATE_FORMAT(date, '%Y-%m')",
        };

        $groupLabel = match ($grouping) {
            'weekly' => 'Semana',
            'biweekly' => 'Quincena',
            'monthly' => 'Mes',
        };

        $query = DailyAssignment::query()
            ->whereBetween('date', [$request->start_date, $request->end_date]);

        if ($request->worker_id) {
            $query->where('worker_id', $request->worker_id);
        }
        if ($request->task_id) {
            $query->where('task_id', $request->task_id);
        }

        $periods = $query->select(
            DB::raw("$groupExpression as periodo"),
            DB::raw('COUNT(DISTINCT worker_id) as trabajadores'),
            DB::raw('COUNT(*) as asignaciones'),
            DB::raw('SUM(gross_amount) as total_bruto'),
            DB::raw('SUM(total_deductions) as total_deducciones'),
            DB::raw('SUM(net_amount) as total_neto'),
            DB::raw('AVG(net_amount) as promedio_neto')
        )
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get();

        // Cost distribution by task
        $taskDistribution = DailyAssignment::query()
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->when($request->worker_id, fn($q) => $q->where('worker_id', $request->worker_id))
            ->when($request->task_id, fn($q) => $q->where('task_id', $request->task_id))
            ->join('tasks', 'daily_assignments.task_id', '=', 'tasks.id')
            ->select(
                'tasks.name',
                DB::raw('SUM(daily_assignments.net_amount) as total_neto'),
                DB::raw('COUNT(*) as asignaciones')
            )
            ->groupBy('tasks.name')
            ->orderByDesc('total_neto')
            ->limit(10)
            ->get();

        $totals = [
            'total_bruto' => $periods->sum('total_bruto'),
            'total_deducciones' => $periods->sum('total_deducciones'),
            'total_neto' => $periods->sum('total_neto'),
            'total_asignaciones' => $periods->sum('asignaciones'),
            'total_trabajadores' => DailyAssignment::query()
                ->whereBetween('date', [$request->start_date, $request->end_date])
                ->when($request->worker_id, fn($q) => $q->where('worker_id', $request->worker_id))
                ->when($request->task_id, fn($q) => $q->where('task_id', $request->task_id))
                ->distinct('worker_id')->count('worker_id'),
            'promedio_diario' => $periods->count() > 0 ? $periods->sum('total_neto') / max($periods->sum('asignaciones'), 1) : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'grouping' => $grouping,
                'group_label' => $groupLabel,
                'periods' => $periods,
                'task_distribution' => $taskDistribution,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * FUN-002: Productividad por Trabajador
     */
    public function workerProductivity(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:active,inactive,all'],
            'sort_by' => ['nullable', 'in:days_worked,total_neto,promedio_diario'],
        ], [
            'start_date.required' => 'La fecha de inicio es requerida',
            'end_date.required' => 'La fecha de fin es requerida',
        ]);

        $status = $request->get('status', 'all');
        $sortBy = $request->get('sort_by', 'total_neto');

        $workersQuery = Worker::query()
            ->leftJoin('daily_assignments as da', function ($join) use ($request) {
                $join->on('workers.id', '=', 'da.worker_id')
                    ->whereBetween('da.date', [$request->start_date, $request->end_date]);
            })
            ->select(
                'workers.id',
                'workers.worker_code',
                'workers.full_name',
                'workers.document_id',
                'workers.status',
                DB::raw('COUNT(DISTINCT da.date) as dias_trabajados'),
                DB::raw('COUNT(da.id) as total_asignaciones'),
                DB::raw('COALESCE(SUM(da.gross_amount), 0) as total_bruto'),
                DB::raw('COALESCE(SUM(da.total_deductions), 0) as total_deducciones'),
                DB::raw('COALESCE(SUM(da.net_amount), 0) as total_neto'),
                DB::raw('COALESCE(AVG(da.net_amount), 0) as promedio_por_asignacion')
            )
            ->groupBy('workers.id', 'workers.worker_code', 'workers.full_name', 'workers.document_id', 'workers.status');

        if ($status !== 'all') {
            $workersQuery->where('workers.status', $status);
        }

        // Only include workers with at least 1 assignment
        $workersQuery->having('total_asignaciones', '>', 0);

        $orderColumn = match ($sortBy) {
            'days_worked' => 'dias_trabajados',
            'promedio_diario' => 'promedio_por_asignacion',
            default => 'total_neto',
        };

        $workers = $workersQuery->orderByDesc($orderColumn)->get();

        // Calculate period days
        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = \Carbon\Carbon::parse($request->end_date);
        $periodDays = $startDate->diffInDays($endDate) + 1;

        // Add percentage of active days
        $workers = $workers->map(function ($w) use ($periodDays) {
            $w->porcentaje_actividad = $periodDays > 0
                ? round(($w->dias_trabajados / $periodDays) * 100, 1)
                : 0;
            return $w;
        });

        $totals = [
            'total_trabajadores' => $workers->count(),
            'total_bruto' => $workers->sum('total_bruto'),
            'total_deducciones' => $workers->sum('total_deducciones'),
            'total_neto' => $workers->sum('total_neto'),
            'total_asignaciones' => $workers->sum('total_asignaciones'),
            'promedio_dias' => $workers->count() > 0 ? round($workers->avg('dias_trabajados'), 1) : 0,
            'max_dias' => $workers->max('dias_trabajados') ?? 0,
            'max_neto' => $workers->max('total_neto') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period_days' => $periodDays,
                'workers' => $workers,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * FUN-003: Analisis de Tareas
     */
    public function taskAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'task_id' => ['nullable', 'uuid'],
            'sort_by' => ['nullable', 'in:frequency,total_neto,unit_cost'],
        ], [
            'start_date.required' => 'La fecha de inicio es requerida',
            'end_date.required' => 'La fecha de fin es requerida',
        ]);

        $sortBy = $request->get('sort_by', 'total_neto');

        $tasksQuery = Task::query()
            ->leftJoin('daily_assignments as da', function ($join) use ($request) {
                $join->on('tasks.id', '=', 'da.task_id')
                    ->whereBetween('da.date', [$request->start_date, $request->end_date]);
            })
            ->select(
                'tasks.id',
                'tasks.code',
                'tasks.name',
                'tasks.daily_cost',
                'tasks.status',
                DB::raw('COUNT(da.id) as veces_asignada'),
                DB::raw('COUNT(DISTINCT da.worker_id) as trabajadores_distintos'),
                DB::raw('COALESCE(SUM(da.gross_amount), 0) as total_bruto'),
                DB::raw('COALESCE(SUM(da.total_deductions), 0) as total_deducciones'),
                DB::raw('COALESCE(SUM(da.net_amount), 0) as total_neto')
            )
            ->groupBy('tasks.id', 'tasks.code', 'tasks.name', 'tasks.daily_cost', 'tasks.status')
            ->having('veces_asignada', '>', 0);

        if ($request->task_id) {
            $tasksQuery->where('tasks.id', $request->task_id);
        }

        $orderColumn = match ($sortBy) {
            'frequency' => 'veces_asignada',
            'unit_cost' => 'tasks.daily_cost',
            default => 'total_neto',
        };

        $tasks = $tasksQuery->orderByDesc($orderColumn)->get();

        $grandTotal = $tasks->sum('total_neto');
        $tasks = $tasks->map(function ($t) use ($grandTotal) {
            $t->porcentaje_costo = $grandTotal > 0
                ? round(($t->total_neto / $grandTotal) * 100, 1)
                : 0;
            return $t;
        });

        $totals = [
            'total_tareas' => $tasks->count(),
            'total_asignaciones' => $tasks->sum('veces_asignada'),
            'total_bruto' => $tasks->sum('total_bruto'),
            'total_deducciones' => $tasks->sum('total_deducciones'),
            'total_neto' => $grandTotal,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $tasks,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * FUN-004: Desglose de Deducciones Consolidado
     */
    public function deductionsBreakdown(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'worker_id' => ['nullable', 'uuid'],
            'deduction_name' => ['nullable', 'string'],
        ], [
            'start_date.required' => 'La fecha de inicio es requerida',
            'end_date.required' => 'La fecha de fin es requerida',
        ]);

        $query = DailyAssignment::with('worker')
            ->whereBetween('date', [$request->start_date, $request->end_date]);

        if ($request->worker_id) {
            $query->where('worker_id', $request->worker_id);
        }

        $assignments = $query->orderBy('date')->get();

        // Breakdown by deduction type
        $byType = [];
        foreach ($assignments as $a) {
            $details = $a->deductions_detail ?? [];
            foreach ($details as $d) {
                $name = $d['name'];
                if ($request->deduction_name && $name !== $request->deduction_name) {
                    continue;
                }
                if (!isset($byType[$name])) {
                    $byType[$name] = [
                        'name' => $name,
                        'total_amount' => 0,
                        'count' => 0,
                        'percentages' => [],
                    ];
                }
                $byType[$name]['total_amount'] += $d['amount'];
                $byType[$name]['count']++;
                $byType[$name]['percentages'][] = $d['percentage'];
            }
        }

        // Calculate averages and sort
        $deductionTypes = collect($byType)->map(function ($item) {
            $item['avg_percentage'] = count($item['percentages']) > 0
                ? round(array_sum($item['percentages']) / count($item['percentages']), 2)
                : 0;
            unset($item['percentages']);
            return $item;
        })->sortByDesc('total_amount')->values();

        // Breakdown by worker (for table)
        $byWorker = [];
        foreach ($assignments as $a) {
            $wId = $a->worker_id;
            if (!isset($byWorker[$wId])) {
                $byWorker[$wId] = [
                    'worker_code' => $a->worker_code,
                    'worker_name' => $a->worker->full_name ?? '',
                    'document_id' => $a->worker->document_id ?? '',
                    'total_gross' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                    'deductions' => [],
                ];
            }
            $byWorker[$wId]['total_gross'] += (float) $a->gross_amount;
            $byWorker[$wId]['total_deductions'] += (float) $a->total_deductions;
            $byWorker[$wId]['total_net'] += (float) $a->net_amount;

            $details = $a->deductions_detail ?? [];
            foreach ($details as $d) {
                $name = $d['name'];
                if (!isset($byWorker[$wId]['deductions'][$name])) {
                    $byWorker[$wId]['deductions'][$name] = 0;
                }
                $byWorker[$wId]['deductions'][$name] += $d['amount'];
            }
        }

        $workerBreakdown = collect($byWorker)->sortByDesc('total_deductions')->values();

        $totals = [
            'total_gross' => $assignments->sum('gross_amount'),
            'total_deductions' => $assignments->sum('total_deductions'),
            'total_net' => $assignments->sum('net_amount'),
            'total_assignments' => $assignments->count(),
            'total_workers' => $assignments->unique('worker_id')->count(),
        ];

        // Get unique deduction names for column headers
        $deductionNames = $deductionTypes->pluck('name')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'deduction_types' => $deductionTypes,
                'deduction_names' => $deductionNames,
                'worker_breakdown' => $workerBreakdown,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * FUN-005: Comparativo entre Periodos
     */
    public function periodComparison(Request $request): JsonResponse
    {
        $request->validate([
            'period1_start' => ['required', 'date'],
            'period1_end' => ['required', 'date', 'after_or_equal:period1_start'],
            'period2_start' => ['required', 'date'],
            'period2_end' => ['required', 'date', 'after_or_equal:period2_start'],
            'worker_id' => ['nullable', 'uuid'],
            'task_id' => ['nullable', 'uuid'],
        ], [
            'period1_start.required' => 'La fecha de inicio del periodo 1 es requerida',
            'period1_end.required' => 'La fecha de fin del periodo 1 es requerida',
            'period2_start.required' => 'La fecha de inicio del periodo 2 es requerida',
            'period2_end.required' => 'La fecha de fin del periodo 2 es requerida',
        ]);

        $buildStats = function ($startDate, $endDate) use ($request) {
            $query = DailyAssignment::query()
                ->whereBetween('date', [$startDate, $endDate]);

            if ($request->worker_id) {
                $query->where('worker_id', $request->worker_id);
            }
            if ($request->task_id) {
                $query->where('task_id', $request->task_id);
            }

            $stats = $query->select(
                DB::raw('COUNT(DISTINCT worker_id) as trabajadores'),
                DB::raw('COUNT(*) as asignaciones'),
                DB::raw('COALESCE(SUM(gross_amount), 0) as total_bruto'),
                DB::raw('COALESCE(SUM(total_deductions), 0) as total_deducciones'),
                DB::raw('COALESCE(SUM(net_amount), 0) as total_neto'),
                DB::raw('COALESCE(AVG(net_amount), 0) as promedio_neto')
            )->first();

            $workerIds = DailyAssignment::query()
                ->whereBetween('date', [$startDate, $endDate])
                ->when($request->worker_id, fn($q) => $q->where('worker_id', $request->worker_id))
                ->when($request->task_id, fn($q) => $q->where('task_id', $request->task_id))
                ->distinct()->pluck('worker_id');

            $taskIds = DailyAssignment::query()
                ->whereBetween('date', [$startDate, $endDate])
                ->when($request->worker_id, fn($q) => $q->where('worker_id', $request->worker_id))
                ->when($request->task_id, fn($q) => $q->where('task_id', $request->task_id))
                ->distinct()->pluck('task_id');

            return [
                'stats' => $stats,
                'worker_ids' => $workerIds,
                'task_ids' => $taskIds,
            ];
        };

        $p1 = $buildStats($request->period1_start, $request->period1_end);
        $p2 = $buildStats($request->period2_start, $request->period2_end);

        // Calculate variations
        $calcVariation = function ($v1, $v2) {
            if ($v1 == 0 && $v2 == 0) return 0;
            if ($v1 == 0) return 100;
            return round((($v2 - $v1) / abs($v1)) * 100, 1);
        };

        $metrics = [
            [
                'metric' => 'Total Bruto',
                'period1' => (float) $p1['stats']->total_bruto,
                'period2' => (float) $p2['stats']->total_bruto,
                'difference' => (float) $p2['stats']->total_bruto - (float) $p1['stats']->total_bruto,
                'variation' => $calcVariation($p1['stats']->total_bruto, $p2['stats']->total_bruto),
                'type' => 'currency',
            ],
            [
                'metric' => 'Total Deducciones',
                'period1' => (float) $p1['stats']->total_deducciones,
                'period2' => (float) $p2['stats']->total_deducciones,
                'difference' => (float) $p2['stats']->total_deducciones - (float) $p1['stats']->total_deducciones,
                'variation' => $calcVariation($p1['stats']->total_deducciones, $p2['stats']->total_deducciones),
                'type' => 'currency',
            ],
            [
                'metric' => 'Total Neto',
                'period1' => (float) $p1['stats']->total_neto,
                'period2' => (float) $p2['stats']->total_neto,
                'difference' => (float) $p2['stats']->total_neto - (float) $p1['stats']->total_neto,
                'variation' => $calcVariation($p1['stats']->total_neto, $p2['stats']->total_neto),
                'type' => 'currency',
            ],
            [
                'metric' => 'Trabajadores',
                'period1' => (int) $p1['stats']->trabajadores,
                'period2' => (int) $p2['stats']->trabajadores,
                'difference' => (int) $p2['stats']->trabajadores - (int) $p1['stats']->trabajadores,
                'variation' => $calcVariation($p1['stats']->trabajadores, $p2['stats']->trabajadores),
                'type' => 'number',
            ],
            [
                'metric' => 'Asignaciones',
                'period1' => (int) $p1['stats']->asignaciones,
                'period2' => (int) $p2['stats']->asignaciones,
                'difference' => (int) $p2['stats']->asignaciones - (int) $p1['stats']->asignaciones,
                'variation' => $calcVariation($p1['stats']->asignaciones, $p2['stats']->asignaciones),
                'type' => 'number',
            ],
            [
                'metric' => 'Promedio Neto/Asignación',
                'period1' => (float) $p1['stats']->promedio_neto,
                'period2' => (float) $p2['stats']->promedio_neto,
                'difference' => (float) $p2['stats']->promedio_neto - (float) $p1['stats']->promedio_neto,
                'variation' => $calcVariation($p1['stats']->promedio_neto, $p2['stats']->promedio_neto),
                'type' => 'currency',
            ],
        ];

        // Workers that are new in P2 (not in P1)
        $newWorkerIds = $p2['worker_ids']->diff($p1['worker_ids']);
        $newWorkers = $newWorkerIds->isNotEmpty()
            ? Worker::whereIn('id', $newWorkerIds)->select('id', 'worker_code', 'full_name')->get()
            : collect();

        // Workers that left (in P1 but not in P2)
        $leftWorkerIds = $p1['worker_ids']->diff($p2['worker_ids']);
        $leftWorkers = $leftWorkerIds->isNotEmpty()
            ? Worker::whereIn('id', $leftWorkerIds)->select('id', 'worker_code', 'full_name')->get()
            : collect();

        // Tasks that are new in P2
        $newTaskIds = $p2['task_ids']->diff($p1['task_ids']);
        $newTasks = $newTaskIds->isNotEmpty()
            ? Task::whereIn('id', $newTaskIds)->select('id', 'code', 'name')->get()
            : collect();

        // Tasks that left
        $leftTaskIds = $p1['task_ids']->diff($p2['task_ids']);
        $leftTasks = $leftTaskIds->isNotEmpty()
            ? Task::whereIn('id', $leftTaskIds)->select('id', 'code', 'name')->get()
            : collect();

        return response()->json([
            'success' => true,
            'data' => [
                'period1' => ['start' => $request->period1_start, 'end' => $request->period1_end],
                'period2' => ['start' => $request->period2_start, 'end' => $request->period2_end],
                'metrics' => $metrics,
                'changes' => [
                    'new_workers' => $newWorkers,
                    'left_workers' => $leftWorkers,
                    'new_tasks' => $newTasks,
                    'left_tasks' => $leftTasks,
                ],
            ],
        ]);
    }

    /**
     * Export analytics report to Excel (generic)
     */
    public function exportExcel(Request $request, string $type)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $exportClass = match ($type) {
            'labor-costs' => new \App\Exports\LaborCostsExport($request->all()),
            'worker-productivity' => new \App\Exports\WorkerProductivityExport($request->all()),
            'task-analysis' => new \App\Exports\TaskAnalysisExport($request->all()),
            'deductions-breakdown' => new \App\Exports\DeductionsBreakdownExport($request->all()),
            'period-comparison' => new \App\Exports\PeriodComparisonExport($request->all()),
            default => abort(404, 'Tipo de reporte no válido'),
        };

        $fileName = "reporte_{$type}_" . $request->start_date . '_' . $request->end_date . '.xlsx';

        return Excel::download($exportClass, $fileName);
    }

    /**
     * Export analytics report to PDF (generic)
     */
    public function exportPdf(Request $request, string $type)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        // Reuse the same logic from each method to get data
        $data = match ($type) {
            'labor-costs' => $this->getLaborCostsData($request),
            'worker-productivity' => $this->getWorkerProductivityData($request),
            'task-analysis' => $this->getTaskAnalysisData($request),
            'deductions-breakdown' => $this->getDeductionsData($request),
            'period-comparison' => $this->getPeriodComparisonData($request),
            default => abort(404, 'Tipo de reporte no válido'),
        };

        $view = "reports.analytics.{$type}";
        $pdf = Pdf::loadView($view, $data)->setPaper('letter', 'landscape');

        $fileName = "reporte_{$type}_" . $request->start_date . '_' . $request->end_date . '.pdf';

        return $pdf->download($fileName);
    }

    private function getLaborCostsData(Request $request): array
    {
        $response = $this->laborCosts($request);
        $content = json_decode($response->getContent(), true);
        return array_merge($content['data'], [
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
        ]);
    }

    private function getWorkerProductivityData(Request $request): array
    {
        $response = $this->workerProductivity($request);
        $content = json_decode($response->getContent(), true);
        return array_merge($content['data'], [
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
        ]);
    }

    private function getTaskAnalysisData(Request $request): array
    {
        $response = $this->taskAnalysis($request);
        $content = json_decode($response->getContent(), true);
        return array_merge($content['data'], [
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
        ]);
    }

    private function getDeductionsData(Request $request): array
    {
        $response = $this->deductionsBreakdown($request);
        $content = json_decode($response->getContent(), true);
        return array_merge($content['data'], [
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
        ]);
    }

    private function getPeriodComparisonData(Request $request): array
    {
        $response = $this->periodComparison($request);
        $content = json_decode($response->getContent(), true);
        return array_merge($content['data'], [
            'startDate' => $request->period1_start ?? $request->start_date,
            'endDate' => $request->period2_end ?? $request->end_date,
        ]);
    }
}
