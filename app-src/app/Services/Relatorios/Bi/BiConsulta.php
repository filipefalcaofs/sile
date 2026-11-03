<?php

namespace App\Services\Relatorios\Bi;

use App\Services\Relatorios\ReportFilters;

/**
 * Parâmetros de uma chamada à API de indicadores do BI: os filtros comuns dos
 * relatórios mais as opções de apresentação (agrupamento da série, limite de
 * linhas, precisão do mapa).
 */
final readonly class BiConsulta
{
    public const AGRUPAMENTOS = ['dia', 'semana', 'mes'];

    public const FILTROS = ['data_de', 'data_ate', 'setor', 'analista', 'bairro', 'cnae', 'categoria'];

    public function __construct(
        public ReportFilters $filtros,
        public string $agrupamento = 'mes',
        public ?int $limite = null,
        public int $precisao = 3,
        public int $minDecididas = 5,
        public ?string $situacao = null,
    ) {}

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function deArray(array $dados): self
    {
        return new self(
            filtros: ReportFilters::fromArray(array_intersect_key($dados, array_flip(self::FILTROS))),
            agrupamento: in_array($dados['agrupamento'] ?? null, self::AGRUPAMENTOS, true) ? $dados['agrupamento'] : 'mes',
            limite: isset($dados['limite']) ? (int) $dados['limite'] : null,
            precisao: isset($dados['precisao']) ? (int) $dados['precisao'] : 3,
            minDecididas: isset($dados['min_decididas']) ? (int) $dados['min_decididas'] : 5,
            situacao: isset($dados['situacao']) ? (string) $dados['situacao'] : null,
        );
    }

    public function limite(int $padrao): int
    {
        return $this->limite ?? $padrao;
    }

    /**
     * @return array<string, mixed>
     */
    public function aplicados(): array
    {
        return [
            ...$this->filtros->aplicados(),
            'agrupamento' => $this->agrupamento,
        ];
    }
}
