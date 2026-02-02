<?php

namespace App\Exports;

use App\Models\Worker;
use App\Models\Task;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyAssignmentsTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return ['codigo_empleado', 'codigo_tarea'];
    }

    public function array(): array
    {
        $workers = Worker::active()->limit(3)->pluck('worker_code')->toArray();
        $tasks = Task::active()->limit(3)->pluck('code')->toArray();

        $rows = [];
        $count = min(count($workers), count($tasks), 3);
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [$workers[$i], $tasks[$i]];
        }

        if (empty($rows)) {
            $rows = [
                ['EMP001', 'TAR001'],
                ['EMP002', 'TAR002'],
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
