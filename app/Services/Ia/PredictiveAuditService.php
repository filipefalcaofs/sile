<?php

namespace App\Services\Ia;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Enums\DecisionOutcome;
use App\Models\PredictiveAnomaly;
use App\Models\ViabilityRequest;
use App\Services\Analise\MalhaFinaService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Auditoria Preditiva de Processos Expressos (Módulo 3 — IA/Malha Fina). Varre os
 * deferimentos AUTOMÁTICOS do fluxo expresso na janela e pontua sinais
 * DETERMINÍSTICOS de risco (volume de deferimentos por CNPJ, inscrição
 * imobiliária repetida, requerente que prosseguiu apesar de alerta), criando uma
 * anomalia quando o score supera o limiar parametrizado e, na severidade alta,
 * encaminhando o processo à malha fina (reuso de {@see MalhaFinaService::encaminharSistema()}).
 *
 * ANTI-FACHADA / GOVERNANÇA (LGPD art. 20): nasce DESLIGADA (no-op honesto
 * quando `features.ia_auditoria_preditiva` está OFF — default); NUNCA pune nem
 * transiciona o status do processo — só gera ALERTA revisável pelo gestor e
 * malha fina (ortogonal ao status). UPSERT idempotente por fingerprint (não
 * duplica anomalia aberta do mesmo processo). O ciclo é auditado (RN-002).
 *
 * A pontuação é determinística (regras + pesos), sem chamada a provedor de IA: é
 * "preditiva" no sentido de antecipar risco para revisão humana, sem decidir.
 * Limiares/janela são administráveis (HU-014); os pesos são constantes técnicas.
 */
class PredictiveAuditService
{
    private const FLOW_EXPRESSO = 'expresso';

    private const PESO_VOLUME = 40;

    private const PESO_INSCRICAO = 30;

    private const PESO_PROSSEGUIU = 40;

    public function __construct(
        private MalhaFinaService $malhaFina,
        private AuditService $audit,
    ) {}

    /**
     * Executa um ciclo de varredura. Resumo zerado e SEM gravação quando o toggle
     * está OFF (no-op honesto).
     *
     * @return array{executado: bool, criadas: int, reaproveitadas: int, encaminhadas: int}
     */
    public function executar(): array
    {
        if (! Settings::enabled('ia_auditoria_preditiva')) {
            return ['executado' => false, 'criadas' => 0, 'reaproveitadas' => 0, 'encaminhadas' => 0];
        }

        $janelaDias = (int) Settings::get('ia.auditoria_preditiva.janela_dias', 30);
        $limiarScore = (int) Settings::get('ia.auditoria_preditiva.limiar_score', 70);
        $volumeLimite = (int) Settings::get('abuso.volume_cnpj.limite', 5);

        $inicio = now()->subDays($janelaDias);
        $fim = now();

        $volumePorCompany = $this->base($inicio, $fim)
            ->whereNotNull('viability_requests.company_id')
            ->groupBy('viability_requests.company_id')
            ->selectRaw('viability_requests.company_id as company_id, count(*) as total')
            ->pluck('total', 'company_id');

        $volumePorInscricao = $this->base($inicio, $fim)
            ->whereNotNull('viability_requests.property_registration')
            ->groupBy('viability_requests.property_registration')
            ->selectRaw('viability_requests.property_registration as inscricao, count(*) as total')
            ->pluck('total', 'inscricao');

        $deferidos = $this->base($inicio, $fim)
            ->select('viability_requests.*')
            ->orderBy('viability_requests.id')
            ->get();

        $criadas = 0;
        $reaproveitadas = 0;
        $encaminhadas = 0;

        foreach ($deferidos as $request) {
            [$score, $factors] = $this->pontuar($request, $volumePorCompany, $volumePorInscricao, $volumeLimite);

            if ($score < $limiarScore) {
                continue;
            }

            $fingerprint = 'req:'.$request->id;

            if ($this->anomaliaAbertaExiste($fingerprint)) {
                $reaproveitadas++;

                continue;
            }

            $severity = $this->severidade($score);
            $anomalia = $this->criar($request, $score, $severity, $factors, $fingerprint, $inicio, $fim);
            $criadas++;

            if ($severity === AbuseSeverity::Alta && $this->encaminhar($anomalia, $request)) {
                $encaminhadas++;
            }
        }

        $this->audit->log('ia', 'auditoria-preditiva-executar', "Varredura de auditoria preditiva: {$criadas} anomalia(s) criada(s), {$encaminhadas} encaminhada(s) à malha fina.", [
            'criadas' => $criadas,
            'reaproveitadas' => $reaproveitadas,
            'encaminhadas' => $encaminhadas,
            'janela_dias' => $janelaDias,
            'limiar_score' => $limiarScore,
        ]);

        return ['executado' => true, 'criadas' => $criadas, 'reaproveitadas' => $reaproveitadas, 'encaminhadas' => $encaminhadas];
    }

    /**
     * Base: deferimentos do fluxo EXPRESSO na janela (join com a decisão). Cada
     * chamada devolve um Builder fresco.
     *
     * @return Builder<ViabilityRequest>
     */
    private function base(Carbon $inicio, Carbon $fim): Builder
    {
        return ViabilityRequest::query()
            ->join('viability_decisions as vd', 'vd.viability_request_id', '=', 'viability_requests.id')
            ->where('vd.flow', self::FLOW_EXPRESSO)
            ->where('vd.outcome', DecisionOutcome::Deferida->value)
            ->whereBetween('vd.decided_at', [$inicio, $fim]);
    }

    /**
     * Pontua um processo pelos sinais determinísticos; devolve [score, fatores].
     *
     * @param  Collection<int|string, int>  $volumePorCompany
     * @param  Collection<int|string, int>  $volumePorInscricao
     * @return array{0: int, 1: list<array{chave: string, peso: int, detalhe: string}>}
     */
    private function pontuar(ViabilityRequest $request, Collection $volumePorCompany, Collection $volumePorInscricao, int $volumeLimite): array
    {
        $score = 0;
        $factors = [];

        $volumeCnpj = (int) ($volumePorCompany[$request->company_id] ?? 0);
        if ($request->company_id !== null && $volumeCnpj >= $volumeLimite) {
            $score += self::PESO_VOLUME;
            $factors[] = ['chave' => 'volume_cnpj', 'peso' => self::PESO_VOLUME, 'detalhe' => "{$volumeCnpj} deferimentos do mesmo CNPJ na janela"];
        }

        if ($request->property_registration !== null && (int) ($volumePorInscricao[$request->property_registration] ?? 0) >= 2) {
            $score += self::PESO_INSCRICAO;
            $factors[] = ['chave' => 'inscricao_repetida', 'peso' => self::PESO_INSCRICAO, 'detalhe' => 'mesma inscrição imobiliária em múltiplos deferimentos na janela'];
        }

        if ($request->applicant_proceeded_despite) {
            $score += self::PESO_PROSSEGUIU;
            $factors[] = ['chave' => 'prosseguiu_apesar_alerta', 'peso' => self::PESO_PROSSEGUIU, 'detalhe' => 'requerente prosseguiu apesar de alerta de duplicidade/inconsistência'];
        }

        return [min(100, $score), $factors];
    }

    private function severidade(int $score): AbuseSeverity
    {
        return match (true) {
            $score >= 80 => AbuseSeverity::Alta,
            $score >= 60 => AbuseSeverity::Media,
            default => AbuseSeverity::Baixa,
        };
    }

    private function anomaliaAbertaExiste(string $fingerprint): bool
    {
        return PredictiveAnomaly::query()
            ->where('fingerprint', $fingerprint)
            ->where('status', AbuseAlertStatus::Aberto)
            ->exists();
    }

    /**
     * @param  list<array{chave: string, peso: int, detalhe: string}>  $factors
     */
    private function criar(ViabilityRequest $request, int $score, AbuseSeverity $severity, array $factors, string $fingerprint, Carbon $inicio, Carbon $fim): PredictiveAnomaly
    {
        return PredictiveAnomaly::create([
            'viability_request_id' => $request->id,
            'score' => $score,
            'severity' => $severity,
            'status' => AbuseAlertStatus::Aberto,
            'fingerprint' => $fingerprint,
            'factors' => $factors,
            'window_start' => $inicio,
            'window_end' => $fim,
            'detected_at' => now(),
        ]);
    }

    /**
     * Encaminha o processo à malha fina pelo caminho de SISTEMA e grava o vínculo
     * na anomalia. NUNCA transiciona status (ortogonal — RN-001).
     */
    private function encaminhar(PredictiveAnomaly $anomalia, ViabilityRequest $request): bool
    {
        $referral = $this->malhaFina->encaminharSistema($request, 'auditoria preditiva: anomalia de score alto em deferimento do fluxo expresso');

        $anomalia->forceFill(['fine_mesh_referral_id' => $referral->id])->save();

        return true;
    }
}
