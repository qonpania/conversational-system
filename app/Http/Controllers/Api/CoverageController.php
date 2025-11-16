<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CoverageService;
use Illuminate\Http\Request;

class CoverageController extends Controller
{
    public function __construct(
        protected CoverageService $coverageService
    ) {}

    public function check(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
        ]);

        $zone = $this->coverageService->checkCoverage(
            $data['lat'],
            $data['lng'],
        );

        if (! $zone) {
            return response()->json([
                'has_coverage' => false,
            ]);
        }

        return response()->json([
            'has_coverage' => true,
            'zone' => [
                'id'           => $zone->id,
                'name'         => $zone->name,
                'departamento' => $zone->departamento,
                'provincia'    => $zone->provincia,
                'distrito'     => $zone->distrito,
                'score'        => $zone->score,
                'metadata'     => $zone->metadata,
            ],
        ]);
    }
}
