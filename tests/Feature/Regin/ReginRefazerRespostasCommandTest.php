<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestStatus;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Tratamento\PlanilhaTratamentoImporter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

class ReginRefazerRespostasCommandTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private function processoComSnapshotDefasado(): ViabilityRequest
    {
        User::factory()->create();
        $versao = $this->seedTratamentoPlanilha();
        app(PlanilhaTratamentoImporter::class)->complementar($versao, database_path('data/regras-20-08-26/planilha-regras.xlsx'));

        $linha = fn (string $pergunta, string $resposta): array => ['TIPO' => '2', 'CNPJ' => '05457349000170', 'COD_PERGUNTA' => '1', 'PER_PERGUNTA' => $pergunta, 'PER_RESPOSTA' => $resposta];
        $enunciado = 'Identifique entre as opções a seguir os serviços ou as atividades que serão prestado(a)s/desenvolvido (a)s:';

        $corpo = ['rowset' => [
            'RUC_GENERAL' => ['RGE_CGC_CPF' => '13927801000149', 'RGE_NOMB' => 'Empresa REGIN Teste'],
            'RUC_ESTAB' => ['RES_AREA' => '834', 'RES_DIRECCION' => 'Rua das Flores', 'RES_NUME' => '100', 'RES_URBANIZACION' => 'Centro', 'RES_ZONA_POSTAL' => '40000000'],
            'GROUPRUC_ACTV_ECON' => ['RUC_ACTV_ECON' => [['RAE_TAE_COD_ACTVD' => '3314710', 'RAE_CALIF_ACTV' => '1']]],
            'GROUPRUC_GEN_PROTOCOLO' => ['RUC_GEN_PROTOCOLO' => [['RGP_TGE_COD_TIP_TAB' => '5', 'RGP_VALOR' => '12345678']]],
            'GROUPPERGUNTAS' => ['PERGUNTAS' => [
                $linha($enunciado, 'Seguir'),
                $linha('Manutenção e reparação executada por unidade especializada calandras industriais', 'SIM'),
            ]],
        ]];

        $recebimento = ReginRecebimento::factory()->create(['protocolo' => 'BAP2601852181', 'corpo' => $corpo]);
        $processo = app(ReginProcessoProtocolador::class)->protocolar($recebimento);

        $processo->forceFill(['simulation_snapshot' => ['respostas_tratamento_por_cnae' => [], 'perguntas_regin' => []]])->save();

        return $processo->refresh();
    }

    public function test_dry_run_mostra_a_mudanca_e_nao_grava_nem_despacha(): void
    {
        $processo = $this->processoComSnapshotDefasado();
        Queue::fake();

        $this->artisan('regin:refazer-respostas --dry-run')
            ->expectsOutputToContain('BAP2601852181')
            ->assertSuccessful();

        $this->assertSame([], $processo->refresh()->simulation_snapshot['respostas_tratamento_por_cnae']);
        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
    }

    public function test_regrava_o_snapshot_e_reenfileira_a_decisao(): void
    {
        $processo = $this->processoComSnapshotDefasado();
        Queue::fake();

        $this->artisan('regin:refazer-respostas')->assertSuccessful();

        $respostas = $processo->refresh()->simulation_snapshot['respostas_tratamento_por_cnae']['3314710'] ?? [];
        $this->assertSame(4, $respostas[1] ?? null);
        Queue::assertPushed(DecidirFluxoExpressoJob::class, fn (DecidirFluxoExpressoJob $job): bool => $job->viabilityRequestId === $processo->id && $job->reavaliacao);
    }

    public function test_processo_ja_assumido_por_analista_nao_e_tocado(): void
    {
        $processo = $this->processoComSnapshotDefasado();
        Queue::fake();
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'assigned_user_id' => User::factory()->create()->id,
        ])->save();

        $this->artisan('regin:refazer-respostas')
            ->expectsOutputToContain('ignorado')
            ->assertSuccessful();

        $this->assertSame([], $processo->refresh()->simulation_snapshot['respostas_tratamento_por_cnae']);
        Queue::assertNotPushed(DecidirFluxoExpressoJob::class);
    }
}
