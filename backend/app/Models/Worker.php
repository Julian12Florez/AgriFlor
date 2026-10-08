<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditado;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Worker extends Model implements AuditableContract
{
    use HasFactory, HasUuids, Auditado;

    protected $table = 'workers';

    protected $fillable = [
        'worker_code',
        'full_name',
        'document_id',
        'hire_date',
        'status',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('worker_code', $code);
    }

    // Relationships
    public function dailyAssignments()
    {
        return $this->hasMany(DailyAssignment::class);
    }
}
