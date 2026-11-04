<?php

namespace App\Console\Commands;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ResultadoViabilidade;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Indefere pelo fluxo expresso os processos da caixa da análise (em análise ou
 * em convite, sem decisão) que têm pergunta da planilha sem resposta: sem ela a
 * análise não pode ser concluída. Vale também para análise já iniciada, com
 * ficha, convite ou vistoria; convite aberto é cancelado. Para os da JUCEB, as
 * respostas são refeitas pelo envelope antes de decidir: pergunta respondida
 * com a numeração trocada não é indeferida.
 */
class IndeferirPerguntaSemRespostaCommand extends Command
{
    protected $signature = 'processos:indeferir-pergunta-sem-resposta
        {--id=* : Só estes processos (id)}
        {--limite= : Máximo de processos nesta passada}
        {--dry-run : Mostra o que seria feito, sem gravar}';

    protected $description = 'Indefere pelo expresso os processos da caixa da análise com pergunta da planilha sem resposta';

    /** @var list<ViabilityRequestStatus> */
    private const STATUS_CAIXA = [
        ViabilityRequestStatus::EmAnalise,
        ViabilityRequestStatus::EmPendencia,
    ];

    public function handle(
        SolicitacaoViabilityResolver $resolver,
        FluxoExpressoService $expresso,
        ReginProcessoProtocolador $protocolador,
        AuditService $audit,
    ): int {
        if (! (bool) config('viabiliza.expresso.indeferir_pergunta_sem_resposta', true)) {
            $this->warn('viabiliza.expresso.indeferir_pergunta_sem_resposta está desligado; nada a fazer.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $query = ViabilityRequest::query()
            ->whereIn('status', self::STATUS_CAIXA)
            ->whereDoesntHave('decision')
            ->orderBy('id');

        $ids = array_filter((array) $this->option('id'));

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $contagem = ['indeferidos' => 0, 'respondidos' => 0, 'sem_pergunta' => 0, 'erros' => 0];

        foreach ($query->cursor() as $processo) {
            $rotulo = $processo->protocol_number ?? "#{$processo->id}";

            try {
                $respostasRefeitas = $this->refazerRespostas($processo, $protocolador, $audit, $dryRun);
                $resolved = $resolver->resolve($processo);
                $pergunta = $resolved->perguntaSemResposta();

                if ($pergunta === null || $resolved->consolidado === ResultadoViabilidade::NaoPermitido->value) {
                    $contagem[$respostasRefeitas ? 'respondidos' : 'sem_pergunta']++;

                    if ($respostasRefeitas) {
                        $this->line("{$rotulo}: respostas corrigidas pelo envelope; pergunta respondida, segue na análise");
                    }

                    continue;
                }

                $this->line("{$rotulo}: pergunta {$pergunta['numero']} ({$pergunta['cnae']}) sem resposta".($dryRun ? ' — seria indeferido' : ' — indeferido'));

                if (! $dryRun) {
                    $expresso->indeferirPerguntaSemResposta($processo, $resolved, $pergunta, null);
                    $this->cancelarConvitesAbertos($processo, $audit);
                }

                $contagem['indeferidos']++;
            } catch (Throwable $e) {
                $this->error("{$rotulo}: {$e->getMessage()}");
                $contagem['erros']++;
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."indeferidos {$contagem['indeferidos']}, com resposta corrigida {$contagem['respondidos']}, sem pergunta pendente {$contagem['sem_pergunta']}, erros {$contagem['erros']}");

        return $contagem['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Convite aberto perde o objeto com o processo indeferido: fica cancelado
     * para o requerente não ser cobrado nem o prazo indeferir de novo.
     */
    private function cancelarConvitesAbertos(ViabilityRequest $processo, AuditService $audit): void
    {
        $parecer = 'Cancelado pelo indeferimento por pergunta da planilha sem resposta.';

        foreach ($processo->pendencies()->where('status', AnalysisPendencyStatus::Aberta)->get() as $convite) {
            $convite->forceFill([
                'status' => AnalysisPendencyStatus::Cancelada,
                'parecer' => $parecer,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => null,
            ])->save();

            $audit->log(
                logName: 'analise',
                event: 'convite-cancelado',
                description: "Convite #{$convite->id} cancelado pelo indeferimento da solicitação #{$processo->id}",
                properties: ['viability_request_id' => $processo->id, 'analysis_pendency_id' => $convite->id, 'parecer' => $parecer],
                subject: $processo,
            );
        }
    }

    /**
     * Respostas da JUCEB conferidas pelo envelope. Devolve true quando mudaram.
     * No dry-run a mudança só vale em memória, para a simulação.
     */
    private function refazerRespostas(ViabilityRequest $processo, ReginProcessoProtocolador $protocolador, AuditService $audit, bool $dryRun): bool
    {
        if ($processo->origin !== ViabilityRequestOrigin::Regin || $processo->external_reference === null) {
            return false;
        }

        $recebimento = ReginRecebimento::query()->where('protocolo', $processo->external_reference)->latest('id')->first();
        $novo = $recebimento === null ? null : $protocolador->snapshotDoRecebimento($recebimento);

        if ($novo === null) {
            return false;
        }

        $atual = is_array($processo->simulation_snapshot) ? $processo->simulation_snapshot : [];

        if (($atual['respostas_tratamento_por_cnae'] ?? []) == $novo['respostas_tratamento_por_cnae']) {
            return false;
        }

        $processo->forceFill(['simulation_snapshot' => array_merge($atual, $novo)]);

        if ($dryRun) {
            return true;
        }

        $processo->save();

        $audit->log(
            logName: 'regin',
            event: 'respostas-refeitas',
            description: "Respostas da planilha refeitas pelo envelope do protocolo {$processo->external_reference}",
            properties: ['anterior' => $atual['respostas_tratamento_por_cnae'] ?? [], 'novo' => $novo['respostas_tratamento_por_cnae']],
            subject: $processo,
        );

        return true;
    }
}
