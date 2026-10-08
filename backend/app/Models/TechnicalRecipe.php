<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Concerns\Auditado;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TechnicalRecipe extends Model implements AuditableContract
{
    use HasUuids, Auditado;

    protected $table = 'technical_recipes';

    /**
     * La tabla solo tiene `created_at` (con valor por defecto). Sin esto, crear
     * o editar una receta intentaba escribir `updated_at` y respondía 500: por
     * eso en producción no había ni una receta.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'description',
        'category',
        'application_instructions',
        'safety_notes',
        'estimated_cost',
        'usage_count',
        'status',
        'created_by',
        'last_used',
    ];

    protected $casts = [
        'category' => 'string',
        'estimated_cost' => 'decimal:2',
        'usage_count' => 'integer',
        'status' => 'string',
        'created_at' => 'datetime',
        'last_used' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeMostUsed($query, $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    // Relationships

    // User who created this recipe
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Recipe products
    public function recipeProducts()
    {
        return $this->hasMany(RecipeProduct::class, 'recipe_id');
    }

    // Products in this recipe (many-to-many through recipe_products)
    public function products()
    {
        return $this->belongsToMany(Product::class, 'recipe_products', 'recipe_id', 'product_id')
            ->withPivot(['brand_id', 'quantity', 'unit', 'application_rate', 'observations']);
    }

    // Technical orders using this recipe
    public function technicalOrders()
    {
        return $this->hasMany(TechnicalOrder::class, 'recipe_id');
    }
}
