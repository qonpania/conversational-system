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
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('source_path'); // path en S3 o local
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('hash_sha256', 64)->index();
            $table->string('doc_type', 32)->index(); // sop|faq|producto|plantilla|ticket
            $table->string('store', 64)->nullable()->index(); // sucursal/tenant
            $table->string('version', 32)->index(); // ej "2025-10"
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('indexed_at')->nullable();
            $table->unsignedInteger('vector_count')->default(0);
            $table->enum('status', ['pending','processing','ready','failed'])->default('pending')->index();
            $table->json('extra')->nullable(); // tags u otros metadatos
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_documents');
    }
};
