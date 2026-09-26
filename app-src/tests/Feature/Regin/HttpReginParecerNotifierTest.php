<?php

namespace Tests\Feature\Regin;

use App\Enums\DecisionOutcome;
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
        $this->assertSame(2, $dados['ANALISES']['AREA'][0]['STATUS_ANALISE']);
        $this->assertSame($decision->decided_at->format('Ymd'), $dados['ANALISES']['AREA'][0]['DATA_ANALISE']);
        $this->assertNotSame('', $dados['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE']);
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

    public function test_indeferimento_envia_a_justificativa_seguida_da_fundamentacao(): void
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

        $this->assertStringStartsWith('A solicitação está localizada no logradouro 212', $justificativa);
        $this->assertStringContainsString('Fundamentação legal: art. 172-A da Lei Municipal nº 9.148/2016', $justificativa);
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
