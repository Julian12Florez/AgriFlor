<?php

namespace App\Exports;

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja REMANENTES del Informe de Inventario Mensual.
 *
 * Un REMANENTE es producto que se envió a una finca, sobró y la finca DEVUELVE
 * a la bodega. Se registra como una salida (`product_outputs`) de tipo
 * `output_types.code = 'remanente'` con origen = finca y destino = bodega, y al
 * recepcionarse produce una ENTRADA de kardex en la bodega.
 *
 * Esta hoja replica el formato que el cliente mantiene a mano (hoja REMANENTES
 * de "INVENTARIO <MES> FINAL.xlsx"): una fila por producto, una columna por
 * finca con lo que ESA finca devolvió de ESE producto en el mes, celda VACÍA
 * (no 0) cuando no devolvió nada, y una última columna con el total de la fila.
 */
class MonthlyRemainderSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    /**
     * Columnas de finca EXACTAS (nombre y orden) de la hoja del cliente.
     *
     * NO es "todas las fincas de `locations`": el cliente no lleva remanentes de
     * Breva Lote 7 ni de Laboratorio, así que su plantilla tiene 17 columnas y
     * no 19. Se resuelven contra `locations` por nombre normalizado (sin
     * acentos, mayúsculas) — ver {@see resolveFarmIds()} —, de modo que un
     * cambio de tildes o de mayúsculas en el maestro de ubicaciones no rompe la
     * hoja.
     */
    public const FARM_HEADINGS = [
        'ALQUERÍA',
        'BREVA',
        'CIRUELO',
        'ESPÁRRAGOS',
        'LA PALMA',
        'VENTA',
        'MANSIÓN',
        'MELON',
        'NARANJOS',
        'ROBLE 1',
        'ROBLE 2',
        'SALADEROS',
        'TORONJAS 2',
        'TORONJAS',
        'UVA',
        'VILLA',
        'ARANDANOS',
    ];

    /** Encabezados fijos previos a la matriz de fincas. */
    private const FIXED_HEADINGS = ['Codigo', 'Grupo Insumo', 'Producto', 'Unidad Medida'];

    /** Última columna: suma de la fila (total devuelto por todas las fincas). */
    private const TOTAL_HEADING = 'INVENTARIO FINAL';

    /** Código del tipo de salida que representa la devolución finca -> bodega. */
    private const REMAINDER_OUTPUT_CODE = 'remanente';

    /**
     * Igual que InventoryController::RECEPTION_DOCUMENT_TYPE: el kardex guarda
     * el FQCN del documento relacionado, no un alias.
     */
    private const RECEPTION_DOCUMENT_TYPE = 'App\Models\Reception';

    protected int $month;
    protected int $year;

    /** Bodega del informe (la misma que usa la primera hoja). */
    protected ?string $warehouseId;

    /** Cache de {@see rows()}: array() y styles() la necesitan. */
    private ?array $rows = null;

    /** Cache de {@see resolveFarmIds()}: encabezado => location_id|null. */
    private ?array $farmIds = null;

    public function __construct(int $month, int $year, ?string $warehouseId)
    {
        $this->month = $month;
        $this->year = $year;
        $this->warehouseId = $warehouseId;
    }

    public function title(): string
    {
        return 'REMANENTES';
    }

    public function headings(): array
    {
        return array_merge(self::FIXED_HEADINGS, self::FARM_HEADINGS, [self::TOTAL_HEADING]);
    }

    public function array(): array
    {
        return $this->rows();
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,  // Codigo
            'B' => 18,  // Grupo Insumo
            'C' => 35,  // Producto
            'D' => 14,  // Unidad Medida
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->rows()) + 1;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
            count($this->headings())
        );

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
            "A2:{$lastColLetter}{$lastRow}" => [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
            "E2:{$lastColLetter}{$lastRow}" => [
                'numberFormat' => ['formatCode' => '#,##0.00'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }

    /**
     * Filas finales de la hoja: una por producto con al menos un remanente en el
     * mes, ordenadas por código de producto y con celda vacía (null) donde la
     * finca no devolvió nada.
     *
     * @return array<int, array<int, mixed>>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $farmIds = $this->resolveFarmIds();
        $products = $this->remainderMatrix($farmIds);

        $rows = [];

        foreach ($products as $product) {
            $row = [
                $product['product_code'],
                $product['category'],
                $product['product_name'],
                $product['unit'],
            ];

            $total = 0.0;

            foreach (self::FARM_HEADINGS as $heading) {
                $farmId = $farmIds[$heading] ?? null;
                $quantity = $farmId !== null ? ($product['farms'][$farmId] ?? null) : null;

                // null (celda VACÍA), nunca 0: es como lo lleva el cliente y es
                // lo que distingue "esta finca no devolvió nada" de "devolvió y
                // el neto dio cero".
                $row[] = $quantity === null ? null : round($quantity, 2);
                $total += $quantity ?? 0.0;
            }

            $row[] = round($total, 2);

            $rows[] = $row;
        }

        return $this->rows = $rows;
    }

    /**
     * Devuelto por finca y producto, medido sobre lo que REALMENTE entró a la
     * bodega (que es lo que concilia el cliente), no sobre `product_outputs`.
     *
     * Criterio: entradas de kardex (`type = 'entry'`) en la bodega del informe
     * cuyo documento relacionado es una recepción (`receptions.source_type =
     * 'output'`) de una salida con `output_types.code = 'remanente'`. La finca
     * es el `origin_location_id` de esa salida.
     *
     * Se filtra por `movement_date` (NUNCA `created_at`): es el campo por el que
     * filtran todos los informes de inventario del proyecto, y es el que hace
     * que esta hoja cuadre contra la columna REMANENTE de la primera hoja, que
     * también se deriva del kardex.
     *
     * Se restringe a las fincas de {@see FARM_HEADINGS} para que la hoja sea
     * consistente consigo misma: INVENTARIO FINAL es la suma de la fila y la
     * plantilla del cliente no tiene columna donde poner una ubicación fuera de
     * esa lista. (Verificado sobre la base de producción: el 100 % de los
     * remanentes registrados sale de una de estas 17 fincas.)
     *
     * @param  array<string, string|null>  $farmIds
     * @return array<int, array<string, mixed>>
     */
    private function remainderMatrix(array $farmIds): array
    {
        $resolvedFarmIds = array_values(array_filter($farmIds));

        if ($this->warehouseId === null || empty($resolvedFarmIds)) {
            return [];
        }

        [$startDate, $endDate] = $this->monthRange();

        $rows = DB::table('inventory_movements as m')
            ->join('receptions as r', function ($join) {
                $join->on('r.id', '=', 'm.related_document_id')
                    ->where('r.source_type', '=', 'output');
            })
            ->join('product_outputs as po', 'po.id', '=', 'r.source_id')
            ->join('output_types as ot', 'ot.id', '=', 'po.output_type_id')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('ot.code', self::REMAINDER_OUTPUT_CODE)
            ->where('m.type', 'entry')
            ->where('m.location_id', $this->warehouseId)
            ->where('m.related_document_type', self::RECEPTION_DOCUMENT_TYPE)
            ->whereBetween('m.movement_date', [$startDate, $endDate])
            ->whereIn('po.origin_location_id', $resolvedFarmIds)
            ->groupBy('p.id', 'p.product_code', 'p.name', 'p.base_unit', 'c.name', 'po.origin_location_id')
            ->selectRaw(
                'p.id as product_id, p.product_code as product_code, p.name as product_name, '
                . 'p.base_unit as unit, c.name as category, '
                . 'po.origin_location_id as farm_id, SUM(m.quantity) as quantity'
            )
            ->get();

        $products = [];

        foreach ($rows as $row) {
            if (!isset($products[$row->product_id])) {
                $products[$row->product_id] = [
                    'product_code' => $row->product_code,
                    'product_name' => $row->product_name,
                    'category' => $row->category ?? 'Sin categoría',
                    'unit' => $row->unit,
                    'farms' => [],
                ];
            }

            $products[$row->product_id]['farms'][$row->farm_id] =
                ($products[$row->product_id]['farms'][$row->farm_id] ?? 0.0) + (float) $row->quantity;
        }

        // Orden natural por código: los códigos son numéricos guardados como
        // texto ('1000', '999'), así que un orden lexicográfico los desordena.
        uasort($products, fn ($a, $b) => strnatcasecmp(
            trim((string) $a['product_code']),
            trim((string) $b['product_code'])
        ));

        return array_values($products);
    }

    /**
     * Encabezado de la hoja del cliente => id de la ubicación correspondiente.
     *
     * El emparejamiento es por nombre NORMALIZADO (sin acentos, en mayúsculas,
     * espacios colapsados) porque el archivo del cliente escribe 'ESPÁRRAGOS' y
     * `locations` guarda 'Espárragos'. Si un encabezado no resuelve, la columna
     * se dibuja igual pero vacía: es preferible mantener la plantilla del
     * cliente intacta a mover columnas de sitio.
     *
     * No se filtra por `status`: los remanentes históricos de una finca
     * desactivada deben seguir apareciendo.
     *
     * @return array<string, string|null>
     */
    private function resolveFarmIds(): array
    {
        if ($this->farmIds !== null) {
            return $this->farmIds;
        }

        $byNormalizedName = [];

        foreach (Location::where('type', 'farm')->get(['id', 'name']) as $location) {
            $key = self::normalizeName((string) $location->name);
            $byNormalizedName[$key] ??= $location->id;
        }

        $map = [];

        foreach (self::FARM_HEADINGS as $heading) {
            $map[$heading] = $byNormalizedName[self::normalizeName($heading)] ?? null;
        }

        return $this->farmIds = $map;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthRange(): array
    {
        $start = \Carbon\Carbon::create($this->year, $this->month, 1)->startOfDay();

        return [
            $start->format('Y-m-d H:i:s'),
            $start->copy()->endOfMonth()->endOfDay()->format('Y-m-d H:i:s'),
        ];
    }

    private static function normalizeName(string $name): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', Str::ascii($name))));
    }
}
