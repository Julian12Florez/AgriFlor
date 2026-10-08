<?php

namespace App\Models;

use App\Models\Concerns\LineaDeDocumento;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OutputFarmLot extends Pivot
{
    use HasUuids, LineaDeDocumento;

    /** Sus cambios se ven en la foto de auditoría de este documento: [alias, columna]. */
    public const DOCUMENTO_AUDITADO = ['output', 'product_output_id'];

    protected $table = 'output_farm_lots';

    protected $fillable = [
        'product_output_id',
        'farm_lot_id',
    ];

    public function productOutput()
    {
        return $this->belongsTo(ProductOutput::class, 'product_output_id');
    }

    public function farmLot()
    {
        return $this->belongsTo(FarmLot::class, 'farm_lot_id');
    }
}
