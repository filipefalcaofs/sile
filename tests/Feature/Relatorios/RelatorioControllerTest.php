<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\ExpressoQueda;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Camada HTTP dos relatórios (HU-122..131/145): cada tela é servida com dado
 * REAL dos serviços route-free (15-03/05/06/07), gated por consultar-relatorios e
 * com a consulta auditada (RN-002). Qualquer relatório exporta pelo contrato
 * único via ?formato= delegando ao ReportExporter (RN-004/009); a produtividade
 * nominal só sai com relatorios.produtividade.nominal (RN-007) — sem ela, o
 * default conservador anonimiza e restringe ao próprio. O 403 sem
 * consultar-relatorios é auditado no ponto único (CA-04). Os componentes React
 * chegam em 15-13/15-14; aqui os testes provam o controller (Inertia + props
 * reais + export + gate + auditoria).
 */
class RelatorioControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Usuário com consultar-relatorios mas SEM relatorios.produtividade.nominal —
     * o caso que prova o gate nominal (default conservador anonimizado/escopado).
     */
    private function consultorSemNominal(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo(['acessar-gestao', 'consultar-relatorios']);

        return $user;
    }

    /**
     * Solicitação protocolada com protocolo único (a população dos indicadores).
     *
     * @param  array<string, mixed>  $attrs
     */
    private function protocolada(array $attrs = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ], $attrs));
    }

    /**
     * Decisão de análise técnica cravada para um analista (flow analise_tecnica,
     * decided_by = analista) — fonte da produtividade (HU-130).
     */
    private function decisaoTecnica(User $analista, DecisionOutcome $outcome = DecisionOutcome::Deferida): void
    {
        $request = $this->protocolada(['status' => ViabilityRequestStatus::EmAnalise, 'protocol_number' => null]);

        $factory = ViabilityDecision::factory();

        if ($outcome === DecisionOutcome::Indeferida) {
            $factory = $factory->indeferida();
        }

        $factory->create([
            'viability_request_id' => $request->id,
            'flow' => 'analise_tecnica',
            'outcome' => $outcome,
            'decided_by_user_id' => $analista->id,
            'decided_at' => now(),
            'tvl_product_number' => null,
        ]);
    }

    public function test_sem_consultar_relatorios_recebe_403_auditado(): void
    {
        // Analista acessa a gestão mas NÃO tem consultar-relatorios (CA-04).
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/relatorios/indicadores')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_indicadores_renderiza_inertia_com_props_reais_auditado(): void
    {
        $this->protocolada(['protocoled_at' => now()]);
        $this->protocolada(['protocoled_at' => now()]);

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/indicadores')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/relatorios/indicadores', $page['component']);

        // Props REAIS dos serviços (15-03): séries e taxas calculadas em SQL.
        $props = $page['props'];
        $this->assertArrayHasKey('porPeriodo', $props);
        $this->assertArrayHasKey('porZona', $props);
        $this->assertArrayHasKey('porCnae', $props);
        $this->assertArrayHasKey('porRisco', $props);
        $this->assertArrayHasKey('taxaDeferimento', $props);
        $this->assertArrayHasKey('taxaIndeferimento', $props);
        $this->assertSame(2, $props['porPeriodo'][0]['total']);

        // A consulta é auditada (RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-indicadores',
            'result' => 'sucesso',
        ]);
    }

    public function test_indicadores_exporta_csv_pelo_contrato_unico_e_audita(): void
    {
        $alvo = $this->protocolada();

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/indicadores?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $conteudo = $response->streamedContent();
        $this->assertStringContainsString('Processo', $conteudo);
        $this->assertStringContainsString($alvo->protocol_number, $conteudo);

        // A exportação delega ao ReportExporter, que audita (RN-008): o event é
        // exporta-solicitacoes-<formato> (SolicitacoesReportSource).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-solicitacoes-csv',
            'result' => 'sucesso',
        ]);
    }

    public function test_tempo_renderiza_inertia_com_props_reais(): void
    {
        $this->protocolada();

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/tempo')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/relatorios/tempo', $page['component']);
        $this->assertArrayHasKey('tempoPorEtapa', $page['props']);
        $this->assertArrayHasKey('tempoEmissaoTvl', $page['props']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-tempo',
            'result' => 'sucesso',
        ]);
    }

    public function test_tempo_exporta_detalhamento_por_padrao(): void
    {
        $this->protocolada();

        $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/tempo?formato=csv')
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-tempo-analise-csv',
            'result' => 'sucesso',
        ]);
    }

    public function test_tempo_exporta_escritorio_virtual_pelo_parametro_relatorio(): void
    {
        $this->protocolada(['is_virtual_office' => true]);

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/tempo?formato=csv&relatorio=escritorio-virtual')
            ->assertOk();

        $this->assertStringContainsString('Empresa', $response->streamedContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-escritorio-virtual-csv',
            'result' => 'sucesso',
        ]);
    }

    public function test_produtividade_nominal_para_quem_tem_a_permissao_inclui_nome(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Analista']);
        $this->decisaoTecnica($ana);

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/produtividade')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/relatorios/produtividade', $page['component']);
        $this->assertTrue($page['props']['nominal']);
        $this->assertSame('Ana Analista', $page['props']['produtividade'][0]['nome']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-produtividade',
            'result' => 'sucesso',
        ]);
    }

    public function test_produtividade_sem_nominal_anonimiza_e_escopa_ao_proprio(): void
    {
        $consultor = $this->consultorSemNominal();
        $outroAnalista = User::factory()->create(['name' => 'Bruno Analista']);

        // Uma decisão do próprio consultor e outra de um analista diferente.
        $this->decisaoTecnica($consultor);
        $this->decisaoTecnica($outroAnalista);

        $response = $this->actingAs($consultor, 'gestao')
            ->get('/gestao/relatorios/produtividade')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertFalse($props['nominal']);
        // Escopo do próprio: só a linha dele, anonimizada (sem nome/id).
        $this->assertCount(1, $props['produtividade']);
        $this->assertArrayHasKey('analista_rotulo', $props['produtividade'][0]);
        $this->assertArrayNotHasKey('nome', $props['produtividade'][0]);
    }

    public function test_quedas_renderiza_inertia_com_props_reais(): void
    {
        ExpressoQueda::factory()->count(2)->create();

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/quedas')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/relatorios/quedas', $page['component']);
        $this->assertArrayHasKey('taxa', $page['props']);
        $this->assertArrayHasKey('serie', $page['props']);
        $this->assertArrayHasKey('ranking', $page['props']);
        $this->assertNotEmpty($page['props']['ranking']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-quedas',
            'result' => 'sucesso',
        ]);
    }
}
