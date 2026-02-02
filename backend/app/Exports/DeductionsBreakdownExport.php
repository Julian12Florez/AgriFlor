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

class DeductionsBreakdownExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function array(): array
    {
        $query = DailyAssignment::query()
            ->whereBetween('date', [$this->filters['start_date'], $this->filters['end_date']])
            ->whereNotNull('deductions_detail');

        if (!empty($this->filters['worker_id'])) {
            $query->where('worker_id', $this->filters['worker_id']);
        }

        if (!empty($this->filters['task_id'])) {
            $query->where('task_id', $this->filters['task_id']);
        }

        $assignments = $query->get();

        // Parse deductions_detail JSON and aggregate by deduction name
        $deductionAggregates = [];

        foreach ($assignments as $assignment) {
            $details = $assignment->deductions_detail;
            if (!is_array($details)) {
                continue;
            }

            foreach ($details as $deduction) {
                $name = $deduction['name'] ?? 'Sin nombre';
                $amount = (float) ($deduction['amount'] ?? 0);
                $percentage = (float) ($deduction['percentage'] ?? 0);

                if (!isset($deductionAggregates[$name])) {
                    $deductionAggregates[$name] = [
                        'total_amount' => 0,
                        'count' => 0,
                        'percentage_sum' => 0,
                    ];
                }

                $deductionAggregates[$name]['total_amount'] += $amount;
                $deductionAggregates[$name]['count'] += 1;
                $deductionAggregates[$name]['percentage_sum'] += $percentage;
            }
        }

        // Build rows sorted by total_amount DESC
        $rows = [];
        foreach ($deductionAggregates as $name => $data) {
            $promedioPorc = $data['count'] > 0 ? round($data['percentage_sum'] / $data['count'], 1) : 0;

            $rows[] = [
                'tipo' => $name,
                'total' => round($data['total_amount'], 0),
                'aplicaciones' => $data['count'],
                'promedio' => $promedioPorc . '%',
            ];
        }

        usort($rows, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        // Convert to indexed arrays
        return array_map(function ($row) {
            return array_values($row);
        }, $rows);
    }

    public function headings(): array
    {
        return [
            'TIPO DEDUCCIÓN',
            'TOTAL DEDUCIDO',
            'N° APLICACIONES',
            'PORCENTAJE PROMEDIO',
        ];
    }

    public function title(): string
    {
        return 'Desglose Deducciones';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 20,
            'C' => 20,
            'D' => 24,
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
                    'startColor' => ['rgb' => 'C62828'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            "B2:B{$lastRow}" => [
                'numberFormat' => [
                    'formatCode' => '#,##0',
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }
}
