<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anexos da solicitação (HU-066). Grava o disk DA ÉPOCA (parametrizável,
     * nunca público), path, hash sha256 e metadados — substituível antes do
     * protocolo, imutável depois. requirement_id liga ao requisito atendido
     * (nullable: anexo avulso permitido). nullOnDelete preserva o anexo se o
     * requisito for removido do catálogo.
     */
    public function up(): void
    {
        Schema::create('viability_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('document_requirements')->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_request_documents');
    }
};
