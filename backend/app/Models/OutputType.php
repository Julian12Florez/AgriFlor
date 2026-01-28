<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OutputType extends Model
{
    use HasUuids;

    protected $table = 'output_types';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'code',
        'description',
        'requires_lots',
        'status',
    ];

    protected $casts = [
        'requires_lots' => 'boolean',
        'status' => 'string',
        'created_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRequiringLots($query)
    {
        return $query->where('requires_lots', true);
    }

    // Relationships

    // Product outputs of this type
    public function productOutputs()
    {
        return $this->hasMany(ProductOutput::class, 'output_type_id');
    }
}
