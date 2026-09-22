<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo usuário-empresa (HU-023/HU-028). Modelo Eloquent próprio com
     * ciclo de vida: started_at/ended_at (encerramento nunca apaga — histórico
     * preservado). role é o papel NO CONTEXTO da empresa (responsavel|procurador),
     * não papel spatie.
     */
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason')->nullable();
            $table->timestamps();
            // Unicidade "um vínculo ativo por par" fica NA APLICAÇÃO
            // (portabilidade SQLite/Postgres, precedente procurations); o
            // índice simples cobre as consultas por par.
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
