<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReceptionBatchItem extends Model
{
    use HasUuids;

    protected $table = 'reception_batch_items';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'product_id',
        'reception_item_id',
        'quantity_received',
        'condition',
        'expiration_date',
        'observations',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
        'condition' => 'string',
        'expiration_date' => 'date',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function batch()
    {
        return $this->belongsTo(ReceptionBatch::class, 'batch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function receptionItem()
    {
        return $this->belongsTo(ReceptionItem::class, 'reception_item_id');
    }
}
