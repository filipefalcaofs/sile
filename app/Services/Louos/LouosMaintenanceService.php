<?php

namespace App\Services\Louos;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Rules\RuleVersionService;
use Illuminate\Support\Facades\DB;

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
    public function __construct(
        private RuleVersionService $versions,
        private LouosQuadroCopier $copier,
    ) {}

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

            $this->copier->copy($domain, $current, $draft, $alteracoes);

            return $this->versions->publish($draft, $publisherId);
        });
    }
}
