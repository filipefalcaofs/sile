<?php

namespace Tests\Support;

use App\Enums\ConclusionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\ConclusaoAnaliseService;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Tests\Support\Geo\FakeSpatialRepository;

/**
 * Processos levados à conclusão pelos serviços reais (motor, pré-análise e
 * conclusão da análise), para os indicadores que classificam como cada um
 * foi concluído. A zona ZR-1 permite o grupo nR1; 4712-1/00 é de baixo risco
 * (o motor conclui) e 4639-7/02 é de risco alto (o motor decide e encaminha à
 * análise).
 */
trait MontaFluxosDeConclusao
{
    use SeedsTratamentoPlanilha;

    protected const CNAE_BAIXO_RISCO = '4712100';

    protected const CNAE_ALTO_RISCO = '4639702';

    private int $seqProtocolo = 0;

    protected function cenarioZonaPermitida(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZR-1']]);
        $this->app->instance(SpatialRepository::class, $fake);

        $quadro10 = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'lei-9148-2016-quadro10',
            'rules_version' => 'lei-9148-2016-quadro10',
        ]);
        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $quadro10->id,
            'zona' => 'ZR-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);

        $risco = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
            'rules_version' => 'decreto-32636-2020',
        ]);

        foreach ([self::CNAE_BAIXO_RISCO => RiscoMunicipal::BaixoB, self::CNAE_ALTO_RISCO => RiscoMunicipal::Alto] as $codigo => $nivel) {
            Cnae::factory()->create(['code' => $codigo]);
            RiskClassification::factory()->create(['rule_version_id' => $risco->id, 'cnae_code' => $codigo, 'risco_municipal' => $nivel]);
        }

        $this->seedTratamentoPlanilha();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function protocoladaComCnae(string $cnae, array $atributos = []): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'used_area_m2' => 120.0,
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seqProtocolo, 6, '0', STR_PAD_LEFT),
            ...$atributos,
        ]);
        $solicitacao->cnaes()->attach(Cnae::query()->where('code', $cnae)->value('id'), ['is_primary' => true]);
        $solicitacao->respostasTratamento = [11 => true];

        return $solicitacao;
    }

    /** O motor conclui: deferimento expresso. */
    protected function concluidaPeloMotor(?User $ator = null, array $atributos = []): ViabilityRequest
    {
        $solicitacao = $this->protocoladaComCnae(self::CNAE_BAIXO_RISCO, $atributos);
        app(FluxoExpressoService::class)->decide($solicitacao, $ator);

        $this->assertSame(ViabilityRequestStatus::Deferida, $solicitacao->fresh()->status);

        return $solicitacao->fresh();
    }

    /** Risco alto: o motor não conclui, encaminha à análise e deixa a decisão dele na ficha. */
    protected function encaminhadaComDecisaoDoMotor(array $atributos = []): ViabilityRequest
    {
        $solicitacao = $this->protocoladaComCnae(self::CNAE_ALTO_RISCO, $atributos);
        app(FluxoExpressoService::class)->decide($solicitacao);

        $solicitacao = $solicitacao->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $solicitacao->status);
        $this->assertTrue($solicitacao->analysisRecords()->where('engine_available', true)->exists());

        return $solicitacao;
    }

    protected function concluirAnalise(ViabilityRequest $solicitacao, User $ator, ConclusionOutcome $resultado): void
    {
        app(ConclusaoAnaliseService::class)->concluir($solicitacao->fresh(), $ator, $resultado, 'Parecer técnico da análise.', confirmarDivergencia: true);
    }
}
