<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coverage_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();

            $table->string('departamento')->nullable();
            $table->string('provincia')->nullable();
            $table->string('distrito')->nullable();

            $table->decimal('score', 8, 3)->nullable();

            // Coordenadas del polígono en formato [ [lon, lat], ... ]
            $table->json('polygon');

            // Todo el ExtendedData del KML
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('distrito');
            $table->index('provincia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_zones');
    }
};
