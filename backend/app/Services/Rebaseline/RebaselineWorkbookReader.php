<?php

namespace App\Services\Rebaseline;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

/**
 * Lee los dos Excel que entrega el cliente para el re-baseline de julio 2026.
 *
 * - `INVENTARIO JULIO FINAL.xlsx`
 *     · hoja INVENTARIOS (encabezado fila 1) → columna "INVENTARIO FINAL" = saldo de BODEGA
 *     · hoja REMANENTES: NO se lee. Se verificó que suma exactamente lo mismo
 *       que la columna REMANENTE de INVENTARIOS: es lo que cada finca devolvió
 *       a bodega durante julio (un movimiento de entrada a bodega), y ese valor
 *       ya está incluido en el "INVENTARIO FINAL" de bodega. Usarla como
 *       apertura de finca duplicaría esas unidades. Las fincas abren en 0
 *       (con la excepción de "cierra en cero", ver RebaselineInventory).
 * - `Valoración de inventarios.xlsx`
 *     · hoja Sheet1, encabezado en la FILA 5 → "Vlr Und" = precio unitario
 *
 * Todas las columnas se localizan por NOMBRE (ver HeaderedSheet).
 */
final class RebaselineWorkbookReader
{
    private const SHEET_WAREHOUSE = 'INVENTARIOS';
    private const SHEET_PRICES = 'Sheet1';

    private const PRICES_HEADER_ROW = 5;

    private const COLUMN_CODE = 'Codigo';
    private const COLUMN_PRODUCT = 'Producto';
    private const COLUMN_UNIT = 'Unidad Medida';
    private const COLUMN_FINAL = 'INVENTARIO FINAL';

    private const COLUMN_PRICE_CODE = 'Código producto';
    private const COLUMN_PRICE_QUANTITY = 'Saldo cantidades';
    private const COLUMN_PRICE_UNIT_VALUE = 'Vlr Und';

    /** @var array<int, string> */
    private array $warnings = [];

    public function readInventory(string $path, string $warehouseName): InventoryFileData
    {
        $this->warnings = [];

        $spreadsheet = $this->load($path);

        /** @var array<string, FileBalance> $balances */
        $balances = [];
        $productNames = [];
        $unitLabels = [];

        // La hoja REMANENTES NO se lee (reglas v3): duplicaría lo que ya está en
        // el "INVENTARIO FINAL" de bodega. Las fincas abren siempre en 0.
        $this->readWarehouseSheet($spreadsheet, $warehouseName, $balances, $productNames, $unitLabels);

        return new InventoryFileData($balances, $productNames, $unitLabels, $this->warnings);
    }

    public function readPrices(string $path): PriceFileData
    {
        $this->warnings = [];

        $sheet = $this->sheet($this->load($path), self::SHEET_PRICES, self::PRICES_HEADER_ROW);

        $codeColumn = $sheet->requireColumn(self::COLUMN_PRICE_CODE);
        $quantityColumn = $sheet->requireColumn(self::COLUMN_PRICE_QUANTITY);
        $priceColumn = $sheet->requireColumn(self::COLUMN_PRICE_UNIT_VALUE);

        $prices = [];
        $quantities = [];

        foreach ($sheet->dataRows() as $row) {
            $code = RebaselineText::productCode($sheet->value($codeColumn, $row));

            if ($code === '') {
                continue;
            }

            // El archivo cierra con "Total general" y un pie de página: solo son
            // filas de producto las que traen un código numérico.
            if (! is_numeric($code)) {
                continue;
            }

            $prices[$code] = round($sheet->number($priceColumn, $row), 2);
            $quantities[$code] = $sheet->number($quantityColumn, $row);

            if ($prices[$code] <= 0) {
                $this->warnings[] = "Valoración: el producto {$code} no trae precio unitario (Vlr Und = 0).";
            }
        }

        return new PriceFileData($prices, $quantities, $this->warnings);
    }

    /**
     * @param  array<string, FileBalance>  $balances
     * @param  array<string, string>  $productNames
     * @param  array<string, string>  $unitLabels
     */
    private function readWarehouseSheet(
        Spreadsheet $spreadsheet,
        string $warehouseName,
        array &$balances,
        array &$productNames,
        array &$unitLabels,
    ): void {
        $sheet = $this->sheet($spreadsheet, self::SHEET_WAREHOUSE, 1);

        $codeColumn = $sheet->requireColumn(self::COLUMN_CODE);
        $productColumn = $sheet->requireColumn(self::COLUMN_PRODUCT);
        $unitColumn = $sheet->requireColumn(self::COLUMN_UNIT);
        $finalColumn = $sheet->requireColumn(self::COLUMN_FINAL);

        foreach ($sheet->dataRows() as $row) {
            $code = RebaselineText::productCode($sheet->value($codeColumn, $row));

            if ($code === '') {
                continue;
            }

            $balance = new FileBalance(
                $code,
                $sheet->text($productColumn, $row),
                $sheet->text($unitColumn, $row),
                $warehouseName,
                round($sheet->number($finalColumn, $row), 2),
                self::SHEET_WAREHOUSE,
            );

            $this->remember($balance, $balances, $productNames, $unitLabels);
        }
    }

    /**
     * Acumula el saldo. Si el mismo producto aparece dos veces para la misma
     * ubicación (el archivo trae códigos repetidos) las cantidades se suman y
     * se deja constancia.
     *
     * @param  array<string, FileBalance>  $balances
     * @param  array<string, string>  $productNames
     * @param  array<string, string>  $unitLabels
     */
    private function remember(
        FileBalance $balance,
        array &$balances,
        array &$productNames,
        array &$unitLabels,
    ): void {
        $productNames[$balance->productCode] ??= $balance->productName;

        if ($balance->unitLabel !== '') {
            $unitLabels[$balance->productCode] ??= $balance->unitLabel;
        }

        $key = $balance->key();
        $existing = $balances[$key] ?? null;

        if ($existing === null) {
            $balances[$key] = $balance;

            return;
        }

        $this->warnings[] = sprintf(
            '%s: el código %s aparece repetido para "%s"; se suman las cantidades (%s + %s).',
            $balance->sourceSheet,
            $balance->productCode,
            $balance->locationLabel,
            number_format($existing->quantity, 2),
            number_format($balance->quantity, 2),
        );

        $balances[$key] = $existing->withQuantity(round($existing->quantity + $balance->quantity, 2));
    }

    private function load(string $path): Spreadsheet
    {
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el archivo Excel: {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }

    private function sheet(Spreadsheet $spreadsheet, string $name, int $headerRow): HeaderedSheet
    {
        $worksheet = $spreadsheet->getSheetByName($name);

        if ($worksheet === null) {
            throw new RuntimeException(sprintf(
                'El archivo no tiene la hoja "%s". Hojas disponibles: %s',
                $name,
                implode(', ', $spreadsheet->getSheetNames()),
            ));
        }

        return new HeaderedSheet($worksheet, $headerRow);
    }
}
