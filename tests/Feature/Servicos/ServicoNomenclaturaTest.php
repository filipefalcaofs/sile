<?php

namespace Tests\Feature\Servicos;

use App\Models\ServicoNomenclatura;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * De x Para dos nomes dos serviços (eventos da JUCEB) exibidos no Viabiliza,
 * pedido pela SEDUR em "Alteração de nomenclatura - Serviços".
 */
class ServicoNomenclaturaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_chave_ignora_caixa_acento_e_espacos(): void
    {
        $this->assertSame('inscricao de primeiro estabelecimento', ServicoNomenclatura::chaveDe(' Inscrição  de PRIMEIRO estabelecimento '));
        $this->assertSame('inscricao de primeiro estabelecimento', ServicoNomenclatura::chaveDe('Inscricao de primeiro estabelecimento'));
    }

    public function test_garantir_cria_linha_com_de_igual_a_para_e_exibir_desligado(): void
    {
        $linha = ServicoNomenclatura::garantir('Baixa de inscrição');

        $this->assertSame('Baixa de inscrição', $linha->de);
        $this->assertSame('Baixa de inscrição', $linha->para);
        $this->assertSame('baixa de inscricao', $linha->chave);
        $this->assertFalse($linha->exibir);

        ServicoNomenclatura::garantir('BAIXA DE INSCRIÇÃO');

        $this->assertSame(1, ServicoNomenclatura::query()->where('chave', 'baixa de inscricao')->count());
    }

    public function test_garantir_devolve_a_linha_criada_por_outro_recebimento_ao_mesmo_tempo(): void
    {
        $inseriu = false;
        DB::listen(function ($consulta) use (&$inseriu): void {
            if ($inseriu || ! str_starts_with($consulta->sql, 'select') || ! str_contains($consulta->sql, 'servico_nomenclaturas')) {
                return;
            }

            $inseriu = true;
            DB::table('servico_nomenclaturas')->insert([
                'de' => 'Baixa de inscrição',
                'chave' => 'baixa de inscricao',
                'para' => 'Encerramento',
                'exibir' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $linha = ServicoNomenclatura::garantir('Baixa de inscrição');

        $this->assertSame('Encerramento', $linha->para);
        $this->assertSame(1, ServicoNomenclatura::query()->where('chave', 'baixa de inscricao')->count());
    }

    public function test_garantir_nao_altera_linha_existente(): void
    {
        ServicoNomenclatura::query()->where('chave', ServicoNomenclatura::chaveDe('Inscrição de primeiro estabelecimento'))
            ->update(['para' => 'Abertura']);

        $linha = ServicoNomenclatura::garantir('Inscrição de primeiro estabelecimento');

        $this->assertSame('Abertura', $linha->para);
        $this->assertTrue($linha->exibir);
    }

    public function test_carga_inicial_traz_as_linhas_do_pdf(): void
    {
        $esperado = [
            'Inscrição de primeiro estabelecimento' => 'Viabilidade de Localização',
            'Inscrição dos demais estabelecimentos' => 'Viabilidade de Localização',
            'Alteração de atividades econômicas (principal e secundárias)' => 'Revisão de Atividade - Inclusão',
            'Alteração de endereço dentro do mesmo município' => 'Revisão de Endereço',
            'Alteração de endereço entre estados' => 'Revisão de Endereço',
            'Alteração de endereço entre municípios dentro do mesmo estado' => 'Revisão de Endereço',
        ];

        foreach ($esperado as $de => $para) {
            $this->assertDatabaseHas('servico_nomenclaturas', ['de' => $de, 'para' => $para, 'exibir' => true]);
        }
    }
}
