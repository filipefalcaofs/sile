<?php

namespace Tests\Feature\Decisao;

use App\Enums\Fluxo;
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
use App\Services\Analise\JustificativaFundamentadaComposer;
use App\Services\Analise\PreAnaliseService;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\RiscoResult;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
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

    public function test_conclusao_de_permitido_e_byte_identica(): void
    {
        $paragrafos = $this->paragrafosDaJustificativa('permitido');

        $this->assertContains(
            'Diante do enquadramento acima, manifesta-se pelo deferimento desta atividade, por ser locacionalmente permitida na zona ZEC, sem condicionantes urbanísticas incidentes.',
            $paragrafos,
        );
    }

    public function test_conclusao_de_permitido_com_condicoes_e_byte_identica(): void
    {
        $paragrafos = $this->paragrafosDaJustificativa('permitido_com_condicoes');

        $this->assertContains(
            'Diante do enquadramento acima, manifesta-se pelo deferimento desta atividade na zona ZEC, condicionado ao cumprimento das exigências urbanísticas incidentes.',
            $paragrafos,
        );
    }

    public function test_conclusao_de_nao_permitido_e_byte_identica(): void
    {
        $paragrafos = $this->paragrafosDaJustificativa('nao_permitido');

        $this->assertContains(
            'Diante do enquadramento acima, manifesta-se pelo indeferimento desta atividade, por ser o uso proibido na zona ZEC segundo o Quadro 10 da LOUOS.',
            $paragrafos,
        );
    }

    public function test_conclusao_padrao_sem_desfecho_e_byte_identica(): void
    {
        $paragrafos = $this->paragrafosDaJustificativa('pendente');

        $this->assertContains(
            'Não há elementos suficientes para deferir ou indeferir. Encaminha-se a atividade à análise técnica, sem sugerir desfecho locacional.',
            $paragrafos,
        );
    }

    public function test_fundamentacao_padrao_da_justificativa_e_byte_identica(): void
    {
        $paragrafos = $this->paragrafosDaJustificativa('permitido', fundamentacao: []);

        $this->assertSame('Fundamentação: Lei nº 9.148/2016 (LOUOS).', end($paragrafos));
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

    /**
     * Justificativa redigida pelo caminho público real do composer, em
     * parágrafos, para a comparação byte a byte da conclusão/fundamentação.
     *
     * @param  list<string>|null  $fundamentacao
     * @return list<string>
     */
    private function paragrafosDaJustificativa(string $resultado, ?array $fundamentacao = null): array
    {
        $consulta = $this->consultaSintetica($resultado, $fundamentacao);

        $texto = app(JustificativaFundamentadaComposer::class)->paraConsulta($consulta, [
            'cnae' => '4771701',
            'cnae_formatado' => '4771-7/01',
            'is_primary' => true,
            'descricao' => 'Comércio varejista de produtos farmacêuticos',
        ]);

        return explode("\n\n", $texto);
    }

    /**
     * @param  list<string>|null  $fundamentacao
     */
    private function consultaSintetica(string $resultado, ?array $fundamentacao = null): ConsultaViabilidadeResult
    {
        $fundamentacaoConsolidado = $fundamentacao ?? [
            'Lei nº 9.148/2016 (LOUOS) — nR1-01',
            'Quadro 10 da Lei nº 9.148/2016',
        ];

        return new ConsultaViabilidadeResult(
            entrada: [
                'tipo' => 'ponto',
                'cnae' => '4771701',
                'cnae_formatado' => '4771-7/01',
                'area' => 75.0,
            ],
            geocode: null,
            territory: new TerritoryResult(
                bairro: ['status' => 'identificado', 'nome' => 'Comércio', 'versao_camada' => 'bairro-2024'],
                via: ['status' => 'identificado', 'nome' => 'Av. Estados Unidos', 'versao_camada' => 'via-2024'],
                zona: ['status' => 'identificado', 'nome' => 'ZEC', 'versao_camada' => 'zona-2026'],
                lote: ['status' => 'nao_encontrado', 'versao_camada' => null],
                restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'versao_camada' => null],
            ),
            enquadramento: new EnquadramentoResult(
                enquadramento: [
                    'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                    'grupo' => 'nR1',
                    'subgrupo' => 'nR1-01',
                    'motivo' => 'O CNAE 4771-7/01 com área 75 m² enquadra-se no grupo nR1 (nR1-01) da LOUOS (07.01.05).',
                ],
                quadro10: [
                    'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                    'permissao' => 'permitido',
                    'motivo' => 'O grupo nR1 é permitido na zona ZEC segundo o Quadro 10 da LOUOS.',
                ],
                quadro11a: [
                    'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                    'condicoes' => [],
                    'motivo' => null,
                ],
                consolidado: [
                    'resultado' => $resultado,
                    'fundamentacao' => $fundamentacaoConsolidado,
                    'condicionantes' => [],
                    'motivo' => 'Permitido: o CNAE 4771-7/01 (área 75 m²) classificou-se no grupo nR1 pelo enquadramento da planilha vigente e esse grupo é permitido na zona ZEC pelo Quadro 10.',
                ],
                versoes: [
                    'risco_tratamento' => 'planilha-20-08-26',
                    'quadro10' => 'lei-9148-2016-quadro10',
                    'quadro11a' => null,
                ],
            ),
            risco: new RiscoResult(
                municipal: [
                    'status' => RiscoResult::STATUS_CLASSIFICADO,
                    'nivel' => 'baixo_a',
                    'nivel_label' => 'Baixo',
                    'condicionantes' => [],
                    'versao_regras' => 'decreto-32636-2020',
                ],
                sanitario: [
                    'status' => RiscoResult::STATUS_NAO_CLASSIFICADO,
                    'nivel_original' => null,
                    'nivel_final' => null,
                    'reclassificado' => false,
                    'condicionantes_perguntas' => [],
                ],
                encaminhamento: [
                    'fluxo' => Fluxo::Analise->value,
                    'dimensao_decisiva' => 'municipal',
                    'motivo' => 'Nível baixo_a (municipal) encaminhado para análise técnica',
                    'gatilhos_acionados' => [],
                ],
                fundamentacao: $fundamentacao ?? ['Decreto Municipal nº 32.636/2020'],
                versoes: ['municipal' => 'decreto-32636-2020', 'sanitario' => null],
            ),
        );
    }
}
