<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'has_full_access',
        'excluded_modules',
    ];

    protected $casts = [
        'has_full_access' => 'boolean',
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
