<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\GeoLayer;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use App\Services\Tratamento\TratamentoRamosSedur;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Resposta da SEDUR de 07/10/2026: "não permitir o prosseguimento do cadastro"
 * das regras 38, 39 e 55 significa cadastrar o processo e indeferi-lo logo em
 * seguida, com a mensagem da regra como justificativa.
 */
class ExpressoBloqueioPlanilhaTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private function protocolada(string $cnae, string $naturezaCodigo, array $respostas): ViabilityRequest
    {
        $versao = $this->seedTratamentoPlanilha();
        (new TratamentoRamosSedur)->carregar($versao, database_path('data/regras-20-08-26/regras-fluxo-sedur.json'));

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZR-1']]);
        $this->app->instance(SpatialRepository::class, $fake);

        $empresa = Company::factory()->create(['legal_nature_code' => $naturezaCodigo, 'legal_nature' => null]);
        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0, 'company_id' => $empresa->id]);
        $solicitacao->cnaes()->attach(Cnae::factory()->create(['code' => $cnae])->id, ['is_primary' => true]);
        $solicitacao->respostasTratamento = $respostas;

        return $solicitacao;
    }

    public function test_regra_38_empresa_que_nao_e_orgao_publico_e_indeferida_com_a_mensagem_da_regra(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $solicitacao = $this->protocolada('8411600', '2062', [7 => true]);

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $resultado->status);
        $this->assertSame(DecisionOutcome::Indeferida, $solicitacao->fresh()->decision->outcome);
        $this->assertStringContainsString(
            'Esta atividade só é permitida para órgãos da Administração Pública',
            (string) $solicitacao->fresh()->decision->reason,
        );
        $this->assertStringNotContainsString('Quadro 10', (string) $solicitacao->fresh()->decision->reason);
    }

    public function test_regra_39_resposta_incompativel_com_a_categoria_e_indeferida(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $solicitacao = $this->protocolada('8423000', '1015', [6 => true]);

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $resultado->status);
        $this->assertStringContainsString(
            'Resposta selecionada para a CNAE 8423-0/00 não é compatível com a categoria da empresa',
            (string) $solicitacao->fresh()->decision->reason,
        );
    }
}
