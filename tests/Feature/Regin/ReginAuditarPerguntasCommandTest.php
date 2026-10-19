<?php

namespace Tests\Feature\Regin;

use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Tratamento\PlanilhaTratamentoImporter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

class ReginAuditarPerguntasCommandTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private function protocolar(string $protocolo, array $perguntas): ViabilityRequest
    {
        $linha = fn (string $pergunta, string $resposta): array => ['TIPO' => '2', 'CNPJ' => '05457349000170', 'COD_PERGUNTA' => '1', 'PER_PERGUNTA' => $pergunta, 'PER_RESPOSTA' => $resposta];

        $corpo = ['rowset' => [
            'RUC_GENERAL' => ['RGE_CGC_CPF' => '13927801000149', 'RGE_NOMB' => 'Empresa REGIN Teste'],
            'RUC_ESTAB' => ['RES_AREA' => '834', 'RES_DIRECCION' => 'Rua das Flores', 'RES_NUME' => '100', 'RES_URBANIZACION' => 'Centro', 'RES_ZONA_POSTAL' => '40000000'],
            'GROUPRUC_ACTV_ECON' => ['RUC_ACTV_ECON' => [['RAE_TAE_COD_ACTVD' => '3314710', 'RAE_CALIF_ACTV' => '1']]],
            'GROUPRUC_GEN_PROTOCOLO' => ['RUC_GEN_PROTOCOLO' => [['RGP_TGE_COD_TIP_TAB' => '5', 'RGP_VALOR' => '12345678']]],
            'GROUPPERGUNTAS' => ['PERGUNTAS' => array_map(fn (array $par): array => $linha($par[0], $par[1]), $perguntas)],
        ]];

        $recebimento = ReginRecebimento::factory()->create(['protocolo' => $protocolo, 'corpo' => $corpo]);

        return app(ReginProcessoProtocolador::class)->protocolar($recebimento);
    }

    private function prepararPlanilha(): void
    {
        User::factory()->create();
        $versao = $this->seedTratamentoPlanilha();
        app(PlanilhaTratamentoImporter::class)->complementar($versao, database_path('data/regras-20-08-26/planilha-regras.xlsx'));
    }

    public function test_processo_com_respostas_nao_e_apontado(): void
    {
        $this->prepararPlanilha();
        $this->protocolar('BAP2601852181', [
            ['Identifique entre as opções a seguir os serviços ou as atividades que serão prestado(a)s/desenvolvido (a)s:', 'Seguir'],
            ['Manutenção e reparação executada por unidade especializada calandras industriais', 'SIM'],
            ['A atividade será desenvolvida no local?', 'SIM'],
        ]);

        $this->artisan('regin:auditar-perguntas')
            ->expectsOutputToContain('1 processo(s) REGIN verificados; 0 com problema')
            ->assertSuccessful();
    }

    public function test_cnae_com_pergunta_sem_resposta_e_apontado_como_incompleto(): void
    {
        $this->prepararPlanilha();
        $this->protocolar('BAP2601852186', [
            ['Identifique entre as opções a seguir os serviços ou as atividades que serão prestado(a)s/desenvolvido (a)s:', 'Seguir'],
            ['Manutenção e reparação executada por unidade especializada calandras industriais', 'SIM'],
        ]);

        $this->artisan('regin:auditar-perguntas')
            ->expectsOutputToContain('BAP2601852186  respostas incompletas  3314710  sem resposta nas perguntas 11')
            ->assertFailed();
    }

    public function test_envelope_sem_perguntas_e_apontado(): void
    {
        $this->prepararPlanilha();
        $this->protocolar('BAP2601852182', []);

        $this->artisan('regin:auditar-perguntas')
            ->expectsOutputToContain('BAP2601852182  sem perguntas')
            ->assertFailed();
    }

    public function test_cnae_com_perguntas_na_planilha_e_sem_resposta_e_apontado(): void
    {
        $this->prepararPlanilha();
        $this->protocolar('BAP2601852183', [
            ['Pergunta que nenhum CNAE da planilha exibe', 'SIM'],
        ]);

        $this->artisan('regin:auditar-perguntas')
            ->expectsOutputToContain('BAP2601852183  sem respostas  3314710')
            ->assertFailed();
    }

    public function test_grava_csv_e_filtra_por_protocolo(): void
    {
        $this->prepararPlanilha();
        $this->protocolar('BAP2601852184', []);
        $this->protocolar('BAP2601852185', []);
        $arquivo = tempnam(sys_get_temp_dir(), 'aud').'.csv';

        $this->artisan("regin:auditar-perguntas --protocolo=BAP2601852185 --csv={$arquivo}")->assertFailed();

        $conteudo = (string) file_get_contents($arquivo);
        unlink($arquivo);
        $this->assertStringContainsString('BAP2601852185', $conteudo);
        $this->assertStringNotContainsString('BAP2601852184', $conteudo);
    }
}
