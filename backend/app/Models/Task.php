<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tasks';

    protected $fillable = [
        'code',
        'name',
        'duration_hours',
        'daily_cost',
        'description',
        'status',
    ];

    protected $casts = [
        'duration_hours' => 'decimal:2',
        'daily_cost' => 'decimal:2',
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
        return $query->where('code', $code);
    }

    // Relationships
    public function deductions()
    {
        return $this->hasMany(TaskDeduction::class);
    }

    public function activeDeductions()
    {
        return $this->hasMany(TaskDeduction::class)->where('is_active', true);
    }

    public function dailyAssignments()
    {
        return $this->hasMany(DailyAssignment::class);
    }

    // Business Logic
    public function calculateNetAmount(): array
    {
        $grossAmount = (float) $this->daily_cost;
        $deductions = $this->activeDeductions()->get();

        $totalDeductionPercentage = $deductions->sum('percentage');
        $totalDeductions = round($grossAmount * ($totalDeductionPercentage / 100), 2);
        $netAmount = round($grossAmount - $totalDeductions, 2);

        $deductionsDetail = $deductions->map(function ($deduction) use ($grossAmount) {
            return [
                'name' => $deduction->deduction_name,
                'percentage' => (float) $deduction->percentage,
                'amount' => round($grossAmount * ($deduction->percentage / 100), 2),
            ];
        })->toArray();

        return [
            'gross_amount' => $grossAmount,
            'total_deduction_percentage' => $totalDeductionPercentage,
            'total_deductions' => $totalDeductions,
            'net_amount' => $netAmount,
            'deductions_detail' => $deductionsDetail,
        ];
    }
}
