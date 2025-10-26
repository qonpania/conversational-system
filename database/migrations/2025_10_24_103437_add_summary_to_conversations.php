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
            $table->text('summary')->nullable()->after('meta');               // resumen en español
            $table->jsonb('summary_meta')->nullable()->after('summary');      // {model, tokens, digest, updated_by}
            $table->timestampTz('summary_updated_at')->nullable()->after('summary_meta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['summary','summary_meta','summary_updated_at']);
        });
    }
};
