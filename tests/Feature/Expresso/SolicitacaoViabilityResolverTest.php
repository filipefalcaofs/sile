<?php

namespace Tests\Feature\Expresso;

use App\Enums\Fluxo;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Geo\SpatialRepository;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Resolução de viabilidade por solicitação (09-04): o SolicitacaoViabilityResolver
 * itera os CNAEs (principal + complementares), reusa o motor real da Fase 7
 * (ConsultaViabilidadeService) PELO PONTO da própria solicitação (centroide do
 * polígono — sem geocodificar de novo), PROPAGA o veredito do motor LOUOS e o
 * encaminhamento do motor de risco por CNAE, consolida o PIOR CASO e devolve um
 * ResolvedViability SEM persistir nem decidir (insumo puro — RN-001).
 *
 * Os motores rodam com dados versionados via factory (mesma lógica do fluxo
 * oficial — "muda a carga, nunca o comportamento"): risco municipal (Decreto
 * 32.636/2020), Quadro 7/10 da LOUOS (Lei 9.148/2016) e o território injetado por
 * FAKE (FakeSpatialRepository) para reproduzir os cenários sem PostGIS — espelha
 * os testes dos motores (ConsultaViabilidade*Test / Louos*Test).
 *
 * Elegibilidade ao expresso (HU-073) é por ENCAMINHAMENTO de risco (não pelo
 * veredito locacional): todos os CNAEs em 'expresso' → elegível; qualquer
 * 'analise' → inelegível. Sem zona oficial, o veredito locacional é "pendente"
 * (propagado) — nunca permitido/não permitido inventado.
 */
class SolicitacaoViabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): SolicitacaoViabilityResolver
    {
        return app(SolicitacaoViabilityResolver::class);
    }

    /**
     * Rascunho com área e os CNAEs informados (o primeiro como principal); o
     * polígono default (centroide em Salvador) salvo quando $comPoligono = false.
     *
     * @param  list<string>  $cnaeCodes
     */
    private function draftComCnaes(array $cnaeCodes, bool $comPoligono = true): ViabilityRequest
    {
        $atributos = ['used_area_m2' => 120.0];

        if (! $comPoligono) {
            $atributos['property_polygon_geojson'] = null;
        }

        $solicitacao = ViabilityRequest::factory()->draft()->create($atributos);

        foreach (array_values($cnaeCodes) as $indice => $code) {
            $cnae = Cnae::factory()->create(['code' => $code]);
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        return $solicitacao;
    }

    /**
     * Versão vigente do risco municipal (reusa se já existir para não duplicar a
     * vigência do domínio).
     */
    private function versaoRiscoMunicipal(): RuleVersion
    {
        return RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);
    }

    /**
     * Classifica o CNAE no risco municipal vigente (baixo → expresso; alto →
     * análise, pelo mapa de encaminhamento default).
     */
    private function classificarMunicipal(string $cnae, RiscoMunicipal $nivel): void
    {
        RiskClassification::factory()->create([
            'rule_version_id' => $this->versaoRiscoMunicipal()->id,
            'cnae_code' => $cnae,
            'risco_municipal' => $nivel,
        ]);
    }

    /**
     * Território com BAIRRO identificado e ZONA indisponível (base de zoneamento
     * pendente SEDUR): bairro vigente + fake espacial, deixando a zona sem camada
     * → veredito pendente (propagado do motor LOUOS).
     */
    private function fakeBairroSemZona(): FakeSpatialRepository
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

    /**
     * Território com BAIRRO e ZONA identificados (zoneamento oficial disponível):
     * permite ao motor LOUOS consolidar permitido/não permitido a partir do
     * Quadro 10 da zona.
     */
    private function fakeBairroComZona(string $zona): FakeSpatialRepository
    {
        GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'bairro-2024',
        ]);
        GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Zona,
            'version' => 'zoneamento-louos-2026',
        ]);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, [
            'id' => 1,
            'properties' => ['NOME_BAIRRO' => 'Comércio'],
        ]);
        $fake->setContaining(GeoLayerType::Zona, [
            'id' => 2,
            'properties' => ['NOME' => $zona],
        ]);

        $this->app->instance(SpatialRepository::class, $fake);

        return $fake;
    }

    /**
     * Faixa do Quadro 7 que mapeia o CNAE para um grupo de uso (sem teto de área,
     * para enquadrar a área declarada) na versão vigente.
     */
    private function seedQuadro7(string $cnae, string $grupo, string $subgrupo): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro7,
                'version' => 'lei-9148-2016-quadro7',
                'rules_version' => 'lei-9148-2016-quadro7',
            ]);

        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => $cnae,
            'grupo' => $grupo,
            'subgrupo' => $subgrupo,
            'area_min' => 0,
            'area_max' => null,
        ]);
    }

    /**
     * Permissão do Quadro 10 por (zona, grupo de uso) na versão vigente — o
     * subgrupo vazio replica a regra geral do grupo do seed real.
     */
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

    public function test_um_cnae_expresso_sem_zona_consolida_pendente_e_e_elegivel(): void
    {
        // HU-073 RN-007/008: elegibilidade é por ENCAMINHAMENTO de risco — um CNAE
        // de baixo risco encaminha ao expresso (elegível), MESMO com o veredito
        // locacional pendente (sem zona). Consolidado pendente, nunca inventado.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);

        $resolved = $this->resolver()->resolve($this->draftComCnaes(['2222222']));

        $this->assertInstanceOf(ResolvedViability::class, $resolved);
        $this->assertCount(1, $resolved->por_cnae);
        $this->assertSame(Fluxo::Expresso->value, $resolved->por_cnae[0]['fluxo']);
        $this->assertSame(ResultadoViabilidade::Pendente->value, $resolved->por_cnae[0]['tendencia']);
        $this->assertSame(ResultadoViabilidade::Pendente->value, $resolved->consolidado);
        $this->assertTrue($resolved->elegivelExpresso());

        // Cada item carrega o ConsultaViabilidadeResult bruto (objeto) — a decisão
        // (09-05) lê o veredito e o encaminhamento numa passada, sem reprocessar.
        $this->assertInstanceOf(ConsultaViabilidadeResult::class, $resolved->por_cnae[0]['consulta']);
    }

    public function test_cnae_de_risco_alto_encaminha_analise_e_nao_e_elegivel(): void
    {
        // HU-073: CNAE de alto risco é encaminhado à análise técnica → o conjunto
        // é inelegível ao expresso (semi-expresso), independentemente do veredito.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('3333333', RiscoMunicipal::Alto);

        $resolved = $this->resolver()->resolve($this->draftComCnaes(['3333333']));

        $this->assertSame(Fluxo::Analise->value, $resolved->por_cnae[0]['fluxo']);
        $this->assertFalse($resolved->elegivelExpresso());
    }

    public function test_qualquer_cnae_em_analise_torna_o_conjunto_inelegivel(): void
    {
        // HU-073 RN-008: basta UM CNAE em análise para derrubar a elegibilidade —
        // a elegibilidade exige TODOS em expresso.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);
        $this->classificarMunicipal('3333333', RiscoMunicipal::Alto);

        $resolved = $this->resolver()->resolve($this->draftComCnaes(['2222222', '3333333']));

        $this->assertFalse($resolved->elegivelExpresso());
    }

    public function test_consolida_pior_caso_nao_permitido_entre_os_cnaes(): void
    {
        // HU-074/075 RN-009: com zona oficial, um CNAE proibido (não permitido) e
        // outro permitido → o consolidado é o PIOR CASO (não permitido governa).
        $this->fakeBairroComZona('ZR-1');
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');
        $this->seedQuadro7('8888883', 'nR3', 'nR3-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);
        $this->seedQuadro10('ZR-1', 'nR3', Quadro10Permissao::Proibido);

        $resolved = $this->resolver()->resolve($this->draftComCnaes(['8888881', '8888883']));

        $tendencias = array_column($resolved->por_cnae, 'tendencia');
        $this->assertContains(ResultadoViabilidade::Permitido->value, $tendencias);
        $this->assertContains(ResultadoViabilidade::NaoPermitido->value, $tendencias);
        $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $resolved->consolidado);
    }

    public function test_todos_permitido_consolida_permitido(): void
    {
        // HU-074 RN-009: todos os CNAEs permitidos na zona → consolidado permitido.
        $this->fakeBairroComZona('ZR-1');
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');
        $this->seedQuadro7('8888882', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $resolved = $this->resolver()->resolve($this->draftComCnaes(['8888881', '8888882']));

        $this->assertSame(ResultadoViabilidade::Permitido->value, $resolved->consolidado);
    }

    public function test_sem_poligono_usa_via_cnae_sem_geocodificar(): void
    {
        // Degradação honesta: sem polígono não há ponto — resolve por CNAE (risco
        // real, sem território → veredito pendente) e NÃO dispara consulta espacial.
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);
        $fake = new FakeSpatialRepository;
        $this->app->instance(SpatialRepository::class, $fake);

        $resolved = $this->resolver()->resolve($this->draftComCnaes(['2222222'], comPoligono: false));

        $this->assertNull($resolved->ponto);
        $this->assertNull($resolved->por_cnae[0]['consulta']->territory);
        $this->assertNull($resolved->por_cnae[0]['consulta_array']['territorio']);
        $this->assertSame(ResultadoViabilidade::Pendente->value, $resolved->por_cnae[0]['tendencia']);
        $this->assertSame([], $fake->containingCalls);
    }

    public function test_rules_versions_presentes(): void
    {
        // RN-002/RN-004: o resolver coleta as versões representativas das regras
        // aplicadas (território + louos + risco) para auditoria/reprodução.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);

        $resolved = $this->resolver()->resolve($this->draftComCnaes(['2222222']));

        $this->assertArrayHasKey('territorio', $resolved->rules_versions);
        $this->assertArrayHasKey('louos', $resolved->rules_versions);
        $this->assertArrayHasKey('risco', $resolved->rules_versions);
    }

    public function test_resolver_nao_persiste_nem_decide(): void
    {
        // RN-001: o resolver é insumo puro — não grava snapshot, versões nem
        // resultado na solicitação (a persistência é da simulação; a decisão
        // autoritativa o reexecuta fresco).
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);
        $solicitacao = $this->draftComCnaes(['2222222']);

        $this->resolver()->resolve($solicitacao);
        $solicitacao->refresh();

        $this->assertNull($solicitacao->simulation_snapshot);
        $this->assertNull($solicitacao->simulation_rules_versions);
        $this->assertNull($solicitacao->simulation_resultado);
        $this->assertNull($solicitacao->simulated_at);
    }

    public function test_sem_cnaes_consolida_pendente_e_nao_e_elegivel(): void
    {
        // Sem CNAEs não há nada a decidir: consolidado pendente (honesto) e
        // inelegível ao expresso.
        $solicitacao = ViabilityRequest::factory()->draft()->create(['used_area_m2' => 120.0]);

        $resolved = $this->resolver()->resolve($solicitacao);

        $this->assertSame([], $resolved->por_cnae);
        $this->assertSame(ResultadoViabilidade::Pendente->value, $resolved->consolidado);
        $this->assertFalse($resolved->elegivelExpresso());
    }

    public function test_to_snapshot_preserva_o_shape_da_fase8(): void
    {
        // Trava do refactor 09-04: toSnapshot() deve reproduzir EXATAMENTE o shape
        // que a Fase 8 persiste (ponto, area_m2, por_cnae com consulta = toArray),
        // para a simulação manter o mesmo registro sem mudar comportamento.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);

        $resolved = $this->resolver()->resolve($this->draftComCnaes(['2222222']));
        $snapshot = $resolved->toSnapshot();

        $this->assertSame(['ponto', 'area_m2', 'por_cnae'], array_keys($snapshot));
        $this->assertSame(120.0, $snapshot['area_m2']);

        $item = $snapshot['por_cnae'][0];
        $this->assertSame(
            ['cnae', 'cnae_formatado', 'is_primary', 'tendencia', 'tendencia_label', 'consulta'],
            array_keys($item),
        );
        $this->assertSame('2222222', $item['cnae']);
        $this->assertTrue($item['is_primary']);
        // O snapshot serializa a consulta (toArray), não o objeto.
        $this->assertIsArray($item['consulta']);
        $this->assertArrayHasKey('risco', $item['consulta']);
    }
}
