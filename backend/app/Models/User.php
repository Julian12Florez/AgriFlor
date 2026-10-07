<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, AuditableContract
{
    use HasFactory, Notifiable, HasUuids;
    use Auditable {
        readyForAuditing as protected listoParaAuditar;
    }

    /**
     * Qué se audita de un usuario: quién es y qué acceso tiene. Cambiarle el
     * perfil a alguien es cambiarle lo que puede hacer, así que tiene que
     * quedar. La clave nunca se guarda en la auditoría.
     */
    protected $auditInclude = ['name', 'email', 'role', 'status'];

    /**
     * Un cambio que no toca nada de lo anterior (la clave, por ejemplo) no
     * deja una fila vacía en la auditoría.
     */
    public function readyForAuditing(): bool
    {
        if (!$this->listoParaAuditar()) {
            return false;
        }

        return $this->auditEvent !== 'updated' || $this->isDirty($this->auditInclude);
    }

    protected $table = 'users';

    protected $fillable = [
        'email',
        'name',
        'password',
        'role',
        'role_id',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'role' => 'string',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        // Load role relationship for permissions
        $roleData = null;
        if ($this->roleRelation) {
            $roleData = [
                'id' => $this->roleRelation->id,
                'name' => $this->roleRelation->name,
                'display_name' => $this->roleRelation->display_name,
                'has_full_access' => $this->roleRelation->has_full_access,
                'excluded_modules' => $this->roleRelation->excluded_modules,
                'permissions' => $this->roleRelation->permissions->pluck('name')->toArray(),
            ];
        }

        return [
            'role' => $this->role, // Backward compatibility
            'status' => $this->status,
            'role_data' => $roleData,
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Roles "secretos": usuarios que NO deben aparecer en ningún listado ni
     * gestión de usuarios (ej. el auditor). Siguen pudiendo autenticarse: este
     * scope solo se aplica en los endpoints de gestión, no en el login.
     */
    public const SECRET_ROLES = ['auditor'];

    /**
     * Excluye usuarios secretos de listados y operaciones de gestión.
     */
    public function scopeVisible($query)
    {
        return $query->whereNotIn('role', self::SECRET_ROLES);
    }

    public function isSecret(): bool
    {
        return in_array($this->role, self::SECRET_ROLES, true);
    }

    // Relationships

    // Role relationship
    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // Permission checking methods
    public function hasRole(string $roleName): bool
    {
        return $this->role === $roleName;
    }

    /**
     * El perfil que rige los permisos de este usuario.
     *
     * Normalmente es el enlazado por `role_id`. Si el usuario no tiene enlace
     * (datos viejos), se toma el perfil cuyo nombre coincide con su columna
     * `role`: es lo que hacía el control anterior por nombre (CheckRole), y sin
     * esto un usuario así pasaría de "entra" a "no entra a nada" al migrar las
     * rutas a permisos. En producción los 33 usuarios están enlazados.
     */
    public function effectiveRole(): ?Role
    {
        if ($this->roleRelation) {
            return $this->roleRelation;
        }

        return $this->role ? Role::where('name', $this->role)->first() : null;
    }

    public function hasPermission(string $permissionName): bool
    {
        $perfil = $this->effectiveRole();

        if (!$perfil) {
            // Sin ningún perfil con ese nombre en la base: solo el nombre
            // 'admin' conserva el acceso total (igual que hasModuleAccess).
            return $this->role === 'admin';
        }

        return $perfil->hasPermission($permissionName);
    }

    public function hasModuleAccess(string $module): bool
    {
        $perfil = $this->effectiveRole();

        if (!$perfil) {
            // Fallback to old role system
            return $this->role === 'admin';
        }

        return $perfil->hasModuleAccess($module);
    }

    public function getPermissions(): array
    {
        if (!$this->roleRelation) {
            return [];
        }

        return $this->roleRelation->permissions->pluck('name')->toArray();
    }

    public function getAccessibleModules(): array
    {
        if (!$this->roleRelation) {
            return [];
        }

        return $this->roleRelation->getAccessibleModules();
    }

    /**
     * Locations where this user is the responsible (encargado)
     * INC-001: Relacion inversa para obtener ubicaciones asignadas
     */
    public function managedLocations()
    {
        return $this->hasMany(Location::class, 'responsible_user_id');
    }

    /**
     * "Solo ve su finca" es una casilla del perfil (`roles.location_scoped` y
     * `roles.schedule_scoped`), que el administrador marca en la pantalla de
     * Perfiles. Hasta el 7-oct-2026 eran nombres escritos aquí.
     *
     * Estas dos listas quedan SOLO para un usuario sin ningún perfil en la base
     * (datos viejos; en producción no hay ninguno y el formulario ya no deja
     * crearlos): conserva la restricción que tenía por nombre en vez de pasar a
     * verlo todo.
     */
    private const LEGACY_LOCATION_SCOPED_ROLES = ['supervisor', 'farm'];
    private const LEGACY_SCHEDULE_SCOPED_ROLES = ['farm'];

    /**
     * Nombre canónico del rol (relación nueva `role_id`, con fallback al campo legacy).
     */
    public function roleName(): ?string
    {
        return $this->roleRelation?->name ?? $this->role;
    }

    /**
     * ¿El usuario puede ver los movimientos/entradas/salidas/ajustes de TODAS
     * las ubicaciones? False solo si su perfil tiene marcada la casilla "solo
     * ve las fincas a su cargo": queda limitado a las ubicaciones de las que es
     * responsable. Un perfil nuevo ve todo mientras no se le marque.
     */
    public function canViewAllLocations(): bool
    {
        $perfil = $this->effectiveRole();

        if (!$perfil) {
            return !in_array($this->role, self::LEGACY_LOCATION_SCOPED_ROLES, true);
        }

        return $perfil->has_full_access || !$perfil->location_scoped;
    }

    /**
     * Lo mismo para las programaciones de tareas (Rendimiento): con la casilla
     * marcada solo ve, crea y edita las de las fincas a su cargo.
     */
    public function canViewAllSchedules(): bool
    {
        $perfil = $this->effectiveRole();

        if (!$perfil) {
            return !in_array($this->role, self::LEGACY_SCHEDULE_SCOPED_ROLES, true);
        }

        return $perfil->has_full_access || !$perfil->schedule_scoped;
    }

    /** ¿Su perfil es de acceso total (Administrador)? */
    public function hasFullAccess(): bool
    {
        $perfil = $this->effectiveRole();

        // Sin perfil en la base, igual que hasPermission(): solo el nombre 'admin'.
        return $perfil ? $perfil->has_full_access : $this->role === 'admin';
    }

    /**
     * IDs de las ubicaciones de las que el usuario es responsable (encargado).
     */
    public function managedLocationIds(): array
    {
        return $this->managedLocations()->pluck('id')->all();
    }

    // Products created by this user
    public function products()
    {
        return $this->hasMany(Product::class, 'created_by');
    }

    // Technical recipes created by this user
    public function technicalRecipes()
    {
        return $this->hasMany(TechnicalRecipe::class, 'created_by');
    }

    // Technical orders where this user is the responsible agronomist
    public function technicalOrdersAsAgronomist()
    {
        return $this->hasMany(TechnicalOrder::class, 'responsible_agronomist');
    }

    // Technical orders where this user applied the order
    public function technicalOrdersAsApplier()
    {
        return $this->hasMany(TechnicalOrder::class, 'applied_by');
    }

    // Purchases created by this user
    public function purchasesCreated()
    {
        return $this->hasMany(Purchase::class, 'created_by');
    }

    // Purchases received by this user
    public function purchasesReceived()
    {
        return $this->hasMany(Purchase::class, 'received_by');
    }

    // Product outputs responsible
    public function productOutputs()
    {
        return $this->hasMany(ProductOutput::class, 'responsible_user');
    }

    // Receptions responsible
    public function receptions()
    {
        return $this->hasMany(Reception::class, 'responsible_user');
    }

    // Reception batches received
    public function receptionBatches()
    {
        return $this->hasMany(ReceptionBatch::class, 'received_by');
    }

    // Inventory movements
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'responsible_user');
    }

    // Alerts resolved by this user
    public function alertsResolved()
    {
        return $this->hasMany(Alert::class, 'resolved_by');
    }

    // Purchase attachments uploaded
    public function purchaseAttachments()
    {
        return $this->hasMany(PurchaseAttachment::class, 'uploaded_by');
    }

    // Reception batch attachments uploaded
    public function receptionBatchAttachments()
    {
        return $this->hasMany(ReceptionBatchAttachment::class, 'uploaded_by');
    }
}
