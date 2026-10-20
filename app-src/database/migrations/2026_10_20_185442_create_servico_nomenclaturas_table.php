<?php

use App\Models\ServicoNomenclatura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De x Para dos nomes dos serviços (eventos da JUCEB) exibidos no Viabiliza.
 * Carga inicial com o documento "Alteração de nomenclatura - Serviços" da
 * SEDUR; os demais eventos já recebidos entram com De = Para e ocultos.
 */
return new class extends Migration
{
    private const array CARGA_SEDUR = [
        'Inscrição de primeiro estabelecimento' => 'Viabilidade de Localização',
        'Inscrição dos demais estabelecimentos' => 'Viabilidade de Localização',
        'Alteração de atividades econômicas (principal e secundárias)' => 'Revisão de Atividade - Inclusão',
        'Alteração de endereço dentro do mesmo município' => 'Revisão de Endereço',
        'Alteração de endereço entre estados' => 'Revisão de Endereço',
        'Alteração de endereço entre municípios dentro do mesmo estado' => 'Revisão de Endereço',
    ];

    public function up(): void
    {
        Schema::create('servico_nomenclaturas', function (Blueprint $table): void {
            $table->id();
            $table->string('de');
            $table->string('chave')->unique();
            $table->string('para');
            $table->boolean('exibir')->default(false);
            $table->timestamps();
        });

        $agora = now();
        $chaves = [];

        foreach (self::CARGA_SEDUR as $de => $para) {
            $chaves[ServicoNomenclatura::chaveDe($de)] = ['de' => $de, 'para' => $para, 'exibir' => true];
        }

        $recebidos = DB::table('viability_request_eventos')->distinct()->orderBy('nome')->pluck('nome');

        foreach ($recebidos as $nome) {
            $chave = ServicoNomenclatura::chaveDe((string) $nome);

            if ($chave !== '' && ! isset($chaves[$chave])) {
                $chaves[$chave] = ['de' => $nome, 'para' => $nome, 'exibir' => false];
            }
        }

        foreach ($chaves as $chave => $linha) {
            DB::table('servico_nomenclaturas')->insert($linha + ['chave' => $chave, 'created_at' => $agora, 'updated_at' => $agora]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('servico_nomenclaturas');
    }
};
