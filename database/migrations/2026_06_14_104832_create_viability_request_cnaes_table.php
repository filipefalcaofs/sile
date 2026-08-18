<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CNAEs da solicitação (HU-064/065), espelhando company_cnae: is_primary
     * marca a atividade principal; o limite de 99 complementares é validado na
     * aplicação. unique(request, cnae) impede duplicidade; restrictOnDelete no
     * CNAE protege a integridade (CNAE vinculado não some sob a solicitação).
     */
    public function up(): void
    {
        Schema::create('viability_request_cnaes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cnae_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['viability_request_id', 'cnae_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_request_cnaes');
    }
};
