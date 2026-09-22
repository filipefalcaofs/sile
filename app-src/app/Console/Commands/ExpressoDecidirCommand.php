<?php

namespace App\Console\Commands;

use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Expresso\DecisionResult;
use App\Services\Expresso\FluxoExpressoService;
use Illuminate\Console\Command;

/**
 * Roda o motor de decisão do fluxo expresso (FluxoExpressoService::decide) sobre
 * uma solicitação protocolada e imprime a EVIDÊNCIA REAL de ponta a ponta:
 * status final, desfecho (deferida/indeferida), número TVL no deferimento e o
 * motivo honesto quando encaminha à análise. Útil para evidência/reprocesso
 * manual, espelhando solicitacao:protocolar / louos:enquadrar.
 *
 * O motor é idempotente (Cache::lock + re-check de status, 09-05): rodar o
 * comando de novo numa solicitação já decidida reaproveita a decisão existente,
 * sem redecidir nem redisparar o evento. O ator é o SISTEMA (decided_by null),
 * exatamente como no gatilho automático — o comando só aciona o mesmo motor.
 *
 * Sem fachada: em_analise é um desfecho LEGÍTIMO (degradação honesta — sem zona,
 * semi-expresso ou toggle off), então sai com exit 0, não como erro. Só a
 * solicitação inexistente sai com exit 1.
 */
class ExpressoDecidirCommand extends Command
{
    protected $signature = 'expresso:decidir
        {solicitacao : ID da solicitação protocolada a decidir}';

    protected $description = 'Roda o motor de decisão do fluxo expresso (HU-073 a HU-078) e imprime a evidência real (status/desfecho/TVL)';

    public function handle(FluxoExpressoService $service): int
    {
        $id = (int) $this->argument('solicitacao');
        $request = ViabilityRequest::query()->find($id);

        if ($request === null) {
            $this->error("Solicitação #{$id} não encontrada.");

            return self::FAILURE;
        }

        $this->renderCabecalho($request);

        // Ator nulo = sistema: o comando aciona o MESMO motor do gatilho
        // automático (decided_by null no fluxo expresso). Idempotente.
        $result = $service->decide($request);

        $this->renderResultado($result);

        return self::SUCCESS;
    }

    private function renderCabecalho(ViabilityRequest $request): void
    {
        $this->newLine();
        $this->line('Decisão do fluxo expresso');
        $this->line("Solicitação #{$request->id}");

        if (is_string($request->protocol_number) && $request->protocol_number !== '') {
            $this->line("Número de protocolo: {$request->protocol_number}");
        }

        $empresa = $request->company?->legal_name;
        if (is_string($empresa) && $empresa !== '') {
            $this->line("Empresa: {$empresa}");
        }

        $principal = $request->primaryCnae()->first();
        if ($principal instanceof Cnae) {
            $this->line("Atividade principal: {$principal->formatted_code} — {$principal->description}");
        }

        $this->newLine();
    }

    private function renderResultado(DecisionResult $result): void
    {
        $this->line('Resultado: '.mb_strtoupper($result->status->label()));

        $decision = $result->decision;

        if ($decision !== null) {
            $this->line('Desfecho: '.mb_strtoupper($decision->outcome->label()));

            if ($decision->tvl_product_number !== null) {
                $this->line("Número TVL: {$decision->tvl_product_number}");
            }

            $this->renderFundamentacao($decision->fundamentacao);

            return;
        }

        // Encaminhamento à análise (degradação honesta): imprime o motivo real,
        // sem inventar desfecho. NÃO é erro — exit 0.
        if ($result->reason !== null) {
            $this->line("Motivo: {$result->reason}");
        }

        $this->line('Nenhuma decisão vinculante emitida — segue para análise técnica.');
    }

    /**
     * @param  array<int, string>|null  $fundamentacao
     */
    private function renderFundamentacao(?array $fundamentacao): void
    {
        if (! is_array($fundamentacao) || $fundamentacao === []) {
            return;
        }

        $this->newLine();
        $this->line('Fundamentação legal:');

        foreach ($fundamentacao as $referencia) {
            $this->line(" - {$referencia}");
        }
    }
}
