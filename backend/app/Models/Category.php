<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\Auditado;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Category extends Model implements AuditableContract
{
    use HasUuids, Auditado;

    protected $table = 'categories';

    public $timestamps = false;

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'slug',
        'description',
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
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
