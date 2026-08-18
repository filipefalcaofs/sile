<?php

namespace Tests\Feature\Expresso;

use App\Events\SolicitacaoProtocolada;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Gatilho do fluxo expresso (HU-073/074/075): o listener AUTO-DESCOBERTO
 * AvaliarFluxoExpresso pendura no PRIMEIRO evento de domínio (SolicitacaoProtocolada,
 * Fase 8) e apenas DESPACHA o DecidirFluxoExpressoJob — não decide inline, para
 * manter o protocolo rápido e ganhar a resiliência da fila.
 *
 * CRÍTICO (lição da Fase 8 — RN-002): o listener é registrado SÓ por
 * auto-descoberta (type-hint do evento no handle), NUNCA via Event::listen, que
 * duplicaria a execução (e a auditoria 2×). A não-duplicação é travada por
 * CONTAGEM: exatamente UM job de decisão por protocolo.
 */
class AvaliarFluxoExpressoListenerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Protocola DE VERDADE um rascunho completo pelo serviço real — o caminho que
     * dispara o SolicitacaoProtocolada após o commit (igual ao fluxo do cidadão).
     */
    private function protocolar(): ViabilityRequest
    {
        $user = User::factory()->create();

        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);

        $cnae = Cnae::factory()->create();
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        return app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $user);
    }

    public function test_protocolo_despacha_exatamente_um_job_de_decisao_com_o_id(): void
    {
        // HU-073/074/075: protocolar uma solicitação aciona a decisão. O listener
        // auto-descoberto despacha 1 (e só 1) DecidirFluxoExpressoJob carregando o
        // id da solicitação — a CONTAGEM trava a não-duplicação (auto-descoberta vs
        // Event::listen).
        Bus::fake([DecidirFluxoExpressoJob::class]);

        $solicitacao = $this->protocolar();

        Bus::assertDispatchedTimes(DecidirFluxoExpressoJob::class, 1);
        Bus::assertDispatched(
            DecidirFluxoExpressoJob::class,
            fn (DecidirFluxoExpressoJob $job): bool => $job->viabilityRequestId === $solicitacao->id,
        );
    }

    public function test_disparo_direto_do_evento_despacha_um_unico_job(): void
    {
        // O gatilho é o EVENTO (não o serviço de protocolo): disparar
        // SolicitacaoProtocolada — por qualquer origem — aciona exatamente 1 job.
        // Reforça que o registro é único (auto-descoberta) e que o listener só
        // despacha (não decide).
        Bus::fake([DecidirFluxoExpressoJob::class]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create();

        SolicitacaoProtocolada::dispatch($solicitacao);

        Bus::assertDispatchedTimes(DecidirFluxoExpressoJob::class, 1);
    }

    public function test_job_e_despachado_na_fila_parametrizada(): void
    {
        // Parametrização técnica (config/sile.php): a fila do job é configurável
        // sem deploy. Configurada uma fila dedicada, o listener despacha nela.
        config(['sile.expresso.fila' => 'expresso-decisao']);
        Bus::fake([DecidirFluxoExpressoJob::class]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create();

        SolicitacaoProtocolada::dispatch($solicitacao);

        Bus::assertDispatched(
            DecidirFluxoExpressoJob::class,
            fn (DecidirFluxoExpressoJob $job): bool => $job->queue === 'expresso-decisao',
        );
    }
}
