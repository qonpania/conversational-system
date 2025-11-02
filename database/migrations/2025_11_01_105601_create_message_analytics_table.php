<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->string('sentiment')->index();          // positive|neutral|negative
            $table->float('sentiment_score')->index();     // -1..1
            $table->boolean('toxicity_flag')->default(false)->index();
            $table->boolean('abuse_flag')->default(false)->index();
            $table->boolean('pii_flag')->default(false)->index();
            $table->string('language', 8)->nullable()->index();
            $table->jsonb('intent')->nullable();           // {label,confidence}
            $table->jsonb('entities')->nullable();         // {account, plan, ...}
            $table->timestampsTz();
        });
        
        DB::statement("CREATE INDEX message_analytics_entities_gin ON message_analytics USING GIN ((entities));");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_analytics');
    }
};
