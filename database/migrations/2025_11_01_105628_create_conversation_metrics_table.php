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
        Schema::create('conversation_metrics', function (Blueprint $table) {
            $table->foreignUuid('conversation_id')->primary()->constrained('conversations')->cascadeOnDelete();
            $table->string('sentiment_overall')->index();     // positive|neutral|negative
            $table->float('sentiment_score')->index();        // -1..1
            $table->string('sentiment_trend')->nullable();    // up|down|flat
            $table->integer('message_count')->default(0);
            $table->integer('handover_count')->default(0);
            $table->integer('first_response_time')->nullable(); // seg
            $table->integer('avg_response_time')->nullable();   // seg
            $table->boolean('fcr')->default(false)->index();
            $table->float('csat_pred')->nullable();             // 0..1
            $table->float('churn_risk')->nullable();            // 0..1
            $table->jsonb('top_intents')->nullable();           // [{label,count}]
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_metrics');
    }
};
