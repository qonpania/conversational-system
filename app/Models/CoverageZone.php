<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoverageZone extends Model
{
    protected $casts = [
        'polygon' => 'array',
        'metadata' => 'array',
        'score' => 'float',
    ];
}
