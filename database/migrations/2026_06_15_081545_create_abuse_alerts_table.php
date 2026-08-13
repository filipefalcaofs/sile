<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger de alertas de abuso/fraude (HU-149) — NUNCA punição: o detector
     * registra aqui e (acima do limiar) encaminha à malha fina. A idempotência
     * é estrutural: índice ÚNICO PARCIAL (rule_key, fingerprint) restrito aos
     * alertas em status 'aberto', para o scheduler reprocessar a mesma janela
     * sem duplicar um alerta aberto. Auditável por desenho (HasAuditoria/RN-002).
     */
    public function up(): void
    {
        Schema::create('abuse_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key')->index();
            $table->string('severity');
            $table->string('status')->default('aberto')->index();
            $table->string('fingerprint');
            $table->json('evidence')->nullable();
            $table->foreignId('viability_request_id')->nullable()->index()->constrained('viability_requests')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->timestamp('detected_at');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('justification')->nullable();
            $table->foreignId('fine_mesh_referral_id')->nullable()->constrained('fine_mesh_referrals')->nullOnDelete();
            $table->timestamps();
        });

        // Idempotência estrutural: 1 alerta ABERTO por (rule_key, fingerprint).
        // Confirmados/descartados NÃO bloqueiam um novo alerta futuro. Índice
        // parcial — PostgreSQL (produção) e SQLite (suíte) usam a MESMA sintaxe.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX abuse_alerts_open_unique ON abuse_alerts (rule_key, fingerprint) WHERE status = 'aberto'",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('abuse_alerts');
    }
};
