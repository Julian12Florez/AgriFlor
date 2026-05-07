<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'taskCatalogId' => $this->task_catalog_id,
            'task_catalog_id' => $this->task_catalog_id,
            'task' => $this->whenLoaded('task', fn() => [
                'id' => $this->task->id,
                'code' => $this->task->code,
                'name' => $this->task->name,
                'unit' => $this->task->unit,
                'category' => $this->task->category,
                'referenceYield' => $this->task->reference_yield,
            ]),
            'locationId' => $this->location_id,
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn() => [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ]),
            'lotId' => $this->lot_id,
            'lot_id' => $this->lot_id,
            'lot' => $this->whenLoaded('lot', fn() => $this->lot ? [
                'id' => $this->lot->id,
                'name' => $this->lot->name,
                'totalTrees' => $this->lot->total_trees,
                'areaHectares' => $this->lot->area_hectares,
            ] : null),
            'totalQuantity' => $this->total_quantity,
            'total_quantity' => $this->total_quantity,
            'startDate' => $this->start_date?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'endDate' => $this->end_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'workingDays' => $this->working_days,
            'working_days' => $this->working_days,
            'plannedPersons' => $this->planned_persons,
            'planned_persons' => $this->planned_persons,
            'externalFarmWorkers' => $this->external_farm_workers,
            'external_farm_workers' => $this->external_farm_workers,
            'thirdPartyWorkers' => $this->third_party_workers,
            'third_party_workers' => $this->third_party_workers,
            'totalWorkers' => ($this->planned_persons ?? 0) + ($this->external_farm_workers ?? 0) + ($this->third_party_workers ?? 0),
            'budgetedJornales' => $this->budgeted_jornales,
            'budgeted_jornales' => $this->budgeted_jornales,
            'suggestedPersons' => $this->suggested_persons,
            'suggested_persons' => $this->suggested_persons,
            'suggestedWorkingDays' => $this->suggested_working_days,
            'suggested_working_days' => $this->suggested_working_days,
            'suggestedEndDate' => $this->suggested_end_date?->format('Y-m-d'),
            'suggested_end_date' => $this->suggested_end_date?->format('Y-m-d'),
            'referenceYieldUsed' => $this->reference_yield_used,
            'reference_yield_used' => $this->reference_yield_used,
            'accumulatedPct' => $this->accumulated_pct,
            'accumulated_pct' => $this->accumulated_pct,
            'realJornales' => $this->real_jornales,
            'real_jornales' => $this->real_jornales,
            'status' => $this->status,
            'finalPerformancePct' => $this->final_performance_pct,
            'final_performance_pct' => $this->final_performance_pct,
            'finalLevel' => $this->final_level,
            'final_level' => $this->final_level,
            'completedAt' => $this->completed_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancellationReason' => $this->cancellation_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'isAdHoc' => $this->is_ad_hoc,
            'is_ad_hoc' => $this->is_ad_hoc,
            'adHocMotive' => $this->ad_hoc_motive,
            'ad_hoc_motive' => $this->ad_hoc_motive,
            'observations' => $this->observations,
            'logsCount' => $this->whenCounted('logs'),
            'createdBy' => $this->created_by,
            'createdAt' => $this->created_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
