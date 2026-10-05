<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RegistroDasEtapasTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function processoSemPonto(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'property_registration' => '7810547',
            'property_polygon_geojson' => null,
            'property_point_lat' => null,
            'property_point_lng' => null,
        ]);
    }

    private function cadastro(callable $resolver): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($resolver) implements PropertyRegistryLookup
        {
            public function __construct(private $resolver) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return ($this->resolver)($inscricao);
            }
        });
    }

    public function test_cadastro_fora_do_ar_registra_a_localizacao_como_indisponivel(): void
    {
        $this->cadastro(fn (string $i) => throw new PropertyRegistryUnavailableException($i));
        $processo = $this->processoSemPonto();

        app(TerritorioProcessoService::class)->localizarPelaInscricao($processo);

        $etapa = $processo->etapas()->where('etapa', EtapaMotor::LocalizacaoInscricao->value)->first();

        $this->assertSame(SituacaoEtapa::Indisponivel, $etapa->situacao);
        $this->assertNotNull($etapa->proxima_tentativa_em);
    }

    public function test_inscricao_nao_encontrada_e_definitiva(): void
    {
        $this->cadastro(fn (string $i) => throw new PropertyNotFoundException($i));
        $processo = $this->processoSemPonto();

        app(TerritorioProcessoService::class)->localizarPelaInscricao($processo);

        $etapa = $processo->etapas()->where('etapa', EtapaMotor::LocalizacaoInscricao->value)->first();

        $this->assertSame(SituacaoEtapa::Definitiva, $etapa->situacao);
        $this->assertNull($etapa->proxima_tentativa_em);
    }

    public function test_localizacao_bem_sucedida_registra_ok(): void
    {
        $this->cadastro(fn (string $i) => new PropertyRegistryResult(
            latitude: -12.9738,
            longitude: -38.4803,
            inscricao: $i,
            raw: ['CodigoLogradouroTributario' => '7172', 'LogradouroTributario' => 'Rua Carlos Marighella'],
        ));
        $processo = $this->processoSemPonto();

        app(TerritorioProcessoService::class)->localizarPelaInscricao($processo);

        $etapa = $processo->etapas()->where('etapa', EtapaMotor::LocalizacaoInscricao->value)->first();

        $this->assertSame(SituacaoEtapa::Ok, $etapa->situacao);
    }

    public function test_processo_que_ja_tem_ponto_nao_gera_etapa_de_localizacao(): void
    {
        $processo = $this->processoSemPonto();
        $processo->forceFill(['property_point_lat' => -12.97, 'property_point_lng' => -38.48])->save();

        app(TerritorioProcessoService::class)->localizarPelaInscricao($processo);

        $this->assertSame(0, $processo->etapas()->count());
    }
}
