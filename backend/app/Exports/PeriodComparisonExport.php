<?php

namespace App\Exports;

use App\Models\DailyAssignment;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PeriodComparisonExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function array(): array
    {
        $period1Metrics = $this->calculatePeriodMetrics(
            $this->filters['period1_start'],
            $this->filters['period1_end']
        );

        $period2Metrics = $this->calculatePeriodMetrics(
            $this->filters['period2_start'],
            $this->filters['period2_end']
        );

        $metrics = [
            'Total Asignaciones' => ['p1' => $period1Metrics['total_assignments'], 'p2' => $period2Metrics['total_assignments']],
            'Trabajadores Activos' => ['p1' => $period1Metrics['active_workers'], 'p2' => $period2Metrics['active_workers']],
            'Tareas Distintas' => ['p1' => $period1Metrics['distinct_tasks'], 'p2' => $period2Metrics['distinct_tasks']],
            'Total Bruto' => ['p1' => $period1Metrics['total_gross'], 'p2' => $period2Metrics['total_gross']],
            'Total Deducciones' => ['p1' => $period1Metrics['total_deductions'], 'p2' => $period2Metrics['total_deductions']],
            'Total Neto' => ['p1' => $period1Metrics['total_net'], 'p2' => $period2Metrics['total_net']],
            'Promedio Bruto/Asignación' => ['p1' => $period1Metrics['avg_gross'], 'p2' => $period2Metrics['avg_gross']],
            'Promedio Neto/Asignación' => ['p1' => $period1Metrics['avg_net'], 'p2' => $period2Metrics['avg_net']],
            'Promedio Neto/Trabajador' => ['p1' => $period1Metrics['avg_net_per_worker'], 'p2' => $period2Metrics['avg_net_per_worker']],
            'Días con Actividad' => ['p1' => $period1Metrics['active_days'], 'p2' => $period2Metrics['active_days']],
        ];

        $rows = [];
        foreach ($metrics as $metricName => $values) {
            $p1 = $values['p1'];
            $p2 = $values['p2'];
            $diff = $p2 - $p1;
            $variation = $p1 > 0 ? round((($p2 - $p1) / $p1) * 100, 1) : ($p2 > 0 ? 100 : 0);

            $rows[] = [
                $metricName,
                round($p1, 0),
                round($p2, 0),
                round($diff, 0),
                $variation . '%',
            ];
        }

        return $rows;
    }

    private function calculatePeriodMetrics(string $startDate, string $endDate): array
    {
        $query = DailyAssignment::query()
            ->whereBetween('date', [$startDate, $endDate]);

        if (!empty($this->filters['worker_id'])) {
            $query->where('worker_id', $this->filters['worker_id']);
        }

        if (!empty($this->filters['task_id'])) {
            $query->where('task_id', $this->filters['task_id']);
        }

        $assignments = $query->get();

        $totalAssignments = $assignments->count();
        $activeWorkers = $assignments->pluck('worker_id')->unique()->count();
        $distinctTasks = $assignments->pluck('task_id')->unique()->count();
        $totalGross = $assignments->sum('gross_amount');
        $totalDeductions = $assignments->sum('total_deductions');
        $totalNet = $assignments->sum('net_amount');
        $avgGross = $totalAssignments > 0 ? round($totalGross / $totalAssignments, 0) : 0;
        $avgNet = $totalAssignments > 0 ? round($totalNet / $totalAssignments, 0) : 0;
        $avgNetPerWorker = $activeWorkers > 0 ? round($totalNet / $activeWorkers, 0) : 0;
        $activeDays = $assignments->pluck('date')->map(fn($d) => $d->format('Y-m-d'))->unique()->count();

        return [
            'total_assignments' => $totalAssignments,
            'active_workers' => $activeWorkers,
            'distinct_tasks' => $distinctTasks,
            'total_gross' => round($totalGross, 0),
            'total_deductions' => round($totalDeductions, 0),
            'total_net' => round($totalNet, 0),
            'avg_gross' => $avgGross,
            'avg_net' => $avgNet,
            'avg_net_per_worker' => $avgNetPerWorker,
            'active_days' => $activeDays,
        ];
    }

    public function headings(): array
    {
        $p1Label = ($this->filters['period1_start'] ?? '') . ' a ' . ($this->filters['period1_end'] ?? '');
        $p2Label = ($this->filters['period2_start'] ?? '') . ' a ' . ($this->filters['period2_end'] ?? '');

        return [
            'MÉTRICA',
            'PERIODO 1 (' . $p1Label . ')',
            'PERIODO 2 (' . $p2Label . ')',
            'DIFERENCIA',
            'VARIACIÓN %',
        ];
    }

    public function title(): string
    {
        return 'Comparativo Periodos';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 28,
            'C' => 28,
            'D' => 18,
            'E' => 16,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Currency rows are rows 5, 6, 7, 8, 9, 10 (Total Bruto, Total Deducciones, Total Neto, Promedios)
        // These correspond to data rows 4-9 (1 heading + rows)
        // Row 1 = heading, rows 2-11 = data
        // Currency metrics: Total Bruto (row 5), Total Deducciones (row 6), Total Neto (row 7),
        // Promedio Bruto/Asig (row 8), Promedio Neto/Asig (row 9), Promedio Neto/Trabajador (row 10)
        $currencyRows = [5, 6, 7, 8, 9, 10];

        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4A148C'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];

        foreach ($currencyRows as $row) {
            if ($row <= $lastRow) {
                $styles["B{$row}:D{$row}"] = [
                    'numberFormat' => [
                        'formatCode' => '#,##0',
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ];
            }
        }

        return $styles;
    }
}
