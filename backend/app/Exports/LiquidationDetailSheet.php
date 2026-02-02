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

class LiquidationDetailSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithMapping
{
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = DailyAssignment::with(['worker', 'task'])
            ->whereBetween('date', [$this->filters['start_date'], $this->filters['end_date']]);

        if (!empty($this->filters['worker_id'])) {
            $query->where('worker_id', $this->filters['worker_id']);
        }

        if (!empty($this->filters['task_id'])) {
            $query->where('task_id', $this->filters['task_id']);
        }

        return $query->orderBy('worker_code')->orderBy('date')->get();
    }

    public function headings(): array
    {
        return [
            'FECHA',
            'CÓDIGO TRABAJADOR',
            'NOMBRE TRABAJADOR',
            'DOCUMENTO',
            'CÓDIGO TAREA',
            'TAREA',
            'VALOR BRUTO',
            'DEDUCCIONES',
            'VALOR NETO',
            'DETALLE DEDUCCIONES',
        ];
    }

    public function map($row): array
    {
        $deductionsText = '';
        if ($row->deductions_detail) {
            $deductionsText = collect($row->deductions_detail)
                ->map(fn($d) => $d['name'] . ': ' . number_format($d['amount'], 0, ',', '.'))
                ->implode(', ');
        }

        return [
            $row->date->format('d/m/Y'),
            $row->worker_code,
            $row->worker->full_name,
            $row->worker->document_id,
            $row->task_code,
            $row->task->name,
            $row->gross_amount,
            $row->total_deductions,
            $row->net_amount,
            $deductionsText,
        ];
    }

    public function title(): string
    {
        return 'Detalle Liquidación';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 18,
            'C' => 30,
            'D' => 18,
            'E' => 18,
            'F' => 35,
            'G' => 16,
            'H' => 16,
            'I' => 16,
            'J' => 50,
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
            "G2:I{$lastRow}" => [
                'numberFormat' => [
                    'formatCode' => '#,##0',
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }
}
