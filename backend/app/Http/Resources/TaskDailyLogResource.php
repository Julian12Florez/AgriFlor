<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDailyLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'taskScheduleId' => $this->task_schedule_id,
            'task_schedule_id' => $this->task_schedule_id,
            'logDate' => $this->log_date?->format('Y-m-d'),
            'log_date' => $this->log_date?->format('Y-m-d'),
            'registeredAt' => $this->registered_at?->toISOString(),
            'registered_at' => $this->registered_at?->toISOString(),
            'mode' => $this->mode,
            'advancePctToday' => $this->advance_pct_today,
            'advance_pct_today' => $this->advance_pct_today,
            'accumulatedSnapshotPct' => $this->accumulated_snapshot_pct,
            'accumulated_snapshot_pct' => $this->accumulated_snapshot_pct,
            'personsToday' => $this->persons_today,
            'persons_today' => $this->persons_today,
            'suspicious' => $this->suspicious,
            'suspiciousConfirmed' => $this->suspicious_confirmed,
            'suspicious_confirmed' => $this->suspicious_confirmed,
            'observations' => $this->observations,
            'createdBy' => $this->created_by,
            'created_by' => $this->created_by,
            'createdAt' => $this->created_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
