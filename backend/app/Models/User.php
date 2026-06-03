<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasUuids;

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

    public function hasPermission(string $permissionName): bool
    {
        if (!$this->roleRelation) {
            return false;
        }

        return $this->roleRelation->hasPermission($permissionName);
    }

    public function hasModuleAccess(string $module): bool
    {
        if (!$this->roleRelation) {
            // Fallback to old role system
            return $this->role === 'admin';
        }

        return $this->roleRelation->hasModuleAccess($module);
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
