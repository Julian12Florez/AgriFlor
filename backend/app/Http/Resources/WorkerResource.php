<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workerCode' => $this->worker_code,
            'worker_code' => $this->worker_code,
            'fullName' => $this->full_name,
            'full_name' => $this->full_name,
            'documentId' => $this->document_id,
            'document_id' => $this->document_id,
            'hireDate' => $this->hire_date?->format('Y-m-d'),
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'status' => $this->status,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
