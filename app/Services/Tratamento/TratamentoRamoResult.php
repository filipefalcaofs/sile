<?php

namespace App\Services\Tratamento;

final readonly class TratamentoRamoResult
{
    /**
     * @param  list<int>  $condicionantes
     * @param  array<string, mixed>|null  $planilha  Onde a planilha decidiu: versão, linha da aba CNAES, regra e o que definiu o fluxo
     * @param  array{numero: int, enunciado: string}|null  $perguntaSemResposta  Pergunta necessária ao enquadramento que ficou sem resposta
     * @param  'area'|'tipo_imovel'|'categoria_empresa'|null  $dadoFaltante  Dado de entrada que impediu o enquadramento do ramo
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
        public bool $bloqueia = false,
        public bool $cnlu = false,
        public ?array $planilha = null,
        public ?array $perguntaSemResposta = null,
        public ?string $dadoFaltante = null,
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
            'condicionantes_planilha' => $this->condicionantes,
            'bloqueia' => $this->bloqueia,
            'cnlu' => $this->cnlu,
            'planilha' => $this->planilha,
            'pergunta_sem_resposta' => $this->perguntaSemResposta,
            'dado_faltante' => $this->dadoFaltante,
        ];
    }
}
