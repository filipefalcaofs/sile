<?php

namespace Tests\Unit\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Models\Parameter;
use App\Services\Geo\LocationValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validação de localização por sobreposição (HU-037 RN-004) provada SEM PostGIS:
 * com a base de lotes pendente (estado real atual) a validação comunica
 * "indisponível — base de lotes pendente SEDUR" sem fabricar lote (sem fachada);
 * o limiar é lido do parâmetro administrável geo.validacao.sobreposicao_minima
 * (HU-014); e a matemática do limiar (percent < limiar => alerta) é provada de
 * forma pura. O SQL espacial real (ST_Area/ST_Intersection) é exercido no
 * PostgisLocationValidationTest (@group postgis).
 */
class LocationValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LocationValidationService
    {
        return app(LocationValidationService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function poligono(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.50, -12.97],
                [-38.49, -12.97],
                [-38.49, -12.96],
                [-38.50, -12.96],
                [-38.50, -12.97],
            ]],
        ];
    }

    public function test_lote_pendente_de_fonte_retorna_indisponivel_sem_consulta_espacial(): void
    {
        GeoLayer::factory()->pendenteFonte()->create([
            'type' => GeoLayerType::Lote,
            'version' => 'lote-pendente',
        ]);

        $result = $this->service()->validate($this->poligono());

        $this->assertSame('indisponivel', $result->status);
        $this->assertNull($result->sobreposicaoPercentual);
        $this->assertFalse($result->alerta);
        $this->assertStringContainsString('pendente SEDUR', (string) $result->motivo);
    }

    public function test_sem_camada_de_lote_retorna_indisponivel(): void
    {
        // Estado real hoje: não há nenhuma camada de lote carregada.
        $result = $this->service()->validate($this->poligono());

        $this->assertSame('indisponivel', $result->status);
        $this->assertStringContainsString('pendente SEDUR', (string) $result->motivo);
    }

    public function test_limiar_segue_o_parametro_administravel(): void
    {
        // Parâmetro administrado (70) difere do fallback de config (50): o limiar
        // do resultado deve seguir o valor do banco (HU-014, efeito sem deploy).
        Parameter::factory()->integer('50')->create([
            'key' => 'geo.validacao.sobreposicao_minima',
            'group' => 'geo',
            'value' => '70',
        ]);

        GeoLayer::factory()->pendenteFonte()->create([
            'type' => GeoLayerType::Lote,
            'version' => 'lote-pendente',
        ]);

        $result = $this->service()->validate($this->poligono());

        $this->assertSame(70, $result->limiar);
    }

    public function test_decide_abaixo_do_limiar_gera_alerta(): void
    {
        $decisao = $this->service()->decide(25.0, 50);

        $this->assertSame('alerta_sobreposicao', $decisao['status']);
        $this->assertTrue($decisao['alerta']);
    }

    public function test_decide_no_limiar_ou_acima_valida_sem_alerta(): void
    {
        $this->assertSame('validado', $this->service()->decide(50.0, 50)['status']);
        $this->assertFalse($this->service()->decide(50.0, 50)['alerta']);
        $this->assertSame('validado', $this->service()->decide(99.9, 50)['status']);
    }
}
