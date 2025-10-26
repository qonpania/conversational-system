<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // si ya existiera por un intento previo, evita re-crear
            if (! Schema::hasColumn('conversations', 'routing_mode')) {
                $table->string('routing_mode')->default('ai')->index(); // ai|human|hybrid
            }

            if (! Schema::hasColumn('conversations', 'assigned_user_id')) {
                // users.id es BIGINT por defecto en Laravel → usa foreignId (BIGINT)
                $table->foreignId('assigned_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('conversations', 'handover_at')) {
                $table->timestampTz('handover_at')->nullable();
            }

            if (! Schema::hasColumn('conversations', 'resume_ai_at')) {
                $table->timestampTz('resume_ai_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // elimina FK si existe
            if (Schema::hasColumn('conversations', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }
            $table->dropColumn([
                'routing_mode',
                'handover_at',
                'resume_ai_at',
            ]);
        });
    }
};
