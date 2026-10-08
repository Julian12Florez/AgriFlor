<?php

namespace App\Models;

use App\Models\Concerns\LineaDeDocumento;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RecipeProduct extends Model
{
    use HasUuids, LineaDeDocumento;

    /** Sus cambios se ven en la foto de auditoría de este documento: [alias, columna]. */
    public const DOCUMENTO_AUDITADO = ['technical_recipe', 'recipe_id'];

    protected $table = 'recipe_products';

    public $timestamps = false;

    protected $fillable = [
        'recipe_id',
        'product_id',
        'brand_id',
        'quantity',
        'unit',
        'application_rate',
        'observations',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function recipe()
    {
        return $this->belongsTo(TechnicalRecipe::class, 'recipe_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
