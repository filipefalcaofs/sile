<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use App\Models\AnalysisStatusTransition;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;

/**
 * Máquina de estados do eixo operacional da análise (espelha
 * ViabilityRequestStateMachine). O mapa cobre as transições do grafo do spec;
 * transições dirigidas por evento (convite/vistoria) também passam por aqui nas
 * Fases 2/3. `force: true` é o override do gestor (justificativa obrigatória no
 * caller). Cada transição grava a timeline interna + auditoria.
 */
class AnalysisStatusStateMachine
{
    /** @var array<string, list<string>> */
    private const array TRANSITIONS = [
        'para_distribuir' => ['encaminhado'],
        'encaminhado' => ['analisar'],
        'analisar' => ['em_analise'],
        'em_analise' => ['analise_concluida', 'em_convite', 'vistoriar'],
        'em_convite' => ['convite_respondido', 'convite_cancelado', 'convite_expirado'],
        'convite_respondido' => ['em_analise'],
        'convite_cancelado' => ['em_analise'],
        'vistoriar' => ['vistoriado'],
        'vistoriado' => ['em_analise'],
    ];

    public function __construct(private AuditService $audit) {}

    public function canTransition(?AnalysisStatus $from, AnalysisStatus $to): bool
    {
        if ($from === null) {
            return $to === AnalysisStatus::ParaDistribuir;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(
        ViabilityRequest $request,
        AnalysisStatus $to,
        ?User $actor = null,
        ?string $reason = null,
        bool $force = false,
    ): AnalysisStatusTransition {
        $from = $request->analysis_status;

        if (! $force && ! $this->canTransition($from, $to)) {
            throw InvalidAnalysisStatusTransitionException::para($from, $to);
        }

        $request->forceFill(['analysis_status' => $to])->save();

        $transition = $request->analysisStatusTransitions()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'actor_user_id' => $actor?->id,
        ]);

        $this->audit->log(
            logName: 'analise',
            event: 'status-analise',
            description: "Status de análise {$request->id}: ".($from?->value ?? '(inicial)')."→{$to->value}",
            properties: [
                'viability_request_id' => $request->id,
                'from' => $from?->value,
                'to' => $to->value,
                'forcado' => $force,
            ],
            subject: $request,
        );

        return $transition;
    }
}
