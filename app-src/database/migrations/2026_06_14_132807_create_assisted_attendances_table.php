<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo leve de atendimento presencial assistido (HU-150): o atendente
     * (attendant_user_id) opera "em nome de" o cidadão presente no balcão
     * (citizen_user_id) por uma janela curta parametrizável. NÃO é procuração
     * jurídica (não há outorga) — é atendimento de balcão; reusa apenas o
     * mecanismo de usuário efetivo/policy/auditoria da Fase 1. O vínculo expira
     * (expires_at) e exige reabertura; ended_at registra o encerramento manual.
     */
    public function up(): void
    {
        Schema::create('assisted_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendant_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('citizen_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            // Consulta do atendimento ativo do atendente (revalidação por request).
            $table->index(['attendant_user_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assisted_attendances');
    }
};
