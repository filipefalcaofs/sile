<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Linhas condicionais da tabela da SEDUR: a alteração de atividades vira
 * Inclusão ou Exclusão de CNAE e a alteração de endereço no município vira
 * Revisão de Endereço ou Alteração de Espaço. A condição detectada fica no
 * evento do processo.
 */
return new class extends Migration
{
    private const string ATIVIDADES = 'alteracao de atividades economicas (principal e secundarias)';

    private const string ENDERECO = 'alteracao de endereco dentro do mesmo municipio';

    public function up(): void
    {
        Schema::table('servico_nomenclaturas', function (Blueprint $table): void {
            $table->string('condicao', 40)->default('')->after('chave');
            $table->dropUnique(['chave']);
            $table->unique(['chave', 'condicao']);
        });

        Schema::table('viability_request_eventos', function (Blueprint $table): void {
            $table->string('condicao', 40)->nullable()->after('nome');
        });

        $agora = now();

        $this->condicionar(self::ATIVIDADES, 'cnae_incluido', 'Alteração de atividades econômicas (principal e secundárias)', 'Revisão de Atividade - Exclusão', 'cnae_excluido', $agora);
        $this->condicionar(self::ENDERECO, 'endereco_localizacao', 'Alteração de endereço dentro do mesmo município', 'Revisão de Endereço - Alteração de Espaço', 'endereco_espaco', $agora);
    }

    public function down(): void
    {
        DB::table('servico_nomenclaturas')->whereIn('condicao', ['cnae_excluido', 'endereco_espaco'])->delete();

        Schema::table('servico_nomenclaturas', function (Blueprint $table): void {
            $table->dropUnique(['chave', 'condicao']);
            $table->dropColumn('condicao');
            $table->unique(['chave']);
        });

        Schema::table('viability_request_eventos', function (Blueprint $table): void {
            $table->dropColumn('condicao');
        });
    }

    /**
     * A linha existente vira a primeira condição; a segunda entra ao lado.
     */
    private function condicionar(string $chave, string $primeira, string $de, string $paraSegunda, string $segunda, mixed $agora): void
    {
        $existente = DB::table('servico_nomenclaturas')->where('chave', $chave)->where('condicao', '')->first();

        if ($existente === null) {
            return;
        }

        DB::table('servico_nomenclaturas')->where('id', $existente->id)->update(['condicao' => $primeira, 'updated_at' => $agora]);

        DB::table('servico_nomenclaturas')->insert([
            'de' => $existente->de ?? $de,
            'chave' => $chave,
            'condicao' => $segunda,
            'para' => $paraSegunda,
            'exibir' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }
};
