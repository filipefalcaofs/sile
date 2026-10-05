<?php

namespace App\Services\Relatorios;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;

/**
 * Materializa o tempo de viabilidade no processo a partir da decisão.
 *
 * Acionado pelo gancho `created` de {@see ViabilityDecision} — que cobre os
 * seis caminhos de decisão de uma vez — e pelo comando de recálculo, para os
 * processos decididos antes de a coluna existir.
 */
class TempoViabilidadeRegistrar
{
    public function __construct(
        private readonly TempoViabilidadeCalculator $calculator,
    ) {}

    public function registrar(ViabilityDecision $decision): ?float
    {
        $request = $decision->viabilityRequest;

        return $request === null ? null : $this->registrarProcesso($request);
    }

    /**
     * Idempotente: recalcular o mesmo processo produz o mesmo valor. Processo
     * sem tempo apurável fica com null, nunca com zero.
     */
    public function registrarProcesso(ViabilityRequest $request): ?float
    {
        $horas = $this->calculator->horasUteis($request->loadMissing(['decision', 'pendencies']));

        $request->forceFill(['viabilidade_horas_uteis' => $horas])->save();

        return $horas;
    }
}
