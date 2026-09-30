<?php

namespace Tests\Unit\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use App\Services\Geo\TerritoryService;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

class TerritoryServiceClasseViaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function servico(ClassificacaoViariaHit $hit): TerritoryService
    {
        GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Via,
            'version' => 'via-2026',
            'feature_count' => 1,
        ]);

        $fake = new FakeSpatialRepository;
        $fake->setNearest(GeoLayerType::Via, [
            'id' => 1,
            'properties' => ['CODLOG' => 822, 'NOME_LOGRADOURO' => 'Avenida Joana Angélica'],
            'distancia_m' => 12.0,
        ]);

        $client = Mockery::mock(GeoServerClassificacaoViariaClient::class);
        $client->shouldReceive('consultar')->with('822')->andReturn($hit);

        return new TerritoryService($fake, app(AuditService::class), null, $client);
    }

    public function test_classe_viaria_entra_nas_propriedades_da_via(): void
    {
        $resultado = $this->servico(new ClassificacaoViariaHit(
            ClassificacaoViariaHit::IDENTIFICADO, 'VC I', 'VC-I', null,
        ))->identify(-12.98, -38.51);

        $this->assertSame('VC I', $resultado->via['propriedades']['CLASSE_VIA_LOUOS']);
    }

    public function test_sem_classe_a_via_carrega_o_motivo_e_nao_inventa_atributo(): void
    {
        $resultado = $this->servico(new ClassificacaoViariaHit(
            ClassificacaoViariaHit::INDISPONIVEL, null, null, 'GeoServer SEDUR indisponível.',
        ))->identify(-12.98, -38.51);

        $this->assertArrayNotHasKey('CLASSE_VIA_LOUOS', $resultado->via['propriedades']);
        $this->assertSame('GeoServer SEDUR indisponível.', $resultado->via['classe_via_motivo']);
    }
}
