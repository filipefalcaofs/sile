<?php

namespace Tests\Unit\Geo;

use App\Enums\PoligonoFonte;
use App\Models\ViabilityRequest;
use Tests\TestCase;

class LocalizacaoNaoConfirmadaTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(array $atributos): ViabilityRequest
    {
        $processo = new ViabilityRequest;
        $processo->forceFill($atributos);

        return $processo;
    }

    private function lote(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[-38.48, -12.96], [-38.47, -12.96], [-38.47, -12.95], [-38.48, -12.96]]]];
    }

    public function test_sem_conferencia_nao_ha_aviso(): void
    {
        $this->assertNull($this->processo([])->localizacaoNaoConfirmada());
    }

    public function test_conferencia_confirmada_ou_substituida_nao_tem_aviso(): void
    {
        foreach (['confirmado', 'substituido', 'nao_verificado'] as $confirmacao) {
            $processo = $this->processo(['localizacao_conferencia' => ['confirmacao' => $confirmacao, 'distancia_m' => 10.0]]);

            $this->assertNull($processo->localizacaoNaoConfirmada(), $confirmacao);
        }
    }

    public function test_nao_confirmada_com_lote_tirado_do_ponto(): void
    {
        $processo = $this->processo([
            'localizacao_conferencia' => ['confirmacao' => 'nao_confirmado', 'distancia_m' => 405.85, 'logradouro' => 'Avenida Heitor Dias'],
            'address_polygon_geojson' => $this->lote(),
            'address_polygon_source' => PoligonoFonte::Ponto,
        ]);

        $this->assertSame(
            ['distancia_m' => 406.0, 'logradouro' => 'Avenida Heitor Dias', 'lote_do_ponto' => true],
            $processo->localizacaoNaoConfirmada(),
        );
    }

    public function test_lote_da_chave_nao_e_lote_do_ponto(): void
    {
        $processo = $this->processo([
            'localizacao_conferencia' => ['confirmacao' => 'nao_confirmado', 'distancia_m' => 300.0, 'logradouro' => null],
            'address_polygon_geojson' => $this->lote(),
            'address_polygon_source' => PoligonoFonte::Chave,
        ]);

        $this->assertFalse($processo->localizacaoNaoConfirmada()['lote_do_ponto']);
    }

    public function test_sem_lote_nao_ha_lote_do_ponto(): void
    {
        $processo = $this->processo([
            'localizacao_conferencia' => ['confirmacao' => 'nao_confirmado', 'distancia_m' => 300.0],
            'address_polygon_source' => PoligonoFonte::Ponto,
        ]);

        $this->assertFalse($processo->localizacaoNaoConfirmada()['lote_do_ponto']);
        $this->assertNull($processo->localizacaoNaoConfirmada()['logradouro']);
    }
}
