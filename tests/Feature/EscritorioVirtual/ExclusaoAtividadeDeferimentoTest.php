<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\GeoLayerType;
use App\Enums\IntencaoAtividade;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * RN-AA-03/RN-AA-05: solicitação exclusivamente de exclusão de atividade
 * (todos os CNAEs marcados "excluir") defere automaticamente, SEM consultar o
 * enquadramento LOUOS — nada de novo vai ser exercido no local, então não há
 * veredito locacional a produzir. A prova de que o enquadramento não é
 * consultado é um espião (FakeSpatialRepository) no SpatialRepository: suas
 * listas de chamadas (`containingCalls`/`nearestCalls`/`intersectingCalls`)
 * ficam vazias após a decisão — nenhuma camada geoespacial foi lida.
 */
class ExclusaoAtividadeDeferimentoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): FluxoExpressoService
    {
        return app(FluxoExpressoService::class);
    }

    /**
     * Espiona o SpatialRepository sem registrar nenhuma resposta — se o
     * enquadramento for consultado (mesmo que degradando), a chamada fica
     * registrada no fake.
     */
    private function espiaoEnquadramento(): FakeSpatialRepository
    {
        $fake = new FakeSpatialRepository;
        $this->app->instance(SpatialRepository::class, $fake);

        return $fake;
    }

    /**
     * Solicitação protocolada com um CNAE marcado para exclusão (não o CNAE
     * gatilho da sede — fora do escopo desta tarefa, ver Task 3).
     */
    private function protocoladaComExclusao(string $cnae = '4712100'): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $cnaeModel = Cnae::factory()->create(['code' => $cnae]);
        $solicitacao->cnaes()->attach($cnaeModel->id, [
            'is_primary' => true,
            'intencao' => IntencaoAtividade::Excluir->value,
        ]);

        return $solicitacao;
    }

    /**
     * Cenário comum (sem exclusão), reaproveitado do caminho de decisão
     * padrão: risco baixo + Quadro 7/10 permitidos na zona ZR-1.
     */
    private function protocoladaComumDeferivel(string $cnae = '8888881'): ViabilityRequest
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZR-1']]);
        $this->app->instance(SpatialRepository::class, $fake);

        $versaoRisco = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $versaoRisco->id,
            'cnae_code' => $cnae,
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $versaoQuadro7 = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro7,
                'version' => 'lei-9148-2016-quadro7',
                'rules_version' => 'lei-9148-2016-quadro7',
            ]);
        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $versaoQuadro7->id,
            'cnae_code' => $cnae,
            'grupo' => 'nR1',
            'subgrupo' => 'nR1-01',
            'area_min' => 0,
            'area_max' => null,
        ]);

        $versaoQuadro10 = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);
        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $versaoQuadro10->id,
            'zona' => 'ZR-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $cnaeModel = Cnae::factory()->create(['code' => $cnae]);
        $solicitacao->cnaes()->attach($cnaeModel->id, ['is_primary' => true]);

        return $solicitacao;
    }

    /**
     * Caso principal: todos os CNAEs marcados "excluir" → defere sem consultar
     * o enquadramento (nem uma camada geoespacial foi lida pelo espião).
     */
    public function test_solicitacao_exclusivamente_de_exclusao_defere_sem_zoneamento(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $fake = $this->espiaoEnquadramento();
        $request = $this->protocoladaComExclusao();

        $this->service()->decide($request);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status);
        $this->assertNotNull($fresh->decision()->first());

        $this->assertSame([], $fake->containingCalls);
        $this->assertSame([], $fake->nearestCalls);
        $this->assertSame([], $fake->intersectingCalls);

        Event::assertDispatched(ResultadoEmitido::class);
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    /**
     * O caso que dá valor à regra: SEM nenhuma camada de zona/bairro
     * cadastrada (o cenário que, numa solicitação comum, encaminha à análise
     * por veredito pendente — anti-fachada), uma exclusão ainda assim defere,
     * porque não há uso novo a avaliar.
     */
    public function test_solicitacao_de_exclusao_defere_mesmo_sem_zona_identificada(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        // Nenhum GeoLayer vigente cadastrado: se o enquadramento fosse
        // consultado, o veredito ficaria "pendente" e encaminharia à análise.
        $fake = $this->espiaoEnquadramento();
        $request = $this->protocoladaComExclusao();

        $this->service()->decide($request);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status);
        $this->assertNotNull($fresh->decision()->first());
        $this->assertSame([], $fake->containingCalls);

        Event::assertDispatched(ResultadoEmitido::class);
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    /**
     * Guarda de escopo: sem marcação de exclusão, a solicitação continua
     * passando pelo enquadramento normalmente (nada muda no caminho comum).
     */
    public function test_solicitacao_comum_continua_passando_pelo_zoneamento(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $request = $this->protocoladaComumDeferivel();

        $this->service()->decide($request);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status);
        $this->assertNotNull($fresh->decision()->first());

        Event::assertDispatched(ResultadoEmitido::class);
    }
}
