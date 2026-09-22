<?php

namespace Tests\Feature\Analise;

use App\Enums\GeoLayerType;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\Parameter;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Fluxo REAL de tramitação da análise técnica, de ponta a ponta (2026-09-20):
 *
 * motor (encaminha para análise) → caixa do setor (setor de triagem
 * parametrizado, analise.setor_triagem_id) → Apoio (tramita para um analista
 * específico) → caixa do analista (fila "meus") → abrir processo → abrir a
 * ficha de análise.
 *
 * Nenhum caminho paralelo: cada etapa usa as rotas, permissões e serviços de
 * produção (FluxoExpressoService, CaixaSetorController, DistribuicaoService,
 * ProcessoController, AnalysisRecordController). Os motores rodam em SQLite
 * com FakeSpatialRepository — mesma lógica do fluxo oficial.
 */
class FluxoApoioTramitacaoTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuarioDoSetor(string $papel, Sector $sector): User
    {
        $user = User::factory()->{$papel}()->withAcceptedLgpdTerm()->create();
        $user->sectors()->attach($sector);

        return $user;
    }

    /**
     * Território com BAIRRO identificado e ZONA indisponível (Quadro 10 pendente
     * SEDUR) → veredito locacional pendente: o expresso encaminha à análise sem
     * decidir (anti-fachada da Fase 9).
     */
    private function fakeBairroSemZona(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);

        $this->app->instance(SpatialRepository::class, $fake);
    }

    private function protocoladaQueExigeAnalise(): ViabilityRequest
    {
        $versao = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);

        RiskClassification::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => '2222222',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $cnae = Cnae::factory()->create(['code' => '2222222']);
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);
        $solicitacao->respostasTratamento = [11 => true];

        return $solicitacao;
    }

    public function test_motor_caixa_do_setor_apoio_analista_ficha_de_ponta_a_ponta(): void
    {
        // 1. Setor de triagem parametrizado (HU-014) + apoio e analista do setor.
        $setor = Sector::factory()->create(['name' => 'Análise Locacional', 'active' => true]);
        Parameter::factory()->create([
            'key' => 'analise.setor_triagem_id',
            'group' => 'analise',
            'type' => 'integer',
            'value' => (string) $setor->id,
            'default_value' => null,
            'validation_rules' => ['nullable', 'integer', 'exists:sectors,id'],
        ]);
        $apoio = $this->usuarioDoSetor('apoio', $setor);
        $analista = $this->usuarioDoSetor('analista', $setor);

        // 2. O motor identifica que o processo precisa de análise.
        $this->fakeBairroSemZona();
        $request = $this->protocoladaQueExigeAnalise();

        app(FluxoExpressoService::class)->decide($request);

        // 3. O processo cai na CAIXA DO SETOR — sem analista atribuído.
        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);
        $this->assertSame($setor->id, $fresh->sector_id, 'O motor deve depositar o processo na caixa do setor de triagem.');
        $this->assertNull($fresh->assigned_user_id, 'O motor não escolhe analista — a distribuição é humana.');

        // 4. O Apoio vê o processo na caixa do setor.
        $caixa = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $idsCaixa = collect($caixa->viewData('page')['props']['processos']['data'])->pluck('id')->all();
        $this->assertContains($request->id, $idsCaixa, 'O processo deve aparecer na caixa do setor para o Apoio.');

        // 5. O Apoio tramita o processo para o analista específico.
        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$request->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame(
            $analista->id,
            $request->fresh()->assigned_user_id,
            'A tramitação do Apoio deve fixar o analista responsável.',
        );

        // 6. O processo aparece na caixa do analista (fila "meus").
        $fila = $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila')
            ->assertOk();

        $idsFila = collect($fila->viewData('page')['props']['processos'])->pluck('id')->all();
        $this->assertContains($request->id, $idsFila, 'O processo distribuído deve aparecer na fila do analista.');

        // 7. O analista abre o processo.
        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$request->id}")
            ->assertOk();

        // 8. O analista abre a ficha de análise — pré-preenchida pelo motor.
        $ficha = $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$request->id}/ficha")
            ->assertOk();

        $page = $ficha->viewData('page');
        $this->assertSame('gestao/ficha-analise/show', $page['component']);
        $this->assertSame($request->id, $page['props']['processo']['id']);
        $this->assertNotNull($page['props']['ficha']['id'], 'A ficha de análise deve estar disponível.');
        $this->assertNotEmpty($page['props']['ficha']['per_cnae'], 'A ficha deve trazer os CNAEs pré-analisados pelo motor.');
    }

    public function test_apoio_nao_abre_a_ficha_nem_assume(): void
    {
        // O Apoio tramita, mas não analisa: a ficha segue gated por
        // analisar-processos e o assumir também (403 auditado no ponto único).
        $setor = Sector::factory()->create(['active' => true]);
        $apoio = $this->usuarioDoSetor('apoio', $setor);

        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
        ])->save();

        $this->actingAs($apoio, 'gestao')
            ->get("/gestao/processos/{$request->id}/ficha")
            ->assertForbidden();

        $this->actingAs($apoio, 'gestao')
            ->post("/gestao/caixa-setor/{$request->id}/assumir")
            ->assertForbidden();
    }
}
