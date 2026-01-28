<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasUuids;

    protected $table = 'alerts';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'title',
        'description',
        'location_id',
        'product_id',
        'severity',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'type' => 'string',
        'severity' => 'string',
        'status' => 'string',
        'created_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('severity', 'high')->where('status', 'active');
    }

    // Relationships

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
