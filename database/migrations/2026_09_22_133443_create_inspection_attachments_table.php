<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anexos da ficha de vistoria (fotos/documentos de campo). Espelha o
     * padrão dos anexos da solicitação (HU-066): disk da época (parametrizado,
     * nunca público), path, sha256 e metadados; acesso por streaming
     * autenticado (LGPD).
     */
    public function up(): void
    {
        Schema::create('inspection_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index('inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_attachments');
    }
};
