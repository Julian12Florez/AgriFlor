<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaskDailyLog extends Model
{
    use HasUuids;

    protected $table = 'task_daily_logs';

    protected $fillable = [
        'task_schedule_id',
        'log_date',
        'registered_at',
        'mode',
        'advance_pct_today',
        'accumulated_snapshot_pct',
        'persons_today',
        'suspicious',
        'suspicious_confirmed',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'log_date' => 'date',
        'registered_at' => 'datetime',
        'advance_pct_today' => 'decimal:2',
        'accumulated_snapshot_pct' => 'decimal:2',
        'persons_today' => 'integer',
        'suspicious' => 'boolean',
        'suspicious_confirmed' => 'boolean',
    ];

    public function schedule()
    {
        return $this->belongsTo(TaskSchedule::class, 'task_schedule_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
