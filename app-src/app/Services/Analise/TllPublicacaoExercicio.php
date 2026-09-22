<?php

namespace App\Services\Analise;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Services\Rules\RuleVersionService;
use DomainException;

/**
 * Publica o rascunho do exercício TLL. Quatro olhos ficam no
 * RuleVersionService (domínio sensível): publicador ≠ autor.
 */
class TllPublicacaoExercicio
{
    public function __construct(private RuleVersionService $ruleVersions) {}

    public function publicar(int $exercicio, int $publicadorId): RuleVersion
    {
        $rascunho = RuleVersion::query()
            ->where('domain', RuleDomain::TllValores->value)
            ->where('version', (string) $exercicio)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->first();

        if ($rascunho === null) {
            throw new DomainException("Não há rascunho do exercício {$exercicio} para publicar.");
        }

        return $this->ruleVersions->publish($rascunho, $publicadorId);
    }
}
