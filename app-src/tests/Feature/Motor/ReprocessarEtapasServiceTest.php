<?php

namespace Tests\Feature\Motor;

use App\Enums\AnalysisStatus;
use App\Enums\EtapaMotor;
use App\Enums\GeoLayerType;
use App\Enums\SituacaoEtapa;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use App\Services\Geo\SpatialRepository;
use App\Services\Motor\ReprocessarEtapasService;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

class ReprocessarEtapasServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $espacial = new FakeSpatialRepository;
        $espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $espacial);
    }

    private function cadastroIndisponivel(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyRegistryUnavailableException($inscricao);
            }
        });
    }

    private function cadastroLocalizado(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.9738,
                    longitude: -38.4803,
                    inscricao: $inscricao,
                    raw: ['CodigoLogradouroTributario' => '7172', 'LogradouroTributario' => 'Rua Carlos Marighella'],
                );
            }
        });
    }

    private function processoEmAnaliseSemPonto(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'property_registration' => '7810547',
            'property_polygon_geojson' => null,
            'property_point_lat' => null,
            'property_point_lng' => null,
            'used_area_m2' => 40,
        ]);
        $processo->cnaes()->attach(Cnae::factory()->create()->id, ['is_primary' => true]);

        return $processo;
    }

    private function localizacaoIndisponivel(ViabilityRequest $processo): void
    {
        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'viability_request_id' => $processo->id,
            'etapa' => EtapaMotor::LocalizacaoInscricao,
        ]);
    }

    public function test_sem_etapa_indisponivel_nao_faz_nada(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create();

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo);

        $this->assertFalse($resultado->reprocessou);
    }

    public function test_para_na_localizacao_quando_o_cadastro_continua_fora_do_ar(): void
    {
        $this->cadastroIndisponivel();
        $processo = $this->processoEmAnaliseSemPonto();
        $this->localizacaoIndisponivel($processo);

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo);

        $this->assertTrue($resultado->reprocessou);
        $this->assertSame(EtapaMotor::LocalizacaoInscricao, $resultado->paradaEm);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
        $this->assertSame(0, $processo->etapas()->where('etapa', EtapaMotor::Zona->value)->count());
    }

    public function test_cadastro_de_volta_grava_o_ponto_refaz_o_territorio_e_reavalia(): void
    {
        $this->cadastroLocalizado();
        $processo = $this->processoEmAnaliseSemPonto();
        $this->localizacaoIndisponivel($processo);

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo);

        $processo->refresh();

        $this->assertNull($resultado->paradaEm);
        $this->assertEqualsWithDelta(-12.9738, (float) $processo->property_point_lat, 0.0000001);
        $this->assertSame(
            SituacaoEtapa::Ok,
            $processo->etapas()->where('etapa', EtapaMotor::LocalizacaoInscricao->value)->first()->situacao,
        );
        $this->assertSame(
            SituacaoEtapa::Ok,
            $processo->etapas()->where('etapa', EtapaMotor::Zona->value)->first()?->situacao,
        );
        $this->assertSame('ZCN-1', $processo->zona_codigo);
    }

    public function test_registra_o_reprocesso_na_auditoria_com_o_ator(): void
    {
        $this->cadastroIndisponivel();
        $ator = User::factory()->create();
        $processo = $this->processoEmAnaliseSemPonto();
        $this->localizacaoIndisponivel($processo);

        app(ReprocessarEtapasService::class)->reprocessar($processo, $ator);

        $log = Activity::query()->where('log_name', 'motor')->where('event', 'reprocesso')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($processo->id, $log->properties['viability_request_id']);
        $this->assertSame($ator->id, $log->properties['ator_id']);
    }
}
