<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReceptionBatch extends Model
{
    use HasUuids;

    protected $table = 'reception_batches';

    public $timestamps = false;

    protected $fillable = [
        'reception_id',
        'batch_number',
        'reception_date',
        'received_by',
        'observations',
    ];

    protected $casts = [
        'batch_number' => 'integer',
        'reception_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function reception()
    {
        return $this->belongsTo(Reception::class, 'reception_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // Batch items
    public function batchItems()
    {
        return $this->hasMany(ReceptionBatchItem::class, 'batch_id');
    }

    // Attachments
    public function attachments()
    {
        return $this->hasMany(ReceptionBatchAttachment::class, 'batch_id');
    }
}
