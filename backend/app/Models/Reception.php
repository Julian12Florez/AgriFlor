<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Reception extends Model implements AuditableContract
{
    use HasUuids, Auditable;

    /**
     * Siguiente número de recepción del año.
     *
     * POR QUÉ NO SE CUENTA
     * --------------------
     * Esto se numeraba con `Reception::count() + 1`, y esa cuenta se rompe sola:
     * basta que se borre UNA recepción para que el contador retroceda y vuelva a
     * proponer un número que ya existe. Entonces `receptions_reception_number_unique`
     * rechaza el insert y NO SE PUEDE RECEPCIONAR NADA — ni esa, ni ninguna otra,
     * porque todas piden el mismo número.
     *
     * Pasó en producción el 09-sep-2026: al borrar REC-2026-000562 quedaron 607
     * recepciones con máximo REC-2026-000608, así que `count()+1` daba 000608, que
     * ya estaba tomado. El sistema quedó bloqueado para recibir.
     *
     * Se numera por el MÁXIMO del año, como ya hacía
     * {@see \App\Models\ProductOutput::generateOutputNumber()}. Los huecos que dejen
     * los borrados se quedan como huecos, que es lo correcto en una numeración
     * documental: un consecutivo no se reutiliza.
     *
     * El bucle cubre el caso de dos recepciones creadas a la vez: si el número ya
     * se lo llevó otra petición, se pide el siguiente en vez de reventar.
     */
    public static function generateReceptionNumber(): string
    {
        $prefix = 'REC-' . date('Y') . '-';

        $ultimo = self::where('reception_number', 'like', $prefix . '%')
            ->orderBy('reception_number', 'desc')
            ->value('reception_number');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen($prefix))) + 1 : 1;

        // Defensa contra concurrencia: si alguien más tomó ese número entre la
        // consulta y el insert, se avanza al siguiente libre.
        for ($intento = 0; $intento < 50; $intento++) {
            $candidato = $prefix . str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);

            if (!self::where('reception_number', $candidato)->exists()) {
                return $candidato;
            }

            $siguiente++;
        }

        // Salida de emergencia: prefiero un número feo pero único a dejar al
        // bodeguero sin poder recibir.
        return $prefix . str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT) . '-' . uniqid();
    }

    protected $table = 'receptions';

    protected $fillable = [
        'reception_number',
        'source_id',
        'source_type',
        'origin_location_id',
        'destination_location_id',
        'shipment_date',
        'status',
        'total_expected',
        'total_received',
        'completion_percentage',
        'responsible_user',
        'observations',
    ];

    protected $casts = [
        'source_type' => 'string',
        'shipment_date' => 'date',
        'status' => 'string',
        'total_expected' => 'decimal:2',
        'total_received' => 'decimal:2',
        'completion_percentage' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeBySourceType($query, $type)
    {
        return $query->where('source_type', $type);
    }

    // Relationships

    // Polymorphic relationship to source (Purchase or ProductOutput)
    // DISABLED: morphTo causes SQL errors trying to add source_type to source tables
    // Use getSource() helper method in controller instead
    /*
    public function source()
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }
    */

    // Alternative specific relationships for easier querying
    // DISABLED: These add where('source_type') to Purchase/ProductOutput tables which don't have that column
    /*
    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'source_id')
            ->where('source_type', 'purchase');
    }

    public function productOutput()
    {
        return $this->belongsTo(ProductOutput::class, 'source_id')
            ->where('source_type', 'output');
    }
    */

    public function originLocation()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user');
    }

    // Reception items
    public function receptionItems()
    {
        return $this->hasMany(ReceptionItem::class, 'reception_id');
    }

    // Reception batches
    public function receptionBatches()
    {
        return $this->hasMany(ReceptionBatch::class, 'reception_id');
    }

    // Inventory movements related to this reception (polymorphic)
    public function inventoryMovements()
    {
        return $this->morphMany(InventoryMovement::class, 'related_document', 'related_document_type', 'related_document_id');
    }
}
