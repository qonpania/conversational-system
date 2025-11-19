<?php

namespace App\Services;

use App\Models\CoverageDepartment;
use App\Models\CoverageDistrict;
use App\Models\CoverageProvince;
use App\Models\CoverageZone;

class CoverageService
{
    public function checkCoverage(
        float $lat,
        float $lng,
        ?CoverageDepartment $department = null,
        ?CoverageProvince $province = null,
        ?CoverageDistrict $district = null,
    ): ?CoverageZone
    {
        // 1) Buscar zonas candidatas (si luego quieres optimizar puedes usar un bounding box simple)
        $zones = CoverageZone::query()
            ->with(['department', 'province', 'district'])
            ->when($department, fn ($query) => $query->where('coverage_department_id', $department->id))
            ->when($province, fn ($query) => $query->where('coverage_province_id', $province->id))
            ->when($district, fn ($query) => $query->where('coverage_district_id', $district->id))
            ->get(); // luego lo optimizamos si hace falta

        foreach ($zones as $zone) {
            $polygon = $zone->polygon ?? [];

            if ($this->pointInPolygon($lng, $lat, $polygon)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * @param float $x   longitud (lng)
     * @param float $y   latitud (lat)
     * @param array<int, array{0: float, 1: float}> $polygon [ [lon, lat], ... ]
     */
    protected function pointInPolygon(float $x, float $y, array $polygon): bool
    {
        $inside = false;
        $j = count($polygon) - 1;

        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            $intersect =
                (($yi > $y) !== ($yj > $y)) &&
                ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }

            $j = $i;
        }

        return $inside;
    }
}
