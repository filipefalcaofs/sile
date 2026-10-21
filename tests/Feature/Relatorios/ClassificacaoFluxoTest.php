<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ConclusionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisRecordService;
use App\Services\Analise\PreAnaliseService;
use App\Services\Relatorios\ClassificacaoFluxoService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\Support\MontaFluxosDeConclusao;
use Tests\TestCase;

/**
 * A classificação vem dos eventos que a aplicação grava, pelos serviços reais:
 * a conclusão é a decisão do processo; a decisão do motor é a ficha
 * pré-analisada; a análise humana é a ficha finalizada por um analista.
 * Nenhum teste preenche analysis_category.
 */
class ClassificacaoFluxoTest extends TestCase
{
    use LazilyRefreshDatabase;
    use MontaFluxosDeConclusao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cenarioZonaPermitida();
    }

    private function service(): ClassificacaoFluxoService
    {
        return app(ClassificacaoFluxoService::class);
    }

    private function periodo(): ReportFilters
    {
        return ReportFilters::fromArray(['data_de' => now()->subDay()->toDateString(), 'data_ate' => now()->addDay()->toDateString()]);
    }

    public function test_motor_concluindo_e_automatica_mesmo_disparado_por_integracao_com_usuario(): void
    {
        $integracao = User::factory()->create();

        $this->concluidaPeloMotor();
        $this->concluidaPeloMotor($integracao);

        $contagem = $this->service()->contagem($this->periodo());

        $this->assertSame(2, $contagem['automatica']);
        $this->assertSame(2, $contagem['total']);
        $this->assertSame(100.0, $contagem['taxa_automacao']);
    }

    public function test_decisao_do_motor_sem_conclusao_nao_entra_no_total(): void
    {
        $this->encaminhadaComDecisaoDoMotor();

        $contagem = $this->service()->contagem($this->periodo());

        $this->assertSame(0, $contagem['total']);
        $this->assertNull($contagem['taxa_automacao']);
    }

    public function test_decisao_do_motor_concluida_pelo_analista_e_semi_expressa(): void
    {
        $analista = User::factory()->analistaServidor()->create();

        $this->concluidaPeloMotor();
        $this->concluirAnalise($this->encaminhadaComDecisaoDoMotor(), $analista, ConclusionOutcome::Deferido);

        $contagem = $this->service()->contagem($this->periodo());

        $this->assertSame(['automatica' => 1, 'semi_expressa' => 1, 'humana' => 0, 'revisada' => 0, 'sem_analise_concluida' => 0], array_intersect_key($contagem, array_flip(ClassificacaoFluxoService::CATEGORIAS)));
        $this->assertSame(2, $contagem['total']);
        $this->assertSame(50.0, $contagem['taxa_automacao']);
    }

    public function test_sem_decisao_do_motor_a_analise_e_integralmente_humana(): void
    {
        $analista = User::factory()->analistaServidor()->create();
        $solicitacao = $this->protocoladaComCnae(self::CNAE_ALTO_RISCO);
        $solicitacao->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $resolver = $this->mock(SolicitacaoViabilityResolver::class);
        $resolver->shouldReceive('resolve')->andThrow(new RuntimeException('motor fora do ar'));
        $ficha = app(PreAnaliseService::class)->preAnalisar($solicitacao->fresh());
        $this->assertFalse($ficha->engine_available);

        // O analista preenche a ficha vazia e conclui.
        app(AnalysisRecordService::class)->autosave($ficha, [
            'per_cnae' => [['cnae' => self::CNAE_ALTO_RISCO, 'status_escolhido' => 'indeferida', 'justificativa' => 'Atividade incompatível com a zona.']],
        ], $analista);

        $this->concluirAnalise($solicitacao, $analista, ConclusionOutcome::Indeferido);

        $contagem = $this->service()->contagem($this->periodo());

        $this->assertSame(1, $contagem['humana']);
        $this->assertSame(0, $contagem['semi_expressa']);
        $this->assertSame(0.0, $contagem['taxa_automacao']);
    }

    public function test_conclusao_automatica_revista_por_humano_sai_do_numerador_sem_duplicar(): void
    {
        $gestor = User::factory()->gestor()->create();
        $solicitacao = $this->concluidaPeloMotor();

        $this->concluirAnalise($solicitacao, $gestor, ConclusionOutcome::Indeferido);
        $this->concluirAnalise($solicitacao, $gestor, ConclusionOutcome::Deferido);

        $contagem = $this->service()->contagem($this->periodo());

        $this->assertSame(1, $contagem['total']);
        $this->assertSame(1, $contagem['revisada']);
        $this->assertSame(0, $contagem['automatica']);
    }

    public function test_filtro_de_categoria_dos_relatorios_usa_a_classificacao(): void
    {
        $analista = User::factory()->analistaServidor()->create();
        $automatica = $this->concluidaPeloMotor();
        $semi = $this->encaminhadaComDecisaoDoMotor();
        $this->concluirAnalise($semi, $analista, ConclusionOutcome::Deferido);

        $ids = fn (string $categoria): array => $this->service()
            ->aplicar(ViabilityRequest::query(), $categoria)
            ->pluck('id')
            ->all();

        $this->assertSame([$automatica->id], $ids(ClassificacaoFluxoService::AUTOMATICA));
        $this->assertSame([$semi->id], $ids(ClassificacaoFluxoService::SEMI_EXPRESSA));
    }

    public function test_produtividade_separa_decisoes_do_motor_e_analises_humanas(): void
    {
        $analista = User::factory()->analistaServidor()->create();

        $semConclusao = $this->encaminhadaComDecisaoDoMotor();
        $concluida = $this->encaminhadaComDecisaoDoMotor();
        $this->concluirAnalise($concluida, $analista, ConclusionOutcome::Deferido);

        // Atribuir não é analisar.
        $semConclusao->forceFill(['assigned_user_id' => $analista->id])->save();

        $produtividade = $this->service()->produtividadeSemiExpressa($this->periodo());

        $this->assertSame(2, $produtividade['decisoes_do_motor']);
        $this->assertSame(1, $produtividade['analises_humanas']);
        $this->assertSame([['analista_id' => $analista->id, 'analises' => 1]], $produtividade['por_analista']);
    }

    public function test_tela_de_produtividade_mostra_a_semi_expressa_com_o_gate_nominal(): void
    {
        $analista = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create(['name' => 'Ana Analista']);
        $outro = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create(['name' => 'Beto Analista']);
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $gestor->givePermissionTo('relatorios.produtividade.nominal');
        $analista->givePermissionTo('consultar-relatorios');

        $this->concluirAnalise($this->encaminhadaComDecisaoDoMotor(), $analista, ConclusionOutcome::Deferido);
        $this->concluirAnalise($this->encaminhadaComDecisaoDoMotor(), $outro, ConclusionOutcome::Deferido);

        $nominal = $this->actingAs($gestor, 'gestao')->get('/gestao/relatorios/produtividade')->assertOk()->viewData('page')['props']['semiExpressa'];
        $this->assertSame(2, $nominal['decisoes_do_motor']);
        $this->assertSame(2, $nominal['analises_humanas']);
        $this->assertEqualsCanonicalizing(['Ana Analista', 'Beto Analista'], array_column($nominal['por_analista'], 'rotulo'));

        $restrita = $this->actingAs($analista, 'gestao')->get('/gestao/relatorios/produtividade')->assertOk()->viewData('page')['props']['semiExpressa'];
        $this->assertSame(1, $restrita['analises_humanas']);
        $this->assertSame([['rotulo' => 'Você', 'analises' => 1]], $restrita['por_analista']);
    }
}
