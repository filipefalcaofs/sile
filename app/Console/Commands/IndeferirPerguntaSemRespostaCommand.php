<?php

namespace App\Console\Commands;

use App\Enums\AnalysisStatus;
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
 * Indefere, como o expresso faz hoje, os processos que ficaram na caixa da
 * análise por pergunta da planilha sem resposta antes dessa regra existir (ou
 * porque outra etapa os encaminhou antes dela). Para os da JUCEB, as respostas
 * são refeitas pelo envelope antes de decidir: pergunta respondida com a
 * numeração trocada não é indeferida. Só toca processo que a analista ainda não
 * começou a analisar: sem ficha, sem vistoria e sem decisão.
 */
class IndeferirPerguntaSemRespostaCommand extends Command
{
    protected $signature = 'processos:indeferir-pergunta-sem-resposta
        {--id=* : Só estes processos (id)}
        {--limite= : Máximo de processos nesta passada}
        {--dry-run : Mostra o que seria feito, sem gravar}';

    protected $description = 'Indefere os processos parados na análise por pergunta da planilha sem resposta';

    /** @var list<string> */
    private const ANALISE_NAO_INICIADA = [
        AnalysisStatus::ParaDistribuir->value,
        AnalysisStatus::Analisar->value,
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
            ->where('status', ViabilityRequestStatus::EmAnalise)
            ->where(fn ($q) => $q->whereNull('analysis_status')->orWhereIn('analysis_status', self::ANALISE_NAO_INICIADA))
            ->whereDoesntHave('decision')
            ->whereDoesntHave('analysisRecords')
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
