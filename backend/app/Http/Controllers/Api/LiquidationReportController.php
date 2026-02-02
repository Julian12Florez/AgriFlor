<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\LiquidationReportExport;
use App\Models\DailyAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class LiquidationReportController extends Controller
{
    /**
     * Generate liquidation report data (for preview)
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'worker_id' => ['nullable', 'uuid', 'exists:workers,id'],
            'task_id' => ['nullable', 'uuid', 'exists:tasks,id'],
        ], [
            'start_date.required' => 'La fecha de inicio es requerida',
            'end_date.required' => 'La fecha de fin es requerida',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio',
        ]);

        $query = DailyAssignment::with(['worker', 'task'])
            ->byDateRange($request->start_date, $request->end_date);

        if ($request->worker_id) {
            $query->byWorker($request->worker_id);
        }

        if ($request->task_id) {
            $query->byTask($request->task_id);
        }

        $assignments = $query->orderBy('date')->get();

        // Group by worker
        $grouped = $assignments->groupBy('worker_id')->map(function ($workerAssignments) {
            $worker = $workerAssignments->first()->worker;
            return [
                'worker' => [
                    'id' => $worker->id,
                    'worker_code' => $worker->worker_code,
                    'full_name' => $worker->full_name,
                    'document_id' => $worker->document_id,
                ],
                'assignments' => $workerAssignments->map(function ($a) {
                    return [
                        'date' => $a->date->format('Y-m-d'),
                        'task_code' => $a->task_code,
                        'task_name' => $a->task->name,
                        'gross_amount' => (float) $a->gross_amount,
                        'total_deductions' => (float) $a->total_deductions,
                        'net_amount' => (float) $a->net_amount,
                        'deductions_detail' => $a->deductions_detail,
                    ];
                })->values(),
                'subtotals' => [
                    'gross_amount' => $workerAssignments->sum('gross_amount'),
                    'total_deductions' => $workerAssignments->sum('total_deductions'),
                    'net_amount' => $workerAssignments->sum('net_amount'),
                    'days_worked' => $workerAssignments->count(),
                ],
            ];
        })->values();

        // Deduction breakdown
        $deductionBreakdown = [];
        foreach ($assignments as $a) {
            if ($a->deductions_detail) {
                foreach ($a->deductions_detail as $d) {
                    $name = $d['name'];
                    if (!isset($deductionBreakdown[$name])) {
                        $deductionBreakdown[$name] = 0;
                    }
                    $deductionBreakdown[$name] += $d['amount'];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                ],
                'workers' => $grouped,
                'totals' => [
                    'gross_amount' => $assignments->sum('gross_amount'),
                    'total_deductions' => $assignments->sum('total_deductions'),
                    'net_amount' => $assignments->sum('net_amount'),
                    'total_assignments' => $assignments->count(),
                    'total_workers' => $grouped->count(),
                ],
                'deduction_breakdown' => $deductionBreakdown,
            ],
        ]);
    }

    /**
     * Export liquidation report to Excel
     */
    public function exportExcel(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'worker_id' => ['nullable', 'uuid'],
            'task_id' => ['nullable', 'uuid'],
        ]);

        $filters = $request->only(['start_date', 'end_date', 'worker_id', 'task_id']);
        $fileName = 'liquidacion_' . $request->start_date . '_' . $request->end_date . '.xlsx';

        return Excel::download(new LiquidationReportExport($filters), $fileName);
    }

    /**
     * Export liquidation report to PDF
     */
    public function exportPdf(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'worker_id' => ['nullable', 'uuid'],
            'task_id' => ['nullable', 'uuid'],
        ]);

        $query = DailyAssignment::with(['worker', 'task'])
            ->byDateRange($request->start_date, $request->end_date);

        if ($request->worker_id) {
            $query->byWorker($request->worker_id);
        }

        if ($request->task_id) {
            $query->byTask($request->task_id);
        }

        $assignments = $query->orderBy('date')->get();

        $grouped = $assignments->groupBy('worker_id')->map(function ($workerAssignments) {
            $worker = $workerAssignments->first()->worker;
            return [
                'worker' => $worker,
                'assignments' => $workerAssignments,
                'subtotals' => [
                    'gross_amount' => $workerAssignments->sum('gross_amount'),
                    'total_deductions' => $workerAssignments->sum('total_deductions'),
                    'net_amount' => $workerAssignments->sum('net_amount'),
                    'days_worked' => $workerAssignments->count(),
                ],
            ];
        });

        $pdf = Pdf::loadView('reports.liquidation', [
            'grouped' => $grouped,
            'startDate' => $request->start_date,
            'endDate' => $request->end_date,
            'totals' => [
                'gross_amount' => $assignments->sum('gross_amount'),
                'total_deductions' => $assignments->sum('total_deductions'),
                'net_amount' => $assignments->sum('net_amount'),
                'total_assignments' => $assignments->count(),
            ],
        ])->setPaper('letter', 'landscape');

        $fileName = 'liquidacion_' . $request->start_date . '_' . $request->end_date . '.pdf';

        return $pdf->download($fileName);
    }
}
