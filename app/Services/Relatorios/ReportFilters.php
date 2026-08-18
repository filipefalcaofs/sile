<?php

namespace App\Services\Relatorios;

use App\Services\Analise\ProcessoQueryService;
use Illuminate\Support\Carbon;

/**
 * Entrada do contrato de exportação (HU-131): um BAG SERIALIZÁVEL com o
 * vocabulário COMPLETO de filtros das telas — não só os campos do indicador.
 *
 * O round-trip {@see fromArray()}/{@see toArray()} é a garantia do RN-005 no
 * caminho ASSÍNCRONO: o GerarExportacaoJob serializa o bag e reconstrói
 * EXATAMENTE o mesmo conjunto filtrado via
 * `app($sourceClass)->definition(ReportFilters::fromArray($bag))`. Por isso a
 * normalização NÃO recorta para um subconjunto fixo de campos — o whitelisting
 * é responsabilidade de cada {@see Export\ReportSource} (lê só as chaves que
 * conhece), evitando o dump integral da trilha de auditoria com PII (RN-007).
 *
 * {@see toProcessoFiltros()} recorta o bag para as 17 chaves do
 * {@see ProcessoQueryService::filtered()} (retrofit da consulta de processos);
 * os acessores tipados ({@see from()}/{@see to()}/{@see setorId()}/...) expõem
 * valores normalizados aos serviços de indicador SEM duplicar SQL.
 */
final readonly class ReportFilters
{
    /**
     * Chaves do filtro completo do SAPS aceitas pelo ProcessoQueryService::filtered
     * (HU-082) — base do retrofit da consulta de processos (RN-005). O mapa do bag
     * já casa 1:1 com elas, então toProcessoFiltros só recorta.
     *
     * @var list<string>
     */
    public const PROCESSO_CHAVES = [
        'grupo', 'status', 'protocolo', 'bap', 'produto_tvl', 'servico', 'setor',
        'analista', 'inscricao', 'cep', 'logradouro', 'bairro', 'nome', 'cnpj',
        'data_de', 'data_ate', 'categoria',
    ];

    /**
     * @param  array<string, scalar>  $bag  Mapa normalizado chave => valor escalar.
     */
    public function __construct(public array $bag = []) {}

    /**
     * Normaliza um mapa cru de filtros: aplica trim às strings, descarta as
     * chaves vazias/nulas e PRESERVA o restante (vocabulário completo das telas).
     * NÃO recorta para um subconjunto fixo — o source faz o whitelisting.
     *
     * @param  array<string, mixed>  $f
     */
    public static function fromArray(array $f): self
    {
        $bag = [];

        foreach ($f as $chave => $valor) {
            if ($valor === null) {
                continue;
            }

            if (is_string($valor)) {
                $valor = trim($valor);

                if ($valor === '') {
                    continue;
                }
            }

            if (is_scalar($valor)) {
                $bag[$chave] = $valor;
            }
        }

        return new self($bag);
    }

    /**
     * Round-trip COMPLETO do bag (o Job serializa isto e reconstrói idêntico —
     * RN-005 no assíncrono).
     *
     * @return array<string, scalar>
     */
    public function toArray(): array
    {
        return $this->bag;
    }

    public function get(string $chave, mixed $default = null): mixed
    {
        return $this->bag[$chave] ?? $default;
    }

    /**
     * Subconjunto do bag restrito às chaves informadas (whitelisting do source).
     *
     * @param  list<string>  $chaves
     * @return array<string, scalar>
     */
    public function only(array $chaves): array
    {
        return array_intersect_key($this->bag, array_flip($chaves));
    }

    /**
     * Só os filtros efetivamente preenchidos (para a trilha de auditoria) — o bag
     * já é normalizado, então corresponde ao toArray.
     *
     * @return array<string, scalar>
     */
    public function aplicados(): array
    {
        return $this->bag;
    }

    /**
     * Recorta o bag para as 17 chaves do ProcessoQueryService::filtered (RN-005
     * do retrofit da consulta de processos). As chaves já casam 1:1.
     *
     * @return array<string, scalar>
     */
    public function toProcessoFiltros(): array
    {
        return $this->only(self::PROCESSO_CHAVES);
    }

    /**
     * Início do recorte de período (data_de no começo do dia), normalizado como o
     * ProcessoQueryService (formato YYYY-MM-DD). Sem data válida → null.
     */
    public function from(): ?Carbon
    {
        return $this->parseData('data_de')?->startOfDay();
    }

    /**
     * Fim do recorte de período (data_ate no fim do dia). Sem data válida → null.
     */
    public function to(): ?Carbon
    {
        return $this->parseData('data_ate')?->endOfDay();
    }

    public function setorId(): ?int
    {
        return $this->inteiro('setor');
    }

    public function analistaId(): ?int
    {
        return $this->inteiro('analista');
    }

    public function bairro(): ?string
    {
        return $this->texto('bairro');
    }

    public function cnae(): ?string
    {
        return $this->texto('cnae');
    }

    /**
     * Categoria derivada (HU-082 RN-005) validada contra o whitelist do
     * ProcessoQueryService — valor fora dele degrada para null (sem inventar).
     */
    public function categoria(): ?string
    {
        $valor = $this->texto('categoria');

        return $valor !== null && array_key_exists($valor, ProcessoQueryService::CATEGORIAS)
            ? $valor
            : null;
    }

    private function texto(string $chave): ?string
    {
        $valor = $this->bag[$chave] ?? null;

        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function inteiro(string $chave): ?int
    {
        $valor = $this->texto($chave);

        return $valor !== null && ctype_digit($valor) ? (int) $valor : null;
    }

    private function parseData(string $chave): ?Carbon
    {
        $valor = $this->texto($chave);

        if ($valor === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $valor) ?: null;
    }
}
