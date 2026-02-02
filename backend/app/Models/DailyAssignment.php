<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'daily_assignments';

    protected $fillable = [
        'date',
        'worker_id',
        'task_id',
        'worker_code',
        'task_code',
        'gross_amount',
        'total_deductions',
        'net_amount',
        'deductions_detail',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'deductions_detail' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByWorker($query, $workerId)
    {
        return $query->where('worker_id', $workerId);
    }

    public function scopeByTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    // Relationships
    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
