<?php

namespace App\Exports;

use App\Models\DailyAssignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class LiquidationConsolidatedSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithMapping
{
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = DailyAssignment::with(['worker'])
            ->whereBetween('date', [$this->filters['start_date'], $this->filters['end_date']]);

        if (!empty($this->filters['worker_id'])) {
            $query->where('worker_id', $this->filters['worker_id']);
        }

        if (!empty($this->filters['task_id'])) {
            $query->where('task_id', $this->filters['task_id']);
        }

        return $query->get()
            ->groupBy('worker_id')
            ->map(function ($assignments) {
                $worker = $assignments->first()->worker;
                return (object) [
                    'worker_code' => $worker->worker_code,
                    'full_name' => $worker->full_name,
                    'document_id' => $worker->document_id,
                    'days_worked' => $assignments->count(),
                    'gross_amount' => $assignments->sum('gross_amount'),
                    'total_deductions' => $assignments->sum('total_deductions'),
                    'net_amount' => $assignments->sum('net_amount'),
                ];
            })
            ->sortBy('worker_code')
            ->values();
    }

    public function headings(): array
    {
        return [
            'CÓDIGO TRABAJADOR',
            'NOMBRE TRABAJADOR',
            'DOCUMENTO',
            'DÍAS TRABAJADOS',
            'TOTAL BRUTO',
            'TOTAL DEDUCCIONES',
            'TOTAL NETO A PAGAR',
        ];
    }

    public function map($row): array
    {
        return [
            $row->worker_code,
            $row->full_name,
            $row->document_id,
            $row->days_worked,
            $row->gross_amount,
            $row->total_deductions,
            $row->net_amount,
        ];
    }

    public function title(): string
    {
        return 'Consolidado por Trabajador';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 35,
            'C' => 18,
            'D' => 20,
            'E' => 18,
            'F' => 22,
            'G' => 22,
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
            "D2:G{$lastRow}" => [
                'numberFormat' => [
                    'formatCode' => '#,##0',
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }
}
