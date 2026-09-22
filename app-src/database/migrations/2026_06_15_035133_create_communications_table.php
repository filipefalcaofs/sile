<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger DEDICADO e imutável das comunicações de PROCESSO (HU-096) — espelha
     * access_logs/viability_request_transitions. NÃO é view sobre
     * EmailLog/notifications: EmailLog é genérico de CONTA (sem
     * viability_request_id) e notifications é morph por usuário; unir por
     * processo seria frágil.
     *
     * viability_request_id é nullable (nem toda comunicação é de processo) e usa
     * nullOnDelete para PRESERVAR a linha de auditoria mesmo se o processo for
     * removido. Os índices compostos atendem: (viability_request_id, type,
     * channel) à idempotência das rotinas (Wave 5) e (viability_request_id,
     * created_at) à consulta cronológica do histórico (HU-096).
     */
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel');
            $table->string('type');
            $table->string('status');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['viability_request_id', 'type', 'channel']);
            $table->index(['viability_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
