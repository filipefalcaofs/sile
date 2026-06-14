<?php

namespace Tests\Feature\Expresso;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Golden cases do MOTOR de decisão do fluxo expresso (fechamento da Fase 9):
 * casos-âncora entrada→esperado declarados como fixtures JSON e executados pelo
 * SERVIÇO REAL (FluxoExpressoService::decide) sobre dados versionados por
 * factory (Quadro 7/10 da LOUOS, risco do Decreto 32.636/2020) + território por
 * FAKE — a mesma lógica do fluxo oficial, sem PostGIS. Via #[DataProvider],
 * espelhando o padrão golden das Fases 5/6/7/8.
 *
 * Trava a regressão de domínio dos TRÊS caminhos reais e da degradação honesta:
 * permitido → DEFERE (+TVL +evento); permitido_condicionado → DEFERE; proibido →
 * INDEFERE (sem TVL, com evento); sem zona → em_analise SEM decisão e SEM evento
 * (anti-fachada); risco alto (semi-expresso) → em_analise. Se o mapeamento
 * RN-009, a consolidação ou a elegibilidade mudarem, o caso âncora falha com o
 * nome do golden.
 */
class ExpressoGoldenCaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Carrega cada fixture entrada→esperado de
     * tests/Fixtures/golden/expresso/*.json. Resolve o caminho por __DIR__
     * (o provider roda antes do boot da app).
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function goldenCases(): array
    {
        $casos = [];

        foreach (glob(dirname(__DIR__, 2).'/Fixtures/golden/expresso/*.json') as $arquivo) {
            /** @var array<string, mixed> $caso */
            $caso = json_decode((string) file_get_contents($arquivo), true, 512, JSON_THROW_ON_ERROR);
            $casos[$caso['nome']] = [$caso];
        }

        return $casos;
    }

    /**
     * @param  array<string, mixed>  $caso
     */
    #[DataProvider('goldenCases')]
    public function test_golden_case(array $caso): void
    {
        Event::fake([ResultadoEmitido::class]);

        $this->montarCenario($caso);
        $request = $this->protocoladaComCnae((string) $caso['cnae']);

        $result = app(FluxoExpressoService::class)->decide($request);

        $esperado = $caso['esperado'];
        $contexto = "Golden case '{$caso['nome']}'";

        $this->assertSame($esperado['status'], $result->status->value, "{$contexto}: status divergente.");

        if ($esperado['outcome'] === null) {
            // Encaminhamento à análise: sem decisão e (anti-fachada) sem evento.
            $this->assertNull($result->decision, "{$contexto}: não pode haver decisão na análise.");
            $this->assertSame(0, ViabilityRequest::find($request->id)->decision()->count());
            $this->assertDatabaseCount('viability_decisions', 0);
            Event::assertNotDispatched(ResultadoEmitido::class);

            return;
        }

        $decision = $request->fresh()->decision;
        $this->assertNotNull($decision, "{$contexto}: esperava uma decisão.");
        $this->assertSame($esperado['outcome'], $decision->outcome->value, "{$contexto}: outcome divergente.");
        $this->assertSame($esperado['consolidado'], $decision->consolidated_result, "{$contexto}: consolidado divergente.");
        $this->assertSame($esperado['tem_tvl'], $decision->tvl_product_number !== null, "{$contexto}: presença de TVL divergente.");

        if ($esperado['evento_emitido'] === true) {
            Event::assertDispatched(ResultadoEmitido::class);
        } else {
            Event::assertNotDispatched(ResultadoEmitido::class);
        }
    }

    /**
     * Monta o cenário declarado: território (com/sem zona), risco municipal,
     * Quadro 7 (sempre) e Quadro 10 (só com zona).
     *
     * @param  array<string, mixed>  $caso
     */
    private function montarCenario(array $caso): void
    {
        $cnae = (string) $caso['cnae'];

        if ($caso['zona'] === null) {
            $this->fakeBairroSemZona();
        } else {
            $this->fakeBairroComZona((string) $caso['zona']);
            $this->seedQuadro10(
                (string) $caso['zona'],
                (string) $caso['grupo'],
                Quadro10Permissao::from((string) $caso['permissao']),
            );
        }

        $this->classificarMunicipal($cnae, $this->nivelRisco((string) $caso['risco']));
        $this->seedQuadro7($cnae, (string) $caso['grupo'], (string) $caso['subgrupo']);
    }

    private function nivelRisco(string $nivel): RiscoMunicipal
    {
        return match ($nivel) {
            'baixo_a' => RiscoMunicipal::BaixoA,
            'baixo_b' => RiscoMunicipal::BaixoB,
            'alto' => RiscoMunicipal::Alto,
            default => throw new \InvalidArgumentException("Nível de risco desconhecido no golden: {$nivel}"),
        };
    }

    private function protocoladaComCnae(string $code): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $cnae = Cnae::factory()->create(['code' => $code]);
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        return $solicitacao;
    }

    private function versaoRiscoMunicipal(): RuleVersion
    {
        return RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);
    }

    private function classificarMunicipal(string $cnae, RiscoMunicipal $nivel): void
    {
        RiskClassification::factory()->create([
            'rule_version_id' => $this->versaoRiscoMunicipal()->id,
            'cnae_code' => $cnae,
            'risco_municipal' => $nivel,
        ]);
    }

    private function fakeBairroComZona(string $zona): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => $zona]]);

        $this->app->instance(SpatialRepository::class, $fake);
    }

    private function fakeBairroSemZona(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);

        $this->app->instance(SpatialRepository::class, $fake);
    }

    private function seedQuadro7(string $cnae, string $grupo, string $subgrupo): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro7,
                'version' => 'lei-9148-2016-quadro7',
                'rules_version' => 'lei-9148-2016-quadro7',
            ]);

        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => $cnae,
            'grupo' => $grupo,
            'subgrupo' => $subgrupo,
            'area_min' => 0,
            'area_max' => null,
        ]);
    }

    private function seedQuadro10(string $zona, string $grupo, Quadro10Permissao $permissao): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => $zona,
            'grupo_uso' => $grupo,
            'subgrupo' => '',
            'permissao' => $permissao,
            'condicionante_ref' => $permissao === Quadro10Permissao::PermitidoCondicionado ? 'CU-01' : null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);
    }
}
