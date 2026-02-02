<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'workerCode' => $this->worker_code,
            'worker_code' => $this->worker_code,
            'taskCode' => $this->task_code,
            'task_code' => $this->task_code,
            'grossAmount' => (float) $this->gross_amount,
            'gross_amount' => (float) $this->gross_amount,
            'totalDeductions' => (float) $this->total_deductions,
            'total_deductions' => (float) $this->total_deductions,
            'netAmount' => (float) $this->net_amount,
            'net_amount' => (float) $this->net_amount,
            'deductionsDetail' => $this->deductions_detail,
            'deductions_detail' => $this->deductions_detail,
            'processedAt' => $this->processed_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
            'worker' => $this->when($this->relationLoaded('worker'), function () {
                return new WorkerResource($this->worker);
            }),
            'task' => $this->when($this->relationLoaded('task'), function () {
                return new TaskResource($this->task);
            }),
            'processedBy' => $this->when($this->relationLoaded('processedByUser'), function () {
                return [
                    'id' => $this->processedByUser->id,
                    'name' => $this->processedByUser->name,
                ];
            }),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
