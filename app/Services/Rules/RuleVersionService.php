<?php

namespace App\Services\Rules;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Models\RuleVersion;
use App\Support\Audit\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Versionamento de regras como dados (HU-019/HU-020/HU-053), espelhando
 * GeoLayerService::openVersion: a publicação de uma nova versão NÃO apaga a
 * anterior (a vigente é fechada — status substituída, valid_to preenchido) e a
 * nova nasce vigente. Diferente do GeoLayer, o rascunho coexiste com a vigente
 * (base do sandbox HU-143) e a publicação exige quatro olhos em domínios
 * sensíveis (publicador distinto do autor). Toda publicação é auditada com a
 * versão de regras (RN-002).
 */
class RuleVersionService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Abre um rascunho de versão para um domínio. Idempotente por
     * (domain, version): se já existir, devolve sem duplicar. NÃO mexe na
     * vigente — o rascunho coexiste com ela (base do sandbox HU-143).
     */
    public function openDraft(
        RuleDomain $domain,
        string $version,
        string $source,
        ?int $createdBy = null,
    ): RuleVersion {
        $existing = RuleVersion::query()
            ->where('domain', $domain->value)
            ->where('version', $version)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return RuleVersion::query()->create([
            'domain' => $domain,
            'version' => $version,
            'status' => RuleVersionStatus::Rascunho,
            'valid_from' => null,
            'valid_to' => null,
            'source' => $source,
            'rules_version' => $version,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Publica um rascunho: fecha a vigente anterior do mesmo domínio (sem
     * apagar) e promove o rascunho a vigente, auditando com a versão de regras.
     * Em domínio sensível, rejeita publicador igual ao autor (quatro olhos).
     *
     * O fechamento da anterior filtra status=vigente e exclui o próprio
     * rascunho: como o rascunho promovido também tem valid_to nulo, fechar
     * apenas por whereNull('valid_to') o marcaria como substituído por engano.
     *
     * @throws FourEyesViolationException
     */
    public function publish(
        RuleVersion $draft,
        ?int $publishedBy = null,
        ?CarbonInterface $validFrom = null,
    ): RuleVersion {
        return DB::transaction(function () use ($draft, $publishedBy, $validFrom): RuleVersion {
            if ($draft->domain->isSensitive() && $publishedBy !== null && $publishedBy === $draft->created_by) {
                throw new FourEyesViolationException(
                    'A publicação por quatro olhos exige um publicador diferente do autor do rascunho.'
                );
            }

            $validFrom ??= Carbon::today();

            $previous = RuleVersion::query()
                ->where('domain', $draft->domain->value)
                ->where('status', RuleVersionStatus::Vigente->value)
                ->whereNull('valid_to')
                ->whereKeyNot($draft->getKey())
                ->get();

            RuleVersion::query()
                ->where('domain', $draft->domain->value)
                ->where('status', RuleVersionStatus::Vigente->value)
                ->whereNull('valid_to')
                ->whereKeyNot($draft->getKey())
                ->update([
                    'status' => RuleVersionStatus::Substituida->value,
                    'valid_to' => $validFrom,
                ]);

            $draft->update([
                'status' => RuleVersionStatus::Vigente,
                'valid_from' => $validFrom,
                'published_at' => Carbon::now(),
                'published_by' => $publishedBy,
            ]);

            $this->audit->log(
                logName: 'regras',
                event: 'publicacao-versao',
                description: "Publicação da versão {$draft->version} do domínio {$draft->domain->label()}",
                properties: [
                    'dominio' => $draft->domain->value,
                    'versao' => $draft->version,
                    'substituiu' => $previous->pluck('version')->values()->all(),
                ],
                subject: $draft,
                result: 'sucesso',
                rulesVersion: $draft->version,
            );

            return $draft->refresh();
        });
    }
}
