<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FarmLot extends Model
{
    use HasUuids;

    protected $table = 'farm_lots';

    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'name',
        'area',
        'area_unit',
        'total_trees',
        'area_hectares',
        'total_cubic_meters',
        'total_linear_meters',
        'description',
        'status',
    ];

    protected $casts = [
        'area' => 'decimal:2',
        'total_trees' => 'decimal:0',
        'area_hectares' => 'decimal:2',
        'total_cubic_meters' => 'decimal:2',
        'total_linear_meters' => 'decimal:2',
        'status' => 'string',
        'created_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Relationships

    // Location (Farm) this lot belongs to
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    // Product outputs that reference this lot
    public function productOutputs()
    {
        return $this->belongsToMany(ProductOutput::class, 'output_farm_lots', 'farm_lot_id', 'product_output_id')
            ->using(OutputFarmLot::class)
            ->withTimestamps();
    }
}
