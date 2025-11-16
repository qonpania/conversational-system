<?php

namespace App\Services;

use App\Models\CoverageZone;

class CoverageService
{
    public function checkCoverage(float $lat, float $lng): ?CoverageZone
    {
        // 1) Buscar zonas candidatas (si luego quieres optimizar puedes usar un bounding box simple)
        $zones = CoverageZone::all(); // luego lo optimizamos si hace falta

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
