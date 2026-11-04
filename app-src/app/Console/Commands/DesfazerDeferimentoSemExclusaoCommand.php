<?php

namespace App\Console\Commands;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Services\Analise\AnalysisSlaService;
use App\Services\Relatorios\TempoViabilidadeRegistrar;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Desfaz o deferimento automático de exclusão que não excluiu nenhuma
 * atividade (pedido igual à viabilidade anterior, deferido por engano em
 * 04/11/2026): apaga a decisão e devolve o processo à análise, para distribuir,
 * com o motivo do encaminhamento anterior. A decisão apagada fica na auditoria.
 * O parecer já enviado à JUCEB não é desfeito por aqui.
 */
class DesfazerDeferimentoSemExclusaoCommand extends Command
{
    private const MOTIVO_DA_EXCLUSAO = 'exclusão de atividade — deferimento automático, sem consulta de enquadramento';

    protected $signature = 'processos:desfazer-deferimento-sem-exclusao
        {--id=* : Só estes processos (id)}
        {--dry-run : Mostra o que seria feito, sem gravar}';

    protected $description = 'Devolve à análise os processos deferidos como exclusão sem nenhuma atividade excluída';

    public function handle(ViabilityRequestStateMachine $estados, AnalysisSlaService $sla, TempoViabilidadeRegistrar $tempo, AuditService $audit): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_filter((array) $this->option('id'));

        $decisoes = ViabilityDecision::query()
            ->where('flow', 'expresso')
            ->where('reason', self::MOTIVO_DA_EXCLUSAO)
            ->whereHas('viabilityRequest', fn ($q) => $q->where('status', ViabilityRequestStatus::Deferida))
            ->when($ids !== [], fn ($q) => $q->whereIn('viability_request_id', $ids))
            ->with('viabilityRequest')
            ->orderBy('viability_request_id')
            ->get()
            ->filter(fn (ViabilityDecision $decisao): bool => ($decisao->per_cnae ?? []) === []);

        $contagem = ['desfeitos' => 0, 'erros' => 0];

        foreach ($decisoes as $decisao) {
            $processo = $decisao->viabilityRequest;
            $rotulo = ($processo->protocol_number ?? '#'.$processo->id)." (id {$processo->id}, viabilidade {$decisao->tvl_product_number})";

            if ($dryRun) {
                $this->line("{$rotulo}: seria desfeito e voltaria à análise");
                $contagem['desfeitos']++;

                continue;
            }

            try {
                DB::transaction(function () use ($processo, $decisao, $estados, $sla, $tempo, $audit): void {
                    $motivoAnterior = $processo->transitions()
                        ->where('to_status', ViabilityRequestStatus::EmAnalise)
                        ->latest('id')
                        ->value('reason');

                    $audit->log(
                        logName: 'expresso',
                        event: 'deferimento-desfeito',
                        description: "Deferimento automático de exclusão sem atividade excluída desfeito na solicitação #{$processo->id}",
                        properties: [
                            'viability_request_id' => $processo->id,
                            'protocol_number' => $processo->protocol_number,
                            'decisao' => $decisao->only(['id', 'tvl_product_number', 'consolidated_result', 'decided_at', 'reason']),
                        ],
                        subject: $processo,
                    );

                    $decisao->delete();

                    $estados->transition(
                        $processo,
                        ViabilityRequestStatus::EmAnalise,
                        reason: $motivoAnterior,
                        publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
                        force: true,
                    );

                    $inicio = $processo->analysis_stage_started_at ?? now();

                    $processo->forceFill([
                        'assigned_user_id' => null,
                        'analysis_status' => AnalysisStatus::ParaDistribuir,
                        'analysis_stage' => AnalysisStage::Distribuicao,
                        'analysis_stage_started_at' => $inicio,
                        'analysis_due_at' => $sla->dueAtFor(AnalysisStage::Distribuicao, $inicio),
                    ])->save();

                    $tempo->registrarProcesso($processo->fresh());
                });
            } catch (Throwable $e) {
                $this->error("{$rotulo}: {$e->getMessage()}");
                $contagem['erros']++;

                continue;
            }

            $this->line("{$rotulo}: de volta à análise, para distribuir");
            $contagem['desfeitos']++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."desfeitos {$contagem['desfeitos']}, erros {$contagem['erros']}");

        return $contagem['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
