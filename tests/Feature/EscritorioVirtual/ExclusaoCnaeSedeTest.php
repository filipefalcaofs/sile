<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\IntencaoAtividade;
use App\Enums\SefazNotificationEvent;
use App\Enums\SefazNotificationStatus;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\SefazNotification;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Notifications\AbrigadoDesvinculadoNotification;
use App\Services\Expresso\FluxoExpressoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * RN-AA-04: exclusão do CNAE gatilho da sede (default 8211-3/00) retira a
 * condição de sede — mas só com a confirmação EXPLÍCITA do requerente
 * (`confirma_perda_condicao_sede === true`). `null` é "ainda não perguntado",
 * nunca confirmação: sem `=== true`, o sistema não retira condição cadastral
 * por conta própria (encaminha à análise). `true` dispara a cascata
 * COMPARTILHADA de `DesvincularInscricaoService` (RN-EV-06) — desvincula o
 * lock, notifica os abrigados e comunica a SEFAZ.
 */
class ExclusaoCnaeSedeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): FluxoExpressoService
    {
        return app(FluxoExpressoService::class);
    }

    /**
     * Sede deferida com produto (TVL) que trava ATIVAMENTE a inscrição
     * informada (mesmo arranjo de ExclusaoAtividadeDeferimentoTest).
     */
    private function sedeAtivaNaInscricao(string $inscricao, string $tvl = 'TVL-2026-SEDE01'): ViabilityRequest
    {
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => $inscricao,
            'protocol_number' => 'VIA-2026-SEDE01',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $sede->id,
            'is_virtual_office_hq' => true,
            'tvl_product_number' => $tvl,
        ]);
        VirtualOfficeInscriptionLock::create([
            'property_registration' => $inscricao,
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        return $sede;
    }

    /**
     * Solicitação (nova protocolação, não a da sede) que exclui EXCLUSIVAMENTE
     * o CNAE gatilho da sede na inscrição já travada.
     */
    private function solicitacaoDeExclusaoDoGatilho(string $inscricao, ?bool $confirma): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'used_area_m2' => 120.0,
            'property_registration' => $inscricao,
            'confirma_perda_condicao_sede' => $confirma,
        ]);
        $cnaeGatilho = Cnae::factory()->create(['code' => '8211300']);
        $solicitacao->cnaes()->attach($cnaeGatilho->id, [
            'is_primary' => true,
            'intencao' => IntencaoAtividade::Excluir->value,
        ]);

        return $solicitacao;
    }

    /**
     * `confirma_perda_condicao_sede === null` (ainda não perguntado) NÃO é
     * confirmação: a solicitação não defere a exclusão do gatilho e a
     * inscrição continua vinculada à sede.
     */
    public function test_exclusao_do_cnae_gatilho_sem_confirmacao_nao_desvincula(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $this->sedeAtivaNaInscricao('123.456.789');
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', null);

        $this->service()->decide($solicitacao);

        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);
        $this->assertNull($fresh->decision);

        $lock = VirtualOfficeInscriptionLock::where('property_registration', '123.456.789')->first();
        $this->assertTrue($lock->active, 'sem confirmação explícita, o lock da sede continua ativo');

        Event::assertDispatched(EncaminhadoParaAnalise::class);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    /**
     * `confirma_perda_condicao_sede === false`: o requerente recusou a
     * exclusão do gatilho. O CNAE não é excluído automaticamente, o lock
     * continua ativo — encaminha à análise, igual ao caso sem resposta.
     */
    public function test_confirmacao_negativa_mantem_a_sede(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $this->sedeAtivaNaInscricao('123.456.789');
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', false);

        $this->service()->decide($solicitacao);

        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);
        $this->assertNull($fresh->decision);

        $lock = VirtualOfficeInscriptionLock::where('property_registration', '123.456.789')->first();
        $this->assertTrue($lock->active);

        Event::assertDispatched(EncaminhadoParaAnalise::class);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    /**
     * O coração da tarefa: `confirma_perda_condicao_sede === true` executa a
     * cascata INTEIRA — deferida, lock inativo, abrigados notificados,
     * SefazNotification criada com o evento SedePerdeuCondicao.
     */
    public function test_confirmacao_positiva_executa_a_cascata_inteira(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        NotificationFacade::fake();

        $this->sedeAtivaNaInscricao('123.456.789');

        $requester = User::factory()->create();
        $abrigado = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => '123.456.789',
            'requester_user_id' => $requester->id,
            'protocol_number' => 'VIA-2026-ABRIGADO1',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $abrigado->id,
            'is_virtual_office_tenant' => true,
            'tvl_product_number' => 'TVL-2026-ABRIGADO1',
        ]);

        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', true);

        $this->service()->decide($solicitacao);

        // 1. Deferida.
        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status);
        $this->assertNotNull($fresh->decision);

        // 2. Lock inativo.
        $lock = VirtualOfficeInscriptionLock::where('property_registration', '123.456.789')->first();
        $this->assertFalse($lock->active, 'a confirmação explícita retira a condição de sede');

        // 3. Abrigados notificados.
        NotificationFacade::assertSentTo($requester, AbrigadoDesvinculadoNotification::class);

        // 4. SefazNotification criada com o evento SedePerdeuCondicao.
        $notification = SefazNotification::where('viability_request_id', $lock->sede_viability_request_id)->first();
        $this->assertNotNull($notification);
        $this->assertSame(SefazNotificationEvent::SedePerdeuCondicao, $notification->event);

        Event::assertDispatched(ResultadoEmitido::class);
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    /**
     * §4.3.3: falha da comunicação à SEFAZ não desfaz a perda da condição de
     * sede (já efetivada e commitada) — o binding padrão do gateway lança, a
     * SefazNotification fica em Falha, reprocessável.
     */
    public function test_falha_da_comunicacao_sefaz_nao_desfaz_a_perda_da_condicao(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        NotificationFacade::fake();

        $this->sedeAtivaNaInscricao('123.456.789');
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', true);

        // Binding default = UnavailableSefazViabilidadeGateway (Fase 13):
        // representa o estado NORMAL deste ambiente hoje, sem gateway forjado.
        $this->service()->decide($solicitacao);

        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status, 'a falha na SEFAZ não desfaz o deferimento');

        $lock = VirtualOfficeInscriptionLock::where('property_registration', '123.456.789')->first();
        $this->assertFalse($lock->active, 'a falha na SEFAZ não desfaz a desvinculação');

        $notification = SefazNotification::where('viability_request_id', $lock->sede_viability_request_id)->first();
        $this->assertNotNull($notification);
        $this->assertSame(SefazNotificationStatus::Falha, $notification->status);
        $this->assertNotNull($notification->erro);
    }
}
