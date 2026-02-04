<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasUuids;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'product_code',
        'brand_id',
        'category_id',
        'base_unit',
        'iva',
        'active_ingredient',
        'min_stock',
        'status',
        'description',
        'created_by',
    ];

    protected $casts = [
        'category_id' => 'string',
        'base_unit' => 'string',
        'iva' => 'integer',
        'min_stock' => 'decimal:2',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('min_stock > (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE inventory.product_id = products.id)');
    }

    // Relationships

    // Brand
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    // Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // User who created this product
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Packaging units (many-to-many)
    public function packagingUnits()
    {
        return $this->belongsToMany(PackagingUnit::class, 'product_packaging_units', 'product_id', 'packaging_unit_id')
            ->withTimestamps()
            ->using(ProductPackagingUnit::class);
    }

    // Recipe products
    public function recipeProducts()
    {
        return $this->hasMany(RecipeProduct::class, 'product_id');
    }

    // Technical recipes (through recipe_products)
    public function technicalRecipes()
    {
        return $this->belongsToMany(TechnicalRecipe::class, 'recipe_products', 'product_id', 'recipe_id')
            ->withPivot(['brand_id', 'quantity', 'unit', 'application_rate', 'observations']);
    }

    // Technical order products
    public function technicalOrderProducts()
    {
        return $this->hasMany(TechnicalOrderProduct::class, 'product_id');
    }

    // Purchase items
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'product_id');
    }

    // Output products
    public function outputProducts()
    {
        return $this->hasMany(OutputProduct::class, 'product_id');
    }

    // Reception items
    public function receptionItems()
    {
        return $this->hasMany(ReceptionItem::class, 'product_id');
    }

    // Reception batch items
    public function receptionBatchItems()
    {
        return $this->hasMany(ReceptionBatchItem::class, 'product_id');
    }

    // Inventory entries
    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'product_id');
    }

    // Inventory movements
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }

    // Alerts related to this product
    public function alerts()
    {
        return $this->hasMany(Alert::class, 'product_id');
    }
}
