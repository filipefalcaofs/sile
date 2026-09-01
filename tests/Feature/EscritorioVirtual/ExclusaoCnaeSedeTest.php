<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\IntencaoAtividade;
use App\Enums\SefazNotificationEvent;
use App\Enums\SefazNotificationStatus;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\SefazNotification;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Notifications\AbrigadoDesvinculadoNotification;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
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
     * Código do CNAE gatilho da sede lido da fonte única (M4 da revisão
     * final) — nunca fixado como literal no teste.
     */
    private function gatilho(): Cnae
    {
        $codigo = app(SedeEscritorioVirtualGatilho::class)->cnaeGatilho();

        return Cnae::query()->where('code', $codigo)->first() ?? Cnae::factory()->create(['code' => $codigo]);
    }

    /**
     * Sede deferida com produto (TVL) que trava ATIVAMENTE a inscrição
     * informada (mesmo arranjo de ExclusaoAtividadeDeferimentoTest). A
     * empresa titular é sempre explicitada — é o que `titularDaSede()`
     * (C2 da revisão final) compara contra a solicitação que pede a
     * exclusão do gatilho.
     */
    private function sedeAtivaNaInscricao(string $inscricao, ?Company $titular = null, string $tvl = 'TVL-2026-SEDE01'): ViabilityRequest
    {
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => ($titular ?? Company::factory()->create())->id,
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
     * Solicitação (nova protocolação, não a da sede) que exclui o CNAE
     * gatilho da sede na inscrição já travada — sozinho (exclusivamente
     * exclusão) por padrão. `$empresa` permite montar o cenário de terceiro
     * (C2) explicitamente informando uma empresa diferente da titular.
     */
    private function solicitacaoDeExclusaoDoGatilho(string $inscricao, ?bool $confirma, ?Company $empresa = null): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'used_area_m2' => 120.0,
            'company_id' => ($empresa ?? Company::factory()->create())->id,
            'property_registration' => $inscricao,
            'confirma_perda_condicao_sede' => $confirma,
        ]);
        $solicitacao->cnaes()->attach($this->gatilho()->id, [
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

        $titular = Company::factory()->create();
        $this->sedeAtivaNaInscricao('123.456.789', $titular);
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', null, $titular);

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

        $titular = Company::factory()->create();
        $this->sedeAtivaNaInscricao('123.456.789', $titular);
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', false, $titular);

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
     * O coração da tarefa: `confirma_perda_condicao_sede === true`, pedida
     * pela TITULAR do vínculo de sede (C2 da revisão final — sem isso a
     * cascata rodaria para qualquer solicitação na mesma inscrição), executa
     * a cascata INTEIRA — deferida, lock inativo, abrigados notificados,
     * SefazNotification criada com o evento SedePerdeuCondicao.
     */
    public function test_confirmacao_positiva_executa_a_cascata_inteira(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        NotificationFacade::fake();

        $titular = Company::factory()->create();
        $this->sedeAtivaNaInscricao('123.456.789', $titular);

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

        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', true, $titular);

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

        $titular = Company::factory()->create();
        $this->sedeAtivaNaInscricao('123.456.789', $titular);
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', true, $titular);

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

    /**
     * M1: o marcador `:cnae` da mensagem de confirmação sai formatado no
     * padrão oficial (NNNN-N/NN), nunca só dígitos — `cnaeGatilho()` devolve
     * a forma normalizada (própria para comparação), não a de exibição.
     */
    public function test_mensagem_de_confirmacao_traz_o_cnae_formatado(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $titular = Company::factory()->create();
        $this->sedeAtivaNaInscricao('123.456.789', $titular);
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', null, $titular);

        $resultado = $this->service()->decide($solicitacao);

        $formatado = app(SedeEscritorioVirtualGatilho::class)->cnaeGatilhoFormatado();
        $this->assertStringContainsString($formatado, (string) $resultado->reason);
        $this->assertMatchesRegularExpression('/\d{4}-\d\/\d{2}/', (string) $resultado->reason);
    }

    /**
     * C2 da revisão final: a solicitação que pede a exclusão do gatilho NÃO é
     * da empresa titular do vínculo de sede (mesma inscrição imobiliária, mas
     * outra empresa protocolando). A cascata NÃO pode derrubar a sede alheia
     * — mesmo com `confirma_perda_condicao_sede === true`, encaminha à
     * análise e o lock continua ativo. Antes da correção, `deferirExclusaoDeSede()`
     * localizava o lock só pela inscrição e desativava a sede de quem quer
     * que a detivesse.
     */
    public function test_solicitacao_de_terceiro_nao_derruba_a_sede(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        NotificationFacade::fake();

        $titular = Company::factory()->create();
        $this->sedeAtivaNaInscricao('123.456.789', $titular);

        $terceiro = Company::factory()->create();
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', true, $terceiro);

        $this->service()->decide($solicitacao);

        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status, 'solicitação de terceiro não pode deferir a exclusão da sede alheia');
        $this->assertNull($fresh->decision);

        $lock = VirtualOfficeInscriptionLock::where('property_registration', '123.456.789')->first();
        $this->assertTrue($lock->active, 'a sede de outra empresa não pode ser derrubada por solicitação de terceiro');

        NotificationFacade::assertNothingSent();
        Event::assertDispatched(EncaminhadoParaAnalise::class);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    /**
     * C1 da revisão final: solicitação MISTA (uma inclusão + o CNAE gatilho
     * marcado para excluir), na inscrição com sede ATIVA, pedida pela
     * titular. RN-AA-07 manda aplicar a RN-AA-04 INTEGRALMENTE também na
     * mista — sem confirmação, NÃO pode ser deferida silenciosamente (o
     * bug documentado pela revisão: os dois ramos exigiam
     * `exclusivamenteExclusao()`, e a mista escapava direto para `emitir()`,
     * deferindo sem perguntar e sem desvincular a inscrição).
     */
    public function test_solicitacao_mista_que_exclui_o_gatilho_sem_confirmacao_nao_e_deferida(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $titular = Company::factory()->create();
        $this->sedeAtivaNaInscricao('123.456.789', $titular);
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', null, $titular);

        // Torna a solicitação MISTA: uma inclusão além da exclusão do gatilho.
        $inclusao = Cnae::factory()->create(['code' => '4712100']);
        $solicitacao->cnaes()->attach($inclusao->id, [
            'is_primary' => false,
            'intencao' => IntencaoAtividade::Incluir->value,
        ]);

        $this->assertFalse($solicitacao->exclusivamenteExclusao(), 'a solicitação precisa ser mista para provar o C1');

        $this->service()->decide($solicitacao);

        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status, 'solicitação mista sem confirmação não pode ser deferida silenciosamente (RN-AA-07)');
        $this->assertNull($fresh->decision);

        $lock = VirtualOfficeInscriptionLock::where('property_registration', '123.456.789')->first();
        $this->assertTrue($lock->active, 'sem confirmação, o vínculo de sede continua ativo mesmo na solicitação mista');

        Event::assertDispatched(EncaminhadoParaAnalise::class);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    /**
     * C1 — a contraparte: a mesma solicitação mista, agora COM confirmação
     * explícita, executa a cascata inteira (RN-AA-07: a regra da RN-AA-04 se
     * aplica integralmente também na mista).
     */
    public function test_solicitacao_mista_que_exclui_o_gatilho_com_confirmacao_executa_a_cascata(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        NotificationFacade::fake();

        $titular = Company::factory()->create();
        $this->sedeAtivaNaInscricao('123.456.789', $titular);
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('123.456.789', true, $titular);

        $inclusao = Cnae::factory()->create(['code' => '4712100']);
        $solicitacao->cnaes()->attach($inclusao->id, [
            'is_primary' => false,
            'intencao' => IntencaoAtividade::Incluir->value,
        ]);

        $this->assertFalse($solicitacao->exclusivamenteExclusao());

        $this->service()->decide($solicitacao);

        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status);
        $this->assertNotNull($fresh->decision);

        $lock = VirtualOfficeInscriptionLock::where('property_registration', '123.456.789')->first();
        $this->assertFalse($lock->active, 'a confirmação explícita retira a condição de sede também na solicitação mista');

        Event::assertDispatched(ResultadoEmitido::class);
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    /**
     * I1 da revisão final: exclusão EXCLUSIVA do CNAE gatilho, mas a
     * inscrição NÃO tem vínculo de sede ativo (nenhum lock) — não há condição
     * a perder, então cai no caminho comum de exclusão (RN-AA-03/05): defere
     * automaticamente, sem zoneamento, sem cascata. Antes da correção, esse
     * caso caía na resolução normal e podia ser indeferido por zoneamento.
     */
    public function test_exclusao_do_gatilho_sem_vinculo_de_sede_ativo_defere_como_exclusao_comum(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        // Nenhuma sede ativa nesta inscrição — só a solicitação de exclusão.
        $solicitacao = $this->solicitacaoDeExclusaoDoGatilho('999.888.777', null);

        $this->service()->decide($solicitacao);

        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status, 'sem vínculo de sede ativo, a exclusão do gatilho é uma exclusão comum');
        $this->assertNotNull($fresh->decision);
        $this->assertNull(VirtualOfficeInscriptionLock::where('property_registration', '999.888.777')->first(), 'não existe lock nenhum para desativar');

        Event::assertDispatched(ResultadoEmitido::class);
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    /**
     * I1 — a exceção: `property_registration` em branco (coluna nulável).
     * Sobre inscrição desconhecida o honesto é encaminhar à análise com o
     * motivo, nunca deferir uma exclusão de sede sem saber qual sede.
     */
    public function test_exclusao_do_gatilho_com_inscricao_em_branco_encaminha_a_analise(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'used_area_m2' => 120.0,
            'property_registration' => null,
            'confirma_perda_condicao_sede' => null,
        ]);
        $solicitacao->cnaes()->attach($this->gatilho()->id, [
            'is_primary' => true,
            'intencao' => IntencaoAtividade::Excluir->value,
        ]);

        $this->service()->decide($solicitacao);

        $fresh = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status, 'inscrição imobiliária ausente não pode deferir sobre sede desconhecida');
        $this->assertNull($fresh->decision);

        Event::assertDispatched(EncaminhadoParaAnalise::class);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }
}
