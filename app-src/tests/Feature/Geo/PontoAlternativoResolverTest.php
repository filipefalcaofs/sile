<?php

namespace Tests\Feature\Geo;

use App\Enums\PropertyLocationSource;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\EnderecamentoHit;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\GeocoderException;
use App\Services\Geo\GeoServerEnderecamentoClient;
use App\Services\Geo\PontoAlternativoResolver;
use App\Services\Geo\SemapLoteClient;
use Tests\TestCase;

class PontoAlternativoResolverTest extends TestCase
{
    private function lote(float $lat, float $lng): EnderecamentoHit
    {
        $d = 0.0001;

        return new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, [
            'type' => 'Polygon',
            'coordinates' => [[
                [$lng - $d, $lat - $d], [$lng + $d, $lat - $d], [$lng + $d, $lat + $d], [$lng - $d, $lat + $d], [$lng - $d, $lat - $d],
            ]],
        ]);
    }

    private function semResultado(string $status = EnderecamentoHit::NAO_ENCONTRADO): EnderecamentoHit
    {
        return new EnderecamentoHit($status);
    }

    private function geocode(float $lat, float $lng): GeocodeResult
    {
        return new GeocodeResult($lat, $lng, 'Av. Heitor Dias, Salvador', 0.5, []);
    }

    /**
     * @param  EnderecamentoHit|\Throwable  $enderecamento
     * @param  EnderecamentoHit|\Throwable  $semap
     * @param  GeocodeResult|\Throwable  $nominatim
     */
    private function resolver(mixed $enderecamento, mixed $semap, mixed $nominatim): PontoAlternativoResolver
    {
        $this->mock(GeoServerEnderecamentoClient::class, function ($mock) use ($enderecamento): void {
            $mock->shouldReceive('consultar')->andReturnUsing(fn () => $enderecamento instanceof \Throwable
                ? throw $enderecamento
                : $enderecamento)->byDefault();
        });

        $this->mock(SemapLoteClient::class, function ($mock) use ($semap): void {
            $mock->shouldReceive('consultar')->andReturnUsing(fn () => $semap instanceof \Throwable
                ? throw $semap
                : $semap)->byDefault();
        });

        $this->app->instance(Geocoder::class, new class($nominatim) implements Geocoder
        {
            public function __construct(private mixed $resultado) {}

            public function geocode(string $address): GeocodeResult
            {
                return $this->resultado instanceof \Throwable ? throw $this->resultado : $this->resultado;
            }
        });

        return app(PontoAlternativoResolver::class);
    }

    public function test_ordem_enderecamento_semap_nominatim(): void
    {
        $resolver = $this->resolver($this->lote(-12.9710, -38.4750), $this->lote(-12.9720, -38.4760), $this->geocode(-12.9730, -38.4770));

        $candidatos = $resolver->candidatos('1078739', '2075', '98', 'Avenida Heitor Dias, Salvador');

        $this->assertSame(
            [PropertyLocationSource::EnderecamentoSedur, PropertyLocationSource::Semap, PropertyLocationSource::Geocodificacao],
            array_map(static fn ($c) => $c->fonte, $candidatos),
        );
        $this->assertEqualsWithDelta(-12.9710, $candidatos[0]->lat, 0.00001);
        $this->assertEqualsWithDelta(-38.4770, $candidatos[2]->lng, 0.00001);
    }

    public function test_fonte_indisponivel_e_pulada_e_as_outras_seguem(): void
    {
        $resolver = $this->resolver($this->semResultado(EnderecamentoHit::INDISPONIVEL), $this->lote(-12.972, -38.476), $this->geocode(-12.973, -38.477));

        $candidatos = $resolver->candidatos('1078739', '2075', '98', 'Avenida Heitor Dias, Salvador');

        $this->assertSame(
            [PropertyLocationSource::Semap, PropertyLocationSource::Geocodificacao],
            array_map(static fn ($c) => $c->fonte, $candidatos),
        );
    }

    public function test_todas_indisponiveis_devolve_lista_vazia_sem_excecao(): void
    {
        $resolver = $this->resolver(
            $this->semResultado(EnderecamentoHit::INDISPONIVEL),
            $this->semResultado(EnderecamentoHit::DESLIGADO),
            new GeocoderException('fora do ar'),
        );

        $this->assertSame([], $resolver->candidatos('1078739', '2075', '98', 'Avenida Heitor Dias, Salvador'));
    }

    public function test_excecao_de_uma_fonte_nao_derruba_as_outras(): void
    {
        $resolver = $this->resolver(new \RuntimeException('boom'), $this->lote(-12.972, -38.476), new AddressNotFoundException('x'));

        $candidatos = $resolver->candidatos('1078739', '2075', '98', 'Avenida Heitor Dias, Salvador');

        $this->assertSame([PropertyLocationSource::Semap], array_map(static fn ($c) => $c->fonte, $candidatos));
    }

    public function test_nominatim_fora_de_salvador_e_descartado(): void
    {
        $resolver = $this->resolver($this->semResultado(), $this->semResultado(), $this->geocode(-23.55, -46.63));

        $this->assertSame([], $resolver->candidatos('1078739', '2075', '98', 'Avenida Heitor Dias, Salvador'));
    }

    public function test_enderecamento_ambiguo_e_descartado(): void
    {
        $resolver = $this->resolver(new EnderecamentoHit(EnderecamentoHit::AMBIGUO, candidatas: 2), $this->semResultado(), new AddressNotFoundException('x'));

        $this->assertSame([], $resolver->candidatos('1078739', '2075', '98', null));
    }

    public function test_sem_codlog_ou_numero_pula_o_enderecamento(): void
    {
        $resolver = $this->resolver($this->lote(-12.971, -38.475), $this->semResultado(), new AddressNotFoundException('x'));

        $this->assertSame([], $resolver->candidatos('1078739', null, '98', null));
        $this->assertSame([], $resolver->candidatos('1078739', '2075', null, null));
    }

    public function test_sem_endereco_para_geocode_pula_o_nominatim(): void
    {
        $resolver = $this->resolver($this->semResultado(), $this->semResultado(), $this->geocode(-12.973, -38.477));

        $this->assertSame([], $resolver->candidatos('1078739', '2075', '98', null));
    }
}
