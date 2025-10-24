<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('title');
            $table->string('source_path');                         // S3 o local
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->string('hash_sha256', 64)->index();
            $table->string('doc_type', 32)->index();               // sop|faq|producto|plantilla|ticket
            $table->string('store', 64)->nullable()->index();      // sucursal/tenant
            $table->string('version', 32)->index();                // ej "2025-10"

            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('indexed_at')->nullable();         // timestamptz en PG

            $table->unsignedInteger('vector_count')->default(0);

            // Portabilidad: usar string + constraint/enum por motor
            $table->string('status', 16)->default('pending')->index();

            $table->json('extra')->nullable();
            $table->timestampsTz();                                // created_at/updated_at con TZ
        });

        // Opcional: ejemplo de índice compuesto útil para reportes
        Schema::table('rag_documents', function (Blueprint $table) {
            $table->index(['store', 'doc_type', 'is_active']);
        });

        // Restringir los valores válidos de "status" según el driver
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // En Postgres: CHECK constraint (no bloquea despliegues multi-DB)
            DB::statement(<<<'SQL'
                ALTER TABLE rag_documents
                ADD CONSTRAINT rag_documents_status_check
                CHECK (status IN ('pending','processing','ready','failed','disabled'))
            SQL);
        } elseif ($driver === 'mysql') {
            // En MySQL: convertir a ENUM manteniendo default e índice
            DB::statement(<<<'SQL'
                ALTER TABLE `rag_documents`
                MODIFY `status` ENUM('pending','processing','ready','failed','disabled')
                NOT NULL DEFAULT 'pending'
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Al dropear la tabla se eliminan índices/constraints asociados
        Schema::dropIfExists('rag_documents');
    }
};
