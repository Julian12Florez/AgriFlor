<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Lightweight user list for dropdowns (all authenticated users)
     */
    public function listSimple(Request $request): JsonResponse
    {
        $query = User::query()->visible()->select('id', 'name', 'role', 'status');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('name')->get();

        return response()->json([
            'data' => $users
        ]);
    }

    /**
     * Display a listing of users
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->visible();

        // Filter by role
        if ($request->has('role')) {
            $query->byRole($request->role);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $users = $query->with('roleRelation.permissions')->orderBy('created_at', 'desc')->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['status'] = $data['status'] ?? 'active';

        // Assign role_id based on role name
        $role = Role::where('name', $data['role'])->firstOrFail();
        if ($denegado = $this->soloAccesoTotalTocaAdministradores(null, $role)) {
            return $denegado;
        }
        $data['role_id'] = $role->id;

        $user = User::create($data);
        $user->load('roleRelation.permissions');

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => new UserResource($user)
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(string $id): JsonResponse
    {
        $user = User::visible()->with('roleRelation.permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = User::visible()->findOrFail($id);
        $data = $request->validated();

        // Only update password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Sync role_id when role changes
        $role = isset($data['role']) ? Role::where('name', $data['role'])->firstOrFail() : null;
        if ($denegado = $this->soloAccesoTotalTocaAdministradores($user, $role)) {
            return $denegado;
        }
        if ($role) {
            $data['role_id'] = $role->id;
        }

        $user->update($data);
        $user->load('roleRelation.permissions');

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::visible()->findOrFail($id);

        if ($denegado = $this->soloAccesoTotalTocaAdministradores($user, null)) {
            return $denegado;
        }

        // Prevent deleting the authenticated user
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar tu propia cuenta'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Candado: solo un usuario de acceso total (Administrador) puede crear,
     * modificar o eliminar a otro de acceso total, o darle ese perfil a alguien.
     *
     * `manage_users` es un permiso que el administrador puede dar a otros
     * perfiles desde la pantalla de Perfiles. Sin este candado, quien lo reciba
     * podría nombrarse administrador o cambiarle la clave al que ya lo es.
     *
     * @param  User|null  $afectado    el usuario que se modifica (null al crear)
     * @param  Role|null  $nuevoPerfil el perfil que se le quiere asignar (null si no cambia)
     */
    private function soloAccesoTotalTocaAdministradores(?User $afectado, ?Role $nuevoPerfil): ?JsonResponse
    {
        if (auth()->user()?->hasFullAccess()) {
            return null;
        }

        if ($afectado?->hasFullAccess() || $nuevoPerfil?->has_full_access) {
            return response()->json([
                'success' => false,
                'message' => 'Solo un administrador puede crear, modificar o eliminar usuarios con perfil de administrador.',
            ], 403);
        }

        return null;
    }

    /**
     * Update user status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:active,inactive']
        ]);

        $user = User::visible()->findOrFail($id);

        if ($denegado = $this->soloAccesoTotalTocaAdministradores($user, null)) {
            return $denegado;
        }

        // Prevent deactivating the authenticated user
        if ($user->id === auth()->id() && $request->status === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'No puedes desactivar tu propia cuenta'
            ], 403);
        }

        $user->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'data' => new UserResource($user)
        ]);
    }
}
