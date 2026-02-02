<?php

namespace App\Exports;

use App\Models\DailyAssignment;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class WorkerProductivityExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
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

        // Calculate total working days in the date range (excluding weekends)
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $totalDaysInRange = $start->diffInDaysFiltered(function (\Carbon\Carbon $date) {
            return $date->isWeekday();
        }, $end) ?: 1;

        // Build base query for assignments
        $assignmentQuery = DailyAssignment::query()
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($this->filters['task_id'])) {
            $assignmentQuery->where('task_id', $this->filters['task_id']);
        }

        if (!empty($this->filters['worker_id'])) {
            $assignmentQuery->where('worker_id', $this->filters['worker_id']);
        }

        $assignments = $assignmentQuery->get();

        // Group by worker
        $grouped = $assignments->groupBy('worker_id');

        // Get all workers involved
        $workerIds = $grouped->keys()->toArray();
        $workers = Worker::whereIn('id', $workerIds)->get()->keyBy('id');

        $rows = [];
        foreach ($grouped as $workerId => $workerAssignments) {
            $worker = $workers->get($workerId);
            if (!$worker) {
                continue;
            }

            $diasTrabajados = $workerAssignments->pluck('date')->unique()->count();
            $totalBruto = $workerAssignments->sum('gross_amount');
            $totalDeducciones = $workerAssignments->sum('total_deductions');
            $totalNeto = $workerAssignments->sum('net_amount');
            $cantidadAsignaciones = $workerAssignments->count();
            $promedioAsignacion = $cantidadAsignaciones > 0 ? round($totalNeto / $cantidadAsignaciones, 0) : 0;
            $porcentajeActividad = $totalDaysInRange > 0 ? round(($diasTrabajados / $totalDaysInRange) * 100, 1) : 0;

            $rows[] = [
                'codigo' => $worker->worker_code,
                'nombre' => $worker->full_name,
                'documento' => $worker->document_id,
                'dias_trabajados' => $diasTrabajados,
                'total_bruto' => round($totalBruto, 0),
                'total_deducciones' => round($totalDeducciones, 0),
                'total_neto' => round($totalNeto, 0),
                'promedio_asignacion' => $promedioAsignacion,
                'porcentaje_actividad' => $porcentajeActividad . '%',
            ];
        }

        // Sort by total_neto DESC
        usort($rows, function ($a, $b) {
            return $b['total_neto'] <=> $a['total_neto'];
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
            'NOMBRE',
            'DOCUMENTO',
            'DÍAS TRABAJADOS',
            'TOTAL BRUTO',
            'TOTAL DEDUCCIONES',
            'TOTAL NETO',
            'PROMEDIO/ASIGNACIÓN',
            '% ACTIVIDAD',
        ];
    }

    public function title(): string
    {
        return 'Productividad Trabajadores';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 30,
            'C' => 18,
            'D' => 20,
            'E' => 18,
            'F' => 22,
            'G' => 18,
            'H' => 24,
            'I' => 16,
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
                    'startColor' => ['rgb' => '1565C0'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            "E2:H{$lastRow}" => [
                'numberFormat' => [
                    'formatCode' => '#,##0',
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }
}
