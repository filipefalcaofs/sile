<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Http\Resources\ProcessoResource;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\TempoAnaliseService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte de exportação do relatório SAPS Sedes de Escritório Virtual (RN-006): o
 * recorte is_virtual_office=true, reusando EXATAMENTE o Builder de
 * {@see TempoAnaliseService::sedesEscritorioVirtual()} (RN-005 — sem filtro
 * reimplementado). Reconstrutível só a partir do bag (INVARIANTE do
 * {@see ReportSource}): o construtor injeta apenas o serviço (resolvido pelo
 * container), sem estado de filtro.
 *
 * `personalData=true`: expõe empresa/CNPJ; o CNPJ vem já formatado da
 * {@see ProcessoResource} (PII minimizada na origem — RN-007).
 */
final class EscritorioVirtualReportSource implements ReportSource
{
    public function __construct(private readonly TempoAnaliseService $tempos) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Sedes de escritório virtual',
            colunas: [
                ['key' => 'protocolo', 'label' => 'Processo'],
                ['key' => 'empresa', 'label' => 'Empresa'],
                ['key' => 'cnpj', 'label' => 'CNPJ'],
                ['key' => 'bairro', 'label' => 'Bairro'],
                ['key' => 'protocolado_em', 'label' => 'Protocolado em'],
                ['key' => 'decidido_em', 'label' => 'Decidido em'],
                ['key' => 'resultado', 'label' => 'Resultado'],
            ],
            builder: fn (): Builder => $this->tempos->sedesEscritorioVirtual($filtros),
            mapRow: fn (ViabilityRequest $solicitacao): array => $this->linha($solicitacao),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-escritorio-virtual',
            personalData: true,
            arquivoBase: 'sedes-escritorio-virtual',
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas), lendo empresa/CNPJ formatados
     * da {@see ProcessoResource} + bairro e decisão diretamente do modelo.
     *
     * @return array<int, scalar|null>
     */
    private function linha(ViabilityRequest $solicitacao): array
    {
        $dados = (new ProcessoResource($solicitacao))->resolve();

        return [
            $dados['protocol_number'],
            $dados['empresa'],
            $dados['cnpj'],
            $solicitacao->address_neighborhood,
            $dados['protocoled_at'],
            $solicitacao->decision?->decided_at?->toIso8601String(),
            $solicitacao->decision?->outcome?->label(),
        ];
    }
}
