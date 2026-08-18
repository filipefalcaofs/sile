<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger de sugestões de IA (Fase 14, fundação de execução das Ondas 1-3).
     * Toda saída de IA é SUGESTÃO revisável (AI-SPEC Failure Mode #1) — nunca
     * decisão. Guarda a saída estruturada (output), a entrada minimizada de
     * proveniência/dedup (input_ref/input_hash) e os metadados de auditoria
     * (provider/model/prompt_version/tokens/custo). A criação é auditada por
     * desenho (HasAuditoria/RN-002). input_hash serve à idempotência (1 sugestão
     * por entrada + versão do prompt + tipo).
     */
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('viability_request_id')->nullable()->index()->constrained('viability_requests')->nullOnDelete();
            $table->json('input_ref')->nullable();
            $table->string('input_hash')->nullable();
            $table->json('output')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('cost_estimated', 12, 6)->nullable();
            $table->string('confidence')->nullable();
            $table->string('status')->default('sugerida');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Idempotência da execução: o Job deduplica por (tipo, versão do
            // prompt, hash da entrada) antes de chamar o provedor.
            $table->index(['type', 'prompt_version', 'input_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
    }
};
