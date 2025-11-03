<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->longText('recommendations')->nullable()->after('summary');
            $table->json('recommendations_meta')->nullable()->after('recommendations');
            $table->timestampTz('recommendations_updated_at')->nullable()->after('recommendations_meta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['recommendations', 'recommendations_meta', 'recommendations_updated_at']);
        });
    }
};
