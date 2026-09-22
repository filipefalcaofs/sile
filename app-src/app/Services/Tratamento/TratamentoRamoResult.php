<?php

namespace App\Services\Tratamento;

final readonly class TratamentoRamoResult
{
    /**
     * @param  list<int>  $condicionantes
     */
    public function __construct(
        public string $status,
        public ?string $grupo = null,
        public ?string $subgrupo = null,
        public ?string $codigoLouos = null,
        public ?string $risco = null,
        public string $fluxo = 'analise',
        public ?string $tll = null,
        public array $condicionantes = [],
        public ?string $motivo = null,
        public ?string $versaoRegra = null,
    ) {}

    public function resolvido(): bool
    {
        return $this->status === 'resolvido';
    }

    /**
     * @return array<string, mixed>
     */
    public function toEnquadramentoDimensao(): array
    {
        return [
            'status' => $this->resolvido() ? 'identificado' : 'nao_encontrado',
            'grupo' => $this->grupo,
            'subgrupo' => $this->subgrupo,
            'codigo_louos' => $this->codigoLouos,
            'motivo' => $this->motivo,
            'versao_regra' => $this->versaoRegra,
        ];
    }
}
