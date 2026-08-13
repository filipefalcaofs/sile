<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Events\PendenciaSolicitada;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\PendenciaSolicitadaNotification;
use App\Services\Analise\PendenciaInvalidaException;
use App\Services\Analise\PendenciaService;
use App\Services\Expresso\BusinessDeadlineCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ciclo de pendência interno (HU-083/084 + HU-090): o analista abre uma
 * pendência (em_analise→em_pendencia, grava analysis_pendencies com prazo
 * parametrizado) e dispara o evento gancho PendenciaSolicitada — o listener
 * auto-descoberto NotificarPendencia notifica o requerente de forma MULTICANAL
 * (anti-duplicação: o serviço não notifica direto). O requerente responde
 * (em_pendencia→em_analise, reabre a análise). Tudo auditado (RN-002).
 * Anti-fachada: o convite via Simplifica/Regin fica bloqueado → Fase 13; o que
 * existe aqui executa de verdade.
 */
class PendenciaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PendenciaService
    {
        return app(PendenciaService::class);
    }

    /**
     * Processo REAL em análise técnica (protocolado + transicionado), cujo
     * requerente é controlado para os testes de escopo/notificação.
     */
    private function emAnalise(?User $requester = null): ViabilityRequest
    {
        $requester ??= User::factory()->create();

        $request = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $requester->id,
        ]);

        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        return $request->refresh();
    }

    public function test_abrir_cria_pendencia_e_transiciona_para_em_pendencia(): void
    {
        Notification::fake();

        $analista = User::factory()->create();
        $request = $this->emAnalise();

        $pendency = $this->actingAs($analista)
            ->service()
            ->abrir($request, $analista, 'Envie o IPTU atualizado do imóvel.');

        // A pendência (convite) nasce ABERTA, com prazo de 48h ÚTEIS (relatório
        // SEDUR 2026-07-09) e o analista como solicitante.
        $this->assertSame(AnalysisPendencyStatus::Aberta, $pendency->status);
        $this->assertSame('Envie o IPTU atualizado do imóvel.', $pendency->description);
        $this->assertSame($analista->id, $pendency->requested_by_user_id);
        $this->assertNotNull($pendency->due_at);
        $this->assertEqualsWithDelta(
            app(BusinessDeadlineCalculator::class)->businessDueAt(now(), 48)->timestamp,
            $pendency->due_at->timestamp,
            5,
            'O prazo do convite deve seguir analise.convite.prazo_resposta_horas_uteis (48h úteis).',
        );

        // O processo entra em em_pendencia de verdade (transição real + timeline).
        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::EmPendencia, $request->status);

        $transition = $request->transitions()->first();
        $this->assertNotNull($transition);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $transition->from_status);
        $this->assertSame(ViabilityRequestStatus::EmPendencia, $transition->to_status);

        // RN-002: a abertura é auditada de forma síncrona (analise/pendencia-aberta).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'pendencia-aberta',
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'causer_id' => $analista->id,
        ]);
    }

    public function test_abrir_notifica_o_requerente_multicanal_pelo_listener(): void
    {
        Notification::fake();

        $requester = User::factory()->create();
        $analista = User::factory()->create();
        $request = $this->emAnalise($requester);

        $this->service()->abrir($request, $analista, 'Anexe o contrato de locação.');

        // Anti-duplicação: o aviso vem do listener auto-descoberto (NotificarPendencia)
        // — EXATAMENTE 1×. O serviço não envia mais e-mail direto.
        Notification::assertSentToTimes($requester, PendenciaSolicitadaNotification::class, 1);

        // Multicanal pelo dispatcher: o ledger communications nasce na_fila por
        // canal (mapa default pendencia_aberta = [email, in_app]; HU-096).
        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $request->id,
            'recipient_user_id' => $requester->id,
            'type' => CommunicationType::PendenciaAberta->value,
            'channel' => CommunicationChannel::Email->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $request->id,
            'recipient_user_id' => $requester->id,
            'type' => CommunicationType::PendenciaAberta->value,
            'channel' => CommunicationChannel::InApp->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);

        // O e-mail direto (EmailLog) da Fase 10 foi REMOVIDO — sem dupla verdade.
        $this->assertDatabaseMissing('email_logs', [
            'notification_class' => PendenciaSolicitadaNotification::class,
        ]);
    }

    public function test_abrir_dispara_o_evento_gancho_pendencia_solicitada(): void
    {
        Notification::fake();
        Event::fake([PendenciaSolicitada::class]);

        $analista = User::factory()->create();
        $request = $this->emAnalise();

        $pendency = $this->service()->abrir($request, $analista, 'Complemente a descrição da atividade.');

        // O evento é o GANCHO honesto para a comunicação plena do EP11 (multicanal):
        // disparado de verdade, carregando o processo e a pendência.
        Event::assertDispatched(
            PendenciaSolicitada::class,
            fn (PendenciaSolicitada $event): bool => $event->request->is($request)
                && $event->pendency->is($pendency)
                && $event->pendency->status === AnalysisPendencyStatus::Aberta,
        );
    }

    public function test_abrir_fora_de_em_analise_e_bloqueado(): void
    {
        Notification::fake();

        $analista = User::factory()->create();
        // Protocolada (não em_analise): não há pendência a abrir.
        $request = ViabilityRequest::factory()->protocoled()->create();

        try {
            $this->service()->abrir($request, $analista, 'Tentativa fora de em_analise.');
            $this->fail('Esperava PendenciaInvalidaException ao abrir pendência fora de em_analise.');
        } catch (PendenciaInvalidaException) {
            // Esperado.
        }

        $this->assertSame(ViabilityRequestStatus::Protocolada, $request->refresh()->status);
        $this->assertSame(0, AnalysisPendency::query()->count());
        $this->assertSame(0, $request->transitions()->count());
        Notification::assertNothingSent();
    }

    public function test_responder_grava_resposta_e_reabre_a_analise(): void
    {
        $requester = User::factory()->create();
        $request = $this->emAnalise($requester);
        $request->forceFill(['status' => ViabilityRequestStatus::EmPendencia])->save();

        $pendency = AnalysisPendency::factory()->create([
            'viability_request_id' => $request->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        $this->actingAs($requester)
            ->service()
            ->responder($pendency, 'Segue o IPTU atualizado em anexo no portal.');

        // A pendência fica respondida com a resposta e a data gravadas.
        $pendency->refresh();
        $this->assertSame(AnalysisPendencyStatus::Respondida, $pendency->status);
        $this->assertSame('Segue o IPTU atualizado em anexo no portal.', $pendency->response);
        $this->assertNotNull($pendency->responded_at);

        // O processo VOLTA para em_analise (reabre a análise) — transição real.
        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->status);

        $transition = $request->transitions()->first();
        $this->assertNotNull($transition);
        $this->assertSame(ViabilityRequestStatus::EmPendencia, $transition->from_status);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $transition->to_status);

        // RN-002: a resposta é auditada (analise/pendencia-respondida).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'pendencia-respondida',
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
            'causer_id' => $requester->id,
        ]);
    }

    public function test_responder_pendencia_ja_respondida_e_bloqueado(): void
    {
        $requester = User::factory()->create();
        $request = $this->emAnalise($requester);

        // Pendência já respondida e processo em_analise (não em_pendencia).
        $pendency = AnalysisPendency::factory()->respondida()->create([
            'viability_request_id' => $request->id,
        ]);

        try {
            $this->service()->responder($pendency, 'Resposta duplicada.');
            $this->fail('Esperava PendenciaInvalidaException ao responder pendência já respondida.');
        } catch (PendenciaInvalidaException) {
            // Esperado.
        }

        // Nada muda: o processo permanece em_analise e a pendência respondida.
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->refresh()->status);
        $this->assertSame(AnalysisPendencyStatus::Respondida, $pendency->refresh()->status);
        $this->assertSame(0, $request->transitions()->count());
    }

    public function test_notification_e_email_simples_sem_anexo_com_link_do_portal(): void
    {
        // O e-mail é simples e HONESTO: informa a pendência e leva ao portal SILE.
        // NÃO anexa documento (o canal pleno/multicanal é EP11).
        $mail = (new PendenciaSolicitadaNotification(
            'VIA-2026-000777',
            'Envie o IPTU atualizado do imóvel.',
            123,
        ))->toMail(User::factory()->make());

        $this->assertStringContainsString('VIA-2026-000777', $mail->subject);

        $linhas = collect($mail->introLines)->merge($mail->outroLines);
        $this->assertTrue(
            $linhas->contains(fn (string $linha) => str_contains($linha, 'VIA-2026-000777')),
            'O corpo do e-mail deve informar o número de protocolo.',
        );
        $this->assertTrue(
            $linhas->contains(fn (string $linha) => str_contains($linha, 'Envie o IPTU atualizado do imóvel.')),
            'O corpo do e-mail deve informar a pendência.',
        );

        $this->assertSame([], $mail->attachments);
        $this->assertSame([], $mail->rawAttachments);
    }

    public function test_notification_e_enfileiravel_e_usa_email(): void
    {
        $notification = new PendenciaSolicitadaNotification('VIA-2026-000001', 'Pendência.', 1);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(['mail'], $notification->via(User::factory()->make()));
    }
}
