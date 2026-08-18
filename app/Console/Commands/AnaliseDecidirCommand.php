<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseDecisionResult;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use App\Services\Analise\AnalysisRecordService;
use DomainException;
use Illuminate\Console\Command;

/**
 * Conclui o processo a partir da ficha do analista pelo serviço REAL
 * (AnaliseTecnicaDecisionService::decide) e imprime a EVIDÊNCIA de ponta a ponta:
 * status final, desfecho (deferida/indeferida), número TVL no deferimento e a
 * fundamentação/parecer. Contraparte humana do expresso:decidir (HU-085 a HU-089)
 * — útil para evidência/reprocesso manual.
 *
 * A decisão grava ViabilityDecision flow 'analise_tecnica' com decided_by =
 * analista (≠ null, distingue do fluxo automático). Sem fachada: ficha em
 * RASCUNHO sem --finalizar sai honesto (exit 0, orienta o --finalizar); processo
 * fora de em_analise sai honesto (exit 0); só a solicitação inexistente sai com
 * exit 1.
 */
class AnaliseDecidirCommand extends Command
{
    protected $signature = 'analise:decidir
        {solicitacao : ID da solicitação em análise a concluir}
        {--finalizar : Finaliza a ficha em rascunho antes de decidir}';

    protected $description = 'Conclui o processo a partir da ficha do analista (HU-085 a HU-089) e imprime a evidência real (status/desfecho/TVL)';

    public function handle(AnaliseTecnicaDecisionService $decisionService, AnalysisRecordService $records): int
    {
        $id = (int) $this->argument('solicitacao');
        $request = ViabilityRequest::query()->find($id);

        if ($request === null) {
            $this->error("Solicitação #{$id} não encontrada.");

            return self::FAILURE;
        }

        $this->renderCabecalho($request);

        // Degradação honesta (não é erro): só processos em análise são concluídos.
        if ($request->status !== ViabilityRequestStatus::EmAnalise) {
            $this->line("O processo não está em análise (situação atual: {$request->status->label()}) — nada a concluir.");

            return self::SUCCESS;
        }

        $record = $request->currentAnalysisRecord()->first();

        if ($record === null) {
            $this->line('O processo não possui ficha de análise para concluir.');

            return self::SUCCESS;
        }

        $analista = $this->resolverAnalista($request);

        if ($analista === null) {
            $this->line('Nenhum analista disponível para atribuir a decisão (atribua o processo a um analista antes).');

            return self::SUCCESS;
        }

        if (! $record->isFinalizada()) {
            if (! $this->option('finalizar')) {
                $this->line('A ficha de análise está em rascunho. Rode novamente com --finalizar para finalizá-la e decidir.');

                return self::SUCCESS;
            }

            $record = $records->finalizar($record, $analista);
        }

        try {
            $result = $decisionService->decide($record, $analista);
        } catch (DomainException $e) {
            $this->line("Não foi possível concluir: {$e->getMessage()}");

            return self::SUCCESS;
        }

        $this->renderResultado($request->fresh(), $record, $result, $analista);

        return self::SUCCESS;
    }

    /**
     * Resolve o analista responsável pela decisão: o já atribuído ao processo
     * (assigned_user_id) ou, na ausência, o primeiro com a permissão de análise.
     */
    private function resolverAnalista(ViabilityRequest $request): ?User
    {
        if ($request->assigned_user_id !== null) {
            $atribuido = User::query()->find($request->assigned_user_id);

            if ($atribuido !== null) {
                return $atribuido;
            }
        }

        return User::query()->permission('analisar-processos')->orderBy('id')->first();
    }

    private function renderCabecalho(ViabilityRequest $request): void
    {
        $this->newLine();
        $this->line('Decisão técnica da análise');
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

    private function renderResultado(
        ViabilityRequest $request,
        AnalysisRecord $record,
        AnaliseDecisionResult $result,
        User $analista,
    ): void {
        $this->line('Resultado: '.mb_strtoupper($request->status->label()));
        $this->line('Desfecho: '.mb_strtoupper($result->outcome->label()));
        $this->line("Analista responsável: {$analista->name} (#{$analista->id})");

        if ($result->decision->tvl_product_number !== null) {
            $this->line("Número TVL: {$result->decision->tvl_product_number}");
        }

        $this->renderFundamentacao($result->decision->fundamentacao);

        if (is_string($record->parecer) && $record->parecer !== '') {
            $this->newLine();
            $this->line('Parecer do analista:');
            $this->line(" {$record->parecer}");
        }
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
