<?php

namespace Tests\Feature\Viabilidade;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\SpatialRepository;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Golden cases da consulta prévia de viabilidade (critérios 1, 2 e 3 do ROADMAP
 * — Fase 7): casos entrada→esperado declarados como fixtures JSON e executados
 * pelo ORQUESTRADOR REAL (ConsultaViabilidadeService) sobre o SEED OFICIAL
 * (Quadro 7 da Lei 9.148/2016 + risco do Decreto 32.636/2020 e da VISA), via
 * #[DataProvider]. É a proteção de regressão de domínio da consulta: se o dado
 * oficial, os motores ou a orquestração mudarem e divergirem do esperado, o caso
 * âncora falha com o nome do golden case.
 *
 * Degradação honesta (anti-fachada): os casos provam que sem zona oficial o
 * veredito é `pendente` (NUNCA permitido/não permitido) e que a inscrição
 * imobiliária indisponível degrada com aviso, sem inventar ponto. Para a entrada
 * por endereço, o ponto e o território são injetados por FAKES (Geocoder +
 * FakeSpatialRepository sem zona) — o território PostGIS real é coberto no grupo
 * postgis; o motor é tabular e roda em SQLite (:memory:).
 */
class ConsultaViabilidadeGoldenCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed OFICIAL: os golden cases batem contra o dado real (Quadro 7 da Lei
        // 9.148/2016 e risco do Decreto 32.636/2020 + VISA), não fixtures
        // sintéticos do dado (anti-fachada).
        $this->seed([
            LouosQuadro7Seeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
        ]);
    }

    /**
     * Data provider: carrega cada fixture entrada→esperado de
     * tests/Fixtures/golden/viabilidade/*.json. Não usa o container (base_path)
     * porque o provider roda antes do boot da app — resolve o caminho por __DIR__.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function goldenCases(): array
    {
        $casos = [];

        foreach (glob(dirname(__DIR__, 2).'/Fixtures/golden/viabilidade/*.json') as $arquivo) {
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
        $nome = (string) $caso['nome'];

        /** @var array<string, mixed> $input */
        $input = $caso['input'];

        // Entrada por endereço usa o ponto/território injetados (sem PostGIS); a
        // zona fica indisponível (base pendente SEDUR) — o veredito degrada sozinho.
        if (($input['tipo'] ?? null) === 'endereco') {
            $this->fakeGeocoder();
            $this->fakeTerritorioBairroSemZona();
        }

        $result = $this->executar($input);

        foreach ($caso['esperado'] as $chave => $valorEsperado) {
            $this->assertGolden($nome, (string) $chave, $valorEsperado, $result);
        }
    }

    /**
     * Executa o orquestrador REAL escolhendo o método pela `tipo` da fixture. A
     * inscrição usa o binding real (UnavailablePropertyRegistryLookup) — degrada
     * sem inventar ponto.
     *
     * @param  array<string, mixed>  $input
     */
    private function executar(array $input): ConsultaViabilidadeResult
    {
        $tipo = (string) ($input['tipo'] ?? 'cnae');
        $cnae = (string) $input['cnae'];
        $area = isset($input['area']) ? (float) $input['area'] : null;

        $service = app(ConsultaViabilidadeService::class);

        return match ($tipo) {
            'endereco' => $service->consultarPorEndereco((string) $input['endereco'], $cnae, $area),
            'inscricao' => $service->consultarPorInscricao((string) $input['inscricao'], $cnae, $area),
            default => $service->consultarPorCnae($cnae, $area),
        };
    }

    /**
     * Asserta uma chave de `esperado` contra o ConsultaViabilidadeResult real,
     * com mensagem que identifica o golden case e a chave divergente (regressão
     * de domínio). Espelha LouosGoldenCaseTest/RiscoGoldenCaseTest::assertGolden.
     */
    private function assertGolden(string $nome, string $chave, mixed $esperado, ConsultaViabilidadeResult $result): void
    {
        $contexto = "Golden case '{$nome}': divergência em '{$chave}'.";

        switch ($chave) {
            case 'veredito':
                $this->assertSame($esperado, $result->vereditoLocacional()['resultado'], $contexto);
                break;

            case 'veredito_motivo_contem':
                $this->assertStringContainsString(
                    (string) $esperado,
                    (string) $result->vereditoLocacional()['motivo'],
                    $contexto,
                );
                break;

            case 'risco_municipal_status':
                $this->assertSame($esperado, $result->risco->municipal['status'] ?? null, $contexto);
                break;

            case 'quadro7_status':
                $this->assertSame($esperado, $result->enquadramento->quadro7['status'] ?? null, $contexto);
                break;

            case 'tem_geocode':
                $this->assertSame($esperado, $result->geocode !== null, $contexto);
                break;

            case 'tem_territorio':
                $this->assertSame($esperado, $result->territory !== null, $contexto);
                break;

            case 'avisos_contem':
                $this->assertTrue(
                    $this->algumAvisoContem($result->avisos, (string) $esperado),
                    "{$contexto} Nenhum aviso contém ".json_encode($esperado, JSON_UNESCAPED_UNICODE)
                        .'. Avisos: '.json_encode($result->avisos, JSON_UNESCAPED_UNICODE).'.',
                );
                break;

            default:
                throw new \InvalidArgumentException(
                    "Chave de 'esperado' desconhecida no golden case '{$nome}': {$chave}",
                );
        }
    }

    /**
     * @param  list<string>  $avisos
     */
    private function algumAvisoContem(array $avisos, string $trecho): bool
    {
        foreach ($avisos as $aviso) {
            if (str_contains($aviso, $trecho)) {
                return true;
            }
        }

        return false;
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
