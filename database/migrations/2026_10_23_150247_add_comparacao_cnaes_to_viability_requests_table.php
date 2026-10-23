<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resultado da comparação dos CNAEs do pedido de alteração de atividade com
     * a viabilidade vinculada ao CNPJ na SEFAZ: incluídas, excluídas, mantidas,
     * inválidas e o número da viabilidade existente.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->json('comparacao_cnaes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropColumn('comparacao_cnaes');
        });
    }
};
