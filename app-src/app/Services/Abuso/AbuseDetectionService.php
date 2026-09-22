<?php

namespace App\Services\Abuso;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Models\AbuseAlert;
use App\Models\ViabilityRequest;
use App\Services\Abuso\Contracts\AbuseDetector;
use App\Services\Analise\MalhaFinaService;
use App\Support\Audit\AuditService;
use App\Support\Settings;

/**
 * Motor de detecção de abuso (HU-149). Orquestra os detectores da tag
 * 'abuse.detectors' sobre a janela `abuso.janela_dias`, faz UPSERT IDEMPOTENTE em
 * abuse_alerts (não duplica alerta aberto com o mesmo rule_key+fingerprint) e,
 * acima de `abuso.severidade_malha_fina`, encaminha o processo à malha fina pelo
 * caminho de SISTEMA (MalhaFinaService::encaminharSistema), gravando
 * fine_mesh_referral_id. NUNCA pune nem transiciona status (RN-001). NO-OP honesto
 * quando features.deteccao_abuso está OFF (default). Audita o ciclo (RN-002/RN-003).
 */
class AbuseDetectionService
{
    /**
     * @param  iterable<AbuseDetector>  $detectors
     */
    public function __construct(
        private iterable $detectors,
        private MalhaFinaService $malhaFina,
        private AuditService $audit,
    ) {}

    /**
     * Executa um ciclo de detecção. Resumo zerado e SEM gravação quando o toggle
     * está OFF (no-op honesto).
     *
     * @return array{executado: bool, criados: int, reaproveitados: int, encaminhados: int}
     */
    public function detectar(): array
    {
        if (! Settings::enabled('deteccao_abuso')) {
            return ['executado' => false, 'criados' => 0, 'reaproveitados' => 0, 'encaminhados' => 0];
        }

        $janelaDias = (int) Settings::get('abuso.janela_dias', 30);
        $window = DetectionWindow::lastDays($janelaDias);
        $limiar = $this->limiarMalhaFina();

        $criados = 0;
        $reaproveitados = 0;
        $encaminhados = 0;

        foreach ($this->detectors as $detector) {
            foreach ($detector->detect($window) as $finding) {
                if ($this->alertaAbertoExiste($finding)) {
                    $reaproveitados++;

                    continue;
                }

                $alert = $this->criarAlerta($finding, $window);
                $criados++;

                if ($this->encaminhavel($finding, $limiar) && $this->encaminharMalhaFina($alert, $finding)) {
                    $encaminhados++;
                }
            }
        }

        $this->audit->log('abuso', 'abuso-detectar', "Ciclo de detecção de abuso: {$criados} alerta(s) criado(s), {$encaminhados} encaminhado(s) à malha fina.", [
            'criados' => $criados,
            'reaproveitados' => $reaproveitados,
            'encaminhados' => $encaminhados,
            'janela_dias' => $janelaDias,
            'limiar_malha_fina' => $limiar->value,
        ]);

        return [
            'executado' => true,
            'criados' => $criados,
            'reaproveitados' => $reaproveitados,
            'encaminhados' => $encaminhados,
        ];
    }

    /**
     * Idempotência (12-03): já existe um alerta ABERTO com este rule_key+fingerprint?
     */
    private function alertaAbertoExiste(AbuseFinding $finding): bool
    {
        return AbuseAlert::query()
            ->where('rule_key', $finding->ruleKey)
            ->where('fingerprint', $finding->fingerprint)
            ->where('status', AbuseAlertStatus::Aberto)
            ->exists();
    }

    private function criarAlerta(AbuseFinding $finding, DetectionWindow $window): AbuseAlert
    {
        return AbuseAlert::create([
            'rule_key' => $finding->ruleKey,
            'severity' => $finding->severity,
            'status' => AbuseAlertStatus::Aberto,
            'fingerprint' => $finding->fingerprint,
            'evidence' => $finding->evidence,
            'viability_request_id' => $finding->viabilityRequestId,
            'subject_type' => $finding->subject?->getMorphClass(),
            'subject_id' => $finding->subject?->getKey(),
            'window_start' => $finding->windowStart ?? $window->start,
            'window_end' => $finding->windowEnd ?? $window->end,
            'detected_at' => now(),
        ]);
    }

    /**
     * Encaminha o processo à malha fina pelo caminho de sistema e grava o vínculo
     * no alerta. NUNCA transiciona status (RN-001 — encaminharSistema é ortogonal).
     */
    private function encaminharMalhaFina(AbuseAlert $alert, AbuseFinding $finding): bool
    {
        $request = ViabilityRequest::query()->find($finding->viabilityRequestId);

        if ($request === null) {
            return false;
        }

        $referral = $this->malhaFina->encaminharSistema($request, "suspeita de abuso: {$finding->ruleKey}");

        $alert->forceFill(['fine_mesh_referral_id' => $referral->id])->save();

        return true;
    }

    private function encaminhavel(AbuseFinding $finding, AbuseSeverity $limiar): bool
    {
        return $finding->severity->isAtLeast($limiar) && $finding->viabilityRequestId !== null;
    }

    /**
     * Limiar de encaminhamento à malha fina (HU-014), com fallback seguro em Alta.
     */
    private function limiarMalhaFina(): AbuseSeverity
    {
        $raw = (string) Settings::get('abuso.severidade_malha_fina', AbuseSeverity::Alta->value);

        return AbuseSeverity::tryFrom($raw) ?? AbuseSeverity::Alta;
    }
}
