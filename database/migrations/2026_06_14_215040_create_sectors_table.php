<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setores da SEDUR (HU-138) — a "caixa" de distribuição da análise técnica.
     * Cada setor agrupa os analistas (pivot sector_user N:N) que recebem os
     * processos encaminhados. active permite inativar sem excluir (RN: setor com
     * pendência não some — só deixa de receber novas distribuições).
     */
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
