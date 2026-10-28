<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anexos da análise do processo (documentos que o analista junta à ficha).
     * Ficam no processo, e não na revisão da ficha, para valer em qualquer
     * revisão. Mesmo padrão dos anexos da vistoria: disk da época (nunca
     * público), sha256 e acesso por streaming autenticado (LGPD).
     */
    public function up(): void
    {
        Schema::create('analysis_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index('viability_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_attachments');
    }
};
