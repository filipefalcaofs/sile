<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\SpatialRepository;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Simulação pré-protocolo (HU-141): o SimulacaoSolicitacaoService itera os CNAEs
 * da solicitação e chama o ConsultaViabilidadeService (Fase 7) PELO PONTO da
 * própria solicitação (centroide do polígono — sem geocodificar de novo),
 * PROPAGANDO o veredito do motor LOUOS (sem decisão paralela, RN-001). Persiste
 * o snapshot + versões + resultado e marca simulated_at (RN-003); é orientativa
 * e NÃO bloqueia o protocolo (RN-002); o toggle features.simulacao_solicitacao
 * degrada sem falha (RN-005). Sem zona oficial, o veredito por CNAE fica
 * "pendente" (propagado) — nunca permitido/não permitido inventado.
 *
 * Os motores rodam com SEEDS REAIS (Quadro 7 da Lei 9.148/2016, risco do Decreto
 * 32.636/2020 e da VISA); o território é injetado por FAKE (FakeSpatialRepository)
 * para reproduzir o cenário "bairro identificado, zona pendente" sem PostGIS.
 */
class SimulacaoSolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    private const CNAE_MINIMERCADO = '4712-1/00';

    protected function setUp(): void
    {
        parent::setUp();

        // Carga REAL dos motores da Fase 7 (mesma versão de regras do fluxo
        // oficial — RN-001): Quadro 7 (enquadramento por área) e risco
        // municipal/sanitário (dimensões separadas).
        $this->seed([
            LouosQuadro7Seeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
        ]);
    }

    /**
     * Território com BAIRRO identificado e ZONA indisponível (base de zoneamento
     * pendente SEDUR): seeda a camada de bairro vigente e deixa a zona sem
     * camada → indisponível → veredito pendente (propagado do motor LOUOS).
     */
    private function fakeTerritorioBairroSemZona(): FakeSpatialRepository
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

        return $fake;
    }

    public function test_motor_expoe_consulta_por_ponto_conhecido_propagando_veredito(): void
    {
        // Refactor mínimo da Fase 7: a solicitação JÁ tem o ponto (centroide do
        // polígono), então o motor expõe uma entrada por ponto+CNAE que NÃO
        // geocodifica de novo e propaga o veredito do motor LOUOS.
        $this->fakeTerritorioBairroSemZona();

        $result = app(ConsultaViabilidadeService::class)
            ->consultarPorPontoConhecido(-12.9710, -38.5107, self::CNAE_MINIMERCADO, 120.0);

        // Identificou o território a partir do ponto, sem geocodificar.
        $this->assertNull($result->geocode);
        $this->assertNotNull($result->territory);
        $this->assertSame('identificado', $result->territory->bairro['status']);

        // Risco real (Decreto 32.636/2020) + Quadro 7 por área; veredito
        // PROPAGADO (pendente sem zona — nunca recomputado aqui).
        $this->assertSame('classificado', $result->risco->municipal['status']);
        $this->assertSame('identificado', $result->enquadramento->quadro7['status']);
        $this->assertSame('pendente', $result->vereditoLocacional()['resultado']);
        $this->assertSame('ponto', $result->entrada['tipo']);
    }
}
