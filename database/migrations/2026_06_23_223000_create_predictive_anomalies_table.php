<?php

use App\Enums\AbuseAlertStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria Preditiva de Processos Expressos (Módulo 3 — IA/Malha Fina). Ledger
 * das anomalias pós-deferimento automático: cada linha é um processo deferido no
 * expresso cujo SCORE de risco (sinais determinísticos) superou o limiar. NUNCA é
 * punição — é insumo humano (gestor confirma/descarta) e, na severidade alta,
 * gera encaminhamento à malha fina (ortogonal ao status, LGPD art. 20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictive_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score');
            $table->string('severity');
            $table->string('status')->default(AbuseAlertStatus::Aberto->value);
            $table->string('fingerprint');
            $table->jsonb('factors')->nullable();
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->timestamp('detected_at');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('justification')->nullable();
            $table->foreignId('fine_mesh_referral_id')->nullable()->constrained('fine_mesh_referrals')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('fingerprint');
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictive_anomalies');
    }
};
