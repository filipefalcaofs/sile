<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relógio do BAP (HU-134) — DORMENTE: bap_due_at marca o vencimento do prazo
     * de atuação na Junta e bap_linked_at a vinculação efetiva. Nada entra em
     * aguardando_bap até o Regin (Fase 13) alimentar estas colunas; até lá a
     * rotina expresso:indeferir-sem-bap varre zero. Migration ADITIVA — só
     * adiciona colunas nullable, sem tocar nas existentes (Pitfall: alterar
     * coluna exigiria repetir todos os atributos).
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->timestamp('bap_due_at')->nullable()->after('cancelled_by_user_id');
            $table->timestamp('bap_linked_at')->nullable()->after('bap_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropColumn(['bap_due_at', 'bap_linked_at']);
        });
    }
};
