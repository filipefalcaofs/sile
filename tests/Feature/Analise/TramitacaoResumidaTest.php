<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Analise\TramitacaoProcessoService;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tramitação resumida: uma ação, uma linha. O espelho da situação de análise e o
 * registro de pendência/convite gravados no mesmo momento de uma mudança de
 * situação do processo ou de uma distribuição não aparecem; o passo automático
 * "Analisar" e o aviso de notificação sem destinatário nunca aparecem.
 */
class TramitacaoResumidaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ViabilityRequest $processo;

    private User $analista;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 12:26:00');
        $this->processo = ViabilityRequest::factory()->protocoled()->create();
        $this->analista = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function eixo(?AnalysisStatus $de, AnalysisStatus $para, string $motivo): void
    {
        $this->processo->analysisStatusTransitions()->create([
            'from_status' => $de,
            'to_status' => $para,
            'reason' => $motivo,
            'actor_user_id' => $this->analista->id,
        ]);
    }

    private function situacao(ViabilityRequestStatus $de, ViabilityRequestStatus $para, string $motivo): void
    {
        ViabilityRequestTransition::factory()->create([
            'viability_request_id' => $this->processo->id,
            'from_status' => $de,
            'to_status' => $para,
            'reason' => $motivo,
            'actor_user_id' => $this->analista->id,
        ]);
    }

    private function atividade(string $evento, string $descricao, string $log = 'analise', string $resultado = 'sucesso'): void
    {
        app(AuditService::class)->log($log, $evento, $descricao, ['viability_request_id' => $this->processo->id], $this->processo, $resultado);
    }

    /**
     * @return list<string>
     */
    private function titulos(): array
    {
        return array_column(app(TramitacaoProcessoService::class)->eventos($this->processo->fresh()), 'to_label');
    }

    public function test_distribuicao_mostra_so_a_atribuicao(): void
    {
        $this->atividade('distribuir', 'Processo distribuído a Suzana.');
        $this->eixo(AnalysisStatus::ParaDistribuir, AnalysisStatus::Encaminhado, 'Encaminhado para Suzana.');
        $this->eixo(AnalysisStatus::Encaminhado, AnalysisStatus::Analisar, 'Na caixa de entrada de Suzana.');

        $this->assertSame(['Distribuído para análise'], $this->titulos());
    }

    public function test_encaminhado_sem_atribuicao_no_mesmo_momento_continua(): void
    {
        $this->eixo(AnalysisStatus::ParaDistribuir, AnalysisStatus::Encaminhado, 'Encaminhado para Suzana.');

        $this->assertSame(['Análise: Encaminhado para'], $this->titulos());
    }

    public function test_analisar_nunca_aparece(): void
    {
        $this->eixo(null, AnalysisStatus::Analisar, 'Na caixa de entrada de Suzana.');

        $this->assertSame([], $this->titulos());
    }

    public function test_abrir_convite_fica_so_na_situacao_do_processo(): void
    {
        $this->situacao(ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::EmPendencia, 'Convite aberto pela análise técnica');
        $this->eixo(AnalysisStatus::EmAnalise, AnalysisStatus::EmConvite, 'Convite aberto pela análise técnica.');
        $this->atividade('pendencia-aberta', 'Pendência #13 aberta na solicitação #2328.');
        $this->atividade('pendencia-aberta', 'Sem destinatário para notificar a pendência #13.', 'notificacoes', 'sem-destinatario');

        $this->assertSame([], $this->titulos());
    }

    public function test_responder_e_cancelar_convite_ficam_so_na_situacao_do_processo(): void
    {
        $this->situacao(ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::EmAnalise, 'Convite respondido pelo requerente');
        $this->eixo(AnalysisStatus::EmConvite, AnalysisStatus::ConviteRespondido, 'Convite respondido pelo requerente.');
        $this->atividade('pendencia-respondida', 'Pendência #13 respondida.');

        Carbon::setTestNow('2026-10-20 15:00:00');
        $this->situacao(ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::EmAnalise, 'Convite cancelado pela análise técnica: x');
        $this->eixo(AnalysisStatus::EmConvite, AnalysisStatus::ConviteCancelado, 'Convite cancelado pela análise técnica.');
        $this->atividade('convite-cancelado', 'Convite #14 cancelado.');

        $this->assertSame([], $this->titulos());
    }

    public function test_pendencia_sem_mudanca_de_situacao_no_mesmo_momento_continua(): void
    {
        $this->atividade('pendencia-aberta', 'Pendência #13 aberta na solicitação #2328.');

        $this->assertSame(['Pendência aberta'], $this->titulos());
    }

    public function test_acao_propria_da_analise_continua(): void
    {
        $this->eixo(AnalysisStatus::Analisar, AnalysisStatus::EmAnalise, 'Analista iniciou o preenchimento da ficha de análise.');

        $this->assertSame(['Análise: Em análise'], $this->titulos());
    }

    public function test_atividade_minutos_depois_da_mudanca_de_situacao_continua(): void
    {
        $this->situacao(ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::EmPendencia, 'Convite aberto pela análise técnica');

        Carbon::setTestNow('2026-10-20 12:30:00');
        $this->atividade('pendencia-aberta', 'Pendência #14 aberta na solicitação #2328.');

        $this->assertSame(['Pendência aberta'], $this->titulos());
    }
}
