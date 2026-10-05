<?php

namespace Tests\Feature\Louos;

use App\Enums\IntencaoAtividade;
use App\Enums\SituacaoEtapa;
use App\Models\Cnae;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Territorial\TerritorialRuleService;
use App\Services\Territorial\TerritorialRuleVerdict;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Avaliador das regras territoriais (RT-01 a RT-05) sobre a solicitação:
 * casamento por endereço (CODLOG/nome × CNAE × número de porta) e por
 * poligonal (WFS vivo do GeoServer SEDUR), com degradação honesta — camada
 * indisponível encaminha à análise, nunca defere por omissão (CA-RT-09).
 */
class TerritorialRuleServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PONTO = ['lat' => -12.98, 'lng' => -38.48];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);
    }

    private function avaliar(ViabilityRequest $request): TerritorialRuleVerdict
    {
        return app(TerritorialRuleService::class)->avaliar($request, self::PONTO);
    }

    /**
     * @param  list<string>  $codigosCnae
     * @param  array<string, mixed>  $attrs
     */
    private function solicitacao(array $attrs = [], array $codigosCnae = []): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create($attrs);

        foreach ($codigosCnae as $i => $codigo) {
            $cnae = Cnae::factory()->create(['code' => $codigo]);
            $request->cnaes()->attach($cnae->id, ['is_primary' => $i === 0]);
        }

        return $request;
    }

    private function featureCollectionVazia(): array
    {
        return ['type' => 'FeatureCollection', 'features' => []];
    }

    private function featureCollectionComFeicao(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                ['type' => 'Feature', 'properties' => ['SUBZONA' => 'ZEM'], 'geometry' => null],
            ],
        ];
    }

    // CA-RT-01
    public function test_rt01_indefere_cnae_da_lista_no_logradouro_212(): void
    {
        $request = $this->solicitacao([], ['4520006']);
        $request->forceFill(['cod_log' => '212'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::INDEFERIR, $verdict->status);
        $this->assertSame('RT-01', $verdict->regra?->codigo);
        $this->assertStringContainsString('art. 172-A', (string) $verdict->motivo);
        $this->assertStringContainsString('Avenida Lafayette Coutinho', (string) $verdict->motivo);
        $this->assertSame('sedur-2026-08-28-regras-territoriais', $verdict->versaoRegra);
    }

    // CA-RT-02
    public function test_rt01_nao_se_aplica_a_cnae_fora_da_lista(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionVazia()));

        $request = $this->solicitacao([], ['9602501']);
        $request->forceFill(['cod_log' => '212'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::SEM_INCIDENCIA, $verdict->status);
    }

    public function test_rt01_casa_pelo_nome_do_logradouro_quando_cod_log_ausente(): void
    {
        $request = $this->solicitacao([
            'address_street' => 'Av. Lafayette Coutinho',
        ], ['4723700']);

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::INDEFERIR, $verdict->status);
        $this->assertSame('RT-01', $verdict->regra?->codigo);
    }

    /**
     * Fake do Cadastro Imobiliário SEFAZ: devolve o logradouro tributário
     * informado, ou lança a exceção de indisponibilidade quando null.
     */
    private function fakeCadastro(?string $codigoLogradouroTributario): object
    {
        $fake = new class($codigoLogradouroTributario) implements PropertyRegistryLookup
        {
            public int $chamadas = 0;

            public function __construct(private ?string $codigo) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                $this->chamadas++;

                if ($this->codigo === null) {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                return new PropertyRegistryResult(
                    latitude: null,
                    longitude: null,
                    inscricao: $inscricao,
                    source: 'SEFAZ via SEDUR',
                    raw: ['CodigoLogradouroTributario' => $this->codigo, 'LogradouroTributario' => 'Avenida Lafayete Coutinho'],
                );
            }
        };

        $this->app->instance(PropertyRegistryLookup::class, $fake);

        return $fake;
    }

    public function test_rt01_indefere_pelo_logradouro_tributario_quando_codlog_espacial_diverge(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionVazia()));
        $this->fakeCadastro('212');

        $request = $this->solicitacao(['property_registration' => '344672', 'address_street' => 'Simulação REGIN'], ['4681802']);
        $request->forceFill(['cod_log' => '881'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::INDEFERIR, $verdict->status);
        $this->assertSame('RT-01', $verdict->regra?->codigo);
        $this->assertStringContainsString('art. 172-A', (string) $verdict->motivo);
    }

    public function test_rt01_logradouro_tributario_de_outra_via_nao_incide(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionVazia()));
        $this->fakeCadastro('999');

        $request = $this->solicitacao(['property_registration' => '344672'], ['4681802']);
        $request->forceFill(['cod_log' => '881'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::SEM_INCIDENCIA, $verdict->status);
    }

    public function test_rt01_cadastro_indisponivel_encaminha_analise_sem_deferir(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionVazia()));
        $this->fakeCadastro(null);

        $request = $this->solicitacao(['property_registration' => '344672'], ['4681802']);
        $request->forceFill(['cod_log' => '881'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::ENCAMINHAR_ANALISE, $verdict->status);
        $this->assertSame('RT-01', $verdict->regra?->codigo);
        $this->assertStringContainsString('Cadastro Imobiliário', (string) $verdict->motivo);
    }

    public function test_rt01_nao_consulta_cadastro_sem_cnae_da_lista(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionVazia()));
        $cadastro = $this->fakeCadastro('212');

        $request = $this->solicitacao(['property_registration' => '344672'], ['9602501']);
        $request->forceFill(['cod_log' => '881'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::SEM_INCIDENCIA, $verdict->status);
        $this->assertSame(0, $cadastro->chamadas);
    }

    public function test_rt01_ignora_cnae_marcado_para_exclusao(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionVazia()));

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['cod_log' => '212'])->save();

        $cnae = Cnae::factory()->create(['code' => '4520006']);
        $request->cnaes()->attach($cnae->id, ['is_primary' => true, 'intencao' => IntencaoAtividade::Excluir->value]);

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::SEM_INCIDENCIA, $verdict->status);
    }

    public function test_sem_versao_vigente_do_dominio_nao_ha_incidencia(): void
    {
        TerritorialRule::query()->delete();
        RuleVersion::query()->where('domain', 'territorial')->delete();

        $request = $this->solicitacao([], ['4520006']);
        $request->forceFill(['cod_log' => '212'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::SEM_INCIDENCIA, $verdict->status);
    }

    // CA-RT-06 (ZEM)
    public function test_poligonal_zem_encaminha_analise_com_motivo_da_camada(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionComFeicao()));

        // Isola a ZEM: as demais poligonais também casariam com o fake.
        TerritorialRule::query()->where('codigo', '!=', 'RT-03-ZEM')->update(['ativo' => false]);

        $request = $this->solicitacao([], ['9602501']);

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::ENCAMINHAR_ANALISE, $verdict->status);
        $this->assertSame('RT-03-ZEM', $verdict->regra?->codigo);
        $this->assertSame('Solicitação cadastrada na Zona de Exploração Mineral.', $verdict->motivo);
    }

    public function test_poligonal_fora_da_camada_nao_incidencia(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionVazia()));

        $request = $this->solicitacao([], ['9602501']);

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::SEM_INCIDENCIA, $verdict->status);
    }

    // CA-RT-09 — degradação honesta
    public function test_camada_indisponivel_encaminha_analise_registrando_a_indisponibilidade(): void
    {
        Http::fake(fn () => Http::response('erro', 500));

        $request = $this->solicitacao([], ['9602501']);

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::ENCAMINHAR_ANALISE, $verdict->status);
        $this->assertStringContainsString('indisponível', (string) $verdict->motivo);
    }

    public function test_sem_ponto_do_imovel_encaminha_com_motivo_de_localizacao_e_nao_de_camada(): void
    {
        $request = $this->solicitacao([], ['9602501']);

        $verdict = app(TerritorialRuleService::class)->avaliar($request, null);

        $this->assertSame(TerritorialRuleVerdict::ENCAMINHAR_ANALISE, $verdict->status);
        $this->assertStringContainsString('localização do imóvel', (string) $verdict->motivo);
        $this->assertStringNotContainsString('camada geográfica', (string) $verdict->motivo);
    }

    public function test_camada_indisponivel_registra_a_etapa_de_regra_territorial_como_indisponivel(): void
    {
        Http::fake(fn () => Http::response('erro', 500));

        $request = $this->solicitacao([], ['9602501']);

        $this->avaliar($request);

        $etapa = $request->etapas()->where('etapa', 'regra_territorial')->first();

        $this->assertSame(SituacaoEtapa::Indisponivel, $etapa?->situacao);
        $this->assertSame('geoserver_camadas', $etapa?->servico);
    }

    public function test_camada_respondendo_registra_a_etapa_como_ok(): void
    {
        Http::fake(fn () => Http::response(['type' => 'FeatureCollection', 'features' => []]));

        $request = $this->solicitacao([], ['9602501']);

        $this->avaliar($request);

        $this->assertSame(
            SituacaoEtapa::Ok,
            $request->etapas()->where('etapa', 'regra_territorial')->first()?->situacao,
        );
    }

    public function test_sem_ponto_nao_registra_a_etapa_de_regra_territorial(): void
    {
        $request = $this->solicitacao([], ['9602501']);

        app(TerritorialRuleService::class)->avaliar($request, null);

        $this->assertSame(0, $request->etapas()->where('etapa', 'regra_territorial')->count());
    }

    public function test_regra_inativa_nao_e_avaliada(): void
    {
        // Todas as poligonais inativas: mesmo com a camada respondendo feição,
        // nenhuma regra dispara (inativa = bloqueio explícito, visível no cadastro).
        Http::fake(fn () => Http::response($this->featureCollectionComFeicao()));

        TerritorialRule::query()->update(['ativo' => false]);

        $request = $this->solicitacao([], ['9602501']);

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::SEM_INCIDENCIA, $verdict->status);
    }

    // CA-RT-04 / CA-RT-05 — allow-list de Itaigara
    public function test_itaigara_fora_da_allowlist_encaminha_com_motivo(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionComFeicao()));

        // Isola Itaigara: as demais poligonais também casariam com o fake.
        TerritorialRule::query()->where('codigo', '!=', 'RT-02-ITAIGARA')->update(['ativo' => false]);

        $request = $this->solicitacao(['address_number' => '999'], ['9602501']);
        $request->forceFill(['cod_log' => '2631'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::ENCAMINHAR_ANALISE, $verdict->status);
        $this->assertSame('Solicitação cadastrada no Loteamento Itaigara.', $verdict->motivo);
    }

    public function test_itaigara_na_allowlist_segue_o_fluxo_normal(): void
    {
        Http::fake(fn () => Http::response($this->featureCollectionComFeicao()));

        TerritorialRule::query()->where('codigo', '!=', 'RT-02-ITAIGARA')->update(['ativo' => false]);

        $request = $this->solicitacao(['address_number' => '135'], ['9602501']);
        $request->forceFill(['cod_log' => '518'])->save();

        $verdict = $this->avaliar($request);

        $this->assertSame(TerritorialRuleVerdict::SEM_INCIDENCIA, $verdict->status);
    }

    // RT-05 — helper das observações do Quadro 10
    public function test_observacao_a_permite_via_578_e_bairros_listados(): void
    {
        $service = app(TerritorialRuleService::class);

        $this->assertTrue($service->observacaoQuadro10Permite('RT-05-A', '578', null));
        $this->assertTrue($service->observacaoQuadro10Permite('RT-05-A', null, 'Calçada'));
        $this->assertFalse($service->observacaoQuadro10Permite('RT-05-A', '999', 'Pituba'));
        $this->assertNull($service->observacaoQuadro10Permite('RT-05-A', null, null));
    }

    public function test_observacao_b_permite_as_oito_vias(): void
    {
        $service = app(TerritorialRuleService::class);

        $this->assertTrue($service->observacaoQuadro10Permite('RT-05-B', '4661', null));
        $this->assertFalse($service->observacaoQuadro10Permite('RT-05-B', '212', null));
    }
}
