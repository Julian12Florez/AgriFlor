<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LiquidationReportExport implements WithMultipleSheets
{
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        return [
            new LiquidationDetailSheet($this->filters),
            new LiquidationConsolidatedSheet($this->filters),
        ];
    }
}
