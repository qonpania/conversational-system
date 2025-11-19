<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coverage_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('coverage_provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coverage_department_id')
                ->nullable()
                ->constrained('coverage_departments')
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['coverage_department_id', 'slug']);
        });

        Schema::create('coverage_districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coverage_department_id')
                ->nullable()
                ->constrained('coverage_departments')
                ->nullOnDelete();
            $table->foreignId('coverage_province_id')
                ->nullable()
                ->constrained('coverage_provinces')
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['coverage_province_id', 'slug']);
        });

        Schema::table('coverage_zones', function (Blueprint $table) {
            $table->foreignId('coverage_department_id')
                ->nullable()
                ->after('name')
                ->constrained('coverage_departments')
                ->nullOnDelete();
            $table->foreignId('coverage_province_id')
                ->nullable()
                ->after('coverage_department_id')
                ->constrained('coverage_provinces')
                ->nullOnDelete();
            $table->foreignId('coverage_district_id')
                ->nullable()
                ->after('coverage_province_id')
                ->constrained('coverage_districts')
                ->nullOnDelete();
        });

        $this->migrateExistingLocations();

        Schema::table('coverage_zones', function (Blueprint $table) {
            $table->dropColumn(['departamento', 'provincia', 'distrito']);
        });
    }

    private function migrateExistingLocations(): void
    {
        $zones = DB::table('coverage_zones')
            ->select('id', 'departamento', 'provincia', 'distrito')
            ->orderBy('id')
            ->get();

        $departmentCache = [];
        $provinceCache = [];
        $districtCache = [];

        foreach ($zones as $zone) {
            $departmentId = null;
            $provinceId = null;
            $districtId = null;

            if ($zone->departamento) {
                $normalized = $this->normalizeName($zone->departamento);
                $departmentCache[$normalized['slug']] ??= $this->firstOrCreateDepartmentId($normalized);
                $departmentId = $departmentCache[$normalized['slug']];
            }

            if ($zone->provincia) {
                $normalized = $this->normalizeName($zone->provincia);
                $provinceKey = ($departmentId ?? 'null') . '|' . $normalized['slug'];
                $provinceCache[$provinceKey] ??= $this->firstOrCreateProvinceId(
                    $normalized,
                    $departmentId
                );
                $provinceId = $provinceCache[$provinceKey];
            }

            if ($zone->distrito) {
                $normalized = $this->normalizeName($zone->distrito);
                $districtKey = ($provinceId ?? 'null') . '|' . $normalized['slug'];
                $districtCache[$districtKey] ??= $this->firstOrCreateDistrictId(
                    $normalized,
                    $departmentId,
                    $provinceId
                );
                $districtId = $districtCache[$districtKey];
            }

            DB::table('coverage_zones')
                ->where('id', $zone->id)
                ->update([
                    'coverage_department_id' => $departmentId,
                    'coverage_province_id'   => $provinceId,
                    'coverage_district_id'   => $districtId,
                ]);
        }
    }

    private function normalizeName(string $value): array
    {
        $name = trim($value);
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug !== '' ? $slug : md5(strtolower($name)),
        ];
    }

    private function firstOrCreateDepartmentId(array $normalized): int
    {
        $existing = DB::table('coverage_departments')->where('slug', $normalized['slug'])->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('coverage_departments')->insertGetId([
            'name'       => $normalized['name'],
            'slug'       => $normalized['slug'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function firstOrCreateProvinceId(array $normalized, ?int $departmentId): int
    {
        $existing = DB::table('coverage_provinces')
            ->where('slug', $normalized['slug'])
            ->where(function ($query) use ($departmentId) {
                $query->where('coverage_department_id', $departmentId);
                if ($departmentId === null) {
                    $query->orWhereNull('coverage_department_id');
                }
            })
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('coverage_provinces')->insertGetId([
            'coverage_department_id' => $departmentId,
            'name'                   => $normalized['name'],
            'slug'                   => $normalized['slug'],
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    private function firstOrCreateDistrictId(array $normalized, ?int $departmentId, ?int $provinceId): int
    {
        $existing = DB::table('coverage_districts')
            ->where('slug', $normalized['slug'])
            ->where(function ($query) use ($provinceId) {
                $query->where('coverage_province_id', $provinceId);
                if ($provinceId === null) {
                    $query->orWhereNull('coverage_province_id');
                }
            })
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('coverage_districts')->insertGetId([
            'coverage_department_id' => $departmentId,
            'coverage_province_id'   => $provinceId,
            'name'                   => $normalized['name'],
            'slug'                   => $normalized['slug'],
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('coverage_zones', function (Blueprint $table) {
            $table->string('departamento')->nullable()->after('name');
            $table->string('provincia')->nullable()->after('departamento');
            $table->string('distrito')->nullable()->after('provincia');
        });

        $zones = DB::table('coverage_zones')
            ->select(
                'coverage_zones.id',
                'coverage_departments.name as departamento',
                'coverage_provinces.name as provincia',
                'coverage_districts.name as distrito'
            )
            ->leftJoin('coverage_departments', 'coverage_departments.id', '=', 'coverage_zones.coverage_department_id')
            ->leftJoin('coverage_provinces', 'coverage_provinces.id', '=', 'coverage_zones.coverage_province_id')
            ->leftJoin('coverage_districts', 'coverage_districts.id', '=', 'coverage_zones.coverage_district_id')
            ->get();

        foreach ($zones as $zone) {
            DB::table('coverage_zones')
                ->where('id', $zone->id)
                ->update([
                    'departamento' => $zone->departamento,
                    'provincia'    => $zone->provincia,
                    'distrito'     => $zone->distrito,
                ]);
        }

        Schema::table('coverage_zones', function (Blueprint $table) {
            $table->dropForeign(['coverage_department_id']);
            $table->dropForeign(['coverage_province_id']);
            $table->dropForeign(['coverage_district_id']);
            $table->dropColumn(['coverage_department_id', 'coverage_province_id', 'coverage_district_id']);
        });

        Schema::dropIfExists('coverage_districts');
        Schema::dropIfExists('coverage_provinces');
        Schema::dropIfExists('coverage_departments');
    }
};
