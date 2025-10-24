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
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->index();         // telegram user id
            $table->string('username')->nullable()->index();// handle o username público
            $table->string('name')->nullable();
            $table->jsonb('profile')->nullable();           // avatar, language_code, etc.
            $table->timestampsTz();
            $table->unique(['external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
