<?php

namespace Tests\Feature\Processo;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Models\Activity;
use App\Models\AnalysisStatusTransition;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tela T06 — Enviar processo de TVL para análise (gatilho manual da gestão,
 * relatório de teste SEDUR). Decisões fechadas: QUALQUER status pode ser enviado
 * (OPEN-F-2 — sem trava de status); vale para sede E abrigado (OPEN-F-3 — a tela
 * não distingue tipo); o envio é IDEMPOTENTE (se já em análise, não duplica a
 * tramitação); "não encontrado" é resposta de domínio (404) com mensagem
 * PARAMETRIZADA, nunca 500; e é gated por permissão dedicada (enviar-tvl-analise,
 * 403 auditado no ponto único).
 *
 * A tela atua no EIXO OPERACIONAL da análise (AnalysisStatus — paralelo ao
 * ViabilityRequestStatus canônico): "entra na análise com status operacional"
 * significa levar o processo à fila de distribuição (ParaDistribuir), reusando
 * os mesmos blocos do encaminhamento do expresso (AnalysisStatusStateMachine +
 * AnalysisSlaService + evento EncaminhadoParaAnalise) — sem tocar no status
 * canônico, o que é o que viabiliza aceitar QUALQUER status de origem.
 */
class EnviarParaAnaliseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Operador com a permissão dedicada (analista recebe enviar-tvl-analise). */
    private function operador(): User
    {
        return User::factory()->analista()->create();
    }

    private function processo(
        string $protocolo,
        ViabilityRequestStatus $status,
        ?AnalysisStatus $analysisStatus = null,
    ): ViabilityRequest {
        return ViabilityRequest::factory()->create([
            'protocol_number' => $protocolo,
            'status' => $status,
            'analysis_status' => $analysisStatus,
        ]);
    }

    public function test_render_da_tela_retorna_componente_inertia(): void
    {
        $this->actingAs($this->operador(), 'gestao')
            ->get('/gestao/processos/enviar-para-analise')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/enviar-para-analise', false)
            );
    }

    public function test_protocolo_inexistente_devolve_mensagem_de_dominio_sem_mudar_estado(): void
    {
        Event::fake([EncaminhadoParaAnalise::class]);

        $this->actingAs($this->operador(), 'gestao')
            ->postJson('/gestao/processos/enviar-para-analise/pesquisar', ['protocolo' => 'VIA-0000-999999'])
            ->assertStatus(404)
            ->assertJsonPath('encontrado', false)
            ->assertJson(fn ($json) => $json->where('encontrado', false)->has('mensagem')->etc());

        // CA-E-01: nenhuma tramitação criada, nada muda de estado.
        $this->assertSame(0, AnalysisStatusTransition::query()->count());
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    public function test_pesquisa_encontra_processo_em_qualquer_status(): void
    {
        // Status terminal (deferida) — o eixo canônico recusaria em_analise; a
        // tela ainda assim encontra e permite enviar (OPEN-F-2).
        $this->processo('VIA-2026-000123', ViabilityRequestStatus::Deferida);

        $this->actingAs($this->operador(), 'gestao')
            ->postJson('/gestao/processos/enviar-para-analise/pesquisar', ['protocolo' => 'VIA-2026-000123'])
            ->assertOk()
            ->assertJsonPath('encontrado', true)
            ->assertJsonPath('processo.protocolo', 'VIA-2026-000123');
    }

    public function test_encontrado_em_status_arbitrario_entra_em_analise_com_auditoria(): void
    {
        Event::fake([EncaminhadoParaAnalise::class]);

        $processo = $this->processo('VIA-2026-000123', ViabilityRequestStatus::Deferida);

        $this->actingAs($this->operador(), 'gestao')
            ->post('/gestao/processos/enviar-para-analise/enviar', ['protocolo' => 'VIA-2026-000123'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $processo->refresh();

        // CA-E-02: entra na análise com status OPERACIONAL (fila de distribuição).
        $this->assertSame(AnalysisStatus::ParaDistribuir, $processo->analysis_status);
        // O status canônico NÃO é forçado — por isso qualquer status de origem serve.
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->status);

        // Auditoria (RN-002): evento dedicado do envio manual.
        $this->assertTrue(
            Activity::query()->where('log_name', 'analise')->where('event', 'enviar-analise')->exists(),
            'Esperava a auditoria do envio manual à análise (RN-002).',
        );
        // Uma tramitação no eixo operacional e o gatilho da pré-análise disparado.
        $this->assertSame(1, $processo->analysisStatusTransitions()->count());
        Event::assertDispatched(
            EncaminhadoParaAnalise::class,
            fn (EncaminhadoParaAnalise $event): bool => $event->request->is($processo),
        );
    }

    public function test_ja_em_analise_avisa_e_nao_duplica_tramitacao(): void
    {
        Event::fake([EncaminhadoParaAnalise::class]);

        $processo = $this->processo('VIA-2026-000200', ViabilityRequestStatus::EmAnalise, AnalysisStatus::EmAnalise);

        $this->actingAs($this->operador(), 'gestao')
            ->post('/gestao/processos/enviar-para-analise/enviar', ['protocolo' => 'VIA-2026-000200'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $processo->refresh();

        // CA-E-03: idempotente — status operacional preservado (não regride), sem
        // nova tramitação, sem auditoria de envio, sem redisparar o gatilho.
        $this->assertSame(AnalysisStatus::EmAnalise, $processo->analysis_status);
        $this->assertSame(0, $processo->analysisStatusTransitions()->count());
        $this->assertFalse(
            Activity::query()->where('log_name', 'analise')->where('event', 'enviar-analise')->exists(),
        );
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    public function test_enviar_protocolo_inexistente_avisa_sem_mudar_estado(): void
    {
        Event::fake([EncaminhadoParaAnalise::class]);

        $this->actingAs($this->operador(), 'gestao')
            ->post('/gestao/processos/enviar-para-analise/enviar', ['protocolo' => 'VIA-0000-000000'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, AnalysisStatusTransition::query()->count());
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    public function test_sem_permissao_dedicada_retorna_403(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('acessar-gestao'); // sem enviar-tvl-analise

        $this->actingAs($user, 'gestao')
            ->get('/gestao/processos/enviar-para-analise')
            ->assertForbidden();
    }
}
