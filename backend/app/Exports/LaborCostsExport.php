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

class LaborCostsExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function array(): array
    {
        $query = DailyAssignment::query()
            ->whereBetween('date', [$this->filters['start_date'], $this->filters['end_date']]);

        if (!empty($this->filters['worker_id'])) {
            $query->where('worker_id', $this->filters['worker_id']);
        }

        if (!empty($this->filters['task_id'])) {
            $query->where('task_id', $this->filters['task_id']);
        }

        $assignments = $query->get();

        // Group by month (YYYY-MM) by default
        $grouped = $assignments->groupBy(function ($assignment) {
            return $assignment->date->format('Y-m');
        })->sortKeys();

        $rows = [];
        foreach ($grouped as $period => $periodAssignments) {
            $totalBruto = $periodAssignments->sum('gross_amount');
            $totalDeducciones = $periodAssignments->sum('total_deductions');
            $totalNeto = $periodAssignments->sum('net_amount');
            $trabajadores = $periodAssignments->pluck('worker_id')->unique()->count();
            $asignaciones = $periodAssignments->count();
            $promedioNeto = $asignaciones > 0 ? round($totalNeto / $asignaciones, 0) : 0;

            $rows[] = [
                $period,
                $trabajadores,
                $asignaciones,
                round($totalBruto, 0),
                round($totalDeducciones, 0),
                round($totalNeto, 0),
                $promedioNeto,
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'PERIODO',
            'TRABAJADORES',
            'ASIGNACIONES',
            'TOTAL BRUTO',
            'TOTAL DEDUCCIONES',
            'TOTAL NETO',
            'PROMEDIO NETO',
        ];
    }

    public function title(): string
    {
        return 'Costos Laborales';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 18,
            'C' => 18,
            'D' => 18,
            'E' => 22,
            'F' => 18,
            'G' => 18,
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
                    'startColor' => ['rgb' => '2E7D32'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            "D2:G{$lastRow}" => [
                'numberFormat' => [
                    'formatCode' => '#,##0',
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }
}
