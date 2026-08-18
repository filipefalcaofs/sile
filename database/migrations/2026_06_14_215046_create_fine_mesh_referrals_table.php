<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encaminhamentos à malha fina (HU-136) — ORTOGONAL ao status (liga a flag
     * in_fine_mesh em viability_requests): é uma tabela (repetível, em lote), não
     * um estado. reason é obrigatório; created_at marca o encaminhamento e
     * resolved_at a baixa (null = em malha fina). Pode atingir até processo
     * deferido (corrige bug do legado).
     */
    public function up(): void
    {
        Schema::create('fine_mesh_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->index()->constrained('viability_requests')->cascadeOnDelete();
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_mesh_referrals');
    }
};
