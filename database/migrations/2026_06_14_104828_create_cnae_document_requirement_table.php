<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot N:N entre CNAE e requisito documental (HU-067): quais requisitos
     * são obrigatórios para cada atividade. unique(cnae, requisito) impede
     * duplicidade; carga oficial da SEDUR ainda pendente (tabela nasce vazia).
     */
    public function up(): void
    {
        Schema::create('cnae_document_requirement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cnae_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_requirement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cnae_id', 'document_requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnae_document_requirement');
    }
};
