<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RagDocument extends Model
{
    use HasUuids;

    protected $casts = [
        'is_active' => 'boolean',
        'indexed_at' => 'datetime',
        'extra' => 'array',
    ];
}
