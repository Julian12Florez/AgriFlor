<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkersTemplateExport implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return ['codigo_empleado', 'nombre_completo', 'documento_identidad', 'fecha_ingreso'];
    }

    public function array(): array
    {
        return [
            ['TRB-001', 'Juan Pérez López', '1234567890', '2026-01-15'],
            ['TRB-002', 'María García Torres', '0987654321', '2026-02-01'],
            ['TRB-003', 'Carlos Rodríguez', '1122334455', '2025-06-10'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
