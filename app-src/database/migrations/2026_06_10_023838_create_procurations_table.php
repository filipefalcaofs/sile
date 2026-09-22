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
        Schema::create('procurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grantor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attorney_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
            // Unicidade "uma ativa por par" fica na aplicação (portabilidade
            // SQLite/Postgres); o índice simples cobre as consultas por par.
            $table->index(['grantor_user_id', 'attorney_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurations');
    }
};
