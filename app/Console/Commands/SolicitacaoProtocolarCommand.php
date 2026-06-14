<?php

namespace App\Console\Commands;

use App\Enums\ResultadoViabilidade;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\DocumentacaoIncompletaException;
use App\Services\Solicitacao\InvalidStatusTransitionException;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\SolicitacaoIncompletaException;
use Illuminate\Console\Command;

/**
 * Protocola uma solicitação de viabilidade instruída pelo SERVIÇO REAL
 * (ProtocolarSolicitacaoService) e imprime a evidência de ponta a ponta: número
 * de protocolo gerado, transição rascunho→protocolada e a tendência da simulação
 * (se houver). É a evidência da Fase 8 (HU-068), espelhando viabilidade:consultar
 * / louos:enquadrar — útil para homologação manual.
 *
 * Sem fachada: documento obrigatório faltante (HU-067) ou dados mínimos ausentes
 * (FA-01) BLOQUEIAM com aviso e exit 1, sem consumir número; solicitação fora de
 * rascunho é transição inválida (exit 1). O sucesso sai com exit 0. O ator é o
 * created_by da solicitação (ou o requerente, na falta dele).
 */
class SolicitacaoProtocolarCommand extends Command
{
    protected $signature = 'solicitacao:protocolar
        {solicitacao : ID da solicitação instruída a protocolar}
        {--proceed-despite : Registra a ciência e protocola mesmo com tendência de indeferimento}';

    protected $description = 'Protocola uma solicitação instruída pelo serviço real (HU-068) — evidência do número e da transição';

    public function handle(ProtocolarSolicitacaoService $service): int
    {
        $id = (int) $this->argument('solicitacao');
        $request = ViabilityRequest::query()->find($id);

        if ($request === null) {
            $this->error("Solicitação #{$id} não encontrada.");

            return self::FAILURE;
        }

        $actor = $request->createdBy ?? $request->requester;

        if ($actor === null) {
            $this->error("Solicitação #{$request->id} sem ator (created_by/requerente) — não é possível protocolar.");

            return self::FAILURE;
        }

        $this->renderCabecalho($request);

        try {
            $service->protocol($request, $actor, (bool) $this->option('proceed-despite'));
        } catch (SolicitacaoIncompletaException|DocumentacaoIncompletaException|InvalidStatusTransitionException $e) {
            // Bloqueio honesto: aviso explícito e exit 1, sem número consumido.
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->renderResultado($request);

        return self::SUCCESS;
    }

    private function renderCabecalho(ViabilityRequest $request): void
    {
        $this->newLine();
        $this->line('Protocolo de solicitação de viabilidade');
        $this->line("Solicitação #{$request->id}");

        $empresa = $request->company?->legal_name;
        if (is_string($empresa) && $empresa !== '') {
            $this->line("Empresa: {$empresa}");
        }

        $tipo = $request->serviceType?->name;
        if (is_string($tipo) && $tipo !== '') {
            $this->line("Tipo de serviço: {$tipo}");
        }

        $principal = $request->primaryCnae()->first();
        if ($principal instanceof Cnae) {
            $this->line("Atividade principal: {$principal->formatted_code} — {$principal->description}");
        }

        $this->newLine();
    }

    private function renderResultado(ViabilityRequest $request): void
    {
        $this->line('Resultado: '.mb_strtoupper($request->status->label()));
        $this->line("Número de protocolo: {$request->protocol_number}");
        $this->line('Transição: rascunho → protocolada');

        if ($request->protocoled_at !== null) {
            $this->line('Data do protocolo: '.$request->protocoled_at->format('d/m/Y H:i'));
        }

        $this->renderSimulacao($request);
    }

    private function renderSimulacao(ViabilityRequest $request): void
    {
        $resultado = $request->simulation_resultado;

        if ($resultado === null) {
            $this->line('Simulação pré-protocolo: não executada (orientativa, não bloqueia o protocolo).');

            return;
        }

        $label = ResultadoViabilidade::tryFrom($resultado)?->label() ?? $resultado;

        $this->line("Simulação pré-protocolo (orientativa): {$label}");
        $this->line('Ciência registrada (prosseguiu mesmo assim): '
            .((bool) $request->applicant_proceeded_despite ? 'sim' : 'não'));
    }
}
