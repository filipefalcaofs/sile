<?php

namespace Tests\Feature\Viabilidade;

use App\Enums\GeoLayerType;
use App\Models\Activity;
use App\Models\GeoLayer;
use App\Models\Parameter;
use App\Models\ViabilityQuery;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\GeocoderException;
use App\Services\Geo\SpatialRepository;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Consulta prévia de viabilidade PÚBLICA (HU-054/055/056): a página e os 3
 * endpoints JSON (endereço/CNAE/inscrição) sob /portal/viabilidade são acessíveis
 * ao cidadão ANÔNIMO, com throttle parametrizado (07-01) e auditoria RN-002. O
 * controller só orquestra via ConsultaViabilidadeService (07-05) e comunica a
 * degradação honesta — endereço não localizado/serviço indisponível/toggle off
 * jamais viram resultado falso.
 *
 * Os motores rodam com SEEDS REAIS (Quadro 7 da Lei 9.148/2016, risco do Decreto
 * 32.636/2020 e da VISA); o ponto e o território são injetados por FAKES
 * (Geocoder + FakeSpatialRepository) para reproduzir os cenários sem PostGIS.
 */
class ConsultaViabilidadePublicaTest extends TestCase
{
    use RefreshDatabase;

    private const CNAE_MINIMERCADO = '4712-1/00';

    private const AVISO_CNAE_SEM_LOCAL = 'Consulta por CNAE não avalia o local: o veredito locacional depende do endereço/zona. Para a viabilidade locacional, consulte por endereço.';

    private const AVISO_INSCRICAO_INDISPONIVEL = 'Resolução por inscrição imobiliária indisponível (base de lotes pendente SEDUR). Resultado sem análise territorial; consulte por endereço para o veredito locacional.';

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

    public function test_pagina_de_consulta_e_publica(): void
    {
        // Cidadão anônimo (sem auth:web) acessa a página: 200, NUNCA redirect ao
        // login (que devolveria 302). A asserção do componente/props Inertia fica
        // para o 07-08 — o assertInertia() exige o arquivo .tsx em disco.
        $response = $this->get('/portal/viabilidade');

        $response->assertOk();
        $this->assertFalse($response->isRedirect(), 'A página de consulta não pode redirecionar (é pública).');
    }

    public function test_consulta_por_endereco_anonima_retorna_resultado_e_e_auditada(): void
    {
        // HU-054/CA-01/CA-02: o cidadão anônimo consulta por endereço e recebe o
        // resultado real dos motores; a consulta é auditada (RN-002) com causer
        // null (anônima), origem registrada pela RecordActivityAction.
        $this->fakeGeocoder();
        $this->fakeTerritorioBairroSemZona();

        $this->postJson('/portal/viabilidade/endereco', [
            'endereco' => 'Praça Municipal, Centro, Salvador',
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])
            ->assertOk()
            ->assertJsonStructure(['entrada', 'veredito_locacional', 'risco', 'enquadramento', 'avisos', 'versoes'])
            ->assertJsonPath('risco.municipal.status', 'classificado')
            ->assertJsonPath('enquadramento.quadro7.status', 'identificado')
            // Sem zona real (pendente SEDUR), o veredito é PROPAGADO como pendente.
            ->assertJsonPath('veredito_locacional.resultado', 'pendente');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'viabilidade',
            'event' => 'consulta',
            'result' => 'sucesso',
        ]);

        $activity = Activity::query()
            ->where('log_name', 'viabilidade')
            ->where('event', 'consulta')
            ->latest('id')
            ->first();

        // Anônima: sem causer (RN-002 — causer null quando não autenticado).
        $this->assertNull($activity->causer_id);
        $this->assertSame('endereco', $activity->properties['tipo']);
    }

    public function test_endereco_nao_localizado_retorna_mensagem_honesta(): void
    {
        // Anti-fachada: endereço não localizado devolve mensagem HONESTA (404),
        // NUNCA um resultado falso.
        $this->app->instance(Geocoder::class, new class implements Geocoder
        {
            public function geocode(string $address): GeocodeResult
            {
                throw new AddressNotFoundException($address);
            }
        });
        $this->fakeTerritorioBairroSemZona();

        $this->postJson('/portal/viabilidade/endereco', [
            'endereco' => 'Endereço inexistente xyz',
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Endereço não localizado. Revise o endereço ou posicione a consulta por CNAE.')
            ->assertJsonMissingPath('veredito_locacional');
    }

    public function test_servico_de_geocodificacao_indisponivel_retorna_mensagem_honesta(): void
    {
        // Anti-fachada: serviço indisponível devolve 503 honesto, sem resultado falso.
        $this->app->instance(Geocoder::class, new class implements Geocoder
        {
            public function geocode(string $address): GeocodeResult
            {
                throw new GeocoderException($address, 503);
            }
        });
        $this->fakeTerritorioBairroSemZona();

        $this->postJson('/portal/viabilidade/endereco', [
            'endereco' => 'Praça Municipal, Centro, Salvador',
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Serviço de geocodificação indisponível no momento. Tente novamente em instantes.');
    }

    public function test_endereco_invalido_e_rejeitado_sem_executar(): void
    {
        // FormRequest valida antes de orquestrar: endereço ausente → 422.
        $this->postJson('/portal/viabilidade/endereco', [
            'cnae' => self::CNAE_MINIMERCADO,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('endereco');
    }

    public function test_consulta_por_cnae_anonima_roda_risco_e_quadro7(): void
    {
        // HU-056: consulta por CNAE (sem endereço/inscrição) roda o risco real e o
        // Quadro 7 por área, SEM território — o veredito fica pendente (sem local)
        // e a consulta avisa que não avalia o local. Não precisa de geocoder.
        $this->postJson('/portal/viabilidade/cnae', [
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])
            ->assertOk()
            ->assertJsonPath('risco.municipal.status', 'classificado')
            ->assertJsonPath('enquadramento.quadro7.status', 'identificado')
            ->assertJsonPath('veredito_locacional.resultado', 'pendente')
            ->assertJsonPath('avisos.0', self::AVISO_CNAE_SEM_LOCAL)
            // Sem ponto: nenhum território/geocode inventado.
            ->assertJsonPath('geocode', null)
            ->assertJsonPath('territorio', null);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'viabilidade',
            'event' => 'consulta',
            'result' => 'sucesso',
        ]);
    }

    public function test_consulta_por_cnae_respeita_toggle(): void
    {
        // HU-014: o toggle desligado bloqueia também a via CNAE, com aviso comunicado.
        $this->seed(ParameterSeeder::class);
        Parameter::query()
            ->where('key', 'features.consulta_viabilidade')
            ->first()
            ->update(['value' => '0']);

        $this->postJson('/portal/viabilidade/cnae', [
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A consulta de viabilidade está temporariamente desativada. Tente novamente mais tarde.');
    }

    public function test_cnae_invalido_e_rejeitado_sem_executar(): void
    {
        // FormRequest valida antes de orquestrar: CNAE ausente → 422.
        $this->postJson('/portal/viabilidade/cnae', ['area' => 120])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cnae');
    }

    public function test_consulta_por_inscricao_degrada_com_aviso_e_nunca_inventa_ponto(): void
    {
        // HU-055/CA-03: com o binding REAL (base de lotes pendente SEDUR), a
        // resolução do ponto degrada para a via CNAE — o serviço captura a
        // indisponibilidade e devolve 200 com o resultado degradado + aviso. O
        // controller NÃO trata essa exceção (a consulta "funcionou", só não
        // resolveu o ponto); NUNCA inventa coordenada.
        $this->postJson('/portal/viabilidade/inscricao', [
            'inscricao' => '123',
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])
            ->assertOk()
            ->assertJsonPath('avisos.0', self::AVISO_INSCRICAO_INDISPONIVEL)
            // Sem ponto inventado: geocode e território nulos no snapshot.
            ->assertJsonPath('geocode', null)
            ->assertJsonPath('territorio', null)
            // Risco REAL presente e veredito pendente (sem análise territorial).
            ->assertJsonPath('risco.municipal.status', 'classificado')
            ->assertJsonPath('veredito_locacional.resultado', 'pendente');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'viabilidade',
            'event' => 'consulta',
            'result' => 'sucesso',
        ]);
    }

    public function test_inscricao_respeita_toggle(): void
    {
        // HU-014: o toggle desligado bloqueia também a via inscrição.
        $this->seed(ParameterSeeder::class);
        Parameter::query()
            ->where('key', 'features.consulta_viabilidade')
            ->first()
            ->update(['value' => '0']);

        $this->postJson('/portal/viabilidade/inscricao', [
            'inscricao' => '123',
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A consulta de viabilidade está temporariamente desativada. Tente novamente mais tarde.');
    }

    public function test_inscricao_invalida_e_rejeitada_sem_executar(): void
    {
        // FormRequest valida antes de orquestrar: inscrição/CNAE ausentes → 422.
        $this->postJson('/portal/viabilidade/inscricao', ['area' => 120])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['inscricao', 'cnae']);
    }

    public function test_throttle_limita_consultas_publicas(): void
    {
        // HU-014: o limite por minuto é administrável sem deploy. Com 2/min, a 3ª
        // consulta pública dentro da janela é bloqueada (429) — usa a via CNAE
        // (sem geocoder/território), que exercita o mesmo middleware.
        $this->seed(ParameterSeeder::class);
        Parameter::query()
            ->where('key', 'seguranca.throttle.consulta_viabilidade.por_minuto')
            ->first()
            ->update(['value' => '2']);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/portal/viabilidade/cnae', [
                'cnae' => self::CNAE_MINIMERCADO,
                'area' => 120,
            ])->assertOk();
        }

        $this->postJson('/portal/viabilidade/cnae', [
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])->assertStatus(429);
    }

    public function test_consulta_anonima_nao_grava_historico(): void
    {
        // Anônima é AUDITADA (RN-002), mas NÃO gera histórico pessoal — a
        // persistência só-quando-autenticado é do 07-07. Aqui garantimos que a
        // consulta anônima não grava nenhuma linha em viability_queries.
        $this->postJson('/portal/viabilidade/cnae', [
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])->assertOk();

        $this->assertSame(0, ViabilityQuery::count());

        // ...mas foi auditada.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'viabilidade',
            'event' => 'consulta',
            'result' => 'sucesso',
        ]);
    }

    public function test_toggle_desligado_bloqueia_antes_de_executar(): void
    {
        // HU-014: features.consulta_viabilidade desligado bloqueia ANTES de
        // executar (degradação controlada e comunicada, nunca falha silenciosa).
        $this->seed(ParameterSeeder::class);
        Parameter::query()
            ->where('key', 'features.consulta_viabilidade')
            ->first()
            ->update(['value' => '0']);

        // Geocoder que falha o teste se for chamado: o bloqueio é ANTES de orquestrar.
        $this->app->instance(Geocoder::class, new class implements Geocoder
        {
            public function geocode(string $address): GeocodeResult
            {
                throw new \RuntimeException('A consulta NÃO deveria executar com o toggle desligado.');
            }
        });

        $this->postJson('/portal/viabilidade/endereco', [
            'endereco' => 'Praça Municipal, Centro, Salvador',
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A consulta de viabilidade está temporariamente desativada. Tente novamente mais tarde.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'viabilidade',
            'event' => 'consulta',
            'result' => 'bloqueado',
        ]);
    }
}
