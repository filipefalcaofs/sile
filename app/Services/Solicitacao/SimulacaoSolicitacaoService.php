<?php

namespace App\Services\Solicitacao;

use App\Enums\ResultadoViabilidade;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Simulação pré-protocolo da viabilidade (HU-141): DELEGA ao
 * SolicitacaoViabilityResolver (09-04) a iteração dos CNAEs (principal +
 * complementares), a chamada ao motor (Fase 7) PELO PONTO da própria solicitação
 * (centroide do polígono — sem geocodificar de novo), a propagação do veredito
 * do motor LOUOS por CNAE (RN-001 — nunca lógica de decisão paralela) e a
 * consolidação do pior caso. Sem polígono, o resolver degrada honesto para a via
 * CNAE (risco + Quadro 7, sem território).
 *
 * Esta classe é a camada ORIENTATIVA: PERSISTE o snapshot (por CNAE) + versões +
 * resultado + simulated_at no processo (RN-003) — o protocolo (08-10) LÊ o
 * snapshot, não reprocessa — e AUDITA a execução (RN-002). Não muda status nem
 * bloqueia o protocolo (RN-002). A decisão autoritativa (09-05) reexecuta o
 * resolver fresco e NUNCA confia neste snapshot. Sem zona oficial, o veredito por
 * CNAE é "pendente" (propagado) — nunca permitido/não permitido inventado.
 */
class SimulacaoSolicitacaoService
{
    public function __construct(
        private SolicitacaoViabilityResolver $resolver,
        private AuditService $audit,
    ) {}

    /**
     * Executa a simulação e persiste o snapshot no processo, devolvendo o
     * resultado consolidado para a etapa do wizard (08-13).
     *
     * @return array<string, mixed>
     */
    public function simulate(ViabilityRequest $request): array
    {
        $resolved = $this->resolver->resolve($request);

        $snapshot = $resolved->toSnapshot();
        $rulesVersions = $resolved->rules_versions;
        $resultado = $resolved->consolidado;

        DB::transaction(function () use ($request, $snapshot, $rulesVersions, $resultado): void {
            $request->update([
                'simulation_snapshot' => $snapshot,
                'simulation_rules_versions' => $rulesVersions,
                'simulation_resultado' => $resultado,
                'simulated_at' => now(),
            ]);

            $this->audit->log(
                logName: 'solicitacoes',
                event: 'simulacao',
                description: 'Simulação de viabilidade pré-protocolo executada (orientativa)',
                properties: [
                    'solicitacao_id' => $request->id,
                    'resultado' => $resultado,
                    'por_cnae' => array_map(
                        static fn (array $item): array => ['cnae' => $item['cnae'], 'tendencia' => $item['tendencia']],
                        $snapshot['por_cnae'],
                    ),
                    'versoes' => $rulesVersions,
                ],
                subject: $request,
                result: 'sucesso',
            );
        });

        return [
            'resultado' => $resultado,
            'resultado_label' => ResultadoViabilidade::from($resultado)->label(),
            'por_cnae' => $snapshot['por_cnae'],
            'rules_versions' => $rulesVersions,
            'simulated_at' => $request->simulated_at?->toIso8601String(),
        ];
    }
}
