<?php

namespace App\Services\Geo;

/**
 * Decisão da zona do lote pelo art. 18, § 2º, da Lei 9.148/2016.
 * Sem zona vencedora quando falta polígono, a medição não fecha ou há empate.
 */
final readonly class ZonaLimitrofeAvaliacao
{
    public const IDENTIFICADO = 'identificado';

    public const EMPATE = 'empate';

    public const SEM_POLIGONO = 'sem_poligono';

    public const SEM_INTERSECAO = 'sem_intersecao';

    public const INDISPONIVEL = 'indisponivel';

    public const FONTE = 'maior_porcao_do_poligono';

    /**
     * @param  list<array{zona: string, area_m2: float, percentual: float}>  $porcoes
     * @param  list<string>  $empatadas
     */
    public function __construct(
        public string $status,
        public ?string $zonaVencedora,
        public array $porcoes,
        public array $empatadas,
        public ?string $motivo,
        public ?string $fonte,
    ) {}

    public static function semPoligono(): self
    {
        return new self(
            status: self::SEM_POLIGONO,
            zonaVencedora: null,
            porcoes: [],
            empatadas: [],
            motivo: 'Falta a geometria do terreno. A maior porção do art. 18, § 2º, da Lei 9.148/2016 não pode ser aplicada.',
            fonte: null,
        );
    }

    public static function indisponivel(string $motivo): self
    {
        return new self(
            status: self::INDISPONIVEL,
            zonaVencedora: null,
            porcoes: [],
            empatadas: [],
            motivo: $motivo,
            fonte: null,
        );
    }

    /**
     * A decisão da lei só substitui a zona quando a medição por área
     * aconteceu: zona vencedora (identificado) ou empate que a lei não
     * resolve. Sem polígono, sem interseção ou com a medição indisponível, o
     * território do ponto segue intacto — a degradação não apaga dado bom.
     */
    public function decideAZona(): bool
    {
        return in_array($this->status, [self::IDENTIFICADO, self::EMPATE], true);
    }

    public function aplicar(TerritoryResult $base): TerritoryResult
    {
        if (! $this->decideAZona()) {
            return $base;
        }

        $zona = $this->status === self::IDENTIFICADO
            ? [
                'status' => 'identificado',
                'nome' => $this->zonaVencedora,
                'propriedades' => [
                    'ZONA' => $this->zonaVencedora,
                    'fonte' => $this->fonte,
                    'porcoes' => $this->porcoes,
                ],
                'motivo' => null,
                'versao_camada' => self::FONTE,
            ]
            : [
                'status' => 'indisponivel',
                'nome' => null,
                'propriedades' => [
                    'fonte' => $this->fonte,
                    'porcoes' => $this->porcoes,
                    'empatadas' => $this->empatadas,
                ],
                'motivo' => $this->motivo,
                'versao_camada' => null,
            ];

        return new TerritoryResult(
            bairro: $base->bairro,
            via: $base->via,
            zona: $zona,
            lote: $base->lote,
            restricoes: $base->restricoes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'zona_vencedora' => $this->zonaVencedora,
            'porcoes' => $this->porcoes,
            'empatadas' => $this->empatadas,
            'motivo' => $this->motivo,
            'fonte' => $this->fonte,
        ];
    }
}
