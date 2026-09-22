<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Camadas geográficas versionadas (HU-036 RN-004). Carga de nova versão
     * NÃO apaga a anterior: a consulta operacional usa a vigente (valid_to
     * nulo) e a reprodução usa a versão da data da decisão. Carga idempotente
     * por (type, version). Camadas sem fonte pública (zona/lote) ficam com
     * status pendente_fonte e feature_count 0 — sem polígono inventado.
     */
    public function up(): void
    {
        Schema::create('geo_layers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('version');
            $table->string('status')->default('vigente');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('source')->nullable();
            $table->string('rules_version')->nullable();
            $table->unsignedInteger('feature_count')->default(0);
            $table->timestamps();
            $table->unique(['type', 'version']);
            $table->index(['type', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_layers');
    }
};
