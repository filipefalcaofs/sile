<?php

namespace App\Services\Geo\Edificacoes;

use App\Enums\FonteEdificacao;

/**
 * Edificação escolhida para o ponto do imóvel.
 */
final readonly class EdificacaoCandidata
{
    public const CONTEM_PONTO = 'contem_ponto';

    public const MENOR_AREA = 'menor_area_entre_sobrepostos';

    /**
     * @param  array<string, mixed>  $geojson
     */
    public function __construct(
        public int $id,
        public FonteEdificacao $fonte,
        public string $sourceId,
        public string $release,
        public array $geojson,
        public float $areaM2,
        public ?float $confidence,
        public string $metodo,
    ) {}
}
