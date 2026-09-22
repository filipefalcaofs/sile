<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\Parameter;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * RN-C-04 (encaminhamento): a solicitação encaminhada à análise pelo gatilho de
 * sede de escritório virtual precisa chegar à fila com o motivo sendo o texto
 * exato da flag do §2º do art. 6º do Decreto Municipal nº 35.062/2021 — é o
 * que a operação lê na fila, e não a descrição interna do gatilho.
 */
class FlagAnaliseSedeTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    public function test_encaminha_a_analise_com_o_texto_da_flag_do_decreto_como_motivo(): void
    {
        $request = $this->protocoladaElegivelParaExpressoComSede();

        app(FluxoExpressoService::class)->decide($request);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);

        $activity = Activity::query()
            ->where('log_name', 'expresso')
            ->where('event', 'decisao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(
            'Verificar se atende ao §2º do artigo 6º do Decreto Municipal nº 35.062, de 29 de dezembro de 2021.',
            $activity->properties['motivo'],
        );
    }

    public function test_texto_da_flag_e_parametrizavel(): void
    {
        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'analise.escritorio_virtual.flag_analise_sede')->first()
            ->update(['value' => 'Verificar condição excepcional de sede (teste).']);

        $request = $this->protocoladaElegivelParaExpressoComSede();

        app(FluxoExpressoService::class)->decide($request);

        $activity = Activity::query()
            ->where('log_name', 'expresso')
            ->where('event', 'decisao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('Verificar condição excepcional de sede (teste).', $activity->properties['motivo']);
    }

    /**
     * Mesmo cenário de app/Services/Expresso/FluxoExpressoService.php's
     * decisão (permitido/Quadro10/Quadro7/risco baixo) de
     * tests/Feature/Analise/EncaminhamentoAnaliseTest::protocoladaDeferivel,
     * mas com o CNAE gatilho da sede + wants_virtual_office_hq=true (copiado de
     * GatilhoSedeTest::protocoladaElegivelParaExpressoComSede).
     */
    private function protocoladaElegivelParaExpressoComSede(string $cnae = '8211300'): ViabilityRequest
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZR-1']]);
        $this->app->instance(SpatialRepository::class, $fake);

        $versaoRisco = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $versaoRisco->id,
            'cnae_code' => $cnae,
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $this->seedTratamentoPlanilha();

        $versaoQuadro10 = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);
        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $versaoQuadro10->id,
            'zona' => 'ZR-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'used_area_m2' => 120.0,
            'wants_virtual_office_hq' => true,
        ]);
        $cnaeModel = Cnae::factory()->create(['code' => $cnae]);
        $solicitacao->cnaes()->attach($cnaeModel->id, ['is_primary' => true]);
        $solicitacao->respostasTratamento = [4 => true, 5 => true];

        return $solicitacao;
    }
}
