<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoverageDepartment;
use App\Models\CoverageDistrict;
use App\Models\CoverageProvince;
use App\Services\CoverageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CoverageController extends Controller
{
    public function __construct(
        protected CoverageService $coverageService
    ) {}

    public function check(Request $request)
    {
        $data = $request->validate([
            'lat'        => ['required', 'numeric'],
            'lng'        => ['required', 'numeric'],
            'department' => ['nullable', 'string'],
            'province'   => ['nullable', 'string'],
            'district'   => ['nullable', 'string'],
        ]);

        $department = null;
        $province   = null;
        $district   = null;

        if (! empty($data['department'])) {
            $department = $this->resolveDepartment($data['department']);

            if (! $department) {
                return response()->json([
                    'message' => 'Department not found.',
                ], 404);
            }
        }

        if (! empty($data['province'])) {
            if (! $department) {
                return response()->json([
                    'message' => 'Provide a department before filtering by province.',
                ], 422);
            }

            $province = $this->resolveProvince($department, $data['province']);

            if (! $province) {
                return response()->json([
                    'message' => 'Province not found for the provided department.',
                ], 404);
            }
        }

        if (! empty($data['district'])) {
            if (! $province) {
                return response()->json([
                    'message' => 'Provide a province before filtering by district.',
                ], 422);
            }

            $district = $this->resolveDistrict($province, $data['district']);

            if (! $district) {
                return response()->json([
                    'message' => 'District not found for the provided province.',
                ], 404);
            }
        }

        $zone = $this->coverageService->checkCoverage(
            $data['lat'],
            $data['lng'],
            $department,
            $province,
            $district,
        );

        if (! $zone) {
            return response()->json([
                'has_coverage' => false,
            ]);
        }

        $zone->loadMissing(['department', 'province', 'district']);

        return response()->json([
            'has_coverage' => true,
            'zone' => [
                'id'           => $zone->id,
                'name'         => $zone->name,
                'score'        => $zone->score,
                'metadata'     => $zone->metadata,
                'departamento' => $zone->department?->name,
                'provincia'    => $zone->province?->name,
                'distrito'     => $zone->district?->name,
                'location'     => [
                    'department' => $zone->department
                        ? [
                            'id'   => $zone->department->id,
                            'name' => $zone->department->name,
                            'slug' => $zone->department->slug,
                        ]
                        : null,
                    'province' => $zone->province
                        ? [
                            'id'   => $zone->province->id,
                            'name' => $zone->province->name,
                            'slug' => $zone->province->slug,
                        ]
                        : null,
                    'district' => $zone->district
                        ? [
                            'id'   => $zone->district->id,
                            'name' => $zone->district->name,
                            'slug' => $zone->district->slug,
                        ]
                        : null,
                ],
            ],
        ]);
    }

    /**
     * Hierarchical lookup for coverage locations.
     */
    public function locations(Request $request)
    {
        $data = $request->validate([
            'department' => ['nullable', 'string'],
            'province'   => ['nullable', 'string'],
            'district'   => ['nullable', 'string'],
            'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $data['limit'] ?? 10;

        if (empty($data['department'])) {
            return response()->json([
                'type' => 'departments',
                'data' => CoverageDepartment::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
            ]);
        }

        $department = $this->resolveDepartment($data['department']);

        if (! $department) {
            return response()->json([
                'message' => 'Department not found.',
            ], 404);
        }

        if (empty($data['province'])) {
            return response()->json([
                'type'       => 'provinces',
                'department' => $this->transformDepartment($department),
                'data'       => $department->provinces()
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
            ]);
        }

        $province = $this->resolveProvince($department, $data['province']);

        if (! $province) {
            return response()->json([
                'message' => 'Province not found for the provided department.',
            ], 404);
        }

        if (empty($data['district'])) {
            return response()->json([
                'type'       => 'districts',
                'department' => $this->transformDepartment($department),
                'province'   => $this->transformProvince($province),
                'data'       => $province->districts()
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
            ]);
        }

        $district = $this->resolveDistrict($province, $data['district']);

        if (! $district) {
            return response()->json([
                'message' => 'District not found for the provided province.',
            ], 404);
        }

        $zonesQuery   = $district->zones()->orderByDesc('score');
        $zonesCount   = $zonesQuery->count();
        $zonesPreview = $zonesCount > 0
            ? $zonesQuery->limit($limit)->get(['id', 'name', 'score'])
            : collect();

        return response()->json([
            'type'       => 'coverage_check',
            'department' => $this->transformDepartment($department),
            'province'   => $this->transformProvince($province),
            'district'   => $this->transformDistrict($district),
            'coverage'   => [
                'status' => $zonesCount > 0
                    ? 'some area zones covered'
                    : 'non area zones covered',
                'zones_count' => $zonesCount,
                'zones'       => $zonesPreview->map(fn ($zone) => [
                    'id'    => $zone->id,
                    'name'  => $zone->name,
                    'score' => $zone->score,
                ]),
            ],
        ]);
    }

    protected function resolveDepartment(string $value): ?CoverageDepartment
    {
        $slug  = $this->normalizeLookupSlug($value);
        $lower = $this->normalizeLookupName($value);

        return CoverageDepartment::query()
            ->where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [$lower])
            ->first();
    }

    protected function resolveProvince(CoverageDepartment $department, string $value): ?CoverageProvince
    {
        $slug  = $this->normalizeLookupSlug($value);
        $lower = $this->normalizeLookupName($value);

        return CoverageProvince::query()
            ->where('coverage_department_id', $department->id)
            ->where(function ($query) use ($slug, $lower) {
                $query->where('slug', $slug)
                    ->orWhereRaw('LOWER(name) = ?', [$lower]);
            })
            ->first();
    }

    protected function resolveDistrict(CoverageProvince $province, string $value): ?CoverageDistrict
    {
        $slug  = $this->normalizeLookupSlug($value);
        $lower = $this->normalizeLookupName($value);

        return CoverageDistrict::query()
            ->where('coverage_province_id', $province->id)
            ->where(function ($query) use ($slug, $lower) {
                $query->where('slug', $slug)
                    ->orWhereRaw('LOWER(name) = ?', [$lower]);
            })
            ->first();
    }

    protected function normalizeLookupSlug(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $slug = Str::slug($value);

        if ($slug === '') {
            return md5(mb_strtolower($value));
        }

        return $slug;
    }

    protected function normalizeLookupName(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    protected function transformDepartment(CoverageDepartment $department): array
    {
        return [
            'id'   => $department->id,
            'name' => $department->name,
            'slug' => $department->slug,
        ];
    }

    protected function transformProvince(CoverageProvince $province): array
    {
        return [
            'id'   => $province->id,
            'name' => $province->name,
            'slug' => $province->slug,
        ];
    }

    protected function transformDistrict(CoverageDistrict $district): array
    {
        return [
            'id'   => $district->id,
            'name' => $district->name,
            'slug' => $district->slug,
        ];
    }
}
