<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasUuids;

    protected $table = 'suppliers';

    public $timestamps = false;

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
