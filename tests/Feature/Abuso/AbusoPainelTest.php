<?php

namespace Tests\Feature\Abuso;

use App\Enums\AbuseAlertStatus;
use App\Enums\ViabilityRequestStatus;
use App\Http\Resources\AbuseAlertResource;
use App\Models\AbuseAlert;
use App\Models\FineMeshReferral;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Painel humano de alertas de abuso (HU-149) — revisão, NUNCA punição. O gestor/
 * admin (gerenciar-alertas-abuso) lista os abuse_alerts com filtros (rule_key/
 * severity/status/período), vê a EFETIVIDADE = confirmados ÷ gerados (geral e por
 * regra, sobre os alertas reais — RN-005) e confirma/descarta com justificativa
 * OBRIGATÓRIA, tudo auditado (RN-002/RN-003). Anti-fachada CA-02: a resolução do
 * alerta muda SÓ o status do ALERTA — NUNCA transiciona o status do PROCESSO nem
 * mexe na malha fina já criada (ortogonal). Gated por gerenciar-alertas-abuso; o
 * 403 é auditado no ponto único (bootstrap/app.php). As props são inspecionadas
 * via viewData('page') (sem exigir o componente .tsx — a tela é 12-11), como o
 * LgpdMonitorTest/ProcessoConsultaTest fazem para os endpoints que precedem a UI.
 */
class AbusoPainelTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Gestor (tem acessar-gestao + gerenciar-alertas-abuso — 12-03). */
    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    /** Acessa a gestão mas NÃO tem gerenciar-alertas-abuso (gate específico barra). */
    private function semPermissao(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::Deferida): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create([
            'status' => $status,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ])->fresh();
    }

    public function test_sem_permissao_gerenciar_alertas_abuso_recebe_403_auditado(): void
    {
        $this->actingAs($this->semPermissao(), 'gestao')
            ->get('/gestao/abuso')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_lista_alertas_paginados_e_audita_a_consulta(): void
    {
        AbuseAlert::factory()->count(3)->create(['rule_key' => 'volume_cnpj']);

        $gestor = $this->gestor();

        $page = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/abuso')
            ->assertOk()
            ->viewData('page');

        $this->assertSame('gestao/abuso/index', $page['component']);
        $this->assertCount(3, $page['props']['alertas']['data']);
        $this->assertArrayHasKey('efetividade', $page['props']);
        $this->assertArrayHasKey('perPageOptions', $page['props']);
        $this->assertArrayHasKey('ruleKeyOptions', $page['props']);
        $this->assertArrayHasKey('severityOptions', $page['props']);
        $this->assertArrayHasKey('statusOptions', $page['props']);
        $this->assertSame('', $page['props']['filtros']['rule_key']);
        $this->assertSame('', $page['props']['filtros']['severity']);
        $this->assertSame('', $page['props']['filtros']['status']);

        // A própria consulta do painel é auditada (RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'abuso',
            'event' => 'consulta-alertas',
            'result' => 'sucesso',
            'causer_id' => $gestor->id,
        ]);
    }

    public function test_filtra_por_rule_key(): void
    {
        AbuseAlert::factory()->create(['rule_key' => 'volume_cnpj']);
        AbuseAlert::factory()->create(['rule_key' => 'volume_contador']);

        $page = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/abuso?rule_key=volume_contador')
            ->assertOk()
            ->viewData('page');

        $this->assertCount(1, $page['props']['alertas']['data']);
        $this->assertSame('volume_contador', $page['props']['alertas']['data'][0]['rule_key']);
    }

    public function test_filtra_por_severity_e_status(): void
    {
        AbuseAlert::factory()->alta()->create(['rule_key' => 'volume_cnpj']);
        AbuseAlert::factory()->media()->create(['rule_key' => 'volume_cnpj']);
        AbuseAlert::factory()->alta()->confirmado()->create(['rule_key' => 'volume_cnpj']);

        // severity=alta → 2 (a aberta + a confirmada).
        $porSeveridade = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/abuso?severity=alta')
            ->assertOk()
            ->viewData('page');
        $this->assertCount(2, $porSeveridade['props']['alertas']['data']);

        // status=confirmado → 1.
        $porStatus = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/abuso?status=confirmado')
            ->assertOk()
            ->viewData('page');
        $this->assertCount(1, $porStatus['props']['alertas']['data']);
        $this->assertSame('confirmado', $porStatus['props']['alertas']['data'][0]['status']['value']);
    }

    public function test_filtra_por_periodo_em_detected_at(): void
    {
        AbuseAlert::factory()->create(['rule_key' => 'volume_cnpj', 'detected_at' => '2026-01-10 10:00:00']);
        AbuseAlert::factory()->create(['rule_key' => 'volume_cnpj', 'detected_at' => '2026-03-20 10:00:00']);

        $deMarco = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/abuso?data_de=2026-03-01')
            ->assertOk()
            ->viewData('page');
        $this->assertCount(1, $deMarco['props']['alertas']['data']);

        $ateFevereiro = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/abuso?data_ate=2026-02-01')
            ->assertOk()
            ->viewData('page');
        $this->assertCount(1, $ateFevereiro['props']['alertas']['data']);
    }

    public function test_efetividade_calcula_confirmados_sobre_gerados_geral_e_por_regra(): void
    {
        // volume_cnpj: 4 gerados (1 confirmado, 3 abertos) → 25.0%
        AbuseAlert::factory()->confirmado()->create(['rule_key' => 'volume_cnpj']);
        AbuseAlert::factory()->count(3)->create(['rule_key' => 'volume_cnpj']);
        // volume_contador: 2 gerados (1 confirmado, 1 aberto) → 50.0%
        AbuseAlert::factory()->confirmado()->create(['rule_key' => 'volume_contador']);
        AbuseAlert::factory()->create(['rule_key' => 'volume_contador']);
        // Geral: 6 gerados, 2 confirmados → 33.3%

        $page = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/abuso')
            ->assertOk()
            ->viewData('page');

        $efetividade = $page['props']['efetividade'];

        $this->assertSame(6, $efetividade['geral']['gerados']);
        $this->assertSame(2, $efetividade['geral']['confirmados']);
        $this->assertSame(33.3, $efetividade['geral']['taxa']);

        // por_regra ordenado por rule_key (volume_cnpj < volume_contador).
        $this->assertSame('volume_cnpj', $efetividade['por_regra'][0]['rule_key']);
        $this->assertSame(4, $efetividade['por_regra'][0]['gerados']);
        $this->assertSame(1, $efetividade['por_regra'][0]['confirmados']);
        $this->assertSame(25.0, $efetividade['por_regra'][0]['taxa']);
        $this->assertSame('volume_contador', $efetividade['por_regra'][1]['rule_key']);
        $this->assertSame(2, $efetividade['por_regra'][1]['gerados']);
        $this->assertSame(1, $efetividade['por_regra'][1]['confirmados']);
        $this->assertSame(50.0, $efetividade['por_regra'][1]['taxa']);
    }

    public function test_efetividade_sem_alertas_nao_inventa_taxa(): void
    {
        // RN-005 anti-fachada: zero gerados → taxa null (nunca número inventado).
        $page = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/abuso')
            ->assertOk()
            ->viewData('page');

        $efetividade = $page['props']['efetividade'];

        $this->assertSame(0, $efetividade['geral']['gerados']);
        $this->assertSame(0, $efetividade['geral']['confirmados']);
        $this->assertNull($efetividade['geral']['taxa']);
        $this->assertSame([], $efetividade['por_regra']);
    }

    public function test_resource_expoe_shape_completo_do_alerta(): void
    {
        $processo = $this->processo();
        $referral = FineMeshReferral::factory()->create(['viability_request_id' => $processo->id]);
        $gestor = User::factory()->create(['name' => 'Gisele Gestora']);

        $alerta = AbuseAlert::factory()->alta()->create([
            'rule_key' => 'volume_cnpj',
            'status' => AbuseAlertStatus::Confirmado,
            'evidence' => ['total' => 7, 'limite' => 5, 'ids' => [1, 2, 3]],
            'viability_request_id' => $processo->id,
            'fine_mesh_referral_id' => $referral->id,
            'window_start' => '2026-02-01 00:00:00',
            'window_end' => '2026-03-01 00:00:00',
            'detected_at' => '2026-03-01 12:00:00',
            'resolved_by_user_id' => $gestor->id,
            'resolved_at' => '2026-03-02 09:00:00',
            'justification' => 'Reincidência confirmada na análise.',
        ]);

        $alerta->load(['viabilityRequest', 'resolvedBy']);

        $dados = (new AbuseAlertResource($alerta))->resolve();

        $this->assertSame('volume_cnpj', $dados['rule_key']);
        $this->assertSame('alta', $dados['severity']['value']);
        $this->assertSame('Alta', $dados['severity']['label']);
        $this->assertSame('confirmado', $dados['status']['value']);
        $this->assertSame('Confirmado', $dados['status']['label']);
        $this->assertSame(['total' => 7, 'limite' => 5, 'ids' => [1, 2, 3]], $dados['evidence']);
        $this->assertStringStartsWith('2026-03-01', $dados['detected_at']);
        $this->assertStringStartsWith('2026-02-01', $dados['window']['start']);
        $this->assertStringStartsWith('2026-03-01', $dados['window']['end']);
        $this->assertSame($processo->id, $dados['processo']['id']);
        $this->assertSame($processo->protocol_number, $dados['processo']['protocol_number']);
        $this->assertTrue($dados['encaminhado_malha_fina']);
        $this->assertSame($gestor->id, $dados['resolucao']['resolved_by']['id']);
        $this->assertSame('Gisele Gestora', $dados['resolucao']['resolved_by']['nome']);
        $this->assertStringStartsWith('2026-03-02', $dados['resolucao']['resolved_at']);
        $this->assertSame('Reincidência confirmada na análise.', $dados['resolucao']['justification']);
    }

    public function test_resource_de_alerta_aberto_sem_processo_minimiza_campos(): void
    {
        $alerta = AbuseAlert::factory()->create([
            'rule_key' => 'volume_contador',
            'viability_request_id' => null,
            'fine_mesh_referral_id' => null,
        ]);

        $dados = (new AbuseAlertResource($alerta))->resolve();

        $this->assertSame('aberto', $dados['status']['value']);
        $this->assertNull($dados['processo']);
        $this->assertFalse($dados['encaminhado_malha_fina']);
        $this->assertNull($dados['resolucao']);
    }
}
