<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Relatorios\TrilhaProcessoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Trilha de auditoria por processo (prestação de contas): consolida, por número
 * de protocolo, as fontes REAIS de histórico — transições do eixo canônico
 * (viability_request_transitions), do eixo operacional (analysis_status_
 * transitions) e a trilha de auditoria (activity_log) — ordenadas por data.
 * Protocolo inexistente → null (a tela mostra o estado honesto, nunca uma
 * trilha inventada). Gated por consultar-relatorios; a impressão em PDF é
 * auditada (RN-002).
 */
class TrilhaProcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $ator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow('2026-06-15 12:00:00');

        $this->ator = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Ana Analista']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function consultor(): User
    {
        $user = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('consultar-relatorios');

        return $user;
    }

    private function processoComTrilha(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-2026-000500',
            'protocoled_at' => Carbon::parse('2026-06-10 10:00'),
        ]);

        ViabilityRequestTransition::factory()->create([
            'viability_request_id' => $processo->id,
            'from_status' => ViabilityRequestStatus::Rascunho,
            'to_status' => ViabilityRequestStatus::Protocolada,
            'actor_user_id' => $this->ator->id,
            'created_at' => Carbon::parse('2026-06-10 10:00'),
        ]);

        ViabilityRequestTransition::factory()->create([
            'viability_request_id' => $processo->id,
            'from_status' => ViabilityRequestStatus::Protocolada,
            'to_status' => ViabilityRequestStatus::EmAnalise,
            'actor_user_id' => $this->ator->id,
            'created_at' => Carbon::parse('2026-06-12 09:00'),
        ]);

        Activity::create([
            'log_name' => 'solicitacoes',
            'event' => 'protocolo',
            'description' => 'Protocolo efetuado',
            'subject_type' => ViabilityRequest::class,
            'subject_id' => $processo->id,
            'causer_type' => User::class,
            'causer_id' => $this->ator->id,
            'created_at' => Carbon::parse('2026-06-10 10:01'),
            'updated_at' => Carbon::parse('2026-06-10 10:01'),
        ]);

        return $processo;
    }

    public function test_trilha_consolida_transicoes_e_auditoria_ordenadas_por_data(): void
    {
        $processo = $this->processoComTrilha();

        $trilha = app(TrilhaProcessoService::class)->trilha('VIA-2026-000500');

        $this->assertNotNull($trilha);
        $this->assertSame($processo->id, $trilha['processo']['id']);
        $this->assertSame('VIA-2026-000500', $trilha['processo']['protocolo']);

        // 3 eventos montados + o log de criação do model (HasAuditoria) — a
        // criação É um evento real da trilha, não ruído.
        $this->assertCount(4, $trilha['eventos']);

        // Ordenadas por data: protocolo (transição) → auditoria → em_analise →
        // log de criação (auditoria, no "agora" congelado do teste).
        $this->assertSame('status', $trilha['eventos'][0]['eixo']);
        $this->assertSame('auditoria', $trilha['eventos'][1]['eixo']);
        $this->assertSame('status', $trilha['eventos'][2]['eixo']);
        $this->assertSame('auditoria', $trilha['eventos'][3]['eixo']);

        // Ator resolvido pelo nome (nunca só o id).
        $this->assertSame('Ana Analista', $trilha['eventos'][0]['usuario']);
    }

    public function test_protocolo_inexistente_retorna_null(): void
    {
        $this->assertNull(app(TrilhaProcessoService::class)->trilha('VIA-2026-999999'));
    }

    public function test_endpoint_renderiza_inertia_com_a_trilha_auditado(): void
    {
        $this->processoComTrilha();

        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/trilha?protocolo=VIA-2026-000500')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/trilha', false)
                ->where('trilha.processo.protocolo', 'VIA-2026-000500')
                ->has('trilha.eventos', 4));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-trilha-processo',
            'result' => 'sucesso',
        ]);
    }

    public function test_endpoint_sem_protocolo_renderiza_tela_de_busca(): void
    {
        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/trilha')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/trilha', false)
                ->where('trilha', null));
    }

    public function test_impressao_pdf_da_trilha_e_real_e_auditada(): void
    {
        $this->processoComTrilha();

        $response = $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/trilha/imprimir?protocolo=VIA-2026-000500')
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'imprime-trilha-processo',
            'result' => 'sucesso',
        ]);
    }

    public function test_impressao_de_protocolo_inexistente_e_404(): void
    {
        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/trilha/imprimir?protocolo=VIA-2026-999999')
            ->assertNotFound();
    }

    public function test_sem_consultar_relatorios_recebe_403(): void
    {
        $semPermissao = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/relatorios/trilha')
            ->assertForbidden();
    }
}
