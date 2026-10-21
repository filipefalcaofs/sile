<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Enums\ViabilityRequestStatus;
use App\Enums\ZonaFonte;
use App\Models\AnalysisRecord;
use App\Models\ViabilityRequest;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeBuildingFootprintRepository;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\TestCase;

class ReprocessarPoligonoAbertosCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FakeBuildingFootprintRepository $base;

    private FakeZonaMaiorPorcaoResolver $maiorPorcao;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.features.edificacoes_overture' => true,
            'viabiliza.features.edificacoes_microsoft' => false,
            'viabiliza.features.edificacoes_ia' => false,
        ]);

        $this->base = new FakeBuildingFootprintRepository;
        $this->app->instance(BuildingFootprintRepository::class, $this->base);
        $this->maiorPorcao = new FakeZonaMaiorPorcaoResolver;
        $this->app->instance(ZonaMaiorPorcaoResolver::class, $this->maiorPorcao);
    }

    public function test_grava_poligono_da_overture_e_recalcula_a_zona(): void
    {
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio());
        $this->maiorPorcao->para($this->predio(), FakeZonaMaiorPorcaoResolver::identificado('ZCMu-1'));
        $processo = $this->processo();

        $this->artisan('geo:poligono:reprocessar-abertos')
            ->expectsOutputToContain('Com polígono: 1')
            ->expectsOutputToContain('Processos que mudaram de zona')
            ->expectsOutputToContain('| ZPR 3      | ZCMu-1')
            ->assertSuccessful();

        $processo->refresh();
        $this->assertSame($this->predio(), $processo->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Overture, $processo->address_polygon_source);
        $this->assertSame('ZCMu-1', $processo->zona_codigo);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $processo->zona_fonte);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);
    }

    public function test_empate_grava_o_poligono_e_mantem_a_zona_do_ponto(): void
    {
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio());
        $this->maiorPorcao->para($this->predio(), FakeZonaMaiorPorcaoResolver::empate('ZPR 3', 'ZCMu-1'));
        $processo = $this->processo();

        $this->artisan('geo:poligono:reprocessar-abertos')->assertSuccessful();

        $processo->refresh();
        $this->assertSame(PoligonoFonte::Overture, $processo->address_polygon_source);
        $this->assertSame('ZPR 3', $processo->zona_codigo);
    }

    public function test_processo_decidido_ou_com_ficha_finalizada_fica_de_fora(): void
    {
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio());
        $this->maiorPorcao->para($this->predio(), FakeZonaMaiorPorcaoResolver::identificado('ZCMu-1'));
        $deferido = $this->processo(ViabilityRequestStatus::Deferida);
        $comFicha = $this->processo();
        AnalysisRecord::factory()->finalizada()->create(['viability_request_id' => $comFicha->id]);

        $this->artisan('geo:poligono:reprocessar-abertos')
            ->expectsOutputToContain('Processos vistos: 0')
            ->assertSuccessful();

        $this->assertNull($deferido->refresh()->address_polygon_geojson);
        $this->assertNull($comFicha->refresh()->address_polygon_geojson);
        $this->assertSame('ZPR 3', $comFicha->zona_codigo);
    }

    public function test_processo_sem_ponto_e_contado_e_nao_muda(): void
    {
        $processo = $this->processo(comPonto: false);

        $this->artisan('geo:poligono:reprocessar-abertos')
            ->expectsOutputToContain('Sem ponto: 1')
            ->assertSuccessful();

        $this->assertNull($processo->refresh()->address_polygon_geojson);
        $this->assertSame([], $this->base->consultas);
    }

    public function test_dry_run_mostra_a_mudanca_sem_gravar(): void
    {
        $this->base->comCandidata(FonteEdificacao::Overture, $this->predio());
        $this->maiorPorcao->para($this->predio(), FakeZonaMaiorPorcaoResolver::identificado('ZCMu-1'));
        $processo = $this->processo();

        $this->artisan('geo:poligono:reprocessar-abertos', ['--dry-run' => true])
            ->expectsOutputToContain('Com polígono: 1')
            ->expectsOutputToContain('ZCMu-1')
            ->assertSuccessful();

        $processo->refresh();
        $this->assertNull($processo->address_polygon_geojson);
        $this->assertSame('ZPR 3', $processo->zona_codigo);
        $this->assertDatabaseCount('lot_polygon_resolutions', 0);
    }

    public function test_sem_fonte_complementar_ligada_falha(): void
    {
        config(['viabiliza.features.edificacoes_overture' => false]);
        $this->processo();

        $this->artisan('geo:poligono:reprocessar-abertos')
            ->expectsOutputToContain('Nenhuma fonte de edificações ligada')
            ->assertFailed();
    }

    /**
     * @return array<string, mixed>
     */
    private function predio(): array
    {
        return ['type' => 'MultiPolygon', 'coordinates' => [[[
            [-38.4601, -12.9801], [-38.4599, -12.9801], [-38.4599, -12.9799], [-38.4601, -12.9799], [-38.4601, -12.9801],
        ]]]];
    }

    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise, bool $comPonto = true): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(['property_polygon_geojson' => null]);
        $processo->forceFill([
            'status' => $status,
            'protocol_number' => 'VIA-'.$processo->id,
            'zona_codigo' => 'ZPR 3',
            'zona_fonte' => ZonaFonte::Ponto,
            'address_polygon_geojson' => null,
            'property_point_lat' => $comPonto ? -12.98 : null,
            'property_point_lng' => $comPonto ? -38.46 : null,
            'property_location_source' => $comPonto ? PropertyLocationSource::CadastroImobiliario : null,
        ])->save();

        return $processo->fresh();
    }
}
