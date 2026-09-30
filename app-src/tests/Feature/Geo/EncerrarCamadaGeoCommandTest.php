<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EncerrarCamadaGeoCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_encerra_a_camada_vigente_sem_apagar_e_audita(): void
    {
        $camada = GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zona-ficticia-dev']);

        $this->artisan('geo:encerrar-camada', ['type' => 'zona', 'versao' => 'zona-ficticia-dev'])
            ->expectsOutputToContain('encerrada')
            ->assertSuccessful();

        $camada->refresh();

        $this->assertSame(GeoLayerStatus::Substituida, $camada->status);
        $this->assertNotNull($camada->valid_to);
        $this->assertNull(GeoLayer::vigente(GeoLayerType::Zona)->first());
        $this->assertTrue(Activity::query()->where('event', 'camada-encerrada')->exists());
    }

    public function test_versao_inexistente_falha(): void
    {
        $this->artisan('geo:encerrar-camada', ['type' => 'zona', 'versao' => 'nao-existe'])->assertFailed();
    }

}
