<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoverageDepartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function provinces(): HasMany
    {
        return $this->hasMany(CoverageProvince::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(CoverageDistrict::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(CoverageZone::class);
    }
}
