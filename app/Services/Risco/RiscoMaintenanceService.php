<?php

namespace App\Services\Risco;

use App\Enums\RuleDomain;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Services\Rules\RuleVersionService;
use Illuminate\Support\Facades\DB;

/**
 * Manutenção da tabela de risco municipal pelos mantenedores da SEDUR
 * (HU-020/HU-053): atualizar a classificação NÃO sobrescreve a vigente —
 * publica uma NOVA versão por quatro olhos, herdando as classificações da
 * versão vigente e aplicando as alterações enviadas. A anterior é preservada
 * como histórico (fechada pelo RuleVersionService), nunca apagada — disciplina
 * de versionamento já provada (06-01), sem fachada.
 */
class RiscoMaintenanceService
{
    public function __construct(private RuleVersionService $versions) {}

    /**
     * Publica uma nova versão da tabela de risco municipal: abre o rascunho,
     * copia as classificações da vigente, aplica as alterações e publica
     * (quatro olhos — publicador distinto do autor). Retorna a versão promovida
     * a vigente.
     *
     * @param  list<array{cnae_code: string, risco_municipal: string, condicionantes?: array<int, string>, observacao?: string|null}>  $alteracoes
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
                $current?->source ?? 'Atualização manual da tabela de risco (mantenedores SEDUR)',
                $authorId,
            );

            $this->copyClassifications($current, $draft, $alteracoes);

            return $this->versions->publish($draft, $publisherId);
        });
    }

    /**
     * Copia as classificações da versão vigente para o rascunho, aplicando as
     * alterações por CNAE (sobrescreve as existentes e adiciona novas). Usa
     * insert em lote (sem model events) — a auditoria da operação é o evento
     * único de publicação do RuleVersionService, não uma activity por linha.
     *
     * @param  list<array{cnae_code: string, risco_municipal: string, condicionantes?: array<int, string>, observacao?: string|null}>  $alteracoes
     */
    private function copyClassifications(?RuleVersion $current, RuleVersion $draft, array $alteracoes): void
    {
        $now = now();

        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        if ($current !== null) {
            RiskClassification::query()
                ->where('rule_version_id', $current->id)
                ->each(function (RiskClassification $classification) use (&$rows, $draft, $now): void {
                    $rows[$classification->cnae_code] = [
                        'rule_version_id' => $draft->id,
                        'cnae_code' => $classification->cnae_code,
                        'risco_municipal' => $classification->risco_municipal->value,
                        'condicionantes' => json_encode($classification->condicionantes ?? [], JSON_UNESCAPED_UNICODE),
                        'observacao' => $classification->observacao,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                });
        }

        foreach ($alteracoes as $alteracao) {
            $code = preg_replace('/\D/', '', (string) $alteracao['cnae_code']);

            $rows[$code] = [
                'rule_version_id' => $draft->id,
                'cnae_code' => $code,
                'risco_municipal' => $alteracao['risco_municipal'],
                'condicionantes' => json_encode($alteracao['condicionantes'] ?? [], JSON_UNESCAPED_UNICODE),
                'observacao' => $alteracao['observacao'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            RiskClassification::query()->insert(array_values($rows));
        }
    }
}
