<?php

namespace Tests\Feature\Seeders;

use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\ViabilityRequestStatus;
use App\Models\GeoLayer;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\ExpressoDevSeeder;
use Database\Seeders\ZonaFicticiaDevSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Prova @group postgis do DEFERIMENTO NAVEGÁVEL do fluxo expresso sobre a ZONA
 * FICTÍCIA real (PostGIS): o seed de dev carrega a feição de zona (Centro) e,
 * com a LÓGICA REAL (motores LOUOS/risco + FluxoExpressoService), DEFERE de
 * verdade a solicitação dentro dela (com número TVL), enquanto a solicitação
 * FORA da zona degrada honestamente para em_analise.
 *
 * É a contraparte PostGIS do DatabaseSeederTest (SQLite, onde a geometria e a
 * decisão são puladas) — aqui a zona fictícia existe de verdade e o motor decide
 * sobre ela. Confirma entrega-funcional: dados fictícios (o polígono da zona),
 * lógica REAL (a decisão), produção honesta sem a zona oficial.
 */
#[Group('postgis')]
class ExpressoSeedPostgisTest extends PostgisTestCase
{
    public function test_seed_carrega_zona_ficticia_e_defere_exemplo_navegavel(): void
    {
        $this->seed();

        // A zona fictícia é a camada de zona VIGENTE (a GeoJsonLayerImporter
        // fechou a pendente_fonte): 1 feição real carregada via ST_*.
        $zona = GeoLayer::vigente(GeoLayerType::Zona)->first();
        $this->assertNotNull($zona, 'Esperava a camada de zona fictícia vigente em pgsql.');
        $this->assertSame(ZonaFicticiaDevSeeder::VERSION, $zona->version);
        $this->assertSame(1, $zona->feature_count);

        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->firstOrFail();

        // DEFERIMENTO navegável: a solicitação sobre a zona fictícia (Centro) com
        // o minimercado (risco baixo → expresso) é DEFERIDA pelo motor real, com
        // número TVL — o core value, sem fachada.
        $deferimento = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', ExpressoDevSeeder::MARK_DEFERIDA)
            ->firstOrFail();

        $this->assertSame(ViabilityRequestStatus::Deferida, $deferimento->status);
        $decision = $deferimento->decision;
        $this->assertNotNull($decision, 'O exemplo sobre a zona fictícia deveria ter uma decisão.');
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertStringStartsWith('TVL-', (string) $decision->tvl_product_number);

        // EM ANÁLISE honesto: a solicitação FORA da zona fictícia (Pituba) não
        // tem zona — o motor degrada para em_analise, SEM decisão e SEM TVL.
        $emAnalise = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->where('address_reference', ExpressoDevSeeder::MARK_EM_ANALISE)
            ->firstOrFail();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $emAnalise->status);
        $this->assertNull($emAnalise->decision, 'Sem zona, NÃO pode haver decisão (anti-fachada).');
    }
}
