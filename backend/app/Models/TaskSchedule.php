<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaskSchedule extends Model
{
    use HasUuids;

    protected $table = 'task_schedules';

    protected $fillable = [
        'code',
        'task_catalog_id',
        'location_id',
        'lot_id',
        'total_quantity',
        'start_date',
        'end_date',
        'working_days',
        'planned_persons',
        'external_farm_workers',
        'third_party_workers',
        'budgeted_jornales',
        'suggested_persons',
        'suggested_working_days',
        'suggested_end_date',
        'reference_yield_used',
        'accumulated_pct',
        'real_jornales',
        'status',
        'final_performance_pct',
        'final_level',
        'completed_at',
        'cancellation_reason',
        'is_ad_hoc',
        'ad_hoc_motive',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'total_quantity' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'working_days' => 'integer',
        'planned_persons' => 'integer',
        'external_farm_workers' => 'integer',
        'third_party_workers' => 'integer',
        'budgeted_jornales' => 'integer',
        'suggested_persons' => 'integer',
        'suggested_working_days' => 'integer',
        'suggested_end_date' => 'date',
        'reference_yield_used' => 'decimal:2',
        'accumulated_pct' => 'decimal:2',
        'real_jornales' => 'integer',
        'final_performance_pct' => 'decimal:2',
        'completed_at' => 'datetime',
        'is_ad_hoc' => 'boolean',
    ];

    public function task()
    {
        return $this->belongsTo(TaskCatalog::class, 'task_catalog_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function lot()
    {
        return $this->belongsTo(FarmLot::class, 'lot_id');
    }

    public function logs()
    {
        return $this->hasMany(TaskDailyLog::class, 'task_schedule_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(TaskScheduleAudit::class, 'task_schedule_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['planificada', 'en_progreso']);
    }

    /**
     * Filtra los schedules para un usuario según su rol.
     * - admin / supervisor / financiero: ven TODAS
     * - farm (encargado de finca): solo ve las locations donde es responsible_user_id
     * - otros roles: ven todas (no se restringen)
     */
    public function scopeForUser($query, $user)
    {
        if (!$user) return $query;
        $roleName = $user->roleRelation?->name ?? $user->role;
        if ($roleName === 'farm') {
            $locationIds = \App\Models\Location::where('responsible_user_id', $user->id)
                ->pluck('id');
            return $query->whereIn('location_id', $locationIds);
        }
        return $query;
    }
}
