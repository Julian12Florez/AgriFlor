<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use App\Models\Concerns\Auditado;

/**
 * Empresa emisora de documentos (orden de compra, remisión).
 *
 * El logo vive en la BD como base64 porque `storage/` no sobrevive al
 * redespliegue del contenedor. Por eso `logo_base64` está en `$hidden`:
 * es un blob de cientos de KB que no tiene por qué viajar en cada listado.
 * El PDF lo obtiene explícitamente vía CompanyInfo::resolve().
 */
class Company extends Model implements AuditableContract
{
    use HasUuids, Auditado;

    protected $table = 'companies';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'nit',
        'address',
        'city',
        'phone',
        'email',
        'legal_rep',
        'tax_regime',
        'ciiu',
        'template',
        'logo_mime',
        'logo_base64',
        'is_default',
        'status',
    ];

    /**
     * Nunca se serializa por accidente en una respuesta JSON.
     */
    protected $hidden = [
        'logo_base64',
    ];

    /**
     * El logo tampoco se copia a la auditoría: cientos de KB de base64 en
     * `audits.new_values` (TEXT, 64 KB) hacían fallar con 500 la subida de
     * cualquier logo de más de ~48 KB, y no le dicen nada a un humano. Queda
     * constancia de que cambió (ver cambiosExtraDeAuditoria).
     */
    protected $auditExclude = [
        'logo_base64',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * "Logo: Sin logo → Logo nuevo" en lugar de la imagen.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    protected function cambiosExtraDeAuditoria(): array
    {
        if ($this->auditEvent === 'created') {
            // `logo_mime` va siempre con el logo, y sí se lee al completar la
            // foto (el base64 no se carga para eso).
            return [[], ($this->logo_base64 || $this->logo_mime) ? ['logo' => 'Cargado'] : []];
        }

        if ($this->auditEvent === 'updated' && $this->isDirty('logo_base64')) {
            return [
                ['logo' => $this->getRawOriginal('logo_base64') ? 'Logo anterior' : 'Sin logo'],
                ['logo' => $this->logo_base64 ? 'Logo nuevo' : 'Sin logo'],
            ];
        }

        return [[], []];
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Relationships

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'company_id');
    }

    public function productOutputs()
    {
        return $this->hasMany(ProductOutput::class, 'company_id');
    }
}
