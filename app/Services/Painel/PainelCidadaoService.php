<?php

namespace App\Services\Painel;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Http\Resources\Portal\SolicitacaoResumoResource;
use App\Models\AnalysisPendency;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityQuery;
use App\Models\ViabilityRequest;

/**
 * Leitura agregada do painel do cidadão (Meu Painel). Route-free e sem estado:
 * recebe o usuário EFETIVO (representado quando "em nome de") e o usuário LOGADO,
 * e devolve um array pronto para a página Inertia.
 *
 * Escopo (LGPD): solicitações/empresas usam o efetivo; consultas usam o logado e
 * são omitidas (null) em representação, para não somar titulares distintos.
 */
class PainelCidadaoService
{
    /**
     * Estados não terminais que contam como "em andamento" (rascunho fica de
     * fora — aparece no bloco de atenção, não é processo em curso).
     *
     * @var list<ViabilityRequestStatus>
     */
    private const EM_ANDAMENTO = [
        ViabilityRequestStatus::Protocolada,
        ViabilityRequestStatus::EmAnalise,
        ViabilityRequestStatus::EmPendencia,
        ViabilityRequestStatus::AguardandoBap,
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(User $efetivo, User $logado): array
    {
        $emRepresentacao = $efetivo->id !== $logado->id;

        return [
            'indicadores' => $this->indicadores($efetivo, $logado, $emRepresentacao),
            'atencao' => $this->atencao($efetivo),
            'solicitacoesRecentes' => $this->solicitacoesRecentes($efetivo),
            'emRepresentacao' => $emRepresentacao,
        ];
    }

    /**
     * @return array{em_andamento: int, empresas: int, consultas: int|null}
     */
    private function indicadores(User $efetivo, User $logado, bool $emRepresentacao): array
    {
        $porStatus = ViabilityRequest::query()
            ->where('requester_user_id', $efetivo->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $emAndamento = collect(self::EM_ANDAMENTO)
            ->sum(fn (ViabilityRequestStatus $status): int => (int) ($porStatus[$status->value] ?? 0));

        return [
            'em_andamento' => (int) $emAndamento,
            'empresas' => Company::countForUser($efetivo),
            'consultas' => $emRepresentacao ? null : ViabilityQuery::forUser($logado)->count(),
        ];
    }

    /**
     * Últimas N solicitações do efetivo (N = config técnica, fora do catálogo
     * HU-014), no shape comum do SolicitacaoResumoResource.
     *
     * @return list<array<string, mixed>>
     */
    private function solicitacoesRecentes(User $efetivo): array
    {
        $limit = (int) config('sile.ui.painel.solicitacoes_recentes', 5);

        return SolicitacaoResumoResource::collection(
            ViabilityRequest::query()
                ->where('requester_user_id', $efetivo->id)
                ->with(['company', 'serviceType'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
        )->resolve();
    }

    /**
     * Itens que dependem de ação do efetivo: pendências ABERTAS (HU-090/091) e
     * rascunhos a protocolar (HU-061/068).
     *
     * @return array{pendencias: list<array<string, mixed>>, rascunhos: list<array<string, mixed>>}
     */
    private function atencao(User $efetivo): array
    {
        $pendencias = AnalysisPendency::query()
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->whereHas('viabilityRequest', fn ($query) => $query->where('requester_user_id', $efetivo->id))
            ->with('viabilityRequest:id,protocol_number')
            ->latest()
            ->get()
            ->map(fn (AnalysisPendency $pendencia): array => [
                'id' => $pendencia->id,
                'solicitacao_id' => $pendencia->viability_request_id,
                'protocol_number' => $pendencia->viabilityRequest?->protocol_number,
                'descricao' => $pendencia->description,
                'due_at' => $pendencia->due_at?->toDateTimeString(),
            ])
            ->all();

        $rascunhos = ViabilityRequest::query()
            ->where('requester_user_id', $efetivo->id)
            ->where('status', ViabilityRequestStatus::Rascunho)
            ->with(['company:id,legal_name', 'serviceType:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ViabilityRequest $rascunho): array => [
                'id' => $rascunho->id,
                'service_type' => $rascunho->serviceType?->name,
                'company_legal_name' => $rascunho->company?->legal_name,
                'created_at' => $rascunho->created_at?->toDateTimeString(),
            ])
            ->all();

        return ['pendencias' => $pendencias, 'rascunhos' => $rascunhos];
    }
}
