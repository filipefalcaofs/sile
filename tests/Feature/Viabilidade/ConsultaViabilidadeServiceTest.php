<?php

namespace Tests\Feature\Viabilidade;

use App\Enums\GeoLayerType;
use App\Models\Activity;
use App\Models\GeoLayer;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\SpatialRepository;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Orquestrador da consulta prévia de viabilidade (HU-054 a HU-059): o
 * ConsultaViabilidadeService COMPÕE os motores reais (Geocoder → TerritoryService
 * → LouosEnquadramentoService → RiscoClassificationService) e AUDITA — sem
 * recomputar o veredito. A degradação honesta "sem zona → pendente" é verdade
 * única do motor LOUOS e aqui apenas PROPAGADA; a entrada por inscrição usa o
 * contrato PropertyRegistryLookup (resolve quando a base existir, degrada para a
 * via CNAE quando indisponível — nunca inventa ponto).
 *
 * Os motores rodam com SEEDS REAIS (Quadro 7 da Lei 9.148/2016, risco do Decreto
 * 32.636/2020 e da VISA); o ponto e o território são injetados por FAKES
 * (Geocoder + FakeSpatialRepository) para reproduzir os cenários sem PostGIS — o
 * território real (PostGIS) é coberto no fechamento 07-10.
 */
class ConsultaViabilidadeServiceTest extends TestCase
{
    use RefreshDatabase;

    private const CNAE_MINIMERCADO = '4712-1/00';

    private const CNAE_SOFTWARE_FORA_QUADRO7 = '6201-5/01';

    private const AVISO_ZONA_PENDENTE = 'Veredito locacional pendente: zona urbanística pendente da base oficial (SEDUR).';

    private const AVISO_CNAE_SEM_LOCAL = 'Consulta por CNAE não avalia o local: o veredito locacional depende do endereço/zona. Para a viabilidade locacional, consulte por endereço.';

    protected function setUp(): void
    {
        parent::setUp();

        // Carga REAL dos motores: Quadro 7 (enquadramento por área) e risco
        // municipal/sanitário (dimensões separadas). A lógica processa dados
        // reais — muda a carga, nunca o comportamento.
        $this->seed([
            LouosQuadro7Seeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
        ]);
    }

    private function service(): ConsultaViabilidadeService
    {
        return app(ConsultaViabilidadeService::class);
    }

    /**
     * Fake do geocoder: devolve um ponto fixo de Salvador para qualquer
     * endereço, sem chamar o provider real (Nominatim).
     */
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

    /**
     * Território com BAIRRO identificado e ZONA indisponível (base de zoneamento
     * pendente SEDUR): seeda a camada de bairro vigente (para o TerritoryService
     * consultar o fake) e deixa a zona sem camada → indisponível. Reproduz o
     * cenário real do projeto sem PostGIS.
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

    public function test_consulta_por_endereco_orquestra_motores_e_propaga_veredito(): void
    {
        $this->fakeGeocoder();
        $this->fakeTerritorioBairroSemZona();

        $result = $this->service()->consultarPorEndereco(
            'Praça Municipal, Centro, Salvador',
            self::CNAE_MINIMERCADO,
            120.0,
        );

        // Geocodificou (ponto real injetado) e identificou o bairro.
        $this->assertNotNull($result->geocode);
        $this->assertSame('identificado', $result->territory->bairro['status']);

        // Risco real classificado (Decreto 32.636/2020) e Quadro 7 enquadrado por área.
        $this->assertSame('classificado', $result->risco->municipal['status']);
        $this->assertSame('identificado', $result->enquadramento->quadro7['status']);

        // Veredito PROPAGADO do motor: sem zona → pendente (nunca recomputado aqui).
        $this->assertSame('pendente', $result->vereditoLocacional()['resultado']);

        // Aviso honesto de zona pendente (camada da UI).
        $this->assertContains(self::AVISO_ZONA_PENDENTE, $result->avisos);
    }

    public function test_sem_zona_o_veredito_e_pendente_e_o_motor_nao_inventa(): void
    {
        $this->fakeGeocoder();
        $this->fakeTerritorioBairroSemZona();

        $veredito = $this->service()
            ->consultarPorEndereco('Praça Municipal, Salvador', self::CNAE_MINIMERCADO, 120.0)
            ->vereditoLocacional();

        // Anti-fachada central: sem zona o motor JAMAIS declara permitido/não
        // permitido — o veredito é pendente.
        $this->assertSame('pendente', $veredito['resultado']);
        $this->assertNotSame('permitido', $veredito['resultado']);
        $this->assertNotSame('nao_permitido', $veredito['resultado']);

        // O motivo vem do motor LOUOS e cita a pendência da base de zoneamento.
        $motivo = mb_strtolower((string) $veredito['motivo']);
        $this->assertStringContainsString('pendente', $motivo);
        $this->assertStringContainsString('sedur', $motivo);
    }

    public function test_consulta_e_auditada_com_versoes(): void
    {
        $this->fakeGeocoder();
        $this->fakeTerritorioBairroSemZona();

        $this->service()->consultarPorEndereco('Praça Municipal, Salvador', self::CNAE_MINIMERCADO, 120.0);

        $activity = Activity::query()
            ->where('log_name', 'viabilidade')
            ->where('event', 'consulta')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('endereco', $activity->properties['tipo']);
        $this->assertSame('4712100', $activity->properties['cnae']);
        $this->assertSame('pendente', $activity->properties['veredito']);

        // Versões de TODAS as regras (RN-002/RN-004) registradas na auditoria.
        $versoes = $activity->properties['versoes'];
        $this->assertArrayHasKey('territorio', $versoes);
        $this->assertArrayHasKey('louos', $versoes);
        $this->assertArrayHasKey('risco', $versoes);
    }

    public function test_endereco_nao_localizado_propaga_excecao_e_nao_inventa_resultado(): void
    {
        // Sem fachada: endereço não localizado deve PROPAGAR a exceção (o
        // controller 07-06 a traduz), nunca virar um resultado falso.
        $this->app->instance(Geocoder::class, new class implements Geocoder
        {
            public function geocode(string $address): GeocodeResult
            {
                throw new AddressNotFoundException($address);
            }
        });
        $this->fakeTerritorioBairroSemZona();

        $this->expectException(AddressNotFoundException::class);

        $this->service()->consultarPorEndereco('Endereço inexistente', self::CNAE_MINIMERCADO, 120.0);
    }

    public function test_consulta_por_cnae_roda_risco_real_e_quadro7_sem_territorio(): void
    {
        // HU-056/CA-01: sem endereço/inscrição não há ponto — o risco e o Quadro
        // 7 por área rodam REAIS; o veredito locacional fica pendente (sem zona).
        $result = $this->service()->consultarPorCnae(self::CNAE_MINIMERCADO, 120.0);

        // Sem ponto: nenhum geocode e nenhum território (não inventa local).
        $this->assertNull($result->geocode);
        $this->assertNull($result->territory);

        // Risco real classificado e Quadro 7 enquadrado por área.
        $this->assertSame('classificado', $result->risco->municipal['status']);
        $this->assertSame('identificado', $result->enquadramento->quadro7['status']);

        // Veredito pendente (sem local) + aviso de que a consulta não avalia o local.
        $this->assertSame('pendente', $result->vereditoLocacional()['resultado']);
        $this->assertContains(self::AVISO_CNAE_SEM_LOCAL, $result->avisos);
    }

    public function test_consulta_por_cnae_fora_do_quadro7_fica_pendente_sem_inventar_grupo(): void
    {
        // CNAE classificado no risco municipal, porém SEM faixa no Quadro 7,
        // consultado SEM área: o motor devolve nao_encontrado (não inventa grupo)
        // e o veredito fica pendente — o risco continua real.
        $result = $this->service()->consultarPorCnae(self::CNAE_SOFTWARE_FORA_QUADRO7, null);

        $this->assertSame('nao_encontrado', $result->enquadramento->quadro7['status']);
        $this->assertNull($result->enquadramento->quadro7['grupo']);

        $this->assertSame('pendente', $result->vereditoLocacional()['resultado']);
        $this->assertSame('classificado', $result->risco->municipal['status']);
    }

    public function test_consulta_por_cnae_sem_area_nao_forca_veredito(): void
    {
        // Anti-fachada do caminho null→0.0: um CNAE enquadrável consultado SEM
        // área continua com veredito pendente (sem território) — JAMAIS um
        // permitido inventado — e o risco segue real.
        $result = $this->service()->consultarPorCnae(self::CNAE_MINIMERCADO, null);

        $veredito = $result->vereditoLocacional();
        $this->assertSame('pendente', $veredito['resultado']);
        $this->assertNotSame('permitido', $veredito['resultado']);
        $this->assertNotSame('nao_permitido', $veredito['resultado']);
        $this->assertSame('classificado', $result->risco->municipal['status']);
    }
}
