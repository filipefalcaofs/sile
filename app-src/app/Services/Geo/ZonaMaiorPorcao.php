<?php

namespace App\Services\Geo;

/**
 * Zona do terreno pela maior porção (art. 18, § 2º, da Lei 9.148/2016): área
 * da interseção do polígono com cada zona candidata, da maior para a menor.
 */
final readonly class ZonaMaiorPorcao
{
    public const IDENTIFICADO = 'identificado';

    public const EMPATE = 'empate';

    public const SEM_INTERSECAO = 'sem_intersecao';

    public const INDISPONIVEL = 'indisponivel';

    /** Diferença de área abaixo da qual duas porções empatam (ruído de ponto flutuante). */
    public const TOLERANCIA_EMPATE_M2 = 0.01;

    /**
     * @param  list<array{zona: string, area_m2: float, percentual: float}>  $porcoes
     * @param  list<string>  $empate
     * @param  list<array{type_name: string, geometria: ?string, status: string}>  $camadas
     */
    public function __construct(
        public string $status,
        public ?string $codigo = null,
        public array $porcoes = [],
        public array $empate = [],
        public array $camadas = [],
        public ?float $areaLoteM2 = null,
        public ?string $motivo = null,
    ) {}

    /**
     * @param  list<array{type_name: string, geometria: ?string, status: string}>  $camadas
     */
    public static function indisponivel(string $motivo, array $camadas = []): self
    {
        return new self(self::INDISPONIVEL, camadas: $camadas, motivo: $motivo);
    }

    public function identificado(): bool
    {
        return $this->status === self::IDENTIFICADO && $this->codigo !== null;
    }

    /**
     * @return array{status: string, codigo: ?string, porcoes: list<array{zona: string, area_m2: float, percentual: float}>, empate: list<string>, camadas: list<array{type_name: string, geometria: ?string, status: string}>, area_lote_m2: ?float, motivo: ?string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'codigo' => $this->codigo,
            'porcoes' => $this->porcoes,
            'empate' => $this->empate,
            'camadas' => $this->camadas,
            'area_lote_m2' => $this->areaLoteM2,
            'motivo' => $this->motivo,
        ];
    }
}
