<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Administración → Perfiles.
 *
 * Aquí el administrador decide qué ve y qué puede hacer cada perfil. Lo que se
 * guarda es lo que el resto del sistema obedece: el menú sale del permiso de
 * "ver" de cada módulo (Role::hasModuleAccess) y cada ruta de escritura pide su
 * permiso (`permission:<nombre>` en routes/api.php).
 *
 * Reglas fijas:
 *  - El perfil de acceso total (Administrador) no se edita ni se elimina.
 *  - El Auditor no existe para esta pantalla (ni se lista, ni se edita, ni se
 *    puede dar su permiso a otro perfil).
 *  - Un perfil con usuarios no se elimina.
 *  - El nombre técnico (`name`) se genera al crear y no cambia: enlaza a los
 *    usuarios (`users.role`). Lo que se renombra es `display_name`.
 */
class RoleController extends Controller
{
    /** Perfiles con sus permisos, para la pantalla. */
    public function index(): JsonResponse
    {
        $perfiles = Role::visible()
            ->with('permissions:id,name,module,is_menu')
            ->orderByDesc('has_full_access')
            ->orderBy('display_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $perfiles->map(fn (Role $perfil) => $this->present($perfil))->values(),
        ]);
    }

    /**
     * Perfiles para elegir en el formulario de usuario, con las secciones del
     * menú que da cada uno. Abierto a cualquier sesión, como el resto de
     * listas para selectores.
     */
    public function options(): JsonResponse
    {
        $perfiles = Role::visible()
            ->with('permissions:id,name,module,is_menu')
            ->orderByDesc('has_full_access')
            ->orderBy('display_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $perfiles->map(fn (Role $perfil) => [
                'id' => $perfil->id,
                'name' => $perfil->name,
                'displayName' => $perfil->display_name,
                'hasFullAccess' => $perfil->has_full_access,
                'menu' => $this->menuDe($perfil),
            ])->values(),
        ]);
    }

    /** Los permisos que se pueden marcar, agrupados por sección del menú. */
    public function catalog(): JsonResponse
    {
        $porModulo = collect(PermissionCatalog::assignable())->groupBy('module');

        $modulos = [];
        foreach (PermissionCatalog::MODULES as $clave => $etiqueta) {
            if (!$porModulo->has($clave)) {
                continue;
            }

            $modulos[] = [
                'key' => $clave,
                'label' => $etiqueta,
                'menuPermission' => PermissionCatalog::menuPermission($clave),
                'permissions' => $porModulo[$clave]->sortBy('sort')->map(fn (array $p) => [
                    'name' => $p['name'],
                    'label' => $p['label'],
                    'group' => $p['group'],
                    'action' => $p['action'],
                    'isMenu' => $p['menu'],
                ])->values(),
            ];
        }

        return response()->json(['success' => true, 'data' => $modulos]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $perfil = DB::transaction(function () use ($datos) {
            $perfil = Role::create([
                'name' => $this->nombreTecnico($datos['display_name']),
                'display_name' => $datos['display_name'],
                'description' => $datos['description'] ?? null,
                'has_full_access' => false,
                'location_scoped' => $datos['location_scoped'] ?? false,
                'schedule_scoped' => $datos['schedule_scoped'] ?? false,
            ]);
            $perfil->replacePermissions($datos['permissions']);

            return $perfil;
        });

        return response()->json([
            'success' => true,
            'message' => 'Perfil creado exitosamente',
            'data' => $this->present($perfil->load('permissions:id,name,module,is_menu')),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $perfil = Role::visible()->findOrFail($id);

        if (!$perfil->isEditable()) {
            return $this->noEditable($perfil);
        }

        $datos = $this->validar($request, $perfil);

        DB::transaction(function () use ($perfil, $datos) {
            $perfil->update([
                'display_name' => $datos['display_name'],
                'description' => array_key_exists('description', $datos) ? $datos['description'] : $perfil->description,
                'location_scoped' => $datos['location_scoped'] ?? $perfil->location_scoped,
                'schedule_scoped' => $datos['schedule_scoped'] ?? $perfil->schedule_scoped,
            ]);
            $perfil->replacePermissions($datos['permissions']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente',
            'data' => $this->present($perfil->load('permissions:id,name,module,is_menu')),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $perfil = Role::visible()->findOrFail($id);

        if (!$perfil->isEditable()) {
            return $this->noEditable($perfil);
        }

        $usuarios = $perfil->usersCount();
        if ($usuarios > 0) {
            return response()->json([
                'success' => false,
                'message' => $usuarios === 1
                    ? 'No se puede eliminar: 1 usuario tiene este perfil. Asígnele otro perfil primero.'
                    : "No se puede eliminar: {$usuarios} usuarios tienen este perfil. Asígneles otro perfil primero.",
            ], 422);
        }

        DB::transaction(function () use ($perfil) {
            $perfil->permissions()->detach();
            $perfil->delete();
        });

        return response()->json(['success' => true, 'message' => 'Perfil eliminado exitosamente']);
    }

    // ------------------------------------------------------------------

    private function validar(Request $request, ?Role $perfil = null): array
    {
        return $request->validate([
            'display_name' => [
                'required', 'string', 'min:3', 'max:60',
                Rule::unique('roles', 'display_name')->ignore($perfil?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'location_scoped' => ['sometimes', 'boolean'],
            'schedule_scoped' => ['sometimes', 'boolean'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::assignableNames())],
        ], [
            'display_name.required' => 'El nombre del perfil es requerido',
            'display_name.min' => 'El nombre del perfil debe tener al menos 3 caracteres',
            'display_name.unique' => 'Ya existe un perfil con ese nombre',
            'permissions.present' => 'Falta la lista de permisos del perfil',
            'permissions.*.in' => 'Uno de los permisos seleccionados no existe o no se puede asignar',
        ]);
    }

    /**
     * Nombre técnico a partir del nombre visible: "Jefe de Bodega" → jefe_de_bodega.
     * Si ya existe, se numera (jefe_de_bodega_2).
     */
    private function nombreTecnico(string $nombreVisible): string
    {
        $base = Str::limit(Str::slug($nombreVisible, '_'), 40, '') ?: 'perfil';
        $nombre = $base;

        for ($n = 2; Role::where('name', $nombre)->exists(); $n++) {
            $nombre = "{$base}_{$n}";
        }

        return $nombre;
    }

    private function noEditable(Role $perfil): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "El perfil {$perfil->display_name} tiene acceso total y no se puede modificar ni eliminar.",
        ], 422);
    }

    /** Etiquetas de las secciones del menú que ve el perfil (según su permiso de "ver"). */
    private function menuDe(Role $perfil): array
    {
        $visibles = $perfil->has_full_access
            ? array_keys(PermissionCatalog::MODULES)
            : $perfil->permissions->where('is_menu', true)->pluck('module')->all();

        $etiquetas = [];
        foreach (PermissionCatalog::MODULES as $clave => $etiqueta) {
            // La auditoría no es de ningún perfil configurable (ni del administrador).
            if ($clave !== 'audit' && in_array($clave, $visibles, true)) {
                $etiquetas[] = $etiqueta;
            }
        }

        return $etiquetas;
    }

    private function present(Role $perfil): array
    {
        $ofrecidos = PermissionCatalog::assignableNames();

        return [
            'id' => $perfil->id,
            'name' => $perfil->name,
            'displayName' => $perfil->display_name,
            'description' => $perfil->description,
            'hasFullAccess' => $perfil->has_full_access,
            'locationScoped' => $perfil->location_scoped,
            'scheduleScoped' => $perfil->schedule_scoped,
            'editable' => $perfil->isEditable(),
            'usersCount' => $perfil->usersCount(),
            // El de acceso total los tiene todos, estén o no en role_permission.
            'permissions' => $perfil->has_full_access
                ? $ofrecidos
                : array_values(array_intersect($perfil->permissions->pluck('name')->all(), $ofrecidos)),
            'menu' => $this->menuDe($perfil),
            'updatedAt' => $perfil->updated_at?->toISOString(),
        ];
    }
}
