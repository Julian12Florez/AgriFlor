<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TaskCategory extends Model
{
    use HasUuids;

    protected $table = 'task_categories';

    protected $fillable = [
        'name',
        'color',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function tasks()
    {
        return $this->hasMany(TaskCatalog::class, 'category_id');
    }
}
