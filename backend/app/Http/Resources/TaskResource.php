<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'durationHours' => (float) $this->duration_hours,
            'duration_hours' => (float) $this->duration_hours,
            'dailyCost' => (float) $this->daily_cost,
            'daily_cost' => (float) $this->daily_cost,
            'description' => $this->description,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];

        // Include deductions if loaded
        if ($this->relationLoaded('deductions')) {
            $data['deductions'] = $this->deductions->map(function ($deduction) {
                return [
                    'id' => $deduction->id,
                    'deductionName' => $deduction->deduction_name,
                    'deduction_name' => $deduction->deduction_name,
                    'percentage' => (float) $deduction->percentage,
                    'isActive' => $deduction->is_active,
                    'is_active' => $deduction->is_active,
                ];
            });
        }

        // Include net amount calculation if requested
        if ($this->relationLoaded('activeDeductions')) {
            $calculation = $this->calculateNetAmount();
            $data['netAmount'] = $calculation['net_amount'];
            $data['net_amount'] = $calculation['net_amount'];
            $data['totalDeductions'] = $calculation['total_deductions'];
            $data['total_deductions'] = $calculation['total_deductions'];
            $data['totalDeductionPercentage'] = $calculation['total_deduction_percentage'];
            $data['total_deduction_percentage'] = $calculation['total_deduction_percentage'];
        }

        return $data;
    }
}
