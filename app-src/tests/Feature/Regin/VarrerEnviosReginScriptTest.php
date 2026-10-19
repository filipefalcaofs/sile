<?php

namespace Tests\Feature\Regin;

use App\Models\ReginRecebimento;
use App\Models\User;
use App\Services\Tratamento\PlanilhaTratamentoImporter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

class VarrerEnviosReginScriptTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private function rodarScript(): array
    {
        $app = app();
        $saida = tempnam(sys_get_temp_dir(), 'varredura');
        $desde = null;

        ob_start();
        include base_path('scripts/varrer-envios-regin.php');
        ob_end_clean();

        $dados = json_decode((string) file_get_contents($saida), true);
        @unlink($saida);

        return $dados;
    }

    private function envio(string $protocolo, array $perguntas, array $estab = []): ReginRecebimento
    {
        $linha = fn (string $p, string $r): array => ['TIPO' => '2', 'CNPJ' => '05457349000170', 'COD_PERGUNTA' => '1', 'PER_PERGUNTA' => $p, 'PER_RESPOSTA' => $r];

        $rowset = [
            'RUC_GENERAL' => ['RGE_CGC_CPF' => '13927801000149', 'RGE_NOMB' => 'Empresa REGIN Teste'],
            'RUC_ESTAB' => $estab + ['RES_AREA' => '834', 'RES_DIRECCION' => 'Rua das Flores', 'RES_NUME' => '100', 'RES_URBANIZACION' => 'Centro', 'RES_ZONA_POSTAL' => '40000000'],
            'GROUPRUC_ACTV_ECON' => ['RUC_ACTV_ECON' => [['RAE_TAE_COD_ACTVD' => '3314710', 'RAE_CALIF_ACTV' => '1']]],
            'GROUPRUC_GEN_PROTOCOLO' => ['RUC_GEN_PROTOCOLO' => [['RGP_TGE_COD_TIP_TAB' => '5', 'RGP_VALOR' => '12345678']]],
        ];

        if ($perguntas !== []) {
            $rowset['GROUPPERGUNTAS'] = ['PERGUNTAS' => array_map(fn (array $p) => $linha($p[0], $p[1]), $perguntas)];
        }

        return ReginRecebimento::factory()->create(['protocolo' => $protocolo, 'corpo' => ['rowset' => $rowset]]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create();
        $versao = $this->seedTratamentoPlanilha();
        app(PlanilhaTratamentoImporter::class)->complementar($versao, database_path('data/regras-20-08-26/planilha-regras.xlsx'));
    }

    public function test_envio_sem_nenhuma_pergunta_aparece_com_as_perguntas_exigidas(): void
    {
        $this->envio('BAP0000000001', []);

        $dados = $this->rodarScript();

        $this->assertCount(1, $dados['perguntas']);
        $linha = $dados['perguntas'][0];
        $this->assertSame('Envio sem nenhuma pergunta', $linha['situacao']);
        $this->assertSame(0, $linha['perguntas_recebidas']);
        $this->assertNotEmpty($linha['faltantes']);
        $this->assertSame(0, $linha['faltantes'][0]['ocorrencias']);
        $this->assertSame('3314710', $linha['faltantes'][0]['cnae']);
        $this->assertContains('A atividade será desenvolvida no local?', array_column($linha['faltantes'], 'enunciado'));
    }

    public function test_envio_com_as_perguntas_respondidas_nao_entra_na_lista(): void
    {
        $enunciado = 'Identifique entre as opções a seguir os serviços ou as atividades que serão prestado(a)s/desenvolvido (a)s:';
        $this->envio('BAP0000000002', [
            [$enunciado, 'Seguir'],
            ['Manutenção e reparação executada por unidade especializada calandras industriais', 'SIM'],
            ['A atividade será desenvolvida no local?', 'Sim'],
        ]);

        $dados = $this->rodarScript();

        $this->assertSame([], $dados['perguntas']);
    }

    public function test_enunciado_presente_no_envio_mas_nao_casado_fica_marcado_para_revisao(): void
    {
        $this->envio('BAP0000000007', [['A atividade será desenvolvida no local?', 'talvez']]);

        $dados = $this->rodarScript();

        $faltante = collect($dados['perguntas'][0]['faltantes'])->firstWhere('numero', 11);
        $this->assertGreaterThan(0, $faltante['ocorrencias']);
    }

    public function test_area_zerada_e_endereco_vazio_entram_nos_envelopes_incompletos(): void
    {
        $this->envio('BAP0000000003', [], ['RES_AREA' => '0']);
        $this->envio('BAP0000000004', [], ['RES_DIRECCION' => '', 'RES_URBANIZACION' => '']);
        $this->envio('BAP0000000005', []);

        $dados = $this->rodarScript();

        $situacoes = collect($dados['envelopes'])->pluck('situacao', 'protocolo')->all();
        $this->assertSame('Área zerada', $situacoes['BAP0000000003']);
        $this->assertSame('Endereço vazio', $situacoes['BAP0000000004']);
        $this->assertArrayNotHasKey('BAP0000000005', $situacoes);
        $this->assertSame('12345678', collect($dados['envelopes'])->firstWhere('protocolo', 'BAP0000000003')['inscricao']);
    }

    public function test_recebimento_sem_rowset_e_contado_e_ignorado(): void
    {
        ReginRecebimento::factory()->create(['protocolo' => 'BAP0000000006', 'corpo' => ['formato' => 'processo']]);

        $dados = $this->rodarScript();

        $this->assertSame(1, $dados['sem_rowset']);
        $this->assertSame([], $dados['perguntas']);
    }
}
