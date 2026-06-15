<?php

namespace Tests\Feature\Lgpd;

use App\Models\Activity;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use App\Services\Lgpd\LgpdMonitorService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Monitoramento de conformidade LGPD (HU-102): o painel agrega TRÊS fontes
 * REAIS — consentimentos (LegalTerm::current('lgpd') × LegalTermAcceptance),
 * retenção (retencao.access_logs.dias + último pruning lido da trilha; decisões
 * ficam FORA do pruning) e acessos a dado pessoal (activity_log.personal_data).
 * Minimizado (métricas, nunca PII crua), gated por monitorar-lgpd e auditado
 * (RN-002); 403 sem permissão é auditado no ponto único (bootstrap/app.php).
 */
class LgpdMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): LgpdMonitorService
    {
        return app(LgpdMonitorService::class);
    }

    /**
     * Semeia uma activity direto na espinha (o model pai usa $guarded = []).
     *
     * @param  array<string, mixed>  $attrs
     */
    private function atividade(array $attrs = []): Activity
    {
        $activity = new Activity;

        $activity->forceFill(array_merge([
            'log_name' => 'teste',
            'event' => 'consulta',
            'description' => 'evento de teste',
            'personal_data' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        $activity->save();

        return $activity;
    }

    public function test_consentimentos_reflete_aceite_da_versao_vigente(): void
    {
        $v1 = LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 1]);

        $users = User::factory()->count(4)->create();

        foreach ($users->take(3) as $user) {
            LegalTermAcceptance::create([
                'user_id' => $user->id,
                'legal_term_id' => $v1->id,
                'accepted_at' => now(),
            ]);
        }

        $c = $this->service()->consentimentos();

        $this->assertFalse($c['sem_termo_publicado']);
        $this->assertSame(1, $c['versao_vigente']);
        $this->assertSame(4, $c['total_usuarios']);
        $this->assertSame(3, $c['aceitaram_vigente']);
        $this->assertSame(1, $c['pendentes_reaceite']);
        $this->assertSame(75.0, $c['percentual_aceite']);

        // Publicar a v2 reexige aceite: ninguém aceitou a NOVA versão vigente.
        LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 2]);

        $apos = $this->service()->consentimentos();

        $this->assertSame(2, $apos['versao_vigente']);
        $this->assertSame(0, $apos['aceitaram_vigente']);
        $this->assertSame(4, $apos['pendentes_reaceite']);
        $this->assertSame(0.0, $apos['percentual_aceite']);
    }

    public function test_consentimentos_degrada_honesto_sem_termo_publicado(): void
    {
        User::factory()->count(2)->create();

        $c = $this->service()->consentimentos();

        $this->assertTrue($c['sem_termo_publicado']);
        $this->assertNull($c['versao_vigente']);
        $this->assertSame(2, $c['total_usuarios']);
        $this->assertSame(0, $c['aceitaram_vigente']);
        $this->assertSame(0, $c['pendentes_reaceite']);
    }

    public function test_acessos_dado_pessoal_conta_so_marcados_na_janela(): void
    {
        // Dentro da janela e marcados (mesmo evento agrupa em uma linha total=2).
        $this->atividade(['log_name' => 'analise', 'event' => 'consulta-processo', 'personal_data' => true, 'created_at' => now()]);
        $this->atividade(['log_name' => 'analise', 'event' => 'consulta-processo', 'personal_data' => true, 'created_at' => now()->subDay()]);
        // Não marcado (não é acesso a dado pessoal) — ignorado.
        $this->atividade(['log_name' => 'analise', 'event' => 'consulta-processos', 'personal_data' => false, 'created_at' => now()]);
        // Marcado, porém fora da janela padrão (7 dias) — ignorado.
        $this->atividade(['log_name' => 'acessos', 'event' => 'consulta-acessos', 'personal_data' => true, 'created_at' => now()->subDays(30)]);

        $a = $this->service()->acessosDadoPessoal();

        $this->assertSame(7, $a['janela_dias']);
        $this->assertSame(2, $a['total']);
        $this->assertCount(1, $a['por_evento']);
        $this->assertSame('analise', $a['por_evento'][0]['log_name']);
        $this->assertSame('consulta-processo', $a['por_evento'][0]['event']);
        $this->assertSame(2, $a['por_evento'][0]['total']);
    }

    public function test_retencao_traz_dias_ultimo_pruning_e_decisoes_fora(): void
    {
        // Último pruning REAL auditado (log_name/event do AuditModelsPruned).
        $this->atividade([
            'log_name' => 'retencao',
            'event' => 'pruning-access-logs',
            'description' => 'Limpeza automática do histórico de acessos',
            'properties' => ['modelo' => 'App\\Models\\AccessLog', 'removidos' => 42],
            'created_at' => '2026-05-01 03:00:00',
        ]);

        $r = $this->service()->retencao();

        $this->assertSame(365, $r['access_logs_dias']);
        $this->assertNotNull($r['ultimo_pruning_em']);
        $this->assertStringStartsWith('2026-05-01', $r['ultimo_pruning_em']);
        $this->assertSame(42, $r['ultimo_pruning_removidos']);
        // Compliance: decisões (viability_decisions) NUNCA entram no pruning.
        $this->assertTrue($r['decisoes_fora_do_pruning']);
    }

    public function test_retencao_sem_pruning_registra_nulo_honesto(): void
    {
        $r = $this->service()->retencao();

        $this->assertNull($r['ultimo_pruning_em']);
        $this->assertNull($r['ultimo_pruning_removidos']);
        $this->assertTrue($r['decisoes_fora_do_pruning']);
    }

    public function test_painel_renderiza_e_audita_para_quem_tem_permissao(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $response = $this->actingAs($admin, 'gestao')
            ->get('/gestao/lgpd')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/lgpd/index', $page['component']);
        $this->assertArrayHasKey('consentimentos', $page['props']);
        $this->assertArrayHasKey('retencao', $page['props']);
        $this->assertArrayHasKey('acessosDadoPessoal', $page['props']);
        $this->assertArrayHasKey('direitosTitular', $page['props']);

        // A própria consulta ao painel é auditada e marcada personal_data (RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'lgpd',
            'event' => 'consulta-painel',
            'result' => 'sucesso',
            'causer_id' => $admin->id,
            'personal_data' => true,
        ]);
    }

    public function test_sem_permissao_monitorar_lgpd_recebe_403_auditado(): void
    {
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/lgpd')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
            'causer_id' => $gestor->id,
        ]);
    }
}
