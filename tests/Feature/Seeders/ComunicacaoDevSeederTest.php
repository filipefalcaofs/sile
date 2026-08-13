<?php

namespace Tests\Feature\Seeders;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\PendenciaSolicitadaNotification;
use App\Notifications\RespostaPendenciaNotification;
use Database\Seeders\ComunicacaoDevSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ComunicacaoDevSeeder (fechamento do EP11): o ambiente de desenvolvimento ganha
 * comunicações e notificações REAIS — não linhas fabricadas. O seeder cria um
 * processo de exemplo DEDICADO (marcador próprio), leva-o a em_analise pelo
 * caminho legítimo (protocolo + transição + atribuição ao analista@sile.dev) e
 * executa o FLUXO REAL: PendenciaService::abrir (dispara o listener
 * NotificarPendencia → communications email/in_app + notificação in-app ao
 * requerente) e PendenciaService::responder (NotificarRespostaPendencia → avisa o
 * analista e reabre a análise). Dados fictícios, lógica de verdade.
 *
 * Asserções de PRESENÇA/estabilidade (não contagem global frágil): provam que o
 * seed gera as comunicações/notificações pelo fluxo real e é idempotente.
 */
class ComunicacaoDevSeederTest extends TestCase
{
    use RefreshDatabase;

    private function dedicada(): ?ViabilityRequest
    {
        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->first();

        if ($cidadao === null) {
            return null;
        }

        return ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', ComunicacaoDevSeeder::MARK)
            ->first();
    }

    public function test_seed_cria_processo_dedicado_em_analise_pelo_fluxo_real(): void
    {
        $this->seed();

        $request = $this->dedicada();

        $this->assertNotNull($request, 'Esperava o processo dedicado de comunicação (marcador estável).');
        // Após abrir + responder a pendência, a análise REABRE: em_analise é o
        // estado final navegável (o ciclo em_analise→em_pendencia→em_analise é real).
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->status);
        $this->assertNotNull($request->assigned_user_id, 'O processo dedicado deve estar atribuído ao analista (caminho legítimo).');
    }

    public function test_seed_gera_communications_reais_da_pendencia_aberta_e_respondida(): void
    {
        $this->seed();

        $request = $this->dedicada();
        $this->assertNotNull($request);

        // Abertura: o listener NotificarPendencia gerou as linhas multicanal do
        // ledger (mapa default pendencia_aberta = [email, in_app]) — lógica real.
        $this->assertTrue(
            Communication::query()
                ->where('viability_request_id', $request->id)
                ->where('type', CommunicationType::PendenciaAberta)
                ->where('channel', CommunicationChannel::Email)
                ->exists(),
            'Esperava a comunicação de e-mail da pendência aberta (fluxo real).'
        );
        $this->assertTrue(
            Communication::query()
                ->where('viability_request_id', $request->id)
                ->where('type', CommunicationType::PendenciaAberta)
                ->where('channel', CommunicationChannel::InApp)
                ->exists(),
            'Esperava a comunicação in-app da pendência aberta (fluxo real).'
        );

        // Resposta: NotificarRespostaPendencia avisou o analista (mapa default
        // pendencia_respondida = [in_app]).
        $this->assertTrue(
            Communication::query()
                ->where('viability_request_id', $request->id)
                ->where('type', CommunicationType::PendenciaRespondida)
                ->exists(),
            'Esperava a comunicação da pendência respondida (analista notificado).'
        );
    }

    public function test_seed_entrega_notificacoes_in_app_reais_ao_requerente_e_ao_analista(): void
    {
        $this->seed();

        $request = $this->dedicada();
        $this->assertNotNull($request);

        $requerente = User::query()->where('email', 'cidadao@sile.dev')->firstOrFail();
        $analista = User::query()->where('email', 'analista@sile.dev')->firstOrFail();

        // Canal database nativo (in-app): o requerente recebeu a notificação da
        // pendência aberta; é não lida (ninguém leu) — central navegável no dev.
        $this->assertTrue(
            $requerente->notifications()
                ->where('type', PendenciaSolicitadaNotification::class)
                ->exists(),
            'Esperava a notificação in-app de pendência aberta para o requerente.'
        );
        $this->assertGreaterThanOrEqual(1, $requerente->unreadNotifications()->count());

        // O analista responsável recebeu a notificação da resposta (retorno do EP11).
        $this->assertTrue(
            $analista->notifications()
                ->where('type', RespostaPendenciaNotification::class)
                ->exists(),
            'Esperava a notificação in-app de pendência respondida para o analista.'
        );
    }

    public function test_seed_e_idempotente_nao_duplica_o_exemplo_nem_as_comunicacoes(): void
    {
        $this->seed();

        $request = $this->dedicada();
        $this->assertNotNull($request);

        $comunicacoesAntes = Communication::query()->where('viability_request_id', $request->id)->count();
        $pendenciasAntes = AnalysisPendency::query()->where('viability_request_id', $request->id)->count();

        // Re-seed: o exemplo dedicado e suas comunicações são estáveis.
        $this->seed();

        $this->assertSame(
            1,
            ViabilityRequest::query()
                ->where('requester_user_id', $request->requester_user_id)
                ->where('address_reference', ComunicacaoDevSeeder::MARK)
                ->count(),
            'O processo dedicado não pode ser duplicado no re-seed.'
        );
        $this->assertSame(
            $comunicacoesAntes,
            Communication::query()->where('viability_request_id', $request->id)->count(),
            'As comunicações do exemplo dedicado não podem ser duplicadas no re-seed.'
        );
        $this->assertSame(
            $pendenciasAntes,
            AnalysisPendency::query()->where('viability_request_id', $request->id)->count(),
            'A pendência do exemplo dedicado não pode ser duplicada no re-seed.'
        );
        // A pendência ficou respondida (ciclo completo), não reaberta a cada seed.
        $this->assertTrue(
            AnalysisPendency::query()
                ->where('viability_request_id', $request->id)
                ->where('status', AnalysisPendencyStatus::Respondida)
                ->exists(),
        );
    }
}
