<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Supplier::query()->with('contacts');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or nit
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nit', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $suppliers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return SupplierResource::collection($suppliers);
    }

    /**
     * Store a newly created supplier
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $contacts = $data['contacts'] ?? [];
            unset($data['contacts']);

            $data['status'] = $data['status'] ?? 'active';

            // Create supplier
            $supplier = Supplier::create($data);

            // Create contacts if provided
            if (!empty($contacts)) {
                foreach ($contacts as $contactData) {
                    $supplier->contacts()->create($contactData);
                }
            }

            $supplier->load('contacts');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proveedor creado exitosamente',
                'data' => new SupplierResource($supplier)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el proveedor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified supplier
     */
    public function show(string $id): JsonResponse
    {
        $supplier = Supplier::with('contacts')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new SupplierResource($supplier)
        ]);
    }

    /**
     * Update the specified supplier
     */
    public function update(UpdateSupplierRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $supplier = Supplier::findOrFail($id);
            $data = $request->validated();
            $contacts = $data['contacts'] ?? null;
            unset($data['contacts']);

            // Update supplier
            $supplier->update($data);

            // Sync contacts if provided
            if ($contacts !== null) {
                // Delete existing contacts
                $supplier->contacts()->delete();

                // Create new contacts
                if (!empty($contacts)) {
                    foreach ($contacts as $contactData) {
                        $supplier->contacts()->create($contactData);
                    }
                }
            }

            $supplier->load('contacts');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proveedor actualizado exitosamente',
                'data' => new SupplierResource($supplier)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el proveedor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified supplier
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $supplier = Supplier::findOrFail($id);

            // Delete associated contacts
            $supplier->contacts()->delete();

            // Delete supplier
            $supplier->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Proveedor eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el proveedor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a contact to the specified supplier
     */
    public function addContact(Request $request, string $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [
            'name.required' => 'El nombre del contacto es requerido',
            'name.max' => 'El nombre del contacto no puede exceder 255 caracteres',
            'position.max' => 'El cargo no puede exceder 100 caracteres',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'email.email' => 'El email del contacto debe ser válido',
            'email.max' => 'El email del contacto no puede exceder 255 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $contact = $supplier->contacts()->create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Contacto agregado exitosamente',
            'data' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'position' => $contact->position,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'createdAt' => $contact->created_at?->toISOString(),
            ]
        ], 201);
    }

    /**
     * Remove a contact from the specified supplier
     */
    public function removeContact(string $supplierId, string $contactId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);
        $contact = SupplierContact::where('supplier_id', $supplierId)
            ->where('id', $contactId)
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contacto eliminado exitosamente'
        ]);
    }
}
