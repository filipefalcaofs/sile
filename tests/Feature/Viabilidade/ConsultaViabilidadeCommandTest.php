<?php

namespace Tests\Feature\Viabilidade;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\SpatialRepository;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Comando viabilidade:consultar (fechamento da Fase 7): exercita o ORQUESTRADOR
 * REAL (ConsultaViabilidadeService) sobre o SEED OFICIAL (Quadro 7 da Lei
 * 9.148/2016 + risco do Decreto 32.636/2020 e da VISA), imprimindo o parecer
 * fundamentado de ponta a ponta — evidência sem fachada, espelhando os comandos
 * louos:enquadrar / risco:classificar.
 *
 * Honestidade comprovada: sem zona oficial o veredito é `pendente` (nunca
 * permitido/não permitido); a inscrição imobiliária degrada com aviso (base de
 * lotes pendente SEDUR), sem inventar ponto; CNAE de formato inválido sai com
 * erro (exit 1); o pendente NÃO é erro (exit 0).
 */
class ConsultaViabilidadeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Carga REAL dos motores: a lógica processa o dado oficial — muda a
        // carga, nunca o comportamento (anti-fachada).
        $this->seed([
            LouosQuadro7Seeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
        ]);
    }

    public function test_comando_por_cnae_imprime_parecer_real(): void
    {
        // 4712-1/00 (minimercado): Baixo Risco B no Decreto (→ expresso) e
        // enquadrado por área no Quadro 7. Sem endereço/inscrição não há zona,
        // então o veredito é pendente — o motor jamais inventa permissão.
        $this->artisan('viabilidade:consultar', ['cnae' => '4712-1/00', '--area' => '120'])
            ->expectsOutputToContain('Baixo Risco B')
            ->expectsOutputToContain('Fluxo expresso')
            ->expectsOutputToContain('Pendente de análise técnica')
            ->expectsOutputToContain('Versões de regras')
            ->assertSuccessful();
    }

    public function test_comando_cnae_invalido_falha(): void
    {
        $this->artisan('viabilidade:consultar', ['cnae' => '123'])
            ->expectsOutputToContain('inválido')
            ->assertFailed();
    }

    public function test_comando_por_inscricao_degrada_com_aviso(): void
    {
        // Base de lotes pendente SEDUR (binding real UnavailablePropertyRegistryLookup):
        // a resolução por inscrição degrada para a via CNAE com aviso honesto e
        // veredito pendente — NUNCA inventa coordenada (exit 0, pendente não é erro).
        $this->artisan('viabilidade:consultar', ['cnae' => '4712-1/00', '--inscricao' => '123456', '--area' => '120'])
            ->expectsOutputToContain('Resolução por inscrição imobiliária indisponível')
            ->expectsOutputToContain('Pendente de análise técnica')
            ->assertSuccessful();
    }

    public function test_comando_por_endereco_geocodifica_e_fica_pendente_sem_zona(): void
    {
        // Caminho --endereco com geocoder fake (sem chamar Nominatim) e território
        // com bairro identificado mas SEM zona (base de zoneamento pendente SEDUR):
        // geocodifica de verdade, mas o veredito fica pendente com aviso de zona.
        $this->fakeGeocoder();
        $this->fakeTerritorioBairroSemZona();

        $this->artisan('viabilidade:consultar', [
            'cnae' => '4712-1/00',
            '--endereco' => 'Praça Municipal, Centro, Salvador',
            '--area' => '120',
        ])
            ->expectsOutputToContain('zona urbanística pendente')
            ->expectsOutputToContain('Pendente de análise técnica')
            ->assertSuccessful();
    }

    /**
     * Geocoder fake: devolve um ponto fixo de Salvador para qualquer endereço,
     * sem chamar o provider real (Nominatim).
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
     * pendente SEDUR): seeda a camada de bairro vigente e deixa a zona sem camada
     * → indisponível. Reproduz o cenário real do projeto sem PostGIS.
     */
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
}
