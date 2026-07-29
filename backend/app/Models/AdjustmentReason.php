<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AdjustmentReason extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'direction',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
