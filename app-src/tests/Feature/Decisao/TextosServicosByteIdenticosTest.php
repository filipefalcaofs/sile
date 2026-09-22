<?php

namespace Tests\Feature\Decisao;

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
use App\Models\ViabilityRequest;
use App\Services\Analise\PreAnaliseService;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\SpatialRepository;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Golden de byte-identidade dos textos decisórios dos services de consulta,
 * risco, pré-análise e justificativa (Fase 4, Task 3): congela as strings
 * EXATAS emitidas hoje, exercitadas pelos caminhos públicos reais. Escrito
 * ANTES da migração das constantes/templates para o catálogo `decision_texts`
 * — verde no código não migrado prova que o golden está correto; verde depois
 * prova que a migração não mudou uma vírgula.
 */
class TextosServicosByteIdenticosTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private const CNAE_MINIMERCADO = '4712-1/00';

    public function test_aviso_de_consulta_por_cnae_e_byte_identico(): void
    {
        $this->seedMotoresReais();

        $result = app(ConsultaViabilidadeService::class)->consultarPorCnae(self::CNAE_MINIMERCADO, 120.0);

        $this->assertContains(
            'Consulta por CNAE não avalia o local: o veredito locacional depende do endereço/zona. Para a viabilidade locacional, consulte por endereço.',
            $result->avisos,
        );
    }

    public function test_aviso_de_inscricao_indisponivel_e_byte_identico(): void
    {
        $this->seedMotoresReais();

        $result = app(ConsultaViabilidadeService::class)->consultarPorInscricao('123456789', self::CNAE_MINIMERCADO, 120.0);

        $this->assertContains(
            'Resolução por inscrição imobiliária indisponível (base de lotes pendente SEDUR). Resultado sem análise territorial; consulte por endereço para o veredito locacional.',
            $result->avisos,
        );
    }

    public function test_aviso_de_zona_pendente_e_byte_identico(): void
    {
        $this->seedMotoresReais();
        $this->fakeGeocoder();
        $this->fakeTerritorioBairroSemZona();

        $result = app(ConsultaViabilidadeService::class)->consultarPorEndereco(
            'Praça Municipal, Centro, Salvador',
            self::CNAE_MINIMERCADO,
            120.0,
        );

        $this->assertContains(
            'Veredito locacional pendente: zona urbanística pendente da base oficial (SEDUR).',
            $result->avisos,
        );
    }

    public function test_fundamentacao_do_risco_cita_o_decreto_byte_identico(): void
    {
        // Relatório SEDUR 21/09 (itens 08–09): o decreto vigente citado é o
        // 41.758/2026. A chave da versão segue a proveniência do dado; a
        // citação legal é texto administrável (Textos decisórios).
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-41758-2026',
            'rules_version' => 'decreto-41758-2026',
        ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => '1234567',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $result = app(RiscoClassificationService::class)->classify(RiscoInput::paraCnae('1234567'));

        $this->assertSame('classificado', $result->municipal['status']);
        $this->assertSame('Decreto Municipal nº 41.758/2026', $result->fundamentacao[0]);
    }

    public function test_parecer_da_pre_analise_nasce_em_branco(): void
    {
        // Relatório SEDUR 21/09, item 04: o parecer técnico é do analista — a
        // pré-análise não o preenche mais. A fundamentação do motor permanece na
        // justificativa por atividade (coberta pelos testes byte-idênticos dela).
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['4712100']);

        $record = app(PreAnaliseService::class)->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertTrue($record->engine_available);
        $this->assertNull($record->parecer);
    }

    /**
     * Carga REAL dos motores da consulta (Quadro 7 + risco municipal/sanitário)
     * — mesmo padrão do ConsultaViabilidadeServiceTest.
     */
    private function seedMotoresReais(): void
    {
        $this->seed([
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
        ]);
    }

    private function fakeGeocoder(float $lat = -12.9714, float $lng = -38.5014): void
    {
        $this->app->instance(Geocoder::class, new class($lat, $lng) implements Geocoder
        {
            public function __construct(private float $lat, private float $lng) {}

            public function geocode(string $address): GeocodeResult
            {
                return new GeocodeResult(
                    latitude: $this->lat,
                    longitude: $this->lng,
                    displayName: 'Salvador, Bahia, Brasil',
                    confidence: 0.9,
                    address: ['city' => 'Salvador', 'state' => 'Bahia'],
                );
            }
        });
    }

    private function fakeTerritorioBairroSemZona(): void
    {
        GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'bairro-2024',
        ]);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, [
            'id' => 1,
            'properties' => ['NOME_BAIRRO' => 'Comércio'],
        ]);

        $this->app->instance(SpatialRepository::class, $fake);
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

    private function classificarMunicipal(string $cnae, RiscoMunicipal $nivel): void
    {
        $version = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);

        RiskClassification::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => $cnae,
            'risco_municipal' => $nivel,
        ]);
    }

    private function seedTratamento(string $cnae = '', string $grupo = '', string $subgrupo = ''): void
    {
        $this->seedTratamentoPlanilha();
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
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);
    }

    /**
     * @param  list<string>  $cnaeCodes
     */
    private function emAnaliseComCnaes(array $cnaeCodes): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $solicitacao->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        foreach (array_values($cnaeCodes) as $indice => $code) {
            $cnae = Cnae::factory()->create(['code' => $code]);
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        $solicitacao->respostasTratamento = [11 => true];

        return $solicitacao;
    }
}
