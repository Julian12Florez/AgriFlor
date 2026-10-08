<?php

namespace App\Models;

use App\Models\Concerns\LineaDeDocumento;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TechnicalOrderFarm extends Pivot
{
    use HasUuids, LineaDeDocumento;

    /** Sus cambios se ven en la foto de auditoría de este documento: [alias, columna]. */
    public const DOCUMENTO_AUDITADO = ['technical_order', 'technical_order_id'];

    protected $table = 'technical_order_farms';

    protected $fillable = [
        'technical_order_id',
        'farm_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships

    public function technicalOrder()
    {
        return $this->belongsTo(TechnicalOrder::class, 'technical_order_id');
    }

    public function farm()
    {
        return $this->belongsTo(Location::class, 'farm_id');
    }
}
