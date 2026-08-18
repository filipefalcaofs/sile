<?php

namespace App\Services\Louos;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Services\Rules\RuleVersionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manutenção dos Quadros da LOUOS pelos mantenedores da SEDUR (HU-015..018 /
 * HU-046): atualizar um Quadro NÃO sobrescreve a vigente — publica uma NOVA
 * versão por quatro olhos, herdando o dado da versão vigente e aplicando as
 * alterações enviadas. A anterior é preservada como histórico (fechada pelo
 * RuleVersionService), nunca apagada. Espelha o RiscoMaintenanceService (Fase 6)
 * e REUSA o RuleVersionService — não recria infraestrutura de versão.
 */
class LouosMaintenanceService
{
    public function __construct(private RuleVersionService $versions) {}

    /**
     * Publica uma nova versão de um Quadro da LOUOS: abre o rascunho, copia as
     * linhas da tabela tipada da vigente, aplica as alterações e publica (quatro
     * olhos — publicador distinto do autor, exigido em domínio sensível).
     * Retorna a versão promovida a vigente.
     *
     * @param  list<array<string, mixed>>  $alteracoes  Estrutura por Quadro; chave natural:
     *                                                  Quadro 7 = cnae_code+area_min; Quadro 10 = zona+grupo_uso+subgrupo;
     *                                                  Quadro 11/11A = classe_via+grupo_uso.
     */
    public function publishNewVersion(
        RuleDomain $domain,
        string $version,
        array $alteracoes,
        int $authorId,
        int $publisherId,
    ): RuleVersion {
        return DB::transaction(function () use ($domain, $version, $alteracoes, $authorId, $publisherId): RuleVersion {
            $current = RuleVersion::vigente($domain)->first();

            $draft = $this->versions->openDraft(
                $domain,
                $version,
                $current?->source ?? 'Atualização manual do '.$domain->label().' (mantenedores SEDUR)',
                $authorId,
            );

            $this->copyRows($domain, $current, $draft, $alteracoes);

            return $this->versions->publish($draft, $publisherId);
        });
    }

    /**
     * Roteia a cópia das linhas da tabela tipada para o domínio do Quadro,
     * mantendo o service coeso (um switch, um método por tabela).
     *
     * @param  list<array<string, mixed>>  $alteracoes
     */
    private function copyRows(RuleDomain $domain, ?RuleVersion $current, RuleVersion $draft, array $alteracoes): void
    {
        match ($domain) {
            RuleDomain::LouosQuadro7 => $this->copyQuadro7($current, $draft, $alteracoes),
            RuleDomain::LouosQuadro10 => $this->copyQuadro10($current, $draft, $alteracoes),
            RuleDomain::LouosQuadro11, RuleDomain::LouosQuadro11a => $this->copyQuadro11($current, $draft, $alteracoes),
            default => throw new InvalidArgumentException(
                "Domínio {$domain->value} não é um Quadro da LOUOS mantido por esta operação.",
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
