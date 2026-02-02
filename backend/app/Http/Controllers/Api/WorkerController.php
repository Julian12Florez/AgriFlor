<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Http\Resources\WorkerResource;
use App\Models\Worker;
use App\Imports\WorkersImport;
use App\Exports\WorkersTemplateExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class WorkerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Worker::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('worker_code', 'like', "%{$search}%")
                    ->orWhere('document_id', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $workers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return WorkerResource::collection($workers);
    }

    public function store(StoreWorkerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';

        $worker = Worker::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Trabajador creado exitosamente',
            'data' => new WorkerResource($worker)
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $worker = Worker::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new WorkerResource($worker)
        ]);
    }

    public function update(UpdateWorkerRequest $request, string $id): JsonResponse
    {
        $worker = Worker::findOrFail($id);
        $worker->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Trabajador actualizado exitosamente',
            'data' => new WorkerResource($worker)
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $worker = Worker::findOrFail($id);

        // Check if worker has assignments
        if ($worker->dailyAssignments()->exists()) {
            // Soft disable instead of delete
            $worker->update(['status' => 'inactive']);
            return response()->json([
                'success' => true,
                'message' => 'Trabajador desactivado (tiene asignaciones asociadas)'
            ]);
        }

        $worker->delete();

        return response()->json([
            'success' => true,
            'message' => 'Trabajador eliminado exitosamente'
        ]);
    }

    /**
     * Lightweight list for dropdowns
     */
    public function listSimple(Request $request): JsonResponse
    {
        $query = Worker::query()->select('id', 'worker_code', 'full_name', 'status');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $workers = $query->orderBy('full_name')->get();

        return response()->json([
            'data' => $workers
        ]);
    }

    /**
     * Download Excel template for bulk worker upload
     */
    public function downloadTemplate()
    {
        return Excel::download(new WorkersTemplateExport(), 'plantilla_trabajadores.xlsx');
    }

    /**
     * Upload Excel file and return preview data with validation
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'file.required' => 'El archivo es requerido',
            'file.mimes' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV (.csv)',
            'file.max' => 'El archivo no puede exceder 5MB',
        ]);

        $rows = Excel::toArray(new WorkersImport(), $request->file('file'));

        if (empty($rows) || empty($rows[0])) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo está vacío o no tiene datos válidos'
            ], 422);
        }

        $data = $rows[0];
        $valid = [];
        $invalid = [];

        // Cache existing codes and documents for duplicate detection
        $existingCodes = Worker::pluck('worker_code')->flip();
        $existingDocs = Worker::pluck('document_id')->flip();

        // Track codes/docs within the file to detect intra-file duplicates
        $fileCodes = [];
        $fileDocs = [];

        foreach ($data as $index => $row) {
            $rowNumber = $index + 2;
            $workerCode = trim($row[0] ?? '');
            $fullName = trim($row[1] ?? '');
            $documentId = trim($row[2] ?? '');
            $hireDate = trim($row[3] ?? '');

            if (empty($workerCode) && empty($fullName) && empty($documentId)) {
                continue; // Skip empty rows
            }

            $errors = [];

            // Required fields
            if (empty($workerCode)) {
                $errors[] = 'Código de empleado vacío';
            }
            if (empty($fullName)) {
                $errors[] = 'Nombre completo vacío';
            }
            if (empty($documentId)) {
                $errors[] = 'Documento de identidad vacío';
            }
            if (empty($hireDate)) {
                $errors[] = 'Fecha de ingreso vacía';
            }

            // Max length
            if (strlen($workerCode) > 50) {
                $errors[] = 'Código excede 50 caracteres';
            }
            if (strlen($fullName) > 255) {
                $errors[] = 'Nombre excede 255 caracteres';
            }
            if (strlen($documentId) > 50) {
                $errors[] = 'Documento excede 50 caracteres';
            }

            // Date validation
            if (!empty($hireDate)) {
                $parsedDate = date_create($hireDate);
                if (!$parsedDate) {
                    $errors[] = "Fecha '$hireDate' no es válida (usar formato YYYY-MM-DD)";
                } else {
                    $hireDate = $parsedDate->format('Y-m-d');
                }
            }

            // Uniqueness: worker_code in DB
            if (!empty($workerCode) && $existingCodes->has($workerCode)) {
                $errors[] = "Código '$workerCode' ya existe en el sistema";
            }

            // Uniqueness: document_id in DB
            if (!empty($documentId) && $existingDocs->has($documentId)) {
                $errors[] = "Documento '$documentId' ya existe en el sistema";
            }

            // Uniqueness: within the file
            if (!empty($workerCode) && in_array($workerCode, $fileCodes)) {
                $errors[] = "Código '$workerCode' duplicado en el archivo";
            }
            if (!empty($documentId) && in_array($documentId, $fileDocs)) {
                $errors[] = "Documento '$documentId' duplicado en el archivo";
            }

            if (!empty($errors)) {
                $invalid[] = [
                    'row' => $rowNumber,
                    'worker_code' => $workerCode,
                    'full_name' => $fullName,
                    'document_id' => $documentId,
                    'hire_date' => $hireDate,
                    'errors' => $errors,
                ];
            } else {
                $valid[] = [
                    'row' => $rowNumber,
                    'worker_code' => $workerCode,
                    'full_name' => $fullName,
                    'document_id' => $documentId,
                    'hire_date' => $hireDate,
                ];
            }

            $fileCodes[] = $workerCode;
            $fileDocs[] = $documentId;
        }

        return response()->json([
            'success' => true,
            'message' => 'Preview generado exitosamente',
            'data' => [
                'total_rows' => count($valid) + count($invalid),
                'valid_count' => count($valid),
                'invalid_count' => count($invalid),
                'valid' => $valid,
                'invalid' => $invalid,
            ],
        ]);
    }

    /**
     * Process confirmed worker import
     */
    public function processImport(Request $request): JsonResponse
    {
        $request->validate([
            'workers' => ['required', 'array', 'min:1'],
            'workers.*.worker_code' => ['required', 'string', 'max:50'],
            'workers.*.full_name' => ['required', 'string', 'max:255'],
            'workers.*.document_id' => ['required', 'string', 'max:50'],
            'workers.*.hire_date' => ['required', 'date'],
        ], [
            'workers.required' => 'Debe enviar al menos un trabajador',
            'workers.min' => 'Debe enviar al menos un trabajador',
        ]);

        $processed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->workers as $index => $workerData) {
                // Check uniqueness before insert
                $codeExists = Worker::where('worker_code', $workerData['worker_code'])->exists();
                $docExists = Worker::where('document_id', $workerData['document_id'])->exists();

                if ($codeExists) {
                    $errors[] = [
                        'row' => $index + 1,
                        'worker_code' => $workerData['worker_code'],
                        'error' => "Código '{$workerData['worker_code']}' ya existe",
                    ];
                    continue;
                }

                if ($docExists) {
                    $errors[] = [
                        'row' => $index + 1,
                        'worker_code' => $workerData['worker_code'],
                        'error' => "Documento '{$workerData['document_id']}' ya existe",
                    ];
                    continue;
                }

                Worker::create([
                    'worker_code' => $workerData['worker_code'],
                    'full_name' => $workerData['full_name'],
                    'document_id' => $workerData['document_id'],
                    'hire_date' => $workerData['hire_date'],
                    'status' => 'active',
                ]);

                $processed++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Importación completada: $processed trabajadores creados",
                'data' => [
                    'processed' => $processed,
                    'errors' => $errors,
                    'total_sent' => count($request->workers),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al importar trabajadores: ' . $e->getMessage()
            ], 500);
        }
    }
}
