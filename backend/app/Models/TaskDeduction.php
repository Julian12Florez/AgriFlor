<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditado;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TaskDeduction extends Model implements AuditableContract
{
    use HasFactory, HasUuids, Auditado;

    protected $table = 'task_deductions';

    protected $fillable = [
        'task_id',
        'deduction_name',
        'percentage',
        'is_active',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
