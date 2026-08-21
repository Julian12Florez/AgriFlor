<?php

namespace App\Services\Rebaseline;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Genera el Excel de simulación del re-baseline: una hoja con una fila por
 * triple producto + marca + ubicación y una hoja RESUMEN con los totales y el
 * conteo por tipo de alerta. Es el documento que se revisa con bodega y
 * contabilidad ANTES de aplicar nada.
 */
final class SimulationWorkbookWriter
{
    private const SHEET_DETAIL = 'SIMULACION';
    private const SHEET_SUMMARY = 'RESUMEN';

    private const HEADERS = [
        'codigo',
        'producto',
        'marca',
        'ubicacion',
        'unidad',
        'J_archivo',
        'K31',
        'A_agosto',
        'P_fisico',
        'mov_kardex',
        'fisico_objetivo',
        'precio_archivo',
        'precio_actual',
        'delta_valor',
        'alerta',
    ];

    private const QUANTITY_FORMAT = '#,##0.00';
    private const MONEY_FORMAT = '#,##0.00';

    /** Columnas (1-based) con formato numérico de cantidad y de dinero. */
    private const QUANTITY_COLUMNS = [6, 7, 8, 9, 10, 11];
    private const MONEY_COLUMNS = [12, 13, 14];

    /**
     * @param  array<int, PlanRow>  $rows
     * @param  array<int, array{concepto: string, valor: mixed}>  $summary
     * @param  array<string, int>  $alertCounts
     */
    public function write(string $path, array $rows, array $summary, array $alertCounts): void
    {
        $spreadsheet = new Spreadsheet();

        $this->fillDetailSheet($spreadsheet->getActiveSheet(), $rows);
        $this->fillSummarySheet($spreadsheet->createSheet(), $summary, $alertCounts);

        $spreadsheet->setActiveSheetIndex(0);

        $this->ensureDirectory($path);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @param  array<int, PlanRow>  $rows
     */
    private function fillDetailSheet(Worksheet $sheet, array $rows): void
    {
        $sheet->setTitle(self::SHEET_DETAIL);
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $line = 2;

        foreach ($rows as $row) {
            $sheet->fromArray($this->toCells($row), null, 'A' . $line, true);
            $line++;
        }

        $this->styleHeader($sheet, count(self::HEADERS));
        $sheet->freezePane('A2');

        $lastLine = max($line - 1, 1);
        $this->applyNumberFormats($sheet, $lastLine);
        $this->autoSize($sheet, count(self::HEADERS));
    }

    /**
     * @return array<int, mixed>
     */
    private function toCells(PlanRow $row): array
    {
        return [
            $row->productCode,
            $row->productName,
            $row->brandName,
            $row->locationName,
            $row->unit,
            $row->fileBalance,
            $row->ledgerAtCutoff,
            $row->augustDelta,
            $row->physicalStock,
            $row->ledgerMovement(),
            $row->physicalTarget(),
            $row->filePrice,
            $row->currentPrice,
            $row->valueDelta(),
            $row->alertLabel(),
        ];
    }

    /**
     * @param  array<int, array{concepto: string, valor: mixed}>  $summary
     * @param  array<string, int>  $alertCounts
     */
    private function fillSummarySheet(Worksheet $sheet, array $summary, array $alertCounts): void
    {
        $sheet->setTitle(self::SHEET_SUMMARY);
        $sheet->fromArray(['Concepto', 'Valor'], null, 'A1');

        $line = 2;

        foreach ($summary as $entry) {
            $sheet->fromArray([$entry['concepto'], $entry['valor']], null, 'A' . $line, true);
            $line++;
        }

        $line++;
        $sheet->fromArray(['Alerta', 'Filas'], null, 'A' . $line);
        $this->styleHeader($sheet, 2, $line);
        $line++;

        foreach ($alertCounts as $alert => $count) {
            $sheet->fromArray([$alert, $count], null, 'A' . $line, true);
            $line++;
        }

        $this->styleHeader($sheet, 2);
        $this->autoSize($sheet, 2);
    }

    private function styleHeader(Worksheet $sheet, int $columns, int $row = 1): void
    {
        $range = 'A' . $row . ':' . Coordinate::stringFromColumnIndex($columns) . $row;

        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');
    }

    private function applyNumberFormats(Worksheet $sheet, int $lastLine): void
    {
        $formats = [
            self::QUANTITY_FORMAT => self::QUANTITY_COLUMNS,
            self::MONEY_FORMAT => self::MONEY_COLUMNS,
        ];

        foreach ($formats as $format => $columns) {
            foreach ($columns as $column) {
                $letter = Coordinate::stringFromColumnIndex($column);
                $sheet->getStyle("{$letter}2:{$letter}{$lastLine}")
                    ->getNumberFormat()->setFormatCode($format);
            }
        }
    }

    private function autoSize(Worksheet $sheet, int $columns): void
    {
        for ($column = 1; $column <= $columns; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
    }

    private function ensureDirectory(string $path): void
    {
        $directory = dirname($path);

        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio de salida: {$directory}");
        }
    }
}
