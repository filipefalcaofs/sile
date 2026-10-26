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
        // 'portal_servicos' (HU-142) não cabe nos 10 caracteres originais.
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_type', 20)->default('local')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_type', 10)->default('local')->change();
        });
    }
};
