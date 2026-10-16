<?php

namespace App\Services\Viabilidade;

use App\Enums\ConfiabilidadeConsulta;

/**
 * Estado de confiabilidade de uma consulta prévia, com o que falta informar
 * (`pendencias`) ou o motivo pelo qual o motor não concluiu (`motivo`).
 */
final readonly class ConfiabilidadeConsultaResultado
{
    /**
     * @param  list<array{campo: string, rotulo: string, itens?: list<string>}>  $pendencias
     */
    public function __construct(
        public ConfiabilidadeConsulta $estado,
        public array $pendencias = [],
        public ?string $motivo = null,
    ) {}

    public function conclusiva(): bool
    {
        return $this->estado === ConfiabilidadeConsulta::Conclusiva;
    }

    /**
     * @return array{estado: string, label: string, pendencias: list<array{campo: string, rotulo: string, itens?: list<string>}>, motivo: ?string}
     */
    public function toArray(): array
    {
        return [
            'estado' => $this->estado->value,
            'label' => $this->estado->label(),
            'pendencias' => $this->pendencias,
            'motivo' => $this->motivo,
        ];
    }
}
