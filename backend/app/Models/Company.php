<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

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
    use HasUuids, Auditable;

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

    protected $casts = [
        'is_default' => 'boolean',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
