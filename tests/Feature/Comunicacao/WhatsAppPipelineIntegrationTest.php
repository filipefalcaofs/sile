<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Models\Activity;
use App\Models\Communication;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\PendenciaSolicitadaNotification;
use App\Services\Comunicacao\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Tests\TestCase;

/**
 * Teste de INTEGRAÇÃO da PIPELINE COMPLETA do WhatsApp (HU-095, anti-fachada) —
 * fecha o gap do caminho ON + provedor indisponível (pré-Fase 13).
 *
 * Diferente dos testes de unidade do canal/dispatcher (que usam Notification::fake
 * ou exercitam um elo isolado), aqui NÃO há Notification::fake: a notificação roda
 * pela pipeline REAL de ponta a ponta com os listeners AUTO-DESCOBERTOS ativos
 * (RegistrarEnvioComunicacao + WhatsAppChannel), com:
 *  - features.notificacao_whatsapp ON (parâmetro HU-014 real); e-mail e in-app ON;
 *  - mapa_canais incluindo o canal whatsapp;
 *  - binding DEFAULT UnavailableWhatsAppGateway (o provedor real é a Fase 13).
 *
 * Invariante provado: a linha communications do canal whatsapp termina BLOQUEADO
 * e AUDITADA, e JAMAIS é sobrescrita para "enviado" — nem mesmo quando o Laravel
 * dispara NotificationSent('whatsapp') após o canal degradar (o canal não relança
 * a exceção). Duas defesas garantem isso: o RegistrarEnvioComunicacao IGNORA o
 * canal whatsapp e a guarda do Communication::markAsSent protege estados terminais
 * honestos. E-mail e in-app, na MESMA notificação, terminam ENVIADO — prova de que
 * só o whatsapp degrada, sem contaminar os canais reais.
 */
class WhatsAppPipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function dispatcher(): NotificationDispatcher
    {
        return app(NotificationDispatcher::class);
    }

    /**
     * Liga o toggle features.notificacao_whatsapp pelo caminho REAL (parâmetro
     * HU-014): Parameter::saved invalida o cache da chave (efeito sem deploy).
     */
    private function ligarWhatsApp(): void
    {
        Parameter::query()->create([
            'key' => 'features.notificacao_whatsapp',
            'group' => 'features',
            'type' => 'boolean',
            'value' => '1',
            'default_value' => '0',
            'validation_rules' => ['required', 'boolean'],
            'description' => 'Toggle do canal de notificação por WhatsApp (HU-095).',
        ]);
    }

    /**
     * Mapa de canais do tipo pendencia_aberta incluindo o whatsapp (e-mail e
     * in-app seguem ON por config). É o cenário em que o admin habilitou o
     * WhatsApp antes do provedor real existir (Fase 13).
     */
    private function comWhatsAppNoMapa(): void
    {
        config()->set('sile.notificacoes.mapa_canais', [
            'pendencia_aberta' => ['email', 'in_app', 'whatsapp'],
        ]);
    }

    public function test_pipeline_real_com_whatsapp_on_e_indisponivel_termina_bloqueado_e_nunca_enviado(): void
    {
        $this->ligarWhatsApp();
        $this->comWhatsAppNoMapa();

        // Destinatário com telefone E.164 (o canal tem "para onde" transmitir) e um
        // processo real — a notificação de pendência é a Notification de PROCESSO real.
        $requerente = User::factory()->create(['phone' => '+5571999990000']);
        $request = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => $requerente->id]);

        // Pipeline REAL: o dispatcher cria as 3 linhas na_fila (email/in_app/whatsapp),
        // congela os canais e chama notify() — SEM Notification::fake (envio real,
        // fila sync). O binding default do WhatsAppGateway é o Unavailable.
        $this->dispatcher()->deliver($requerente, new PendenciaSolicitadaNotification(
            (string) $request->protocol_number,
            'Anexe o comprovante de uso do imóvel.',
            $request->id,
        ));

        $whatsapp = Communication::query()
            ->where('viability_request_id', $request->id)
            ->where('type', CommunicationType::PendenciaAberta)
            ->where('channel', CommunicationChannel::Whatsapp)
            ->firstOrFail();
        $email = Communication::query()
            ->where('viability_request_id', $request->id)
            ->where('type', CommunicationType::PendenciaAberta)
            ->where('channel', CommunicationChannel::Email)
            ->firstOrFail();
        $inApp = Communication::query()
            ->where('viability_request_id', $request->id)
            ->where('type', CommunicationType::PendenciaAberta)
            ->where('channel', CommunicationChannel::InApp)
            ->firstOrFail();

        // INVARIANTE: a linha whatsapp termina BLOQUEADO (provedor indisponível),
        // jamais "enviado" — mesmo após o NotificationSent('whatsapp') do envio real.
        $this->assertSame(
            CommunicationStatus::Bloqueado,
            $whatsapp->status,
            'ON + provedor indisponível: a linha whatsapp deve terminar BLOQUEADO pela pipeline real.'
        );

        // E-mail e in-app, na MESMA notificação, terminam ENVIADO (canais reais).
        $this->assertSame(CommunicationStatus::Enviado, $email->status, 'O e-mail real deve terminar ENVIADO.');
        $this->assertSame(CommunicationStatus::Enviado, $inApp->status, 'O in-app real deve terminar ENVIADO.');

        // Bloqueio auditado (RN-002) e NENHUM "enviado" fictício no canal whatsapp.
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'notificacoes')
                ->where('result', 'bloqueado')
                ->exists(),
            'O bloqueio do canal WhatsApp deve ser auditado.'
        );
        $this->assertSame(
            0,
            Communication::query()
                ->where('channel', CommunicationChannel::Whatsapp)
                ->where('status', CommunicationStatus::Enviado)
                ->count(),
            'Nenhuma comunicação de WhatsApp pode terminar "enviado" sem provedor real.'
        );

        // O requerente recebeu o in-app REAL (canal database nativo).
        $this->assertTrue($requerente->notifications()->exists());
    }

    public function test_notification_sent_tardio_do_whatsapp_nao_reabre_o_bloqueio(): void
    {
        $this->ligarWhatsApp();
        $this->comWhatsAppNoMapa();

        $requerente = User::factory()->create(['phone' => '+5571988887777']);
        $request = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => $requerente->id]);

        $notification = new PendenciaSolicitadaNotification(
            (string) $request->protocol_number,
            'Anexe a planta de situação.',
            $request->id,
        );

        $this->dispatcher()->deliver($requerente, $notification);

        $whatsapp = Communication::query()
            ->where('viability_request_id', $request->id)
            ->where('channel', CommunicationChannel::Whatsapp)
            ->firstOrFail();
        $this->assertSame(CommunicationStatus::Bloqueado, $whatsapp->status);

        // Defesa em profundidade: um NotificationSent('whatsapp') tardio (o canal
        // não relança, então o Laravel dispara o evento) passa pelos DOIS guardas —
        // o RegistrarEnvioComunicacao ignora whatsapp E o markAsSent protege o
        // estado terminal — sem nunca virar "enviado".
        event(new NotificationSent($requerente, $notification, 'whatsapp'));

        $this->assertSame(
            CommunicationStatus::Bloqueado,
            $whatsapp->refresh()->status,
            'Um NotificationSent("whatsapp") tardio não pode transformar bloqueado em enviado.'
        );
    }
}
