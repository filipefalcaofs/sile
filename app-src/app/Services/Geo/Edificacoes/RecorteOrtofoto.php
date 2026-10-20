<?php

namespace App\Services\Geo\Edificacoes;

/**
 * Recorte da ortofoto em volta do ponto do imóvel, com o retângulo em graus
 * (EPSG:4326) e o tamanho em pixels.
 */
final readonly class RecorteOrtofoto
{
    public function __construct(
        public string $caminhoPng,
        public float $oeste,
        public float $sul,
        public float $leste,
        public float $norte,
        public int $largura,
        public int $altura,
        public string $servico,
        public string $ano,
    ) {}

    /**
     * Pixel (x, y) do ponto, com origem no canto superior esquerdo.
     *
     * @return array{0: int, 1: int}
     */
    public function pixel(float $lat, float $lng): array
    {
        return [
            (int) round(($lng - $this->oeste) / ($this->leste - $this->oeste) * $this->largura),
            (int) round(($this->norte - $lat) / ($this->norte - $this->sul) * $this->altura),
        ];
    }

    /**
     * @return array{0: float, 1: float} [lng, lat]
     */
    public function coordenada(float $x, float $y): array
    {
        return [
            $this->oeste + $x / $this->largura * ($this->leste - $this->oeste),
            $this->norte - $y / $this->altura * ($this->norte - $this->sul),
        ];
    }
}
