<?php

namespace App\Exports;

use App\Models\DailyAssignment;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TaskAnalysisExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function array(): array
    {
        $startDate = $this->filters['start_date'];
        $endDate = $this->filters['end_date'];

        // Build base query for assignments
        $assignmentQuery = DailyAssignment::query()
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($this->filters['worker_id'])) {
            $assignmentQuery->where('worker_id', $this->filters['worker_id']);
        }

        if (!empty($this->filters['task_id'])) {
            $assignmentQuery->where('task_id', $this->filters['task_id']);
        }

        $assignments = $assignmentQuery->get();

        // Calculate grand total for percentage calculation
        $grandTotalBruto = $assignments->sum('gross_amount');

        // Group by task
        $grouped = $assignments->groupBy('task_id');

        // Get all tasks involved
        $taskIds = $grouped->keys()->toArray();
        $tasks = Task::whereIn('id', $taskIds)->get()->keyBy('id');

        $rows = [];
        foreach ($grouped as $taskId => $taskAssignments) {
            $task = $tasks->get($taskId);
            if (!$task) {
                continue;
            }

            $vecesAsignada = $taskAssignments->count();
            $trabajadoresDistintos = $taskAssignments->pluck('worker_id')->unique()->count();
            $totalBruto = $taskAssignments->sum('gross_amount');
            $totalDeducciones = $taskAssignments->sum('total_deductions');
            $totalNeto = $taskAssignments->sum('net_amount');
            $porcentajeCosto = $grandTotalBruto > 0 ? round(($totalBruto / $grandTotalBruto) * 100, 1) : 0;

            $rows[] = [
                'codigo' => $task->code,
                'tarea' => $task->name,
                'costo_unitario' => round((float) $task->daily_cost, 0),
                'veces_asignada' => $vecesAsignada,
                'trabajadores_distintos' => $trabajadoresDistintos,
                'total_bruto' => round($totalBruto, 0),
                'total_deducciones' => round($totalDeducciones, 0),
                'total_neto' => round($totalNeto, 0),
                'porcentaje_costo' => $porcentajeCosto . '%',
            ];
        }

        // Sort by total_bruto DESC
        usort($rows, function ($a, $b) {
            return $b['total_bruto'] <=> $a['total_bruto'];
        });

        // Convert to indexed arrays
        return array_map(function ($row) {
            return array_values($row);
        }, $rows);
    }

    public function headings(): array
    {
        return [
            'CÓDIGO',
            'TAREA',
            'COSTO UNITARIO',
            'VECES ASIGNADA',
            'TRABAJADORES DISTINTOS',
            'TOTAL BRUTO',
            'TOTAL DEDUCCIONES',
            'TOTAL NETO',
            '% DEL COSTO TOTAL',
        ];
    }

    public function title(): string
    {
        return 'Análisis de Tareas';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 35,
            'C' => 18,
            'D' => 20,
            'E' => 26,
            'F' => 18,
            'G' => 22,
            'H' => 18,
            'I' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E65100'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            "C2:C{$lastRow}" => [
                'numberFormat' => [
                    'formatCode' => '#,##0',
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
            "F2:H{$lastRow}" => [
                'numberFormat' => [
                    'formatCode' => '#,##0',
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }
}
