<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CNAEs vinculados a uma regra territorial (RT-01: os 24 CNAEs proibidos
     * no logradouro 212). Código em 7 dígitos (precedente do import de CNAE —
     * sem máscara). Deduplicado por regra: a fonte legal repete códigos com
     * descrições divergentes (OPEN-RT-1); a descrição oficial é a do cadastro
     * de CNAE, não uma coluna aqui.
     */
    public function up(): void
    {
        Schema::create('territorial_rule_cnaes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('territorial_rule_id')->constrained('territorial_rules')->cascadeOnDelete();
            $table->string('cnae', 7);
            $table->timestamps();

            $table->unique(['territorial_rule_id', 'cnae']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territorial_rule_cnaes');
    }
};
