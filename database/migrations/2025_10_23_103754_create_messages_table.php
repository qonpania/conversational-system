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
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('direction')->index();              // inbound|outbound
            $table->string('type')->default('text')->index();  // text|photo|video|file|voice
            $table->text('text')->nullable();
            $table->jsonb('payload')->nullable();              // cuerpo crudo de n8n/telegram
            $table->jsonb('attachments')->nullable();          // [{url,mime,size,filename}]
            $table->timestampTz('sent_at')->index();
            $table->timestampsTz();
        });

        // índices GIN para JSONB
        DB::statement('CREATE INDEX messages_payload_gin ON messages USING GIN (payload);');
        DB::statement('CREATE INDEX messages_attachments_gin ON messages USING GIN (attachments);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
