<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseAttachment;
use App\Models\PackagingUnit;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Purchase::query()
            ->with([
                'supplier.contacts',
                'originLocation',
                'destinationLocation',
                'purchaseItems.product.packagingUnits',
                'purchaseItems.product.brand',
                'purchaseItems.brand',
                'purchaseItems.packagingUnit',
                'creator',
                'receiver',
                'attachments.uploader',
                'reception'
            ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Search by order_number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('order_number', 'like', "%{$search}%");
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('purchase_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('purchase_date', '<=', $request->date_to);
        }

        // Filter by destination location
        if ($request->has('destination_location_id')) {
            $query->where('destination_location_id', $request->destination_location_id);
        }

        $perPage = $request->get('per_page', 15);
        $purchases = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        return PurchaseResource::collection($purchases);
    }

    /**
     * Store a newly created purchase
     */
    public function store(StorePurchaseRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Calculate totals with per-product IVA
            $subtotal = 0;
            $totalTax = 0;
            $itemsWithIva = [];

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $ivaPercentage = $product->iva ?? 0;
                $itemTax = round($itemSubtotal * ($ivaPercentage / 100), 2);
                $itemTotal = round($itemSubtotal + $itemTax, 2);

                $subtotal += $itemSubtotal;
                $totalTax += $itemTax;

                $itemsWithIva[] = array_merge($item, [
                    'iva_percentage' => $ivaPercentage,
                    'tax_amount' => $itemTax,
                    'item_total' => $itemTotal,
                    'item_subtotal' => $itemSubtotal,
                ]);
            }

            $total = round($subtotal + $totalTax, 2);

            // Create the purchase
            $purchase = Purchase::create([
                'order_number' => $data['order_number'],
                'supplier_id' => $data['supplier_id'],
                'origin_location_id' => $data['origin_location_id'] ?? null,
                'destination_location_id' => $data['destination_location_id'],
                'purchase_date' => $data['purchase_date'],
                'expected_delivery' => $data['expected_delivery'] ?? null,
                'status' => 'pending', // Disponible para recepción desde la creación
                'subtotal' => $subtotal,
                'tax' => $totalTax,
                'total' => $total,
                'observations' => $data['observations'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            // Create purchase items with per-product IVA
            foreach ($itemsWithIva as $itemData) {
                // Get packaging unit for conversion
                $packagingUnit = PackagingUnit::findOrFail($itemData['packaging_unit_id']);

                // Calculate quantity in base units
                $quantityInBaseUnits = $itemData['quantity'] * $packagingUnit->base_quantity;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product_id'],
                    'brand_id' => $itemData['brand_id'],
                    'packaging_unit_id' => $itemData['packaging_unit_id'],
                    'quantity' => $itemData['quantity'],
                    'quantity_in_base_units' => $quantityInBaseUnits,
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['item_subtotal'],
                    'iva_percentage' => $itemData['iva_percentage'],
                    'tax_amount' => $itemData['tax_amount'],
                    'total' => $itemData['item_total'],
                    'expiration_date' => $itemData['expiration_date'] ?? null,
                ]);
            }

            // Handle file attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs('purchases/' . $purchase->id, $fileName, 'public');

                    PurchaseAttachment::create([
                        'purchase_id' => $purchase->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $filePath,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ]);
                }
            }

            DB::commit();

            // Load relationships
            $purchase->load([
                'supplier',
                'destinationLocation',
                'purchaseItems.product',
                'purchaseItems.brand',
                'purchaseItems.packagingUnit',
                'creator',
                'attachments.uploader'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compra creada exitosamente',
                'data' => new PurchaseResource($purchase)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified purchase
     */
    public function show(string $id): JsonResponse
    {
        $purchase = Purchase::with([
            'supplier.contacts',
            'originLocation',
            'destinationLocation',
            'purchaseItems.product',
            'purchaseItems.brand',
            'purchaseItems.packagingUnit',
            'creator',
            'receiver',
            'attachments.uploader'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new PurchaseResource($purchase)
        ]);
    }

    /**
     * Update the specified purchase
     */
    public function update(UpdatePurchaseRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $purchase = Purchase::findOrFail($id);
            $data = $request->validated();

            // Check if purchase can be updated
            if ($purchase->status === 'received') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una compra que ya fue recibida'
                ], 422);
            }

            // Update basic fields
            $updateData = [];
            if (isset($data['order_number'])) {
                $updateData['order_number'] = $data['order_number'];
            }
            if (isset($data['supplier_id'])) {
                $updateData['supplier_id'] = $data['supplier_id'];
            }
            if (array_key_exists('origin_location_id', $data)) {
                $updateData['origin_location_id'] = $data['origin_location_id'];
            }
            if (isset($data['destination_location_id'])) {
                $updateData['destination_location_id'] = $data['destination_location_id'];
            }
            if (isset($data['purchase_date'])) {
                $updateData['purchase_date'] = $data['purchase_date'];
            }
            if (isset($data['expected_delivery'])) {
                $updateData['expected_delivery'] = $data['expected_delivery'];
            }
            if (isset($data['observations'])) {
                $updateData['observations'] = $data['observations'];
            }

            // Update items if provided
            if (isset($data['items'])) {
                // Calculate new totals with per-product IVA
                $subtotal = 0;
                $totalTax = 0;
                $itemsWithIva = [];

                foreach ($data['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $ivaPercentage = $product->iva ?? 0;
                    $itemTax = round($itemSubtotal * ($ivaPercentage / 100), 2);
                    $itemTotal = round($itemSubtotal + $itemTax, 2);

                    $subtotal += $itemSubtotal;
                    $totalTax += $itemTax;

                    $itemsWithIva[] = array_merge($item, [
                        'iva_percentage' => $ivaPercentage,
                        'tax_amount' => $itemTax,
                        'item_total' => $itemTotal,
                        'item_subtotal' => $itemSubtotal,
                    ]);
                }

                $total = round($subtotal + $totalTax, 2);

                $updateData['subtotal'] = $subtotal;
                $updateData['tax'] = $totalTax;
                $updateData['total'] = $total;

                // Delete existing items
                $purchase->purchaseItems()->delete();

                // Create new items with per-product IVA
                foreach ($itemsWithIva as $itemData) {
                    // Get packaging unit for conversion
                    $packagingUnit = PackagingUnit::findOrFail($itemData['packaging_unit_id']);

                    // Calculate quantity in base units
                    $quantityInBaseUnits = $itemData['quantity'] * $packagingUnit->base_quantity;

                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $itemData['product_id'],
                        'brand_id' => $itemData['brand_id'],
                        'packaging_unit_id' => $itemData['packaging_unit_id'],
                        'quantity' => $itemData['quantity'],
                        'quantity_in_base_units' => $quantityInBaseUnits,
                        'unit_price' => $itemData['unit_price'],
                        'subtotal' => $itemData['item_subtotal'],
                        'iva_percentage' => $itemData['iva_percentage'],
                        'tax_amount' => $itemData['tax_amount'],
                        'total' => $itemData['item_total'],
                        'expiration_date' => $itemData['expiration_date'] ?? null,
                    ]);
                }
            }

            $purchase->update($updateData);

            // Handle new file attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs('purchases/' . $purchase->id, $fileName, 'public');

                    PurchaseAttachment::create([
                        'purchase_id' => $purchase->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $filePath,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ]);
                }
            }

            DB::commit();

            // Load relationships
            $purchase->load([
                'supplier',
                'originLocation',
                'destinationLocation',
                'purchaseItems.product',
                'purchaseItems.brand',
                'purchaseItems.packagingUnit',
                'creator',
                'receiver',
                'attachments.uploader'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compra actualizada exitosamente',
                'data' => new PurchaseResource($purchase)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified purchase
     * Files are deleted AFTER commit to prevent orphaned files (ERR-002 fix)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $purchase = Purchase::with('attachments')->findOrFail($id);

            // Check if purchase can be deleted (only if status is 'ordered')
            if ($purchase->status !== 'ordered') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden eliminar compras en estado "Ordenado"'
                ], 422);
            }

            // Store file paths to delete AFTER successful commit (ERR-002 fix)
            $filesToDelete = $purchase->attachments->pluck('file_path')->toArray();

            DB::beginTransaction();

            // Delete related records from database
            $purchase->attachments()->delete();
            $purchase->purchaseItems()->delete();
            $purchase->delete();

            DB::commit();

            // Delete files from storage AFTER successful commit
            // This prevents orphaned records if file deletion fails
            foreach ($filesToDelete as $filePath) {
                try {
                    Storage::disk('public')->delete($filePath);
                } catch (\Exception $e) {
                    // Log error but don't fail - files can be cleaned up later
                    \Log::warning("Failed to delete file after purchase deletion", [
                        'purchase_id' => $id,
                        'file_path' => $filePath,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Compra eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Export purchase order to PDF
     */
    public function exportPdf(string $id)
    {
        $purchase = Purchase::with([
            'supplier.contacts',
            'originLocation',
            'destinationLocation',
            'purchaseItems.product',
            'purchaseItems.brand',
            'purchaseItems.packagingUnit',
            'creator',
            'receiver',
        ])->findOrFail($id);

        // Determine mixed IVA
        $ivaPercentages = $purchase->purchaseItems->pluck('iva_percentage')->unique()->filter()->values();
        $isMixedIva = $ivaPercentages->count() > 1;
        $singleIvaPercentage = $isMixedIva ? null : $ivaPercentages->first();

        $data = [
            'purchase' => $purchase,
            'companyName' => config('app.company_name', 'AgriFlor S.A.S.'),
            'companyAddress' => config('app.company_address', ''),
            'companyPhone' => config('app.company_phone', ''),
            'companyEmail' => config('app.company_email', ''),
            'companyNit' => config('app.company_nit', ''),
            'isMixedIva' => $isMixedIva,
            'singleIvaPercentage' => $singleIvaPercentage,
        ];

        $pdf = Pdf::loadView('exports.pdf.purchase-order', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('orden_compra_' . $purchase->order_number . '.pdf');
    }

    /**
     * Add attachment to purchase
     */
    public function addAttachment(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            ], [
                'file.required' => 'El archivo es requerido',
                'file.file' => 'Debe ser un archivo válido',
                'file.mimes' => 'El archivo debe ser de tipo: pdf, jpg, jpeg, png, doc, docx, xls, xlsx',
                'file.max' => 'El archivo no puede exceder 10MB',
            ]);

            $purchase = Purchase::findOrFail($id);
            $file = $request->file('file');

            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('purchases/' . $purchase->id, $fileName, 'public');

            $attachment = PurchaseAttachment::create([
                'purchase_id' => $purchase->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);

            $attachment->load('uploader');

            return response()->json([
                'success' => true,
                'message' => 'Archivo adjunto agregado exitosamente',
                'data' => [
                    'id' => $attachment->id,
                    'fileName' => $attachment->file_name,
                    'filePath' => $attachment->file_path,
                    'fileType' => $attachment->file_type,
                    'fileSize' => $attachment->file_size,
                    'uploadedBy' => $attachment->uploader?->name,
                    'createdAt' => $attachment->created_at?->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar el archivo adjunto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove attachment from purchase
     */
    public function removeAttachment(string $id, string $attachmentId): JsonResponse
    {
        try {
            $purchase = Purchase::findOrFail($id);
            $attachment = PurchaseAttachment::where('purchase_id', $purchase->id)
                ->where('id', $attachmentId)
                ->firstOrFail();

            // Delete file from storage
            Storage::disk('public')->delete($attachment->file_path);

            // Delete attachment record
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Archivo adjunto eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el archivo adjunto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel the specified purchase
     * Only purchases without started reception can be cancelled
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $purchase = Purchase::with('reception')->findOrFail($id);

            // Check if purchase can be cancelled (only if no reception has started)
            if ($purchase->status === 'received') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar una compra que ya fue recibida'
                ], 422);
            }

            if ($purchase->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'La compra ya está cancelada'
                ], 422);
            }

            // Check if there's a reception started for this purchase
            if ($purchase->reception && in_array($purchase->reception->status, ['partial', 'completed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar una compra con recepción iniciada'
                ], 422);
            }

            // Check if status is in_transit (reception started)
            if ($purchase->status === 'in_transit') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar una compra con recepción en proceso'
                ], 422);
            }

            DB::beginTransaction();

            $purchase->update(['status' => 'cancelled']);

            // If there was a pending reception, cancel it too
            if ($purchase->reception && $purchase->reception->status === 'pending') {
                $purchase->reception->update(['status' => 'cancelled']);
            }

            DB::commit();

            $purchase->load([
                'supplier',
                'destinationLocation',
                'purchaseItems.product',
                'purchaseItems.brand',
                'purchaseItems.packagingUnit',
                'creator',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compra cancelada exitosamente',
                'data' => new PurchaseResource($purchase)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
