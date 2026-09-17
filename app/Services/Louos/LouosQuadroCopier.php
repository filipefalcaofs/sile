<?php

namespace App\Services\Louos;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use InvalidArgumentException;

/**
 * Copia as linhas da tabela tipada de um Quadro da LOUOS da versão vigente
 * (ou nula, para primeiro cadastro) para uma versão de destino — rascunho
 * editável ou nova versão publicada. Centraliza a lógica de chave natural,
 * insert em lote sem model events e serialização de `condicoes` como JSON,
 * sendo reutilizada pela manutenção (LouosMaintenanceService) e pelo
 * rascunho editável (Task 3).
 */
class LouosQuadroCopier
{
    /**
     * Copia as linhas do Quadro indicado da versão `$from` para `$to`,
     * sobrepondo com as alterações fornecidas (mesma chave natural substitui).
     * Suporta Quadro 7, 10 e 11A; lança InvalidArgumentException para
     * domínios fora deste escopo.
     *
     * @param  list<array<string, mixed>>  $alteracoes
     */
    public function copy(RuleDomain $domain, ?RuleVersion $from, RuleVersion $to, array $alteracoes = []): void
    {
        match ($domain) {
            RuleDomain::LouosQuadro7 => $this->copyQuadro7($from, $to, $alteracoes),
            RuleDomain::LouosQuadro10 => $this->copyQuadro10($from, $to, $alteracoes),
            RuleDomain::LouosQuadro11a => $this->copyQuadro11($from, $to, $alteracoes),
            default => throw new InvalidArgumentException(
                "Domínio {$domain->value} não é um Quadro da LOUOS suportado pelo copier.",
            ),
        };
    }

    /**
     * Quadro 7 (faixas de área): chave natural cnae_code+area_min. Insert em
     * lote sem model events — a auditoria é o evento único do publish.
     *
     * @param  list<array<string, mixed>>  $alteracoes
     */
    private function copyQuadro7(?RuleVersion $current, RuleVersion $draft, array $alteracoes): void
    {
        $now = now();

        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        if ($current !== null) {
            LouosQuadro7Faixa::query()
                ->where('rule_version_id', $current->id)
                ->each(function (LouosQuadro7Faixa $faixa) use (&$rows, $draft, $now): void {
                    $code = (string) preg_replace('/\D/', '', (string) $faixa->cnae_code);
                    $rows[$code.'|'.(float) $faixa->area_min] = [
                        'rule_version_id' => $draft->id,
                        'cnae_code' => $code,
                        'grupo' => $faixa->grupo,
                        'subgrupo' => $faixa->subgrupo,
                        'area_min' => $faixa->area_min,
                        'area_max' => $faixa->area_max,
                        'observacao' => $faixa->observacao,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                });
        }

        foreach ($alteracoes as $alteracao) {
            $code = (string) preg_replace('/\D/', '', (string) $alteracao['cnae_code']);
            $areaMin = (float) ($alteracao['area_min'] ?? 0);

            $rows[$code.'|'.$areaMin] = [
                'rule_version_id' => $draft->id,
                'cnae_code' => $code,
                'grupo' => $alteracao['grupo'],
                'subgrupo' => $alteracao['subgrupo'] ?? null,
                'area_min' => $areaMin,
                'area_max' => $alteracao['area_max'] ?? null,
                'observacao' => $alteracao['observacao'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            LouosQuadro7Faixa::query()->insert(array_values($rows));
        }
    }

    /**
     * Quadro 10 (permissão por zona): chave natural zona+grupo_uso+subgrupo. O
     * subgrupo ausente é normalizado para '' (coerência com a carga/import).
     *
     * @param  list<array<string, mixed>>  $alteracoes
     */
    private function copyQuadro10(?RuleVersion $current, RuleVersion $draft, array $alteracoes): void
    {
        $now = now();

        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        if ($current !== null) {
            LouosQuadro10Permissao::query()
                ->where('rule_version_id', $current->id)
                ->each(function (LouosQuadro10Permissao $permissao) use (&$rows, $draft, $now): void {
                    $subgrupo = (string) $permissao->subgrupo;
                    $rows[$permissao->zona.'|'.$permissao->grupo_uso.'|'.$subgrupo] = [
                        'rule_version_id' => $draft->id,
                        'zona' => $permissao->zona,
                        'grupo_uso' => $permissao->grupo_uso,
                        'subgrupo' => $subgrupo,
                        'permissao' => $permissao->permissao->value,
                        'condicionante_ref' => $permissao->condicionante_ref,
                        'base_legal' => $permissao->base_legal,
                        'observacao' => $permissao->observacao,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                });
        }

        foreach ($alteracoes as $alteracao) {
            $subgrupo = (string) ($alteracao['subgrupo'] ?? '');

            $rows[$alteracao['zona'].'|'.$alteracao['grupo_uso'].'|'.$subgrupo] = [
                'rule_version_id' => $draft->id,
                'zona' => $alteracao['zona'],
                'grupo_uso' => $alteracao['grupo_uso'],
                'subgrupo' => $subgrupo,
                'permissao' => $alteracao['permissao'],
                'condicionante_ref' => $alteracao['condicionante_ref'] ?? null,
                'base_legal' => $alteracao['base_legal'] ?? null,
                'observacao' => $alteracao['observacao'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            LouosQuadro10Permissao::query()->insert(array_values($rows));
        }
    }

    /**
     * Quadros 11/11A (condições pela via): chave natural classe_via+grupo_uso. O
     * grupo_uso ausente é normalizado para '' (coerência com a carga/import);
     * `condicoes` (array) é regravado como JSON no insert em lote.
     *
     * @param  list<array<string, mixed>>  $alteracoes
     */
    private function copyQuadro11(?RuleVersion $current, RuleVersion $draft, array $alteracoes): void
    {
        $now = now();

        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        if ($current !== null) {
            LouosQuadro11CondicaoVia::query()
                ->where('rule_version_id', $current->id)
                ->each(function (LouosQuadro11CondicaoVia $condicao) use (&$rows, $draft, $now): void {
                    $grupoUso = (string) $condicao->grupo_uso;
                    $rows[$condicao->classe_via.'|'.$grupoUso] = [
                        'rule_version_id' => $draft->id,
                        'classe_via' => $condicao->classe_via,
                        'grupo_uso' => $grupoUso,
                        'condicoes' => $condicao->condicoes === null
                            ? null
                            : json_encode($condicao->condicoes, JSON_UNESCAPED_UNICODE),
                        'base_legal' => $condicao->base_legal,
                        'observacao' => $condicao->observacao,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                });
        }

        foreach ($alteracoes as $alteracao) {
            $grupoUso = (string) ($alteracao['grupo_uso'] ?? '');

            $rows[$alteracao['classe_via'].'|'.$grupoUso] = [
                'rule_version_id' => $draft->id,
                'classe_via' => $alteracao['classe_via'],
                'grupo_uso' => $grupoUso,
                'condicoes' => array_key_exists('condicoes', $alteracao) && $alteracao['condicoes'] !== null
                    ? json_encode($alteracao['condicoes'], JSON_UNESCAPED_UNICODE)
                    : null,
                'base_legal' => $alteracao['base_legal'] ?? null,
                'observacao' => $alteracao['observacao'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            LouosQuadro11CondicaoVia::query()->insert(array_values($rows));
        }
    }
}
