<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quadros 11 e 11A da LOUOS (HU-017/HU-018/HU-040/HU-041): condições de
     * instalação pela via. Uma única tabela serve aos dois domínios
     * (louos_quadro11 e louos_quadro11a) — o domínio da versão (rule_version_id)
     * distingue 11 de 11A, evitando duplicar o esquema. Ligada a rule_versions
     * (reusado da Fase 6) — dado versionado. TABULAR (sem PostGIS): roda em
     * SQLite. classe_via é a classificação viária da LOUOS (atributo
     * operacional pendente confirmação SEDUR — modelado); condicoes (json) é o
     * conjunto livre de exigências pela via; base_legal registra o dispositivo.
     */
    public function up(): void
    {
        Schema::create('louos_quadro11_condicoes_via', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('classe_via');
            $table->string('grupo_uso')->nullable();
            $table->jsonb('condicoes')->nullable();
            $table->string('base_legal')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index(['rule_version_id', 'classe_via']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('louos_quadro11_condicoes_via');
    }
};
