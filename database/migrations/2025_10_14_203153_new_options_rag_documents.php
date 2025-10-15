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
        // MySQL: alterar enum añadiendo 'disabled'
        DB::statement("ALTER TABLE `rag_documents`
            MODIFY `status` ENUM('pending','processing','ready','failed','disabled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // revertir quitando 'disabled' (cuidado: si hay filas con 'disabled' esto fallará)
        DB::statement("ALTER TABLE `rag_documents`
            MODIFY `status` ENUM('pending','processing','ready','failed') NOT NULL DEFAULT 'pending'");
    }
};
