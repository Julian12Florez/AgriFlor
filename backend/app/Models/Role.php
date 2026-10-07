<?php

namespace App\Models;

use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Events\AuditCustom;

class Role extends Model implements AuditableContract
{
    use HasFactory, HasUuids, Auditable;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'has_full_access',
        'location_scoped',
        'schedule_scoped',
        'excluded_modules',
    ];

    protected $casts = [
        'has_full_access' => 'boolean',
        'location_scoped' => 'boolean',
        'schedule_scoped' => 'boolean',
        'excluded_modules' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Permissions assigned to this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withTimestamps();
    }

    /**
     * Users with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Excluye los perfiles secretos (el auditor): para la pantalla de Perfiles
     * y el formulario de usuarios no existen.
     */
    public function scopeVisible($query)
    {
        return $query->whereNotIn('name', User::SECRET_ROLES);
    }

    public function isSecret(): bool
    {
        return in_array($this->name, User::SECRET_ROLES, true);
    }

    /**
     * ¿Se puede modificar o eliminar desde la pantalla de Perfiles?
     * El de acceso total (Administrador) nunca: así nadie se queda sin acceso.
     */
    public function isEditable(): bool
    {
        return !$this->has_full_access && !$this->isSecret();
    }

    /**
     * Cuántos usuarios tienen este perfil (activos o no). Cuenta el enlace
     * `role_id` y, para datos viejos sin enlace, el nombre en `users.role`.
     */
    public function usersCount(): int
    {
        return User::where('role_id', $this->id)
            ->orWhere(fn ($q) => $q->whereNull('role_id')->where('role', $this->name))
            ->count();
    }

    /**
     * Deja el perfil con exactamente estos permisos (de los que la pantalla de
     * Perfiles ofrece) y registra en la auditoría cuáles se agregaron y cuáles
     * se quitaron, con su nombre legible.
     *
     * Los permisos que la pantalla no ofrece (PermissionCatalog::HIDDEN_FROM_PROFILES)
     * no se tocan: si el perfil los tenía, los conserva.
     *
     * @param  array<int, string>  $names
     */
    public function replacePermissions(array $names): void
    {
        $ofrecidos = PermissionCatalog::assignableNames();
        $actuales = $this->permissions()->get(['permissions.id', 'permissions.name', 'permissions.display_name', 'permissions.sort_order']);
        $intocables = $actuales->whereNotIn('name', $ofrecidos);
        $nuevos = Permission::whereIn('name', array_intersect($names, $ofrecidos))
            ->get(['id', 'name', 'display_name', 'sort_order']);

        $agregados = $nuevos->whereNotIn('id', $actuales->pluck('id'))->sortBy('sort_order')->pluck('display_name');
        $quitados = $actuales->whereNotIn('id', $nuevos->pluck('id'))
            ->whereIn('name', $ofrecidos)
            ->sortBy('sort_order')
            ->pluck('display_name');

        $this->permissions()->sync($nuevos->pluck('id')->merge($intocables->pluck('id'))->all());

        if (($agregados->isEmpty() && $quitados->isEmpty()) || !static::isAuditingEnabled()) {
            return;
        }

        // La relación perfil↔permiso es una tabla puente: el paquete de
        // auditoría no la ve sola. Se registra como una edición del perfil.
        $this->auditEvent = 'updated';
        $this->isCustomEvent = true;
        $this->auditCustomOld = $quitados->isEmpty() ? [] : ['permisos_quitados' => $quitados->implode(', ')];
        $this->auditCustomNew = $agregados->isEmpty() ? [] : ['permisos_agregados' => $agregados->implode(', ')];
        Event::dispatch(new AuditCustom($this));
        $this->auditCustomOld = $this->auditCustomNew = [];
        $this->isCustomEvent = false;
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        // Admin has full access
        if ($this->has_full_access) {
            return true;
        }

        // Check if permission exists in role's permissions
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Check if role has access to a module
     */
    public function hasModuleAccess(string $module): bool
    {
        // Admin has full access
        if ($this->has_full_access) {
            return true;
        }

        // Check if module is in excluded modules
        if (is_array($this->excluded_modules) && in_array($module, $this->excluded_modules)) {
            return false;
        }

        // El módulo se ve si el perfil tiene el permiso de MENÚ de ese módulo
        // (uno por módulo, ver PermissionCatalog). Antes bastaba con tener
        // CUALQUIER permiso del módulo: con los permisos de crear/editar/
        // eliminar ya separados, eso haría aparecer en el menú una sección
        // donde el perfil solo puede, por ejemplo, registrar algo por API.
        return $this->permissions()->where('module', $module)->where('is_menu', true)->exists();
    }

    /**
     * Get all modules accessible by this role
     */
    public function getAccessibleModules(): array
    {
        if ($this->has_full_access) {
            return ['all'];
        }

        // Solo los módulos de los que tiene el permiso de menú (ver hasModuleAccess).
        $modules = $this->permissions()
            ->where('is_menu', true)
            ->select('module')
            ->distinct()
            ->pluck('module')
            ->toArray();

        // Remove excluded modules
        if (is_array($this->excluded_modules)) {
            $modules = array_diff($modules, $this->excluded_modules);
        }

        return array_values($modules);
    }
}
