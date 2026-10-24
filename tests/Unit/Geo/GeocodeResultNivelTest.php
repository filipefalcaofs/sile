<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\GeocodeResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GeocodeResultNivelTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: bool}>
     */
    public static function tipos(): array
    {
        return [
            'rua' => ['road', true],
            'bairro' => ['suburb', true],
            'vizinhança' => ['neighbourhood', true],
            'distrito' => ['city_district', true],
            'cidade' => ['city', true],
            'estado' => ['state', true],
            'casa' => ['house', false],
            'prédio' => ['building', false],
            'estabelecimento' => ['amenity', false],
            'sem tipo conhecido' => [null, false],
        ];
    }

    #[DataProvider('tipos')]
    public function test_so_o_nivel_de_rua_ou_mais_amplo_nao_serve_de_lote(?string $tipo, bool $esperado): void
    {
        $resultado = new GeocodeResult(-12.97, -38.48, 'x', 0.5, [], $tipo);

        $this->assertSame($esperado, $resultado->nivelDeLogradouroOuMaior());
    }

    public function test_cache_guarda_o_tipo_e_o_endpoint_nao(): void
    {
        $resultado = new GeocodeResult(-12.97, -38.48, 'x', 0.5, ['city' => 'Salvador'], 'road');

        $this->assertArrayNotHasKey('address_type', $resultado->toArray());
        $this->assertSame('road', $resultado->toCache()['address_type']);
        $this->assertSame('road', GeocodeResult::fromCached($resultado->toCache())->addressType);
    }
}
