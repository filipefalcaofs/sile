<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista oficial de CNAEs permitidos para ABRIGADO de escritório virtual
 * (reunião SEDUR 2026-07-16, RN-EV-05/07). Fonte: endpoint SEDUR
 * AtividadesPermitidasEmEscritorioVirtual.php, importada por snapshot
 * versionado. Espelha risk_sanitary_classifications (chave rule_version+cnae).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('cnae_code');
            $table->string('cnae_description')->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'cnae_code']);
            $table->index('cnae_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_office_activity_cnaes');
    }
};
