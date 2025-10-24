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
        Schema::create('agent_prompt_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->integer('version')->unsigned();         // 1,2,3...
            $table->string('title');
            $table->text('content');                        // el prompt
            $table->jsonb('parameters')->nullable();        // {temperature, top_p, system, tools...}
            $table->string('status')->default('draft');     // draft|published|archived
            $table->boolean('is_active')->default(false);   // solo 1 por agente
            $table->timestampTz('activated_at')->nullable();
            $table->string('checksum')->nullable();         // hash contenido+params
            $table->text('notes')->nullable();              // changelog
            $table->timestampsTz();

            $table->unique(['agent_id','version']);
            $table->index(['agent_id','is_active']);
        });

        // Constraint: un solo activo por agente
        DB::statement('CREATE UNIQUE INDEX one_active_prompt_per_agent
            ON agent_prompt_versions (agent_id) WHERE is_active = true');

        // Opcional: check de estados
        DB::statement("ALTER TABLE agent_prompt_versions
            ADD CONSTRAINT chk_status CHECK (status IN ('draft','published','archived'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS one_active_prompt_per_agent');
        Schema::dropIfExists('agent_prompt_versions');
    }
};
