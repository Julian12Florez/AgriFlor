<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReceptionBatchAttachment extends Model
{
    use HasUuids;

    protected $table = 'reception_batch_attachments';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function batch()
    {
        return $this->belongsTo(ReceptionBatch::class, 'batch_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
