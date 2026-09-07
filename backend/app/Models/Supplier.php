<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Supplier extends Model implements AuditableContract
{
    use HasUuids, Auditable;

    protected $table = 'suppliers';

    public $timestamps = false;

    /**
     * FUENTE ÚNICA DE VERDAD: proveedores que NO son proveedores.
     *
     * Cuando una finca devuelve producto sobrante a la bodega, el canal correcto
     * es Salidas → Remanente (origen = la finca), que descuenta la finca y
     * acredita la bodega. Durante 15 meses esa devolución se registró como una
     * COMPRA a un proveedor inventado ("REMANENTES FINCA"): 47 documentos y
     * 178.838,49 unidades, contra 4.271,91 por el canal legítimo.
     *
     * Esa compra acredita la bodega y NUNCA debita la finca. Mientras la finca no
     * tuvo existencias (21-ago → sep-2026) eso solo era una etiqueta equivocada.
     * Desde que la finca volvió a custodiar lo que recibe, el mismo producto
     * queda contado DOS VECES: en la bodega por la compra y en la finca porque
     * nadie lo descontó.
     *
     * Por eso se rechaza la compra y se redirige al flujo correcto, que ya es
     * usable justamente porque la finca tiene stock otra vez.
     */
    public const PATRON_DEVOLUCION_DE_FINCA = 'REMANENTE';

    /**
     * ¿Este nombre de proveedor representa en realidad una devolución de finca?
     *
     * Acepta null (proveedor borrado) y responde false: ante la duda no se
     * bloquea una compra legítima.
     */
    public static function esDevolucionDeFinca(?string $nombre): bool
    {
        if ($nombre === null || $nombre === '') {
            return false;
        }

        return str_contains(
            mb_strtoupper($nombre, 'UTF-8'),
            self::PATRON_DEVOLUCION_DE_FINCA
        );
    }

    protected $fillable = [
        'name',
        'nit',
        'address',
        'city',
        'phone',
        'email',
        'payment_terms',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
        'created_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Relationships

    // Supplier contacts
    public function contacts()
    {
        return $this->hasMany(SupplierContact::class, 'supplier_id');
    }

    // Purchases from this supplier
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'supplier_id');
    }
}
