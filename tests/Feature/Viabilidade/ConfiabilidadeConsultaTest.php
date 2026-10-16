<?php

namespace Tests\Feature\Viabilidade;

use App\Enums\ConfiabilidadeConsulta;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\Parameter;
use App\Models\RuleVersion;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\SpatialRepository;
use App\Services\Viabilidade\ConfiabilidadeConsultaResolver;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Estado de confiabilidade da consulta prévia: só há veredito confiável quando o
 * local foi resolvido e todos os dados que o motor usa foram informados. O estado
 * é derivado do que o motor devolve, nunca de um critério paralelo.
 */
class ConfiabilidadeConsultaTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private const CNAE_MINIMERCADO = '4712-1/00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTratamentoPlanilha();
        $this->seed([RiscoMunicipalSeeder::class, RiscoSanitarioSeeder::class]);
        $this->fakeGeocoder();
    }

    private function fakeGeocoder(): void
    {
        $this->app->instance(Geocoder::class, new class implements Geocoder
        {
            public function geocode(string $address): GeocodeResult
            {
                return new GeocodeResult(
                    latitude: -12.9714,
                    longitude: -38.5014,
                    displayName: 'Salvador, Bahia, Brasil',
                    confidence: 0.9,
                    address: ['city' => 'Salvador', 'state' => 'Bahia'],
                );
            }
        });
    }

    private function territorioComZona(string $zona): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => $zona]]);
        $this->app->instance(SpatialRepository::class, $fake);

        $versao = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $versao->id,
            'zona' => $zona,
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
        ]);
    }

    private function territorioSemZona(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $this->app->instance(SpatialRepository::class, $fake);
    }

    private function resolver(ConsultaViabilidadeResult $result): array
    {
        return app(ConfiabilidadeConsultaResolver::class)->resolver($result)->toArray();
    }

    private function service(): ConsultaViabilidadeService
    {
        return app(ConsultaViabilidadeService::class);
    }

    public function test_endereco_com_zona_e_dados_completos_e_conclusiva(): void
    {
        $this->territorioComZona('ZR-1');

        $result = $this->service()->consultarPorEndereco('Praça da Sé', self::CNAE_MINIMERCADO, 120.0, [11 => true]);
        $confiabilidade = $this->resolver($result);

        $this->assertSame(ConfiabilidadeConsulta::Conclusiva->value, $confiabilidade['estado']);
        $this->assertSame([], $confiabilidade['pendencias']);
        $this->assertNotSame('pendente', $result->vereditoLocacional()['resultado']);
    }

    public function test_area_zero_em_endereco_e_incompleta_com_pendencia_area(): void
    {
        $this->territorioComZona('ZR-1');

        $result = $this->service()->consultarPorEndereco('Praça da Sé', self::CNAE_MINIMERCADO, 0.0, [11 => true]);
        $confiabilidade = $this->resolver($result);

        $this->assertSame(ConfiabilidadeConsulta::Incompleta->value, $confiabilidade['estado']);
        $this->assertContains('area', array_column($confiabilidade['pendencias'], 'campo'));
    }

    public function test_pergunta_da_planilha_sem_resposta_e_incompleta(): void
    {
        $this->territorioComZona('ZR-1');

        $result = $this->service()->consultarPorEndereco('Praça da Sé', self::CNAE_MINIMERCADO, 120.0, []);
        $confiabilidade = $this->resolver($result);

        $this->assertSame(ConfiabilidadeConsulta::Incompleta->value, $confiabilidade['estado']);
        $pendencia = collect($confiabilidade['pendencias'])->firstWhere('campo', 'pergunta');
        $this->assertNotNull($pendencia);
        $this->assertNotSame('', $pendencia['rotulo']);
    }

    public function test_zona_nao_identificada_e_inconclusiva_com_motivo(): void
    {
        $this->territorioSemZona();

        $result = $this->service()->consultarPorEndereco('Praça da Sé', self::CNAE_MINIMERCADO, 120.0, [11 => true]);
        $confiabilidade = $this->resolver($result);

        $this->assertSame(ConfiabilidadeConsulta::Inconclusiva->value, $confiabilidade['estado']);
        $this->assertNotEmpty($confiabilidade['motivo']);
    }

    public function test_cnae_invalido_ou_sem_regra_e_inconclusiva(): void
    {
        $this->territorioComZona('ZR-1');

        foreach (['123', '9999-9/99'] as $cnae) {
            $result = $this->service()->consultarPorEndereco('Praça da Sé', $cnae, 120.0, [11 => true]);

            $this->assertNotSame(
                ConfiabilidadeConsulta::Conclusiva->value,
                $this->resolver($result)['estado'],
                "CNAE {$cnae} não pode terminar conclusivo",
            );
        }
    }

    public function test_cnae_com_area_e_respostas_completas_nunca_e_conclusiva(): void
    {
        $result = $this->service()->consultarPorCnae(self::CNAE_MINIMERCADO, 120.0, null, [11 => true]);
        $confiabilidade = $this->resolver($result);

        $this->assertSame(ConfiabilidadeConsulta::Inconclusiva->value, $confiabilidade['estado']);
    }

    public function test_cnae_sem_area_continua_sem_pendencia_de_area(): void
    {
        $result = $this->service()->consultarPorCnae(self::CNAE_MINIMERCADO, null, null, [11 => true]);

        $this->assertNotContains('area', array_column($this->resolver($result)['pendencias'], 'campo'));
    }

    public function test_vagas_exigidas_sem_declaracao_e_incompleta_com_itens(): void
    {
        $this->territorioComZona('ZR-1');
        Parameter::query()->create([
            'key' => 'louos.vagas.exigencia_por_grupo',
            'group' => 'louos',
            'type' => 'json',
            'value' => json_encode(['nR1' => ['vagas' => 5]]),
            'default_value' => '{}',
            'validation_rules' => ['required', 'json'],
            'description' => 'Exigência de vagas por grupo de uso (HU-042).',
        ]);

        $result = $this->service()->consultarPorEndereco('Praça da Sé', self::CNAE_MINIMERCADO, 120.0, [11 => true]);
        $confiabilidade = $this->resolver($result);

        $this->assertSame(ConfiabilidadeConsulta::Incompleta->value, $confiabilidade['estado']);
        $vagas = collect($confiabilidade['pendencias'])->firstWhere('campo', 'vagas');
        $this->assertSame(['vagas'], $vagas['itens']);
    }

    public function test_confiabilidade_e_calculada_uma_vez_e_guardada_no_resultado(): void
    {
        $this->territorioComZona('ZR-1');

        $result = $this->service()->consultarPorEndereco('Praça da Sé', self::CNAE_MINIMERCADO, 120.0, [11 => true]);

        $this->assertSame($result->confiabilidade(), $result->confiabilidade());
        $this->assertSame($result->confiabilidade()->toArray(), $result->toArray()['confiabilidade']);
    }
}
