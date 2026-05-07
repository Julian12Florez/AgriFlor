<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaskScheduleAudit extends Model
{
    use HasUuids;

    protected $table = 'task_schedule_audit';

    public $timestamps = false;

    protected $fillable = [
        'task_schedule_id',
        'field_changed',
        'old_value',
        'new_value',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(TaskSchedule::class, 'task_schedule_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
