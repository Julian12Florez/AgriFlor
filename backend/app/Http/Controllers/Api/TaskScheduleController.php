<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskDailyLogResource;
use App\Http\Resources\TaskScheduleResource;
use App\Models\FarmLot;
use App\Models\PerformanceSettings;
use App\Models\TaskCatalog;
use App\Models\TaskDailyLog;
use App\Models\TaskSchedule;
use App\Models\TaskScheduleAudit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskScheduleController extends Controller
{
    // ───── PROGRAMACIONES ─────

    /**
     * Verifica si el usuario actual puede acceder a un schedule.
     * Si rol='farm', solo puede ver/editar schedules de fincas donde es responsable.
     */
    private function canAccessSchedule(TaskSchedule $schedule): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        $roleName = $user->roleRelation?->name ?? $user->role;
        if ($roleName !== 'farm') return true; // admin/supervisor/etc ven todo
        // farm operator: debe ser responsable de la finca
        return \App\Models\Location::where('id', $schedule->location_id)
            ->where('responsible_user_id', $user->id)
            ->exists();
    }

    public function index(Request $request)
    {
        $query = TaskSchedule::with(['task', 'location', 'lot'])->withCount('logs')
            ->forUser(auth()->user());

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('task_catalog_id')) {
            $query->where('task_catalog_id', $request->task_catalog_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('is_ad_hoc')) {
            $query->where('is_ad_hoc', $request->boolean('is_ad_hoc'));
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->where('start_date', '<=', $request->date_to)
                  ->where('end_date', '>=', $request->date_from);
        }

        $perPage = $request->get('per_page', 20);
        $schedules = $query->orderByDesc('created_at')->paginate($perPage);

        return TaskScheduleResource::collection($schedules);
    }

    public function show(string $id): JsonResponse
    {
        $schedule = TaskSchedule::with(['task', 'location', 'lot', 'logs' => function ($q) {
            $q->orderBy('log_date');
        }, 'auditLogs'])->findOrFail($id);

        if (!$this->canAccessSchedule($schedule)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a esta programación. Solo puedes ver las de tus fincas asignadas.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new TaskScheduleResource($schedule),
            'logs' => TaskDailyLogResource::collection($schedule->logs),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_catalog_id' => ['required', 'uuid', 'exists:task_catalog,id'],
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'lot_id' => ['nullable', 'uuid', 'exists:farm_lots,id'],
            'total_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date', $request->boolean('is_ad_hoc') ? 'before_or_equal:tomorrow' : 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'planned_persons' => ['nullable', 'integer', 'min:0'],
            'external_farm_workers' => ['nullable', 'integer', 'min:0'],
            'third_party_workers' => ['nullable', 'integer', 'min:0'],
            'observations' => ['nullable', 'string'],
            'is_ad_hoc' => ['nullable', 'boolean'],
            'ad_hoc_motive' => ['nullable', 'string', 'required_if:is_ad_hoc,true'],
        ]);

        // Si user es farm, validar que la finca destino sea de su responsabilidad
        $authUser = auth()->user();
        $authRoleName = $authUser?->roleRelation?->name ?? $authUser?->role;
        if ($authRoleName === 'farm') {
            $isResponsible = \App\Models\Location::where('id', $validated['location_id'])
                ->where('responsible_user_id', $authUser->id)
                ->exists();
            if (!$isResponsible) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo puedes crear programaciones en fincas donde eres responsable.',
                ], 403);
            }
        }

        // Determinar trabajadores de cada tipo
        $ownPersons = $validated['planned_persons'] ?? 0;
        $externalWorkers = $validated['external_farm_workers'] ?? 0;
        $thirdPartyWorkers = $validated['third_party_workers'] ?? 0;
        $totalWorkers = $ownPersons + $externalWorkers + $thirdPartyWorkers;

        $task = TaskCatalog::findOrFail($validated['task_catalog_id']);

        // PASO 1: Resolver total_quantity ANTES de cualquier cálculo (autofill desde capacidad del lote si vino null)
        $lotId = $validated['lot_id'] ?? null;
        if ($lotId) {
            $this->validateLotCapacity($task, $lotId);

            $lot = FarmLot::findOrFail($validated['lot_id']);
            $maxCapacity = $this->getLotCapacity($task->unit, $lot);

            // Si NO se envió cantidad, tomar la capacidad total del lote
            if (empty($validated['total_quantity'])) {
                if ($maxCapacity === null) {
                    return response()->json([
                        'success' => false,
                        'message' => "Debe ingresar la cantidad o el lote debe tener configurada la capacidad para la unidad '{$task->unit}'.",
                    ], 422);
                }
                $validated['total_quantity'] = $maxCapacity;
            } elseif ($maxCapacity !== null && $validated['total_quantity'] > $maxCapacity) {
                $unitLabels = ['arbol' => 'árboles', 'hectarea' => 'hectáreas', 'm2' => 'm²', 'metro' => 'metros'];
                $unitLabel = $unitLabels[$task->unit] ?? $task->unit;
                return response()->json([
                    'success' => false,
                    'message' => "La cantidad ({$validated['total_quantity']}) supera la capacidad del lote '{$lot->name}' que tiene {$maxCapacity} {$unitLabel}.",
                    'maxCapacity' => $maxCapacity,
                ], 422);
            }
        } elseif (empty($validated['total_quantity'])) {
            return response()->json([
                'success' => false,
                'message' => 'Debe ingresar la cantidad o seleccionar un lote con capacidad configurada.',
            ], 422);
        }

        // PASO 2: LÓGICA INVERSA — si NO hay trabajadores pero SÍ hay rango de fechas,
        // calcular personas necesarias (ya tenemos total_quantity garantizada)
        if ($totalWorkers < 1 && !empty($validated['end_date'])) {
            $refYield = $task->reference_yield;
            if (!$refYield || $refYield <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "La tarea '{$task->name}' no tiene rendimiento de referencia. No se puede calcular trabajadores. Asígnelos manualmente.",
                ], 422);
            }
            $tmpDays = $this->calculateWorkingDays($validated['start_date'], $validated['end_date']);
            if ($tmpDays < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'El rango de fechas debe contener al menos un día hábil.',
                ], 422);
            }
            $ownPersons = (int) ceil($validated['total_quantity'] / ($refYield * $tmpDays));
            $totalWorkers = $ownPersons;
        }

        if ($totalWorkers < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Debe asignar al menos 1 trabajador (de la finca, otra finca o terceros) o ingresar la fecha fin para calcular automáticamente.',
            ], 422);
        }

        // Auto-calcular end_date si no se envió: start_date + dias_necesarios
        // dias_necesarios = ceil(total_quantity / (rendimiento × total_trabajadores))
        if (empty($validated['end_date'])) {
            $refYield = $task->reference_yield;
            if (!$refYield || $refYield <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "La tarea '{$task->name}' no tiene rendimiento de referencia configurado. No se puede calcular la fecha fin automáticamente. Configure el rendimiento o ingrese la fecha fin manualmente.",
                ], 422);
            }
            $daysNeeded = (int) ceil($validated['total_quantity'] / ($refYield * $totalWorkers));
            $endDate = Carbon::parse($validated['start_date'])->copy();
            $count = 0;
            while ($count < $daysNeeded) {
                $endDate->addDay();
                if ($endDate->dayOfWeek !== Carbon::SUNDAY) {
                    $count++;
                }
            }
            $validated['end_date'] = $endDate->format('Y-m-d');
        }

        // Validar solapamiento de programaciones activas en el mismo lote/tarea
        if ($lotId) {
            $overlap = TaskSchedule::where('task_catalog_id', $validated['task_catalog_id'])
                ->where('location_id', $validated['location_id'])
                ->where('lot_id', $lotId)
                ->whereIn('status', ['planificada', 'en_progreso'])
                ->where('start_date', '<=', $validated['end_date'])
                ->where('end_date', '>=', $validated['start_date'])
                ->exists();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una programación activa de la misma tarea en este lote con fechas que se solapan.',
                ], 422);
            }
        }

        $location = \App\Models\Location::findOrFail($validated['location_id']);

        // Validar disponibilidad de trabajadores propios (solo si se asignan trabajadores propios)
        if ($ownPersons > 0) {
            if (!$location->total_workers || $location->total_workers <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "La finca '{$location->name}' no tiene registrado el número de trabajadores. Actualice la ficha de la finca o use trabajadores de otra finca/terceros.",
                ], 422);
            }

            // Calcular trabajadores propios ocupados (solo planned_persons, no externos)
            $busyAtStart = TaskSchedule::where('location_id', $validated['location_id'])
                ->whereIn('status', ['planificada', 'en_progreso'])
                ->where('start_date', '<=', $validated['end_date'])
                ->where('end_date', '>=', $validated['start_date'])
                ->sum('planned_persons');

            $available = $location->total_workers - $busyAtStart;
            if ($ownPersons > $available) {
                return response()->json([
                    'success' => false,
                    'message' => "La finca '{$location->name}' tiene {$location->total_workers} trabajadores propios, {$busyAtStart} están ocupados. Solo hay {$available} disponibles, pero se solicitan {$ownPersons}. Use trabajadores de otra finca o terceros para complementar.",
                ], 422);
            }
        }

        // Calcular dias habiles (sin domingos)
        $workingDays = $this->calculateWorkingDays($validated['start_date'], $validated['end_date']);
        if ($workingDays < 1) {
            return response()->json([
                'success' => false,
                'message' => 'El rango de fechas debe contener al menos un dia habil (sin contar domingos)',
            ], 422);
        }

        // Generar codigo PROG-XXXX
        $lastCode = TaskSchedule::orderByRaw("CAST(SUBSTRING(code, 6) AS UNSIGNED) DESC")->first();
        $nextNum = $lastCode ? (int) substr($lastCode->code, 5) + 1 : 1;
        $code = 'PROG-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        // Jornales presupuestados = TOTAL trabajadores (propios + externos + terceros) × días hábiles
        $budgetedJornales = $totalWorkers * $workingDays;

        // Calcular valores sugeridos por el sistema
        $refYield = $task->reference_yield;
        $suggestedPersons = null;
        $suggestedWorkingDays = null;
        $suggestedEndDate = null;

        if ($refYield && $refYield > 0) {
            // Personas sugeridas = ceil(cantidad / (rendimiento × días hábiles))
            $suggestedPersons = $workingDays > 0
                ? (int) ceil($validated['total_quantity'] / ($refYield * $workingDays))
                : null;

            // Días hábiles sugeridos con el total de trabajadores que el usuario eligió
            $suggestedWorkingDays = $totalWorkers > 0
                ? (int) ceil($validated['total_quantity'] / ($refYield * $totalWorkers))
                : null;

            // Fecha fin sugerida = start_date + suggestedWorkingDays (saltando domingos)
            if ($suggestedWorkingDays) {
                $sugEnd = Carbon::parse($validated['start_date'])->copy();
                $count = 0;
                while ($count < $suggestedWorkingDays) {
                    $sugEnd->addDay();
                    if ($sugEnd->dayOfWeek !== Carbon::SUNDAY) {
                        $count++;
                    }
                }
                $suggestedEndDate = $sugEnd->format('Y-m-d');
            }
        }

        $schedule = TaskSchedule::create([
            'code' => $code,
            'task_catalog_id' => $validated['task_catalog_id'],
            'location_id' => $validated['location_id'],
            'lot_id' => $lotId,
            'total_quantity' => $validated['total_quantity'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'working_days' => $workingDays,
            'planned_persons' => $ownPersons,
            'external_farm_workers' => $externalWorkers,
            'third_party_workers' => $thirdPartyWorkers,
            'budgeted_jornales' => $budgetedJornales,
            'suggested_persons' => $suggestedPersons,
            'suggested_working_days' => $suggestedWorkingDays,
            'suggested_end_date' => $suggestedEndDate,
            'reference_yield_used' => $refYield,
            'status' => 'planificada',
            'is_ad_hoc' => $validated['is_ad_hoc'] ?? false,
            'ad_hoc_motive' => $validated['ad_hoc_motive'] ?? null,
            'observations' => $validated['observations'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $schedule->load(['task', 'location', 'lot']);

        return response()->json([
            'success' => true,
            'message' => 'Programacion creada exitosamente',
            'data' => new TaskScheduleResource($schedule),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $schedule = TaskSchedule::findOrFail($id);

        if (!$this->canAccessSchedule($schedule)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta programación.'], 403);
        }

        if ($schedule->status === 'completada' || $schedule->status === 'cancelada') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar una programacion completada o cancelada',
            ], 422);
        }

        $rules = ['observations' => ['nullable', 'string']];

        if ($schedule->status === 'planificada') {
            $rules = array_merge($rules, [
                'total_quantity' => ['sometimes', 'numeric', 'min:0.01'],
                'start_date' => ['sometimes', 'date'],
                'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
                'planned_persons' => ['sometimes', 'integer', 'min:1'],
                'lot_id' => ['nullable', 'uuid', 'exists:farm_lots,id'],
            ]);
        } else {
            // En progreso: solo se puede modificar end_date y observaciones
            $rules['end_date'] = ['sometimes', 'date', 'after_or_equal:' . $schedule->start_date->format('Y-m-d')];
        }

        $validated = $request->validate($rules);

        // Audit si cambian campos sensibles
        $auditFields = ['total_quantity', 'planned_persons', 'end_date', 'lot_id'];
        foreach ($auditFields as $field) {
            if (isset($validated[$field]) && $validated[$field] != $schedule->{$field}) {
                TaskScheduleAudit::create([
                    'task_schedule_id' => $schedule->id,
                    'field_changed' => $field,
                    'old_value' => (string) $schedule->{$field},
                    'new_value' => (string) $validated[$field],
                    'reason' => $request->input('audit_reason', 'Ajuste manual del supervisor'),
                    'changed_by' => auth()->id(),
                    'changed_at' => now(),
                ]);
            }
        }

        // Recalcular si cambian fechas o personas
        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $start = $validated['start_date'] ?? $schedule->start_date->format('Y-m-d');
            $end = $validated['end_date'] ?? $schedule->end_date->format('Y-m-d');
            $validated['working_days'] = $this->calculateWorkingDays($start, $end);
        }
        if (isset($validated['planned_persons']) || isset($validated['working_days'])) {
            $persons = $validated['planned_persons'] ?? $schedule->planned_persons;
            $days = $validated['working_days'] ?? $schedule->working_days;
            $validated['budgeted_jornales'] = $persons * $days;
        }

        $schedule->update($validated);
        $schedule->load(['task', 'location', 'lot']);

        return response()->json([
            'success' => true,
            'message' => 'Programacion actualizada exitosamente',
            'data' => new TaskScheduleResource($schedule),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $schedule = TaskSchedule::findOrFail($id);

        if (!$this->canAccessSchedule($schedule)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta programación.'], 403);
        }

        if ($schedule->status === 'completada' || $schedule->status === 'cancelada') {
            return response()->json([
                'success' => false,
                'message' => 'La programacion ya esta ' . $schedule->status,
            ], 422);
        }

        $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $schedule->update([
            'status' => 'cancelada',
            'cancellation_reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Programacion cancelada exitosamente',
        ]);
    }

    // ───── ESTIMACION AD-HOC ─────

    public function estimateAdHoc(Request $request): JsonResponse
    {
        $request->validate([
            'task_catalog_id' => ['required', 'uuid', 'exists:task_catalog,id'],
            'location_id' => ['required', 'uuid'],
            'lot_id' => ['nullable', 'uuid'],
            'scope_pct' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $task = TaskCatalog::findOrFail($request->task_catalog_id);
        $lot = $request->lot_id ? FarmLot::find($request->lot_id) : null;

        // Obtener capacidad del lote segun unidad de la tarea
        $lotCapacity = $lot ? $this->getLotCapacity($task->unit, $lot) : null;
        $quantity = $lotCapacity ? ($lotCapacity * $request->scope_pct / 100) : null;

        // Cascada de estimacion del rendimiento
        $yieldSource = 'none';
        $referenceYield = null;

        // 1. Historico de la misma tarea en la misma finca (90 dias)
        $fincaAvg = TaskSchedule::where('task_catalog_id', $task->id)
            ->where('location_id', $request->location_id)
            ->where('status', 'completada')
            ->where('completed_at', '>=', now()->subDays(90))
            ->whereNotNull('final_performance_pct')
            ->selectRaw('AVG(total_quantity / NULLIF(real_jornales, 0)) as avg_yield')
            ->value('avg_yield');

        if ($fincaAvg && $fincaAvg > 0) {
            $referenceYield = round($fincaAvg, 2);
            $yieldSource = 'finca_history';
        }

        // 2. Historico global (180 dias)
        if (!$referenceYield) {
            $globalAvg = TaskSchedule::where('task_catalog_id', $task->id)
                ->where('status', 'completada')
                ->where('completed_at', '>=', now()->subDays(180))
                ->whereNotNull('final_performance_pct')
                ->selectRaw('AVG(total_quantity / NULLIF(real_jornales, 0)) as avg_yield')
                ->value('avg_yield');

            if ($globalAvg && $globalAvg > 0) {
                $referenceYield = round($globalAvg, 2);
                $yieldSource = 'global_history';
            }
        }

        // 3. Referencia del catalogo
        if (!$referenceYield && $task->reference_yield) {
            $referenceYield = $task->reference_yield;
            $yieldSource = 'catalog';
        }

        // Calcular jornales presupuestados
        $budgetedJornales = null;
        $suggestedPersons = null;
        if ($referenceYield && $quantity) {
            $budgetedJornales = (int) ceil($quantity / $referenceYield);
            $suggestedPersons = max(1, $budgetedJornales); // Para 1 dia ad-hoc
        }

        return response()->json([
            'success' => true,
            'data' => [
                'taskName' => $task->name,
                'taskUnit' => $task->unit,
                'lotCapacity' => $lotCapacity,
                'quantity' => $quantity,
                'referenceYield' => $referenceYield,
                'yieldSource' => $yieldSource,
                'budgetedJornales' => $budgetedJornales,
                'suggestedPersons' => $suggestedPersons,
            ],
        ]);
    }

    // ───── REGISTROS DIARIOS ─────

    public function storeLog(Request $request, string $scheduleId): JsonResponse
    {
        $schedule = TaskSchedule::with('task')->findOrFail($scheduleId);

        if (!$this->canAccessSchedule($schedule)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta programación. Solo puedes registrar avance en tus fincas.'], 403);
        }

        if ($schedule->status === 'completada' || $schedule->status === 'cancelada') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede registrar avance en una tarea ' . $schedule->status,
            ], 422);
        }

        $validated = $request->validate([
            'log_date' => ['required', 'date'],
            'persons_today' => ['required', 'integer', 'min:1'],
            'advance_pct_today' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observations' => ['nullable', 'string'],
        ]);

        // Validar el rango permitido para el avance.
        // Se permite registrar avance desde la fecha de CREACIÓN de la programación
        // (no solo desde start_date), para poder avanzar tareas programadas a futuro.
        $logDate = Carbon::parse($validated['log_date']);
        $minDate = $schedule->created_at->copy()->startOfDay();
        if ($schedule->start_date->copy()->startOfDay()->lt($minDate)) {
            $minDate = $schedule->start_date->copy()->startOfDay();
        }
        if ($logDate->lt($minDate) || $logDate->gt($schedule->end_date)) {
            return response()->json([
                'success' => false,
                'message' => "La fecha de registro ({$validated['log_date']}) debe estar entre la creación de la programación ({$minDate->format('Y-m-d')}) y el fin del periodo ({$schedule->end_date->format('Y-m-d')}).",
            ], 422);
        }

        // Determinar modo
        $mode = 'programada';
        if ($schedule->is_ad_hoc) {
            $mode = 'ad_hoc';
        } elseif (!$logDate->isToday()) {
            $mode = 'retroactiva';
        }

        return DB::transaction(function () use ($schedule, $validated, $mode, $logDate) {
            $advancePct = $validated['advance_pct_today'] ?? null;
            $newAccumulated = $advancePct !== null
                ? $schedule->accumulated_pct + $advancePct
                : $schedule->accumulated_pct;

            $log = TaskDailyLog::create([
                'task_schedule_id' => $schedule->id,
                'log_date' => $validated['log_date'],
                'registered_at' => now(),
                'mode' => $mode,
                'advance_pct_today' => $advancePct,
                'accumulated_snapshot_pct' => $newAccumulated,
                'persons_today' => $validated['persons_today'],
                'suspicious' => false,
                'suspicious_confirmed' => false,
                'observations' => $validated['observations'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Actualizar acumulados del schedule
            $schedule->accumulated_pct = min(100, $newAccumulated);
            $schedule->real_jornales += $validated['persons_today'];
            if ($schedule->status === 'planificada') {
                $schedule->status = 'en_progreso';
            }
            $schedule->save();

            $response = [
                'success' => true,
                'message' => "Registro guardado: {$validated['persons_today']} personas el {$validated['log_date']}",
                'data' => new TaskDailyLogResource($log),
            ];

            if ($mode === 'retroactiva') {
                $daysAgo = $logDate->diffInDays(now());
                if ($daysAgo > 30) {
                    $response['retroactiveWarning'] = "Registro retroactivo de hace {$daysAgo} dias";
                }
            }

            return response()->json($response, 201);
        });
    }

    /**
     * Finalizar manualmente una programación: calcula rendimientos.
     */
    public function finalize(Request $request, string $scheduleId): JsonResponse
    {
        $schedule = TaskSchedule::with('task', 'logs')->findOrFail($scheduleId);

        if (!$this->canAccessSchedule($schedule)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta programación.'], 403);
        }

        if (!in_array($schedule->status, ['planificada', 'en_progreso'])) {
            return response()->json([
                'success' => false,
                'message' => "La tarea ya está {$schedule->status}, no se puede finalizar.",
            ], 422);
        }

        if ($schedule->logs->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede finalizar una tarea sin registros de avance.',
            ], 422);
        }

        // Calcular rendimiento final
        $realJornales = $schedule->logs->sum('persons_today');
        $finalPerformancePct = $schedule->budgeted_jornales > 0
            ? round(($schedule->budgeted_jornales / max(1, $realJornales)) * 100, 2)
            : 0;
        $finalLevel = $this->classifyPerformance($finalPerformancePct, $schedule->task);

        $schedule->status = 'completada';
        $schedule->completed_at = now();
        $schedule->real_jornales = $realJornales;
        $schedule->accumulated_pct = 100; // Forzar 100% al finalizar manualmente
        $schedule->final_performance_pct = $finalPerformancePct;
        $schedule->final_level = $finalLevel;
        $schedule->save();

        return response()->json([
            'success' => true,
            'message' => "Tarea finalizada. Rendimiento: " . strtoupper($finalLevel) . " ({$finalPerformancePct}%)",
            'data' => [
                'code' => $schedule->code,
                'budgetedJornales' => $schedule->budgeted_jornales,
                'realJornales' => $realJornales,
                'finalPerformancePct' => $finalPerformancePct,
                'finalLevel' => $finalLevel,
                'totalLogs' => $schedule->logs->count(),
                'completedAt' => $schedule->completed_at->toISOString(),
            ],
        ]);
    }

    /**
     * Rendimiento diario detallado de una programación (para reportes).
     */
    public function dailyPerformance(string $scheduleId): JsonResponse
    {
        $schedule = TaskSchedule::with('task', 'location', 'lot', 'logs')->findOrFail($scheduleId);

        if (!$this->canAccessSchedule($schedule)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta programación.'], 403);
        }

        // Agrupar logs por fecha (puede haber varios el mismo día)
        $byDate = $schedule->logs->groupBy(fn($l) => $l->log_date->format('Y-m-d'))
            ->map(function ($logsOfDay, $date) use ($schedule) {
                $persons = $logsOfDay->sum('persons_today');
                $advancePct = $logsOfDay->sum('advance_pct_today');
                // Jornales esperados por día = budgeted / working_days
                $expectedDailyJornales = $schedule->working_days > 0
                    ? round($schedule->budgeted_jornales / $schedule->working_days, 2)
                    : 0;
                $efficiency = $expectedDailyJornales > 0
                    ? round(($expectedDailyJornales / max(1, $persons)) * 100, 1)
                    : null;
                return [
                    'date' => $date,
                    'persons' => $persons,
                    'advancePct' => $advancePct > 0 ? round($advancePct, 2) : null,
                    'logsCount' => $logsOfDay->count(),
                    'expectedDailyJornales' => $expectedDailyJornales,
                    'efficiency' => $efficiency,
                    'observations' => $logsOfDay->pluck('observations')->filter()->implode(' | '),
                ];
            })->values();

        $totalDays = $byDate->count();
        $totalPersons = $byDate->sum('persons');
        $avgPersonsPerDay = $totalDays > 0 ? round($totalPersons / $totalDays, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => [
                    'code' => $schedule->code,
                    'taskName' => $schedule->task->name,
                    'locationName' => $schedule->location->name,
                    'lotName' => $schedule->lot?->name,
                    'startDate' => $schedule->start_date->format('Y-m-d'),
                    'endDate' => $schedule->end_date->format('Y-m-d'),
                    'workingDays' => $schedule->working_days,
                    'plannedPersons' => $schedule->planned_persons,
                    'externalFarmWorkers' => $schedule->external_farm_workers,
                    'thirdPartyWorkers' => $schedule->third_party_workers,
                    'budgetedJornales' => $schedule->budgeted_jornales,
                    'realJornales' => $schedule->real_jornales,
                    'accumulatedPct' => (float) $schedule->accumulated_pct,
                    'status' => $schedule->status,
                    'finalLevel' => $schedule->final_level,
                    'finalPerformancePct' => $schedule->final_performance_pct,
                ],
                'summary' => [
                    'totalDaysWorked' => $totalDays,
                    'totalPersons' => $totalPersons,
                    'avgPersonsPerDay' => $avgPersonsPerDay,
                    'expectedDailyJornales' => $schedule->working_days > 0
                        ? round($schedule->budgeted_jornales / $schedule->working_days, 2) : 0,
                ],
                'daily' => $byDate,
            ],
        ]);
    }

    public function logs(string $scheduleId): JsonResponse
    {
        $schedule = TaskSchedule::findOrFail($scheduleId);
        if (!$this->canAccessSchedule($schedule)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta programación.'], 403);
        }
        $logs = $schedule->logs()->orderBy('log_date')->get();

        return response()->json([
            'success' => true,
            'data' => TaskDailyLogResource::collection($logs),
        ]);
    }

    public function deleteLog(string $logId): JsonResponse
    {
        $log = TaskDailyLog::findOrFail($logId);
        $schedule = $log->schedule;

        if (!$this->canAccessSchedule($schedule)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta programación.'], 403);
        }

        if ($schedule->status === 'completada') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar registros de una tarea completada',
            ], 422);
        }

        DB::transaction(function () use ($log, $schedule) {
            // Recalcular acumulados
            $schedule->accumulated_pct -= $log->advance_pct_today;
            $schedule->real_jornales -= $log->persons_today;

            if ($schedule->accumulated_pct < 0) $schedule->accumulated_pct = 0;
            if ($schedule->real_jornales < 0) $schedule->real_jornales = 0;

            // Si queda sin logs, volver a planificada
            if ($schedule->logs()->where('id', '!=', $log->id)->count() === 0) {
                $schedule->status = 'planificada';
            }

            $schedule->save();
            $log->delete();

            // Recalcular snapshots de logs restantes
            $remaining = $schedule->logs()->orderBy('log_date')->get();
            $cumulative = 0;
            foreach ($remaining as $r) {
                $cumulative += $r->advance_pct_today;
                $r->accumulated_snapshot_pct = $cumulative;
                $r->save();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado y acumulados recalculados',
        ]);
    }

    // ───── PANEL DEL DIA ─────

    public function panel(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $locationId = $request->get('location_id');

        $includeFuture = $request->boolean('include_future', true);

        $query = TaskSchedule::with(['task', 'location', 'lot'])
            ->whereIn('status', ['planificada', 'en_progreso'])
            ->forUser(auth()->user());

        if (!$includeFuture) {
            // Comportamiento original: solo tareas que cubren la fecha
            $query->where('start_date', '<=', $date)
                  ->where('end_date', '>=', $date);
        }
        // Si includeFuture=true (default), incluir todas las activas (planificada y en_progreso)
        // sin filtrar por fecha → permite ver futuras y registrar avance en cualquier día

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $activeSchedules = $query->orderBy('start_date')->get();

        // Logs del dia
        $scheduleIds = $activeSchedules->pluck('id');
        $todayLogs = TaskDailyLog::whereIn('task_schedule_id', $scheduleIds)
            ->where('log_date', $date)
            ->get()
            ->keyBy('task_schedule_id');

        // Tareas completadas hoy
        $completedToday = TaskSchedule::with(['task', 'location', 'lot'])
            ->where('status', 'completada')
            ->whereDate('completed_at', $date)
            ->forUser(auth()->user());
        if ($locationId) {
            $completedToday->where('location_id', $locationId);
        }
        $completedToday = $completedToday->get();

        // Tarjetas de resumen
        $totalPersons = $todayLogs->sum('persons_today');
        $totalTasks = $activeSchedules->count();
        $adHocCount = $activeSchedules->where('is_ad_hoc', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'summary' => [
                    'totalTasks' => $totalTasks,
                    'totalPersons' => $totalPersons,
                    'programmedCount' => $totalTasks - $adHocCount,
                    'adHocCount' => $adHocCount,
                ],
                'activeSchedules' => $activeSchedules->map(function ($s) use ($todayLogs) {
                    $log = $todayLogs->get($s->id);
                    return [
                        'schedule' => new TaskScheduleResource($s),
                        'todayLog' => $log ? new TaskDailyLogResource($log) : null,
                        'hasLogToday' => $log !== null,
                    ];
                }),
                'completedToday' => TaskScheduleResource::collection($completedToday),
            ],
        ]);
    }

    // ───── DISPONIBILIDAD DE TRABAJADORES ─────

    public function workerAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'exclude_schedule_id' => ['nullable', 'uuid'],
        ]);

        $location = \App\Models\Location::findOrFail($request->location_id);

        // Si rol farm, validar que sea responsable de esta finca
        $user = auth()->user();
        $roleName = $user?->roleRelation?->name ?? $user?->role;
        if ($roleName === 'farm' && $location->responsible_user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta finca.'], 403);
        }

        $totalWorkers = $location->total_workers ?? 0;
        $dateFrom = $request->date_from ?? now()->format('Y-m-d');
        $dateTo = $request->date_to ?? now()->addDays(30)->format('Y-m-d');

        // Tareas activas que se solapan con el rango
        $activeQuery = TaskSchedule::with('task')
            ->where('location_id', $request->location_id)
            ->whereIn('status', ['planificada', 'en_progreso'])
            ->where('start_date', '<=', $dateTo)
            ->where('end_date', '>=', $dateFrom);

        if ($request->filled('exclude_schedule_id')) {
            $activeQuery->where('id', '!=', $request->exclude_schedule_id);
        }

        $activeSchedules = $activeQuery->get();

        // Trabajadores ocupados hoy
        $today = now()->format('Y-m-d');
        $busyToday = $activeSchedules
            ->filter(fn($s) => $s->start_date->lte($today) && $s->end_date->gte($today))
            ->sum('planned_persons');

        // Próximos a liberar (terminan en 1-3 días)
        $soonAvailable = $activeSchedules
            ->filter(function ($s) use ($today) {
                $daysLeft = now()->diffInDays($s->end_date, false);
                return $daysLeft >= 0 && $daysLeft <= 3 && $s->status === 'en_progreso';
            })
            ->map(fn($s) => [
                'code' => $s->code,
                'taskName' => $s->task->name,
                'persons' => $s->planned_persons,
                'endDate' => $s->end_date->format('Y-m-d'),
                'daysRemaining' => max(0, (int) now()->diffInDays($s->end_date, false)),
                'accumulatedPct' => (float) $s->accumulated_pct,
            ])
            ->values();

        // Detalle de tareas activas
        $activeTasks = $activeSchedules->map(fn($s) => [
            'code' => $s->code,
            'taskName' => $s->task->name,
            'persons' => $s->planned_persons,
            'startDate' => $s->start_date->format('Y-m-d'),
            'endDate' => $s->end_date->format('Y-m-d'),
            'status' => $s->status,
            'accumulatedPct' => (float) $s->accumulated_pct,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'locationName' => $location->name,
                'totalWorkers' => $totalWorkers,
                'busyToday' => $busyToday,
                'availableToday' => max(0, $totalWorkers - $busyToday),
                'activeTasks' => $activeTasks,
                'soonAvailable' => $soonAvailable,
                'soonAvailablePersons' => $soonAvailable->sum('persons'),
            ],
        ]);
    }

    // ───── HELPERS PRIVADOS ─────

    private function calculateWorkingDays(string $start, string $end): int
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        $days = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if ($date->dayOfWeek !== Carbon::SUNDAY) {
                $days++;
            }
        }

        return $days;
    }

    private function validateLotCapacity(TaskCatalog $task, string $lotId): void
    {
        $lot = FarmLot::findOrFail($lotId);

        $columnMap = [
            'arbol' => 'total_trees',
            'hectarea' => 'area_hectares',
            'm2' => 'area_hectares', // Derivar de hectareas
            'metro' => 'total_linear_meters',
        ];

        $column = $columnMap[$task->unit] ?? null;

        if ($column && is_null($lot->{$column})) {
            $unitLabels = [
                'arbol' => 'arboles (total_trees)',
                'hectarea' => 'hectareas (area_hectares)',
                'm2' => 'area en hectareas',
                'metro' => 'metros lineales (total_linear_meters)',
            ];
            $label = $unitLabels[$task->unit] ?? $task->unit;

            abort(422, "El lote '{$lot->name}' no tiene registrado el total de {$label}. Actualice la ficha del lote primero.");
        }
    }

    private function getLotCapacity(string $unit, FarmLot $lot): ?float
    {
        return match ($unit) {
            'arbol' => $lot->total_trees ? (float) $lot->total_trees : null,
            'hectarea' => $lot->area_hectares ? (float) $lot->area_hectares : null,
            'm2' => $lot->area_hectares ? (float) $lot->area_hectares * 10000 : null,
            'metro' => $lot->total_linear_meters ? (float) $lot->total_linear_meters : null,
            default => null,
        };
    }

    private function classifyPerformance(float $pct, TaskCatalog $task): string
    {
        $thresholds = $task->effectiveThresholds();

        if ($pct >= $thresholds['sobrepaso']) return 'sobrepaso';
        if ($pct >= $thresholds['alto']) return 'alto';
        if ($pct >= $thresholds['medio']) return 'medio';
        return 'bajo';
    }
}
