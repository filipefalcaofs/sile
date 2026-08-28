<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\SefazNotificationEvent;
use App\Enums\SefazNotificationStatus;
use App\Models\Company;
use App\Models\SefazNotification;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\EscritorioVirtual\DesvincularInscricaoService;
use App\Services\Sefaz\SefazUnavailableException;
use App\Services\Sefaz\SefazViabilidadeGateway;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * A comunicação devida à SEFAZ na desvinculação vira registro consultável e
 * reprocessável (RN-EV-09/EV-10), não texto solto dentro da auditoria
 * (`Alteração de Endereço` §4.3.2/§4.3.3).
 */
class ComunicacaoSefazTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function lockComSede(): VirtualOfficeInscriptionLock
    {
        $company = Company::factory()->create(['cnpj' => '12345678000199']);
        $sede = ViabilityRequest::factory()->create([
            'property_registration' => '123.456.789',
            'company_id' => $company->id,
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
        ]);

        return VirtualOfficeInscriptionLock::create([
            'property_registration' => '123.456.789',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);
    }

    /**
     * A desvinculacao registra a comunicacao devida a SEFAZ como registro
     * consultavel, nao como texto dentro da auditoria (RN-EV-09/EV-10).
     */
    public function test_desvinculacao_registra_a_comunicacao_devida(): void
    {
        NotificationFacade::fake();

        $lock = $this->lockComSede();

        $resultado = app(DesvincularInscricaoService::class)
            ->desvincular($lock, 'Sede mudou de endereço.', User::factory()->create());

        $this->assertNotNull($resultado['sefaz_notification_id']);

        $notification = SefazNotification::find($resultado['sefaz_notification_id']);

        $this->assertNotNull($notification);
        $this->assertSame(SefazNotificationEvent::SedeEncerrada, $notification->event);
        $this->assertSame('12345678000199', $notification->cnpj);
        $this->assertSame('123.456.789', $notification->property_registration_anterior);
        // A auditoria nao guarda mais o texto solto: so a referencia ao registro.
        $this->assertDatabaseMissing('activity_log', ['properties->sefaz' => 'pendente (integração bloqueada — Fase 13)']);
    }

    /**
     * Falha na comunicacao NAO desfaz o deferimento nem a desvinculacao
     * (Alteracao de Endereco §4.3.3). O registro guarda o erro e continua
     * disponivel para reprocessamento.
     */
    public function test_falha_na_comunicacao_nao_desfaz_a_desvinculacao(): void
    {
        NotificationFacade::fake();

        $lock = $this->lockComSede();

        // Binding default = UnavailableSefazViabilidadeGateway (Fase 13):
        // representa o estado NORMAL deste ambiente hoje, sem gateway forjado.
        $resultado = app(DesvincularInscricaoService::class)
            ->desvincular($lock, 'Sede mudou de endereço.', User::factory()->create());

        $lock->refresh();
        $this->assertFalse($lock->active, 'a desvinculacao valeu mesmo com a SEFAZ indisponivel');

        NotificationFacade::assertSentTimes(\App\Notifications\AbrigadoDesvinculadoNotification::class, 0);

        $notification = SefazNotification::find($resultado['sefaz_notification_id']);

        $this->assertSame(SefazNotificationStatus::Falha, $notification->status);
        $this->assertSame(1, $notification->tentativas);
        $this->assertNotNull($notification->erro);
    }

    /**
     * O registro em falha e reprocessavel e converge quando a SEFAZ volta.
     */
    public function test_comunicacao_em_falha_pode_ser_reprocessada(): void
    {
        NotificationFacade::fake();

        $lock = $this->lockComSede();

        $resultado = app(DesvincularInscricaoService::class)
            ->desvincular($lock, 'Sede mudou de endereço.', User::factory()->create());

        $notification = SefazNotification::find($resultado['sefaz_notification_id']);
        $this->assertSame(SefazNotificationStatus::Falha, $notification->status);

        // A SEFAZ volta: gateway forjado que aceita o envio.
        $this->app->bind(SefazViabilidadeGateway::class, fn () => new class implements SefazViabilidadeGateway
        {
            public function sendViabilidade(ViabilityRequest $request, \App\Models\ViabilityDecision $decision): void {}

            public function sendEventoEscritorioVirtual(SefazNotification $notification): void {}
        });

        app(DesvincularInscricaoService::class)->reprocessarComunicacaoSefaz($notification);

        $notification->refresh();
        $this->assertSame(SefazNotificationStatus::Enviada, $notification->status);
        $this->assertSame(2, $notification->tentativas);
        $this->assertNotNull($notification->enviada_em);
    }
}
