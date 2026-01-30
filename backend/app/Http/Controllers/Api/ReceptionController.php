<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReceptionRequest;
use App\Http\Requests\StoreReceptionBatchRequest;
use App\Http\Resources\ReceptionResource;
use App\Http\Resources\ReceptionBatchResource;
use App\Models\Reception;
use App\Models\ReceptionItem;
use App\Models\ReceptionBatch;
use App\Models\ReceptionBatchItem;
use App\Models\ReceptionBatchAttachment;
use App\Models\Purchase;
use App\Models\ProductOutput;
use App\Models\PurchaseItem;
use App\Models\OutputProduct;
use App\Models\InventoryMovement;
use App\Models\Inventory;
use App\Models\PackagingUnit;
use App\Models\Application;
use App\Models\ApplicationProduct;
use App\Models\Location;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceptionController extends Controller
{
    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get available sources for reception (purchases and outputs that still have pending items)
     */
    public function availableSources(Request $request): JsonResponse
    {
        $sources = [];

        // Get purchases that are not cancelled or fully received
        $purchases = Purchase::whereNotIn('status', ['received', 'cancelled'])
            ->with(['supplier', 'destinationLocation.responsibleUser', 'originLocation', 'purchaseItems'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($purchases as $purchase) {
            // Get reception info for this purchase
            $reception = Reception::where('source_id', $purchase->id)
                ->where('source_type', 'purchase')
                ->with(['receptionBatches.receiver', 'receptionBatches.batchItems', 'receptionItems'])
                ->first();

            $sources[] = [
                'id' => $purchase->id,
                'source_type' => 'purchase',
                'document_number' => $purchase->order_number,
                'date' => $purchase->purchase_date,
                'origin_location' => $purchase->originLocation ? [
                    'id' => $purchase->originLocation->id,
                    'name' => $purchase->originLocation->name,
                ] : null,
                'destination_location' => [
                    'id' => $purchase->destinationLocation->id,
                    'name' => $purchase->destinationLocation->name,
                    'responsible_user_id' => $purchase->destinationLocation->responsible_user_id,
                    'responsible_user_name' => $purchase->destinationLocation->responsibleUser?->name,
                ],
                'supplier_name' => $purchase->supplier->name ?? null,
                'total' => $purchase->total,
                'items_count' => $purchase->purchaseItems->count(),
                'status' => $purchase->status,
                'created_at' => $purchase->created_at,
                'updated_at' => $purchase->updated_at,
                // Reception info
                'reception' => $reception ? [
                    'id' => $reception->id,
                    'reception_number' => $reception->reception_number,
                    'status' => $reception->status,
                    'total_expected' => $reception->total_expected,
                    'total_received' => $reception->total_received,
                    'completion_percentage' => $reception->completion_percentage,
                    'batches_count' => $reception->receptionBatches->count(),
                    'batches' => $reception->receptionBatches->map(function($batch) {
                        return [
                            'id' => $batch->id,
                            'batch_number' => $batch->batch_number,
                            'reception_date' => $batch->reception_date,
                            'received_by' => $batch->receiver?->name ?? 'N/A',
                            'observations' => $batch->observations,
                            'items' => $batch->batchItems->map(function($item) {
                                return [
                                    'product_id' => $item->product_id,
                                    'reception_item_id' => $item->reception_item_id,
                                    'quantity_received' => $item->quantity_received,
                                    'condition' => $item->condition,
                                ];
                            }),
                        ];
                    }),
                    'items' => $reception->receptionItems->map(function($item) use ($reception) {
                        return $this->formatReceptionItemForApi($item, $reception);
                    }),
                ] : null,
            ];
        }

        // Get outputs that are not fully completed
        $outputs = ProductOutput::whereNotIn('status', ['completed'])
            ->with(['originLocation', 'destinationLocation.responsibleUser', 'outputProducts'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($outputs as $output) {
            // Get reception info for this output
            $reception = Reception::where('source_id', $output->id)
                ->where('source_type', 'output')
                ->with(['receptionBatches.receiver', 'receptionBatches.batchItems', 'receptionItems'])
                ->first();

            $sources[] = [
                'id' => $output->id,
                'source_type' => 'output',
                'document_number' => $output->output_number,
                'date' => $output->output_date,
                'origin_location' => [
                    'id' => $output->originLocation->id,
                    'name' => $output->originLocation->name,
                ],
                'destination_location' => [
                    'id' => $output->destinationLocation->id,
                    'name' => $output->destinationLocation->name,
                    'responsible_user_id' => $output->destinationLocation->responsible_user_id,
                    'responsible_user_name' => $output->destinationLocation->responsibleUser?->name,
                ],
                'supplier_name' => null,
                'total' => null,
                'items_count' => $output->outputProducts->count(),
                'status' => $output->status,
                'created_at' => $output->created_at,
                'updated_at' => $output->updated_at,
                // Reception info
                'reception' => $reception ? [
                    'id' => $reception->id,
                    'reception_number' => $reception->reception_number,
                    'status' => $reception->status,
                    'total_expected' => $reception->total_expected,
                    'total_received' => $reception->total_received,
                    'completion_percentage' => $reception->completion_percentage,
                    'batches_count' => $reception->receptionBatches->count(),
                    'batches' => $reception->receptionBatches->map(function($batch) {
                        return [
                            'id' => $batch->id,
                            'batch_number' => $batch->batch_number,
                            'reception_date' => $batch->reception_date,
                            'received_by' => $batch->receiver?->name ?? 'N/A',
                            'observations' => $batch->observations,
                            'items' => $batch->batchItems->map(function($item) {
                                return [
                                    'product_id' => $item->product_id,
                                    'reception_item_id' => $item->reception_item_id,
                                    'quantity_received' => $item->quantity_received,
                                    'condition' => $item->condition,
                                ];
                            }),
                        ];
                    }),
                    'items' => $reception->receptionItems->map(function($item) use ($reception) {
                        return $this->formatReceptionItemForApi($item, $reception);
                    }),
                ] : null,
            ];
        }

        // Sort all sources by updated_at descending (most recently updated first)
        usort($sources, function($a, $b) {
            $aTime = strtotime($a['updated_at'] ?? $a['created_at'] ?? $a['date']);
            $bTime = strtotime($b['updated_at'] ?? $b['created_at'] ?? $b['date']);
            return $bTime - $aTime;
        });

        return response()->json([
            'success' => true,
            'data' => $sources,
            'meta' => [
                'total' => count($sources),
                'purchases_count' => $purchases->count(),
                'outputs_count' => $outputs->count(),
            ]
        ]);
    }

    /**
     * Get available sources for reception filtered by current user as responsible of destination location
     * FUN-001: Permite al encargado de una finca ver solo las recepciones pendientes para sus ubicaciones
     */
    public function availableSourcesForResponsible(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Get locations where this user is responsible
        $managedLocationIds = Location::where('responsible_user_id', $userId)
            ->pluck('id')
            ->toArray();

        if (empty($managedLocationIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'purchases_count' => 0,
                    'outputs_count' => 0,
                    'managed_locations' => 0,
                    'message' => 'No tiene ubicaciones asignadas como encargado'
                ]
            ]);
        }

        $sources = [];

        // Get purchases destined to managed locations
        $purchases = Purchase::whereNotIn('status', ['received', 'cancelled'])
            ->whereIn('destination_location_id', $managedLocationIds)
            ->with(['supplier', 'destinationLocation.responsibleUser', 'originLocation', 'purchaseItems'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($purchases as $purchase) {
            $reception = Reception::where('source_id', $purchase->id)
                ->where('source_type', 'purchase')
                ->with(['receptionBatches.receiver', 'receptionBatches.batchItems', 'receptionItems'])
                ->first();

            $sources[] = [
                'id' => $purchase->id,
                'source_type' => 'purchase',
                'document_number' => $purchase->order_number,
                'date' => $purchase->purchase_date,
                'origin_location' => $purchase->originLocation ? [
                    'id' => $purchase->originLocation->id,
                    'name' => $purchase->originLocation->name,
                ] : null,
                'destination_location' => [
                    'id' => $purchase->destinationLocation->id,
                    'name' => $purchase->destinationLocation->name,
                    'responsible_user_id' => $purchase->destinationLocation->responsible_user_id,
                    'responsible_user_name' => $purchase->destinationLocation->responsibleUser?->name,
                ],
                'supplier_name' => $purchase->supplier->name ?? null,
                'total' => $purchase->total,
                'items_count' => $purchase->purchaseItems->count(),
                'status' => $purchase->status,
                'created_at' => $purchase->created_at,
                'updated_at' => $purchase->updated_at,
                'reception' => $reception ? [
                    'id' => $reception->id,
                    'reception_number' => $reception->reception_number,
                    'status' => $reception->status,
                    'completion_percentage' => $reception->completion_percentage,
                ] : null,
            ];
        }

        // Get outputs destined to managed locations
        $outputs = ProductOutput::whereNotIn('status', ['completed'])
            ->whereIn('destination_location_id', $managedLocationIds)
            ->with(['originLocation', 'destinationLocation.responsibleUser', 'outputProducts'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($outputs as $output) {
            $reception = Reception::where('source_id', $output->id)
                ->where('source_type', 'output')
                ->with(['receptionBatches.receiver', 'receptionBatches.batchItems', 'receptionItems'])
                ->first();

            $sources[] = [
                'id' => $output->id,
                'source_type' => 'output',
                'document_number' => $output->output_number,
                'date' => $output->output_date,
                'origin_location' => [
                    'id' => $output->originLocation->id,
                    'name' => $output->originLocation->name,
                ],
                'destination_location' => [
                    'id' => $output->destinationLocation->id,
                    'name' => $output->destinationLocation->name,
                    'responsible_user_id' => $output->destinationLocation->responsible_user_id,
                    'responsible_user_name' => $output->destinationLocation->responsibleUser?->name,
                ],
                'supplier_name' => null,
                'total' => null,
                'items_count' => $output->outputProducts->count(),
                'status' => $output->status,
                'created_at' => $output->created_at,
                'updated_at' => $output->updated_at,
                'reception' => $reception ? [
                    'id' => $reception->id,
                    'reception_number' => $reception->reception_number,
                    'status' => $reception->status,
                    'completion_percentage' => $reception->completion_percentage,
                ] : null,
            ];
        }

        // Sort by updated_at descending
        usort($sources, function($a, $b) {
            $aTime = strtotime($a['updated_at'] ?? $a['created_at'] ?? $a['date']);
            $bTime = strtotime($b['updated_at'] ?? $b['created_at'] ?? $b['date']);
            return $bTime - $aTime;
        });

        return response()->json([
            'success' => true,
            'data' => $sources,
            'meta' => [
                'total' => count($sources),
                'purchases_count' => $purchases->count(),
                'outputs_count' => $outputs->count(),
                'managed_locations' => count($managedLocationIds),
            ]
        ]);
    }

    /**
     * Display a listing of completed receptions (100% received with source in final status)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Reception::query()
            ->with([
                'originLocation',
                'destinationLocation',
                'responsibleUser',
                'receptionItems.product.packagingUnits',
                'receptionItems.brand',
                'receptionBatches.batchItems.product',
                'receptionBatches.receiver'
            ])
            // Only show completed receptions (100%)
            ->where('status', 'completed')
            ->where('completion_percentage', '>=', 100);

        // Filter by source type and verify source is in final status
        if ($request->has('source_type')) {
            $sourceType = $request->source_type;
            $query->where('source_type', $sourceType);

            // Source relation is disabled to avoid morphTo SQL errors
            // Reception status is managed independently
        }

        // Filter by origin location
        if ($request->has('origin_location_id')) {
            $query->where('origin_location_id', $request->origin_location_id);
        }

        // Filter by destination location
        if ($request->has('destination_location_id')) {
            $query->where('destination_location_id', $request->destination_location_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Search by reception number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('reception_number', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 15);
        $receptions = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        return ReceptionResource::collection($receptions);
    }

    /**
     * Store a newly created reception from purchase or output
     */
    public function store(StoreReceptionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Verify source exists
            $source = $this->getSource($data['source_type'], $data['source_id']);
            if (!$source) {
                return response()->json([
                    'success' => false,
                    'message' => 'El origen especificado no existe'
                ], 404);
            }

            // Auto-generate reception number if not provided
            if (empty($data['reception_number'])) {
                $data['reception_number'] = 'REC-' . date('Y') . '-' . str_pad(Reception::count() + 1, 6, '0', STR_PAD_LEFT);
            }

            // Get origin and destination from source if not provided
            if (empty($data['origin_location_id'])) {
                $data['origin_location_id'] = $data['source_type'] === 'purchase'
                    ? $source->origin_location_id
                    : $source->originLocation->id ?? null;
            }

            if (empty($data['destination_location_id'])) {
                $data['destination_location_id'] = $data['source_type'] === 'purchase'
                    ? $source->destination_location_id
                    : $source->destinationLocation->id ?? null;
            }

            if (empty($data['shipment_date'])) {
                $data['shipment_date'] = $data['source_type'] === 'purchase'
                    ? $source->purchase_date
                    : $source->output_date;
            }

            // Create the reception
            $reception = Reception::create([
                'reception_number' => $data['reception_number'],
                'source_id' => $data['source_id'],
                'source_type' => $data['source_type'],
                'origin_location_id' => $data['origin_location_id'],
                'destination_location_id' => $data['destination_location_id'],
                'shipment_date' => $data['shipment_date'] ?? null,
                'status' => 'pending',
                'total_expected' => 0,
                'total_received' => 0,
                'completion_percentage' => 0,
                'responsible_user' => $request->user()->id,
                'observations' => $data['observations'] ?? null,
            ]);

            // Create reception items based on source
            $this->createReceptionItems($reception, $source, $data['source_type']);

            // Calculate total expected
            $totalExpected = $reception->receptionItems()->sum('quantity_expected');
            $reception->update(['total_expected' => $totalExpected]);

            DB::commit();

            $reception->load([
                'originLocation',
                'destinationLocation',
                'responsibleUser',
                'receptionItems.product',
                'receptionItems.brand'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recepción creada exitosamente',
                'data' => new ReceptionResource($reception)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la recepción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified reception with full details
     */
    public function show(string $id): JsonResponse
    {
        $reception = Reception::with([
            'originLocation',
            'destinationLocation',
            'responsibleUser',
            'receptionItems.product',
            'receptionItems.brand',
            'receptionBatches.receiver',
            'receptionBatches.batchItems.product',
            'receptionBatches.attachments'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ReceptionResource($reception)
        ]);
    }

    /**
     * Create reception from source and add first batch (for direct reception from available sources)
     */
    public function createReceptionWithBatch(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validate([
                'source_id' => 'required|uuid',
                'source_type' => 'required|in:purchase,output',
                'reception_date' => 'required|date',
                'received_by' => 'required|uuid|exists:users,id',
                'items' => 'required|array|min:1',
                'items.*.reception_item_id' => 'nullable|uuid',
                'items.*.product_id' => 'required|uuid',
                'items.*.brand_id' => 'required|uuid|exists:brands,id',
                'items.*.quantity_received' => 'required|numeric|gt:0',
                'items.*.condition' => 'required|in:good,damaged,expired',
                'items.*.expiration_date' => 'nullable|date',
                'items.*.observations' => 'nullable|string',
                'observations' => 'nullable|string',
            ]);

            // Get source
            $source = $this->getSource($data['source_type'], $data['source_id']);
            if (!$source) {
                return response()->json([
                    'success' => false,
                    'message' => 'El origen especificado no existe'
                ], 404);
            }

            // Check if reception already exists for this source
            $reception = Reception::where('source_id', $data['source_id'])
                ->where('source_type', $data['source_type'])
                ->first();

            // If reception doesn't exist, create it
            if (!$reception) {
                $reception = Reception::create([
                    'reception_number' => 'REC-' . date('Y') . '-' . str_pad(Reception::count() + 1, 6, '0', STR_PAD_LEFT),
                    'source_id' => $data['source_id'],
                    'source_type' => $data['source_type'],
                    'origin_location_id' => $data['source_type'] === 'purchase'
                        ? $source->origin_location_id
                        : $source->originLocation->id,
                    'destination_location_id' => $data['source_type'] === 'purchase'
                        ? $source->destination_location_id
                        : $source->destinationLocation->id,
                    'shipment_date' => $data['source_type'] === 'purchase'
                        ? $source->purchase_date
                        : $source->output_date,
                    'status' => 'pending',
                    'total_expected' => 0,
                    'total_received' => 0,
                    'completion_percentage' => 0,
                    'responsible_user' => $request->user()->id,
                    'observations' => $data['observations'] ?? null,
                ]);

                // Create reception items
                $this->createReceptionItems($reception, $source, $data['source_type']);

                // Calculate total expected
                $totalExpected = $reception->receptionItems()->sum('quantity_expected');
                $reception->update(['total_expected' => $totalExpected]);
            }

            // Get next batch number
            $batchNumber = $reception->receptionBatches()->max('batch_number') + 1;

            // Create batch
            $batch = ReceptionBatch::create([
                'reception_id' => $reception->id,
                'batch_number' => $batchNumber,
                'reception_date' => $data['reception_date'],
                'received_by' => $data['received_by'],
                'observations' => $data['observations'] ?? null,
            ]);

            // Create batch items and update inventory
            foreach ($data['items'] as $itemData) {
                $quantityReceived = floatval($itemData['quantity_received']);

                // Find matching reception item - prefer exact ID, fallback to product+brand
                $receptionItem = $this->findReceptionItem(
                    $reception,
                    $itemData['reception_item_id'] ?? null,
                    $itemData['product_id'],
                    $itemData['brand_id'] ?? null,
                    $quantityReceived
                );

                if (!$receptionItem) {
                    throw new \Exception(
                        'Producto no encontrado en los items de la recepción.'
                    );
                }

                // Validate quantity does not exceed pending
                $maxAllowed = floatval($receptionItem->quantity_pending);
                if ($quantityReceived > $maxAllowed + 0.01) {
                    $productName = $receptionItem->product?->name ?? 'Producto';
                    throw new \Exception(
                        "La cantidad recibida ({$quantityReceived}) de {$productName} " .
                        "excede la cantidad pendiente ({$maxAllowed})."
                    );
                }

                // Create batch item linked to specific reception_item
                ReceptionBatchItem::create([
                    'batch_id' => $batch->id,
                    'product_id' => $itemData['product_id'],
                    'reception_item_id' => $receptionItem->id,
                    'quantity_received' => $quantityReceived,
                    'condition' => $itemData['condition'],
                    'expiration_date' => $itemData['expiration_date'] ?? null,
                    'observations' => $itemData['observations'] ?? null,
                ]);

                // Update reception item quantities
                $newQuantityReceived = $receptionItem->quantity_received + $quantityReceived;
                $newQuantityPending = $receptionItem->quantity_expected - $newQuantityReceived;

                $receptionItem->update([
                    'quantity_received' => $newQuantityReceived,
                    'quantity_pending' => max(0, $newQuantityPending),
                ]);

                // Process inventory movements
                $this->processInventoryMovements(
                    $reception,
                    $itemData,
                    $receptionItem,
                    $batchNumber,
                    $request->user()->id
                );
            }

            // Update reception status
            $this->updateReceptionStatus($reception);

            DB::commit();

            $reception->load([
                'originLocation',
                'destinationLocation',
                'responsibleUser',
                'receptionItems.product',
                'receptionItems.brand'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recepción registrada exitosamente',
                'data' => new ReceptionResource($reception)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la recepción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a new batch (partial reception) to an existing reception
     */
    public function addBatch(StoreReceptionBatchRequest $request, string $receptionId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Get reception
            $reception = Reception::with('receptionItems')->findOrFail($receptionId);

            // Verify reception is not completed or cancelled
            if (in_array($reception->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden agregar lotes a una recepción completada o cancelada'
                ], 400);
            }

            // Get next batch number
            $batchNumber = $reception->receptionBatches()->max('batch_number') + 1;

            // Create batch
            $batch = ReceptionBatch::create([
                'reception_id' => $reception->id,
                'batch_number' => $batchNumber,
                'reception_date' => $data['reception_date'],
                'received_by' => $data['received_by'],
                'observations' => $data['observations'] ?? null,
            ]);

            // Create batch items and update inventory
            foreach ($data['items'] as $itemData) {
                $quantityReceived = floatval($itemData['quantity_received']);

                // Find matching reception item - prefer exact ID, fallback to product+brand
                $receptionItem = $this->findReceptionItem(
                    $reception,
                    $itemData['reception_item_id'] ?? null,
                    $itemData['product_id'],
                    $itemData['brand_id'] ?? null,
                    $quantityReceived
                );

                if (!$receptionItem) {
                    throw new \Exception(
                        'Producto no encontrado en los items de la recepción.'
                    );
                }

                // Validate quantity does not exceed pending
                $maxAllowed = floatval($receptionItem->quantity_pending);
                if ($quantityReceived > $maxAllowed + 0.01) {
                    $productName = $receptionItem->product?->name ?? 'Producto';
                    throw new \Exception(
                        "La cantidad recibida ({$quantityReceived}) de {$productName} " .
                        "excede la cantidad pendiente ({$maxAllowed})."
                    );
                }

                // Create batch item linked to specific reception_item
                $batchItem = ReceptionBatchItem::create([
                    'batch_id' => $batch->id,
                    'product_id' => $itemData['product_id'],
                    'reception_item_id' => $receptionItem->id,
                    'quantity_received' => $quantityReceived,
                    'condition' => $itemData['condition'],
                    'expiration_date' => $itemData['expiration_date'] ?? null,
                    'observations' => $itemData['observations'] ?? null,
                ]);

                // Update reception item quantities
                $newQuantityReceived = $receptionItem->quantity_received + $quantityReceived;
                $newQuantityPending = $receptionItem->quantity_expected - $newQuantityReceived;

                $receptionItem->update([
                    'quantity_received' => $newQuantityReceived,
                    'quantity_pending' => max(0, $newQuantityPending),
                ]);

                // Process inventory movements
                $this->processInventoryMovements(
                    $reception,
                    $itemData,
                    $receptionItem,
                    $batchNumber,
                    $request->user()->id
                );
            }

            // Handle file attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs('receptions/' . $reception->id . '/batch_' . $batch->id, $fileName, 'public');

                    ReceptionBatchAttachment::create([
                        'batch_id' => $batch->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $filePath,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ]);
                }
            }

            // Update reception totals and status
            $this->updateReceptionStatus($reception);

            DB::commit();

            $batch->load([
                'receiver',
                'batchItems.product',
                'attachments'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lote de recepción agregado exitosamente',
                'data' => new ReceptionBatchResource($batch)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar el lote de recepción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all batches for a reception
     */
    public function getBatches(string $receptionId): AnonymousResourceCollection
    {
        $reception = Reception::findOrFail($receptionId);

        $batches = $reception->receptionBatches()
            ->with([
                'receiver',
                'batchItems.product',
                'attachments'
            ])
            ->orderBy('batch_number', 'asc')
            ->get();

        return ReceptionBatchResource::collection($batches);
    }

    /**
     * Get only pending products (quantity_pending > 0) for a reception
     */
    public function getPendingProducts(string $receptionId): JsonResponse
    {
        $reception = Reception::findOrFail($receptionId);

        $pendingItems = $reception->receptionItems()
            ->where('quantity_pending', '>', 0)
            ->with(['product', 'brand'])
            ->get()
            ->map(function ($item) {
                return [
                    'reception_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'brand_id' => $item->brand_id,
                    'product_name' => $item->product->name,
                    'product_category' => $item->product->category,
                    'brand_name' => $item->brand ? $item->brand->name : null,
                    'quantity_expected' => $item->quantity_expected,
                    'quantity_received' => $item->quantity_received,
                    'quantity_pending' => $item->quantity_pending,
                    'unit' => $item->unit,
                    'packaging_units' => $item->product->packagingUnits->map(fn($pu) => [
                        'id' => $pu->id,
                        'name' => $pu->name,
                        'base_quantity' => $pu->base_quantity,
                        'base_unit' => $pu->base_unit,
                    ]),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Productos pendientes obtenidos exitosamente',
            'data' => $pendingItems->values()
        ]);
    }

    /**
     * Mark reception as completed
     */
    public function complete(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $reception = Reception::findOrFail($id);

            if ($reception->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'La recepción ya está completada'
                ], 400);
            }

            if ($reception->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede completar una recepción cancelada'
                ], 400);
            }

            // Update reception status
            $reception->update(['status' => 'completed']);

            // Update source status if applicable - get source manually to avoid morphTo issues
            $source = $this->getSource($reception->source_type, $reception->source_id);
            if ($source) {
                if ($reception->source_type === 'purchase') {
                    $source->update([
                        'status' => 'received',
                        'received_by' => auth()->id(),
                        'received_at' => now(),
                    ]);
                } elseif ($reception->source_type === 'output') {
                    $source->update([
                        'status' => 'completed'
                    ]);
                }
            }

            DB::commit();

            $reception->load([
                'originLocation',
                'destinationLocation',
                'responsibleUser',
                'receptionItems.product',
                'receptionItems.brand'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recepción completada exitosamente',
                'data' => new ReceptionResource($reception)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al completar la recepción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a reception
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $reception = Reception::findOrFail($id);

            if ($reception->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar una recepción completada'
                ], 400);
            }

            if ($reception->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'La recepción ya está cancelada'
                ], 400);
            }

            // Update reception status
            $reception->update(['status' => 'cancelled']);

            DB::commit();

            $reception->load([
                'originLocation',
                'destinationLocation',
                'responsibleUser',
                'receptionItems.product',
                'receptionItems.brand'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recepción cancelada exitosamente',
                'data' => new ReceptionResource($reception)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la recepción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format a reception item for API responses.
     * Uses source_item_id for exact OutputProduct lookup (handles duplicate product+brand).
     */
    private function formatReceptionItemForApi(ReceptionItem $item, Reception $reception): array
    {
        $suggestedExpirationDate = $item->expiration_date;

        if (!$suggestedExpirationDate && $reception->source_type === 'output' && $reception->origin_location_id) {
            // Use source_item_id for exact match, fallback to product_id+brand_id
            $outputProduct = $item->source_item_id
                ? OutputProduct::find($item->source_item_id)
                : OutputProduct::where('product_output_id', $reception->source_id)
                    ->where('product_id', $item->product_id)
                    ->where('brand_id', $item->brand_id)
                    ->first();

            $inventoryQuery = Inventory::where('product_id', $item->product_id)
                ->where('brand_id', $item->brand_id)
                ->where('location_id', $reception->origin_location_id)
                ->whereNotIn('status', ['expired'])
                ->where('quantity', '>', 0);
            if ($outputProduct?->batch_number) {
                $inventoryQuery->where('batch_number', $outputProduct->batch_number);
            }
            $inventory = $inventoryQuery->orderBy('expiration_date', 'asc')->first();

            $suggestedExpirationDate = $inventory?->expiration_date;
        }

        // Lookup packaging info from source purchase item
        $packagingInfo = null;
        if ($reception->source_type === 'purchase' && $item->source_item_id) {
            $purchaseItem = PurchaseItem::with('packagingUnit')->find($item->source_item_id);
            if ($purchaseItem && $purchaseItem->packagingUnit) {
                $packagingInfo = [
                    'packagingUnitName' => $purchaseItem->packagingUnit->name,
                    'packagingQuantity' => $purchaseItem->quantity,
                    'baseQuantityPerUnit' => $purchaseItem->packagingUnit->base_quantity,
                    'baseUnit' => $purchaseItem->packagingUnit->base_unit,
                ];
            }
        }

        return [
            'id' => $item->id,
            'productId' => $item->product_id,
            'productName' => $item->product?->name,
            'product' => $item->product ? [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'category' => $item->product->category,
            ] : null,
            'brandId' => $item->brand_id,
            'brandName' => $item->brand?->name,
            'brand' => $item->brand ? [
                'id' => $item->brand->id,
                'name' => $item->brand->name,
            ] : null,
            'sourceItemId' => $item->source_item_id,
            'quantityExpected' => $item->quantity_expected,
            'quantityReceived' => $item->quantity_received,
            'quantityPending' => $item->quantity_pending,
            'unit' => $item->unit,
            'expirationDate' => $item->expiration_date?->format('Y-m-d'),
            'suggestedExpirationDate' => $suggestedExpirationDate instanceof \DateTimeInterface
                ? $suggestedExpirationDate->format('Y-m-d')
                : ($suggestedExpirationDate ? (string)$suggestedExpirationDate : null),
            'packagingInfo' => $packagingInfo,
        ];
    }

    /**
     * Find the exact reception item for a given request item.
     * Uses reception_item_id for exact match (handles duplicate product+brand).
     * Falls back to product_id+brand_id for backwards compatibility.
     */
    private function findReceptionItem(
        Reception $reception,
        ?string $receptionItemId,
        string $productId,
        ?string $brandId,
        ?float $quantityRequested = null
    ): ?ReceptionItem {
        if ($receptionItemId) {
            // Exact match by reception_item ID (preferred)
            $item = $reception->receptionItems()->find($receptionItemId);
            if ($item) {
                return $item;
            }

            // The frontend may send the source item ID (OutputProduct/PurchaseItem ID)
            // instead of the ReceptionItem ID on first reception
            $item = $reception->receptionItems()
                ->where('source_item_id', $receptionItemId)
                ->first();
            if ($item) {
                return $item;
            }
        }

        // Fallback: product_id + brand_id
        $candidates = $reception->receptionItems()
            ->where('product_id', $productId)
            ->where('brand_id', $brandId)
            ->where('quantity_pending', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($candidates->count() <= 1) {
            return $candidates->first();
        }

        // Multiple items with same product+brand (duplicates): find the best match
        // First try exact match by quantity_expected == quantityRequested
        if ($quantityRequested !== null) {
            $exactMatch = $candidates->first(function ($item) use ($quantityRequested) {
                return abs(floatval($item->quantity_expected) - $quantityRequested) < 0.01;
            });
            if ($exactMatch) {
                return $exactMatch;
            }
        }

        // Otherwise return first item that can accommodate the requested quantity
        if ($quantityRequested !== null) {
            $fittingMatch = $candidates->first(function ($item) use ($quantityRequested) {
                return floatval($item->quantity_pending) >= $quantityRequested - 0.01;
            });
            if ($fittingMatch) {
                return $fittingMatch;
            }
        }

        // Last resort: return first with pending
        return $candidates->first();
    }

    /**
     * Get source based on source type
     */
    private function getSource(string $sourceType, string $sourceId)
    {
        if ($sourceType === 'purchase') {
            $source = Purchase::where('id', $sourceId)->first();
            if ($source) {
                $source->load(['purchaseItems.product', 'purchaseItems.brand']);
            }
            return $source;
        } elseif ($sourceType === 'output') {
            $source = ProductOutput::where('id', $sourceId)->first();
            if ($source) {
                $source->load(['outputProducts', 'outputProducts.product', 'outputProducts.brand', 'originLocation', 'destinationLocation', 'outputType', 'farmLots']);
            }
            return $source;
        }

        return null;
    }

    /**
     * Create reception items based on source
     */
    private function createReceptionItems(Reception $reception, $source, string $sourceType): void
    {
        if ($sourceType === 'purchase') {
            foreach ($source->purchaseItems as $purchaseItem) {
                $packagingUnit = PackagingUnit::find($purchaseItem->packaging_unit_id);
                $baseUnit = $packagingUnit ? $packagingUnit->base_unit : 'unidades';
                $quantityInBase = $purchaseItem->quantity_in_base_units ?? $purchaseItem->quantity;

                ReceptionItem::create([
                    'reception_id' => $reception->id,
                    'product_id' => $purchaseItem->product_id,
                    'brand_id' => $purchaseItem->brand_id,
                    'source_item_id' => $purchaseItem->id,
                    'quantity_expected' => $quantityInBase,
                    'quantity_received' => 0,
                    'quantity_pending' => $quantityInBase,
                    'unit' => $baseUnit,
                ]);
            }
        } elseif ($sourceType === 'output') {
            foreach ($source->outputProducts as $outputProduct) {
                $expirationDate = $outputProduct->expiration_date;
                if (!$expirationDate && $reception->origin_location_id) {
                    $inventoryQuery = Inventory::where('product_id', $outputProduct->product_id)
                        ->where('brand_id', $outputProduct->brand_id)
                        ->where('location_id', $reception->origin_location_id)
                        ->whereNotIn('status', ['expired'])
                        ->where('quantity', '>', 0);
                    if ($outputProduct->batch_number) {
                        $inventoryQuery->where('batch_number', $outputProduct->batch_number);
                    }
                    $inventory = $inventoryQuery->orderBy('expiration_date', 'asc')->first();
                    $expirationDate = $inventory?->expiration_date;
                }

                ReceptionItem::create([
                    'reception_id' => $reception->id,
                    'product_id' => $outputProduct->product_id,
                    'brand_id' => $outputProduct->brand_id ?? null,
                    'source_item_id' => $outputProduct->id,
                    'quantity_expected' => $outputProduct->quantity_delivered,
                    'quantity_received' => 0,
                    'quantity_pending' => $outputProduct->quantity_delivered,
                    'unit' => $outputProduct->unit,
                    'expiration_date' => $expirationDate,
                ]);
            }
        }
    }

    /**
     * Update reception status based on items received
     */
    private function updateReceptionStatus(Reception $reception): void
    {
        $totalReceived = $reception->receptionItems()->sum('quantity_received');
        $totalExpected = $reception->total_expected;

        $completionPercentage = $totalExpected > 0 ? round(($totalReceived / $totalExpected) * 100, 2) : 0;

        $status = 'pending';
        if ($completionPercentage >= 100) {
            $status = 'completed';
        } elseif ($completionPercentage > 0) {
            $status = 'partial';
        }

        $reception->update([
            'total_received' => $totalReceived,
            'completion_percentage' => $completionPercentage,
            'status' => $status,
        ]);

        // Update source status when reception is partial or completed
        if ($status === 'partial' || $status === 'completed') {
            $this->updateSourceStatus($reception, $status);
        }
    }

    /**
     * Update the source (Purchase or ProductOutput) status based on reception status
     */
    private function updateSourceStatus(Reception $reception, string $receptionStatus): void
    {
        if ($reception->source_type === 'purchase') {
            $purchase = Purchase::find($reception->source_id);
            if (!$purchase) {
                return;
            }

            // Determine purchase status based on reception status
            if ($receptionStatus === 'completed') {
                // Recepción 100% completada -> Purchase 'received'
                if ($purchase->status !== 'received') {
                    $purchase->update([
                        'status' => 'received',
                        'received_at' => now(),
                        'received_by' => auth()->id(),
                    ]);

                    \Log::info('Purchase status updated to received', [
                        'purchase_id' => $purchase->id,
                        'order_number' => $purchase->order_number,
                        'reception_id' => $reception->id,
                    ]);
                }
            } elseif ($receptionStatus === 'partial') {
                // Recepción parcial iniciada -> Purchase 'in_transit'
                if ($purchase->status === 'ordered') {
                    $purchase->update([
                        'status' => 'in_transit',
                    ]);

                    \Log::info('Purchase status updated to in_transit (partial reception started)', [
                        'purchase_id' => $purchase->id,
                        'order_number' => $purchase->order_number,
                        'reception_id' => $reception->id,
                    ]);
                }
            }
        } elseif ($reception->source_type === 'output') {
            $output = ProductOutput::find($reception->source_id);
            if (!$output) {
                return;
            }

            // Determine output status based on reception status
            if ($receptionStatus === 'completed') {
                // Recepción 100% completada -> Output 'completed'
                if ($output->status !== 'completed') {
                    $output->update([
                        'status' => 'completed',
                    ]);

                    \Log::info('Output status updated to completed', [
                        'output_id' => $output->id,
                        'output_number' => $output->output_number,
                        'reception_id' => $reception->id,
                    ]);
                }
            } elseif ($receptionStatus === 'partial') {
                // Recepción parcial iniciada -> Output 'partial'
                if ($output->status === 'pending') {
                    $output->update([
                        'status' => 'partial',
                    ]);

                    \Log::info('Output status updated to partial (partial reception started)', [
                        'output_id' => $output->id,
                        'output_number' => $output->output_number,
                        'reception_id' => $reception->id,
                    ]);
                }
            }
        }
    }

    /**
     * Process complete inventory movements for a reception
     * Handles both purchases (entry only) and outputs (exit + entry)
     * Updates inventory table and creates audit trail
     */
    private function processInventoryMovements(
        Reception $reception,
        array $itemData,
        $receptionItem,
        int $batchNumber,
        string $userId
    ): void {
        // Only process items in good condition
        if ($itemData['condition'] !== 'good' || !$receptionItem) {
            \Log::info('Skipping inventory movement - item not in good condition or no reception item', [
                'condition' => $itemData['condition'],
                'has_reception_item' => !!$receptionItem,
            ]);
            return;
        }

        $sourceType = $reception->source_type;
        $productId = $itemData['product_id'];
        $brandId = $receptionItem->brand_id;
        $quantityReceived = $itemData['quantity_received'];
        $unit = $receptionItem->unit ?? 'unidades';
        $expirationDate = $itemData['expiration_date'] ?? null;

        // Get unit price based on source type (use source_item_id for exact batch lookup)
        $unitPrice = $this->getUnitPriceForMovement($reception, $productId, $brandId, $receptionItem->source_item_id);

        if ($sourceType === 'purchase') {
            // PURCHASE: Only create ENTRY movement in destination
            $this->createEntryMovement(
                $reception,
                $productId,
                $brandId,
                $quantityReceived,
                $unit,
                $expirationDate,
                $unitPrice,
                $userId,
                $batchNumber,
                $itemData['condition']
            );
        } elseif ($sourceType === 'output') {
            // OUTPUT: Behavior depends on output type
            // Inventory is reduced during reception (not during approval)

            // Get the ProductOutput to check its type
            $output = $this->getSource($reception->source_type, $reception->source_id);
            $outputTypeCode = $output?->outputType?->code;

            // 1. Create EXIT movement in origin location (reduces inventory)
            // Use source_item_id to get the correct batch_number for FIFO
            $sourceBatchNumber = null;
            if ($receptionItem->source_item_id) {
                $sourceOutputProduct = OutputProduct::find($receptionItem->source_item_id);
                $sourceBatchNumber = $sourceOutputProduct?->batch_number;
            }

            $this->createExitMovement(
                $reception,
                $productId,
                $brandId,
                $quantityReceived,
                $unit,
                $unitPrice,
                $userId,
                $batchNumber,
                $sourceBatchNumber
            );

            // 2. Create ENTRY movement in destination ONLY if NOT consumption
            // Consumption type means the product is used/consumed, not transferred
            if ($outputTypeCode !== 'consumption') {
                // For transfer, technical_order, free_request: create entry in destination
                $this->createEntryMovement(
                    $reception,
                    $productId,
                    $brandId,
                    $quantityReceived,
                    $unit,
                    $expirationDate,
                    $unitPrice,
                    $userId,
                    $batchNumber,
                    $itemData['condition']
                );

                \Log::info('Output transfer: inventory moved from origin to destination', [
                    'output_type' => $outputTypeCode,
                    'origin' => $reception->origin_location_id,
                    'destination' => $reception->destination_location_id,
                    'quantity' => $quantityReceived,
                ]);
            } else {
                // For consumption: only exit from origin, no entry in destination
                // AND create Application record for traceability

                // Get the first farm lot (or create application per lot if needed)
                $farmLot = $output?->farmLots?->first();

                if ($farmLot) {
                    // Check if application already exists for this output+product+lot combination
                    $existingApplication = Application::where('product_output_id', $output->id)
                        ->where('farm_lot_id', $farmLot->id)
                        ->first();

                    if ($existingApplication) {
                        // Add product to existing application
                        ApplicationProduct::create([
                            'application_id' => $existingApplication->id,
                            'product_id' => $productId,
                            'brand_id' => $brandId,
                            'quantity' => $quantityReceived,
                            'unit' => $unit,
                            'reception_id' => $reception->id,
                            'observations' => "Recepcion automatica lote #{$batchNumber}",
                        ]);

                        \Log::info('ApplicationProduct added to existing Application', [
                            'application_id' => $existingApplication->id,
                            'product_id' => $productId,
                            'quantity' => $quantityReceived,
                        ]);
                    } else {
                        // Create new Application
                        $application = Application::create([
                            'application_number' => Application::generateApplicationNumber(),
                            'origin_location_id' => $reception->origin_location_id,
                            'farm_lot_id' => $farmLot->id,
                            'product_output_id' => $output->id,
                            'application_date' => now()->toDateString(),
                            'applied_by' => $userId,
                            'status' => 'approved',
                            'application_type' => 'consumo_salida',
                            'observations' => "Aplicacion automatica desde salida {$output->output_number}",
                            'approved_by' => $userId,
                            'approved_at' => now(),
                        ]);

                        // Create ApplicationProduct
                        ApplicationProduct::create([
                            'application_id' => $application->id,
                            'product_id' => $productId,
                            'brand_id' => $brandId,
                            'quantity' => $quantityReceived,
                            'unit' => $unit,
                            'reception_id' => $reception->id,
                            'observations' => "Recepcion automatica lote #{$batchNumber}",
                        ]);

                        \Log::info('Application created automatically from consumption output', [
                            'application_id' => $application->id,
                            'application_number' => $application->application_number,
                            'output_id' => $output->id,
                            'output_number' => $output->output_number,
                            'farm_lot_id' => $farmLot->id,
                            'farm_lot_name' => $farmLot->name,
                            'product_id' => $productId,
                            'quantity' => $quantityReceived,
                        ]);
                    }
                }

                \Log::info('Output consumption: inventory consumed (no entry in destination)', [
                    'output_type' => $outputTypeCode,
                    'origin' => $reception->origin_location_id,
                    'destination' => $reception->destination_location_id,
                    'farm_lots' => $output?->farmLots?->pluck('id')->toArray(),
                    'application_created' => isset($application) || isset($existingApplication),
                ]);
            }
        }

        \Log::info('Inventory movements processed successfully', [
            'reception_id' => $reception->id,
            'source_type' => $sourceType,
            'product_id' => $productId,
            'quantity' => $quantityReceived,
        ]);
    }

    /**
     * Create ENTRY inventory movement and update inventory table
     */
    private function createEntryMovement(
        Reception $reception,
        string $productId,
        string $brandId,
        float $quantity,
        string $unit,
        ?string $expirationDate,
        float $unitPrice,
        string $userId,
        int $batchNumber,
        string $condition
    ): void {
        $locationId = $reception->destination_location_id;
        $totalPrice = $quantity * $unitPrice;

        // Generate unique batch number for this reception batch
        // Format: REC-{reception_id_short}-{batch_number}
        $receptionBatchNumber = 'REC-' . substr($reception->id, 0, 8) . '-' . $batchNumber;

        // Create inventory movement
        $movement = InventoryMovement::create([
            'type' => 'entry',
            'product_id' => $productId,
            'brand_id' => $brandId,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'unit' => $unit,
            'expiration_date' => $expirationDate,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'responsible_user' => $userId,
            'related_document_id' => $reception->id,
            'related_document_type' => 'App\\Models\\Reception',
            'observations' => "Recepción lote #{$batchNumber} - {$condition} - " .
                            ($reception->source_type === 'purchase' ? 'Compra' : 'Transferencia'),
        ]);

        // Update or create inventory record with the batch number
        $this->updateInventoryStock(
            $productId,
            $brandId,
            $locationId,
            $quantity, // positive for entry
            $unit,
            $expirationDate,
            $unitPrice,
            $receptionBatchNumber
        );

        \Log::info('Entry movement created', [
            'movement_id' => $movement->id,
            'location_id' => $locationId,
            'batch_number' => $receptionBatchNumber,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
        ]);
    }

    /**
     * Create EXIT inventory movement and update inventory table
     * For exits, we use FIFO to reduce from existing batches
     */
    private function createExitMovement(
        Reception $reception,
        string $productId,
        string $brandId,
        float $quantity,
        string $unit,
        float $unitPrice,
        string $userId,
        int $batchNumber,
        ?string $inventoryBatchNumber = null
    ): void {
        $locationId = $reception->origin_location_id;
        $totalPrice = $quantity * $unitPrice;

        // Create inventory movement
        $movement = InventoryMovement::create([
            'type' => 'exit',
            'product_id' => $productId,
            'brand_id' => $brandId,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'unit' => $unit,
            'expiration_date' => null,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'responsible_user' => $userId,
            'related_document_id' => $reception->id,
            'related_document_type' => 'App\\Models\\Reception',
            'observations' => "Salida confirmada en recepción lote #{$batchNumber} - " .
                            (ProductOutput::find($reception->source_id)?->outputType?->name ?? 'Salida') .
                            " a " . ($reception->destinationLocation->name ?? 'ubicación destino'),
        ]);

        // Reduce inventory using FIFO, targeting the specific batch when known
        $this->inventoryService->reduceInventoryFIFO(
            $productId,
            $brandId,
            $locationId,
            $quantity,
            $unit,
            $inventoryBatchNumber
        );

        \Log::info('Exit movement created', [
            'movement_id' => $movement->id,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
        ]);
    }

    /**
     * Update or create inventory stock record for ENTRY movements
     * Creates a new inventory record with a specific batch number
     */
    private function updateInventoryStock(
        string $productId,
        string $brandId,
        string $locationId,
        float $quantityChange,
        string $unit,
        ?string $expirationDate,
        float $unitPrice,
        string $batchNumber
    ): void {
        // For entries, always create a new inventory record with the specific batch number
        if ($quantityChange > 0) {
            // Check if this exact batch already exists
            $existingBatch = Inventory::where('product_id', $productId)
                ->where('brand_id', $brandId)
                ->where('location_id', $locationId)
                ->where('batch_number', $batchNumber)
                ->first();

            if ($existingBatch) {
                // Update existing batch (should rarely happen, but possible for partial receptions)
                $newQuantity = $existingBatch->quantity + $quantityChange;

                // Calculate weighted average price
                $totalValue = ($existingBatch->quantity * $existingBatch->unit_price) + ($quantityChange * $unitPrice);
                $newUnitPrice = $newQuantity > 0 ? $totalValue / $newQuantity : $unitPrice;

                $existingBatch->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $newUnitPrice,
                    'total_value' => $newQuantity * $newUnitPrice,
                    'expiration_date' => $expirationDate ?? $existingBatch->expiration_date,
                    'status' => $this->calculateInventoryStatus($newQuantity, $expirationDate ?? $existingBatch->expiration_date),
                ]);

                \Log::info('Inventory batch updated', [
                    'inventory_id' => $existingBatch->id,
                    'batch_number' => $batchNumber,
                    'old_quantity' => $existingBatch->quantity - $quantityChange,
                    'quantity_change' => $quantityChange,
                    'new_quantity' => $existingBatch->quantity,
                    'new_unit_price' => $newUnitPrice,
                ]);
            } else {
                // Create new inventory batch
                $newInventory = Inventory::create([
                    'product_id' => $productId,
                    'brand_id' => $brandId,
                    'location_id' => $locationId,
                    'batch_number' => $batchNumber,
                    'quantity' => $quantityChange,
                    'unit' => $unit,
                    'expiration_date' => $expirationDate,
                    'unit_price' => $unitPrice,
                    'total_value' => $quantityChange * $unitPrice,
                    'status' => $this->calculateInventoryStatus($quantityChange, $expirationDate),
                ]);

                \Log::info('New inventory batch created', [
                    'inventory_id' => $newInventory->id,
                    'batch_number' => $batchNumber,
                    'quantity' => $quantityChange,
                    'unit_price' => $unitPrice,
                    'expiration_date' => $expirationDate,
                ]);
            }
        } else {
            \Log::warning('updateInventoryStock called with non-positive quantity for entry', [
                'product_id' => $productId,
                'brand_id' => $brandId,
                'location_id' => $locationId,
                'quantity_change' => $quantityChange,
            ]);
        }
    }

    // NOTE: reduceInventoryFIFO has been moved to App\Services\InventoryService
    // with unit conversion support (ERR-001 fix)

    /**
     * Get unit price for inventory movement
     * For purchases: get from purchase_items
     * For outputs: get from existing inventory or use average
     */
    private function getUnitPriceForMovement(
        Reception $reception,
        string $productId,
        string $brandId,
        ?string $sourceItemId = null
    ): float {
        if ($reception->source_type === 'purchase') {
            // Use source_item_id for exact match, fallback to product+brand
            $source = Purchase::find($reception->source_id);
            if ($source) {
                $purchaseItem = $sourceItemId
                    ? $source->purchaseItems()->find($sourceItemId)
                    : $source->purchaseItems()
                        ->where('product_id', $productId)
                        ->where('brand_id', $brandId)
                        ->first();

                if ($purchaseItem && $purchaseItem->unit_price) {
                    return floatval($purchaseItem->unit_price);
                }
            }

            \Log::warning('Could not find purchase item price, using 0', [
                'reception_id' => $reception->id,
                'product_id' => $productId,
            ]);
            return 0.0;
        } else {
            // For outputs, get price from origin inventory using batch_number for exact match
            $query = Inventory::where('product_id', $productId)
                ->where('brand_id', $brandId)
                ->where('location_id', $reception->origin_location_id);

            if ($sourceItemId) {
                $outputProduct = OutputProduct::find($sourceItemId);
                if ($outputProduct?->batch_number) {
                    $query->where('batch_number', $outputProduct->batch_number);
                }
            }

            $inventory = $query->where('quantity', '>', 0)
                ->orderBy('created_at', 'asc')
                ->first();

            if ($inventory && $inventory->unit_price) {
                return floatval($inventory->unit_price);
            }

            // If not found in origin, try to get average price from any location
            $avgInventory = Inventory::where('product_id', $productId)
                ->where('brand_id', $brandId)
                ->where('quantity', '>', 0)
                ->avg('unit_price');

            if ($avgInventory) {
                \Log::info('Using average price from other locations', [
                    'product_id' => $productId,
                    'avg_price' => $avgInventory,
                ]);
                return floatval($avgInventory);
            }

            \Log::warning('Could not find inventory price, using 0', [
                'reception_id' => $reception->id,
                'product_id' => $productId,
                'origin_location_id' => $reception->origin_location_id,
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate inventory status based on quantity and expiration
     */
    private function calculateInventoryStatus(float $quantity, ?string $expirationDate): string
    {
        if ($quantity == 0) {
            return 'good'; // Will be filtered out in queries
        }

        if ($expirationDate) {
            $expirationDateTime = new \DateTime($expirationDate);
            $now = new \DateTime();
            $interval = $now->diff($expirationDateTime);
            $daysToExpiry = $interval->days * ($expirationDateTime < $now ? -1 : 1);

            if ($daysToExpiry < 0) {
                return 'expired';
            } elseif ($daysToExpiry <= 30) {
                return 'near_expiry';
            }
        }

        return 'good';
    }
}
