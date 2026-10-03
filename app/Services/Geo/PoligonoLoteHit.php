<?php

namespace App\Services\Geo;

use App\Enums\LotePoligonoFonte;

/**
 * Resposta de UMA fonte de geometria do lote. Ausência e indisponibilidade são
 * respostas legítimas e ficam registradas — nunca viram polígono vazio.
 */
final readonly class PoligonoLoteHit
{
    public const ENCONTRADO = 'encontrado';

    public const NAO_ENCONTRADO = 'nao_encontrado';

    public const AMBIGUO = 'ambiguo';

    public const INDISPONIVEL = 'indisponivel';

    /**
     * @param  array<string, mixed>|null  $geojson
     * @param  array<string, mixed>  $propriedades
     */
    public function __construct(
        public LotePoligonoFonte $fonte,
        public string $status,
        public ?array $geojson,
        public array $propriedades = [],
        public ?string $motivo = null,
        public int $candidatas = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $geojson
     * @param  array<string, mixed>  $propriedades
     */
    public static function encontrado(LotePoligonoFonte $fonte, array $geojson, array $propriedades = []): self
    {
        return new self($fonte, self::ENCONTRADO, $geojson, $propriedades, null, 1);
    }

    public static function naoEncontrado(LotePoligonoFonte $fonte, ?string $motivo = null): self
    {
        return new self($fonte, self::NAO_ENCONTRADO, null, [], $motivo);
    }

    public static function ambiguo(LotePoligonoFonte $fonte, int $candidatas, ?string $motivo = null): self
    {
        return new self(
            $fonte,
            self::AMBIGUO,
            null,
            [],
            $motivo ?? "Mais de uma geometria para a mesma chave ({$candidatas} candidatas).",
            $candidatas,
        );
    }

    public static function indisponivel(LotePoligonoFonte $fonte, string $motivo): self
    {
        return new self($fonte, self::INDISPONIVEL, null, [], $motivo);
    }

    public function temPoligono(): bool
    {
        return $this->status === self::ENCONTRADO && $this->geojson !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'geojson' => $this->geojson,
            'propriedades' => $this->propriedades,
            'motivo' => $this->motivo,
            'candidatas' => $this->candidatas,
        ];
    }
}
