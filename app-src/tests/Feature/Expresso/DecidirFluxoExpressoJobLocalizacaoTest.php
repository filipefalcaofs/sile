<?php

namespace Tests\Feature\Expresso;

use App\Enums\ViabilityRequestOrigin;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\ViabilityRequest;
use App\Services\Expresso\DecisionResult;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Falha transitória do Cadastro Imobiliário não vira resultado definitivo: o
 * job de decisão tenta localizar o imóvel pela inscrição antes de decidir e,
 * com o cadastro fora do ar, adia a decisão até esgotar as tentativas.
 */
class DecidirFluxoExpressoJobLocalizacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function protocoladaSemPonto(): ViabilityRequest
    {
        return ViabilityRequest::factory()->protocoled()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'property_registration' => '7810547',
            'property_polygon_geojson' => null,
            'property_point_lat' => null,
            'property_point_lng' => null,
        ]);
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

    private function cadastroSemInscricao(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyNotFoundException($inscricao);
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

    private function job(ViabilityRequest $request, int $tentativa): DecidirFluxoExpressoJob
    {
        $job = new DecidirFluxoExpressoJob($request->id);
        $job->withFakeQueueInteractions();
        $job->job->attempts = $tentativa;

        return $job;
    }

    public function test_cadastro_indisponivel_adia_a_decisao_enquanto_ha_tentativas(): void
    {
        config(['sile.expresso.job.tries' => 3, 'sile.expresso.job.backoff' => [30, 60, 120]]);
        $this->cadastroIndisponivel();
        $request = $this->protocoladaSemPonto();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldNotReceive('decide');

        $job = $this->job($request, 1);
        $job->handle($service);

        $this->assertTrue($job->job->isReleased());
        $this->assertSame(30, $job->job->releaseDelay);
    }

    public function test_cadastro_indisponivel_na_ultima_tentativa_decide_com_a_degradacao_honesta(): void
    {
        config(['sile.expresso.job.tries' => 3, 'sile.expresso.job.backoff' => [30, 60, 120]]);
        $this->cadastroIndisponivel();
        $request = $this->protocoladaSemPonto();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        $job = $this->job($request, 3);
        $job->handle($service);

        $this->assertFalse($job->job->isReleased());
    }

    public function test_inscricao_nao_encontrada_e_definitiva_e_decide_na_hora(): void
    {
        config(['sile.expresso.job.tries' => 3]);
        $this->cadastroSemInscricao();
        $request = $this->protocoladaSemPonto();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        $job = $this->job($request, 1);
        $job->handle($service);

        $this->assertFalse($job->job->isReleased());
    }

    public function test_cadastro_que_volta_na_segunda_tentativa_grava_o_ponto_antes_de_decidir(): void
    {
        config(['sile.expresso.job.tries' => 3]);
        $this->cadastroLocalizado();
        $request = $this->protocoladaSemPonto();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        $job = $this->job($request, 2);
        $job->handle($service);

        $request->refresh();

        $this->assertEqualsWithDelta(-12.9738, (float) $request->property_point_lat, 0.0000001);
        $this->assertEqualsWithDelta(-38.4803, (float) $request->property_point_lng, 0.0000001);
        $this->assertFalse($job->job->isReleased());
    }

    public function test_processo_que_ja_tem_ponto_nao_consulta_o_cadastro_de_novo(): void
    {
        config(['sile.expresso.job.tries' => 3]);
        $this->cadastroIndisponivel();
        $request = $this->protocoladaSemPonto();
        $request->forceFill(['property_point_lat' => -12.97, 'property_point_lng' => -38.48])->save();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldReceive('decide')->once()->andReturn(DecisionResult::paraAnalise('teste'));

        $job = $this->job($request, 1);
        $job->handle($service);

        $this->assertFalse($job->job->isReleased());
    }
}
