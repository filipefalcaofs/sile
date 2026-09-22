<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos TVL emitidos (HU-132) — cada emissão/reimpressão do PDF da TVL é
     * uma linha auditada vinculada à decisão (viability_decisions, reuso da
     * Fase 9, fonte do parecer). disk/path apontam o arquivo no Storage (disco
     * parametrizado, NUNCA público); verification_code é o código de validação
     * único; generated_by/generated_at registram autor e momento. O download é
     * por rota assinada temporária (TTL parametrizado) — não vai ao cidadão.
     */
    public function up(): void
    {
        Schema::create('tvl_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_decision_id')->index()->constrained('viability_decisions')->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('verification_code')->unique();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tvl_documents');
    }
};
