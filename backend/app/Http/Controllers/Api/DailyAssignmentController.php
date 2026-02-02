<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyAssignmentResource;
use App\Models\DailyAssignment;
use App\Models\Task;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DailyAssignmentsImport;
use App\Exports\DailyAssignmentsTemplateExport;

class DailyAssignmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DailyAssignment::query()->with(['worker', 'task', 'processedByUser']);

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        if ($request->has('worker_id')) {
            $query->byWorker($request->worker_id);
        }

        if ($request->has('task_id')) {
            $query->byTask($request->task_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('worker_code', 'like', "%{$search}%")
                    ->orWhere('task_code', 'like', "%{$search}%")
                    ->orWhereHas('worker', function ($wq) use ($search) {
                        $wq->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $assignments = $query->orderBy('date', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        return DailyAssignmentResource::collection($assignments);
    }

    /**
     * Upload Excel file and return preview data
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'date' => ['required', 'date'],
        ], [
            'file.required' => 'El archivo es requerido',
            'file.mimes' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV (.csv)',
            'file.max' => 'El archivo no puede exceder 5MB',
            'date.required' => 'La fecha de asignación es requerida',
            'date.date' => 'La fecha debe ser válida',
        ]);

        $date = $request->date;
        $rows = Excel::toArray(new DailyAssignmentsImport(), $request->file('file'));

        if (empty($rows) || empty($rows[0])) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo está vacío o no tiene datos válidos'
            ], 422);
        }

        $data = $rows[0];
        $valid = [];
        $invalid = [];

        // Cache workers and tasks for performance
        $workers = Worker::active()->get()->keyBy('worker_code');
        $tasks = Task::active()->with('activeDeductions')->get()->keyBy('code');

        foreach ($data as $index => $row) {
            $rowNumber = $index + 2; // +2 because index starts at 0 and header is row 1
            $workerCode = trim($row[0] ?? '');
            $taskCode = trim($row[1] ?? '');

            if (empty($workerCode) && empty($taskCode)) {
                continue; // Skip empty rows
            }

            $errors = [];

            if (empty($workerCode)) {
                $errors[] = 'Código de empleado vacío';
            }
            if (empty($taskCode)) {
                $errors[] = 'Código de tarea vacío';
            }

            $worker = $workers->get($workerCode);
            $task = $tasks->get($taskCode);

            if (!empty($workerCode) && !$worker) {
                $errors[] = "Trabajador '$workerCode' no encontrado o inactivo";
            }

            if (!empty($taskCode) && !$task) {
                $errors[] = "Tarea '$taskCode' no encontrada o inactiva";
            }

            // Check for duplicate assignment on same date
            if ($worker && $task) {
                $existingAssignment = DailyAssignment::where('date', $date)
                    ->where('worker_id', $worker->id)
                    ->where('task_id', $task->id)
                    ->exists();

                if ($existingAssignment) {
                    $errors[] = "Ya existe una asignación para este trabajador y tarea en la fecha $date";
                }
            }

            if (!empty($errors)) {
                $invalid[] = [
                    'row' => $rowNumber,
                    'worker_code' => $workerCode,
                    'task_code' => $taskCode,
                    'errors' => $errors,
                ];
            } else {
                $calculation = $task->calculateNetAmount();
                $valid[] = [
                    'row' => $rowNumber,
                    'worker_code' => $workerCode,
                    'worker_name' => $worker->full_name,
                    'task_code' => $taskCode,
                    'task_name' => $task->name,
                    'gross_amount' => $calculation['gross_amount'],
                    'total_deductions' => $calculation['total_deductions'],
                    'net_amount' => $calculation['net_amount'],
                    'deductions_detail' => $calculation['deductions_detail'],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Preview generado exitosamente',
            'data' => [
                'date' => $date,
                'total_rows' => count($valid) + count($invalid),
                'valid_count' => count($valid),
                'invalid_count' => count($invalid),
                'valid' => $valid,
                'invalid' => $invalid,
                'totals' => [
                    'gross_amount' => collect($valid)->sum('gross_amount'),
                    'total_deductions' => collect($valid)->sum('total_deductions'),
                    'net_amount' => collect($valid)->sum('net_amount'),
                ],
            ],
        ]);
    }

    /**
     * Process confirmed assignments
     */
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.worker_code' => ['required', 'string'],
            'assignments.*.task_code' => ['required', 'string'],
        ], [
            'date.required' => 'La fecha es requerida',
            'assignments.required' => 'Debe enviar al menos una asignación',
            'assignments.min' => 'Debe enviar al menos una asignación',
        ]);

        $date = $request->date;
        $userId = auth()->id();
        $processed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->assignments as $index => $assignment) {
                $worker = Worker::where('worker_code', $assignment['worker_code'])->active()->first();
                $task = Task::where('code', $assignment['task_code'])->active()->with('activeDeductions')->first();

                if (!$worker || !$task) {
                    $errors[] = [
                        'row' => $index + 1,
                        'worker_code' => $assignment['worker_code'],
                        'task_code' => $assignment['task_code'],
                        'error' => 'Trabajador o tarea no encontrado/inactivo',
                    ];
                    continue;
                }

                // Check for duplicates
                $exists = DailyAssignment::where('date', $date)
                    ->where('worker_id', $worker->id)
                    ->where('task_id', $task->id)
                    ->exists();

                if ($exists) {
                    $errors[] = [
                        'row' => $index + 1,
                        'worker_code' => $assignment['worker_code'],
                        'task_code' => $assignment['task_code'],
                        'error' => 'Asignación duplicada para esta fecha',
                    ];
                    continue;
                }

                $calculation = $task->calculateNetAmount();

                DailyAssignment::create([
                    'date' => $date,
                    'worker_id' => $worker->id,
                    'task_id' => $task->id,
                    'worker_code' => $worker->worker_code,
                    'task_code' => $task->code,
                    'gross_amount' => $calculation['gross_amount'],
                    'total_deductions' => $calculation['total_deductions'],
                    'net_amount' => $calculation['net_amount'],
                    'deductions_detail' => $calculation['deductions_detail'],
                    'processed_at' => now(),
                    'processed_by' => $userId,
                ]);

                $processed++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Procesamiento completado: $processed asignaciones creadas",
                'data' => [
                    'processed' => $processed,
                    'errors' => $errors,
                    'total_sent' => count($request->assignments),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar asignaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a single manual assignment
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
            'worker_id' => ['required', 'uuid', 'exists:workers,id'],
            'task_id' => ['required', 'uuid', 'exists:tasks,id'],
        ], [
            'date.required' => 'La fecha es requerida',
            'date.date' => 'La fecha debe ser válida',
            'worker_id.required' => 'El trabajador es requerido',
            'worker_id.exists' => 'El trabajador no existe',
            'task_id.required' => 'La tarea es requerida',
            'task_id.exists' => 'La tarea no existe',
        ]);

        $worker = Worker::where('id', $request->worker_id)->active()->first();
        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'El trabajador no existe o está inactivo'
            ], 422);
        }

        $task = Task::where('id', $request->task_id)->active()->with('activeDeductions')->first();
        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'La tarea no existe o está inactiva'
            ], 422);
        }

        $exists = DailyAssignment::where('date', $request->date)
            ->where('worker_id', $worker->id)
            ->where('task_id', $task->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una asignación para este trabajador y tarea en la fecha seleccionada'
            ], 422);
        }

        $calculation = $task->calculateNetAmount();

        $assignment = DailyAssignment::create([
            'date' => $request->date,
            'worker_id' => $worker->id,
            'task_id' => $task->id,
            'worker_code' => $worker->worker_code,
            'task_code' => $task->code,
            'gross_amount' => $calculation['gross_amount'],
            'total_deductions' => $calculation['total_deductions'],
            'net_amount' => $calculation['net_amount'],
            'deductions_detail' => $calculation['deductions_detail'],
            'processed_at' => now(),
            'processed_by' => auth()->id(),
        ]);

        $assignment->load(['worker', 'task', 'processedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'Asignación creada exitosamente',
            'data' => new DailyAssignmentResource($assignment),
        ], 201);
    }

    /**
     * Download Excel template for bulk upload
     */
    public function downloadTemplate()
    {
        return Excel::download(new DailyAssignmentsTemplateExport(), 'plantilla_asignaciones.xlsx');
    }
}
