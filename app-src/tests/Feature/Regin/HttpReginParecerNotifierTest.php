<?php

namespace Tests\Feature\Regin;

use App\Enums\DecisionOutcome;
use App\Models\ReginRecebimento;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Regin\HttpReginParecerNotifier;
use App\Services\Regin\ReginHttpClient;
use App\Services\Regin\ReginIntegrationSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HttpReginParecerNotifierTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function clienteEspiao(): ReginHttpClient
    {
        return new class(app(ReginIntegrationSettings::class)) extends ReginHttpClient
        {
            /** @var list<array<string, mixed>> */
            public array $envios = [];

            public function enviarParecer(array $resposta): void
            {
                $this->envios[] = $resposta;
            }
        };
    }

    public function test_monta_envelope_deferido_com_status_2(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => '43747']);
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'outcome' => DecisionOutcome::Deferida,
        ]);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $this->assertCount(1, $espiao->envios);
        $envio = $espiao->envios[0];

        $this->assertSame('43747', $envio['protocolo']);
        $this->assertSame('WsProSol098', $envio['servico']);
        $this->assertSame(110, $envio['codFuncao']);
        $this->assertSame('13927801000149', $envio['cnpjDestino']);
        $this->assertSame('13927801000149', $envio['cnpjOrigem']);

        $dados = $envio['dadosProcesso'];
        $this->assertSame('43747', $dados['PROTOCOLO']);
        $this->assertSame(1, $dados['FINALIZA_PROCESSO']);
        $this->assertSame(1, $dados['PROCESSO_INTERESSE_INSTITUICAO']);
        $this->assertSame(0, $dados['GERA_DOCUMENTO_PROCESSO']);
        $this->assertSame(70, $dados['ANALISES']['AREA'][0]['CODIGO_AREA']);
        $this->assertSame(2, $dados['ANALISES']['AREA'][0]['STATUS_ANALISE']);
        $this->assertSame($decision->decided_at->format('Ymd'), $dados['ANALISES']['AREA'][0]['DATA_ANALISE']);
        $this->assertNotSame('', $dados['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE']);
    }

    public function test_cnpj_empresa_vem_do_envelope_recebido_da_juceb(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => 'BAP2601000001']);
        $decision = ViabilityDecision::factory()->create(['viability_request_id' => $request->id]);
        ReginRecebimento::factory()->create(['protocolo' => 'BAP2601000001', 'cnpj_empresa' => '98945203320']);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $this->assertSame('98945203320', $espiao->envios[0]['cnpjEmpresa']);
    }

    public function test_monta_envelope_indeferido_com_status_4(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => '53514']);
        $decision = ViabilityDecision::factory()->indeferida()->create(['viability_request_id' => $request->id]);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $this->assertSame(4, $espiao->envios[0]['dadosProcesso']['ANALISES']['AREA'][0]['STATUS_ANALISE']);
    }

    public function test_justificativa_sem_motivo_nao_leva_fundamentacao_nem_condicionantes(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => 'BAP2601875586']);
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'outcome' => DecisionOutcome::Deferida,
            'reason' => null,
            'fundamentacao' => [
                'Atividade sem enquadramento na planilha vigente — segue para análise técnica',
                'Decreto Municipal nº 32.636/2020, com os Anexos na redação do Decreto Municipal nº 41.758/2026',
                'Lei nº 9.148/2016 (LOUOS) — nR2-12',
                'GeoServer SEDUR indisponível ou resposta incompleta (HTTP 20).',
                'Não foi possível verificar a restrição territorial "Loteamento Vela Branca (TAC vigente)" (camada geográfica indisponível)',
                'Desde que seja escritório da empresa',
                'Desde que a área utilizada não ultrapasse 1.250m² (mil duzentos e cinquenta metros quadrados).',
            ],
        ]);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $justificativa = $espiao->envios[0]['dadosProcesso']['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE'];

        $this->assertSame('Viabilidade deferida pelo fluxo expresso.', $justificativa);
    }

    public function test_justificativa_so_com_notas_internas_cai_no_texto_padrao(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => 'BAP2601875587']);
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'outcome' => DecisionOutcome::Deferida,
            'reason' => null,
            'fundamentacao' => ['GeoServer SEDUR indisponível ou resposta incompleta (HTTP 20).'],
        ]);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $this->assertSame(
            'Viabilidade deferida pelo fluxo expresso.',
            $espiao->envios[0]['dadosProcesso']['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE'],
        );
    }

    public function test_indeferimento_envia_so_o_motivo_da_decisao(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => '00990026']);
        $decision = ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $request->id,
            'reason' => 'A solicitação está localizada no logradouro 212 – Avenida Lafayette Coutinho.',
            'fundamentacao' => ['art. 172-A da Lei Municipal nº 9.148/2016'],
        ]);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $justificativa = $espiao->envios[0]['dadosProcesso']['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE'];

        $this->assertSame('A solicitação está localizada no logradouro 212 – Avenida Lafayette Coutinho.', $justificativa);
    }

    public function test_motivo_com_condicionantes_na_fundamentacao_vai_sem_elas(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => '00990027']);
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'outcome' => DecisionOutcome::Deferida,
            'reason' => 'Deferida conforme análise técnica.',
            'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — nR2-12', 'Desde que seja escritório da empresa'],
        ]);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $this->assertSame(
            'Deferida conforme análise técnica.',
            $espiao->envios[0]['dadosProcesso']['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE'],
        );
    }

    public function test_decisao_da_analise_tecnica_sem_motivo_nao_e_atribuida_ao_motor(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => '00990028']);
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'outcome' => DecisionOutcome::Deferida,
            'flow' => 'analise_tecnica',
            'reason' => null,
            'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — nR2-12'],
        ]);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $this->assertSame(
            'Viabilidade deferida após análise técnica.',
            $espiao->envios[0]['dadosProcesso']['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE'],
        );
    }

    public function test_justificativa_respeita_o_limite_do_campo(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => '1']);
        $decision = ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $request->id,
            'reason' => str_repeat('a', 12000),
        ]);

        $espiao = $this->clienteEspiao();
        $this->app->instance(ReginHttpClient::class, $espiao);

        $this->app->make(HttpReginParecerNotifier::class)->notifyParecer($request, $decision);

        $this->assertLessThanOrEqual(
            10000,
            mb_strlen($espiao->envios[0]['dadosProcesso']['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE']),
        );
    }
}
