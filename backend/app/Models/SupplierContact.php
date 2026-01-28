<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupplierContact extends Model
{
    use HasUuids;

    protected $table = 'supplier_contacts';

    public $timestamps = false;

    protected $fillable = [
        'supplier_id',
        'name',
        'position',
        'phone',
        'email',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relationships

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
