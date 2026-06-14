<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pendências da análise (HU-083/084 — ciclo interno) — o analista solicita
     * informação/documento ao requerente; status (aberta/respondida/expirada,
     * índice para a fila), due_at é o prazo (parâmetro analise.pendencia.
     * prazo_resposta_dias) e response guarda a resposta. O convite via
     * Simplifica/Regin + multicanal fica para o EP11 (aqui é o ciclo interno).
     */
    public function up(): void
    {
        Schema::create('analysis_pendencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->index()->constrained('viability_requests')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->string('status')->default('aberta')->index();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_pendencies');
    }
};
