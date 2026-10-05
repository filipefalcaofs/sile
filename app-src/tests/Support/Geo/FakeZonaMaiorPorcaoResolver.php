<?php

namespace Tests\Support\Geo;

use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;

class FakeZonaMaiorPorcaoResolver implements ZonaMaiorPorcaoResolver
{
    public int $chamadas = 0;

    /** @var array<string, ZonaMaiorPorcao> */
    private array $respostas = [];

    /**
     * @param  array<string, mixed>  $poligono
     */
    public function para(array $poligono, ZonaMaiorPorcao $resultado): self
    {
        $this->respostas[md5((string) json_encode($poligono))] = $resultado;

        return $this;
    }

    public function resolver(array $poligono): ZonaMaiorPorcao
    {
        $this->chamadas++;

        return $this->respostas[md5((string) json_encode($poligono))]
            ?? ZonaMaiorPorcao::indisponivel('Fake sem resposta para o polígono.');
    }

    /**
     * @param  list<array{zona: string, area_m2: float, percentual: float}>  $porcoes
     */
    public static function identificado(string $zona, array $porcoes = []): ZonaMaiorPorcao
    {
        return new ZonaMaiorPorcao(
            ZonaMaiorPorcao::IDENTIFICADO,
            codigo: $zona,
            porcoes: $porcoes !== [] ? $porcoes : [['zona' => $zona, 'area_m2' => 100.0, 'percentual' => 100.0]],
            camadas: [['type_name' => 'louos_teste:VM_TESTE', 'geometria' => 'poligonal', 'status' => 'ok']],
            areaLoteM2: 100.0,
        );
    }

    public static function empate(string $a, string $b): ZonaMaiorPorcao
    {
        return new ZonaMaiorPorcao(
            ZonaMaiorPorcao::EMPATE,
            porcoes: [['zona' => $a, 'area_m2' => 50.0, 'percentual' => 50.0], ['zona' => $b, 'area_m2' => 50.0, 'percentual' => 50.0]],
            empate: [$a, $b],
            areaLoteM2: 100.0,
        );
    }
}
