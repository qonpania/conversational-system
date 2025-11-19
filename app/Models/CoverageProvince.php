<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoverageProvince extends Model
{
    use HasFactory;

    protected $fillable = [
        'coverage_department_id',
        'name',
        'slug',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(CoverageDepartment::class, 'coverage_department_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(CoverageDistrict::class, 'coverage_province_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(CoverageZone::class, 'coverage_province_id');
    }
}
