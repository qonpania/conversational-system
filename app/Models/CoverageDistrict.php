<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoverageDistrict extends Model
{
    use HasFactory;

    protected $fillable = [
        'coverage_department_id',
        'coverage_province_id',
        'name',
        'slug',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(CoverageDepartment::class, 'coverage_department_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(CoverageProvince::class, 'coverage_province_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(CoverageZone::class, 'coverage_district_id');
    }
}
