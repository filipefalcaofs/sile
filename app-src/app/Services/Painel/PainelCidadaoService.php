<?php

namespace App\Services\Painel;

use App\Enums\ViabilityRequestStatus;
use App\Http\Resources\Portal\SolicitacaoResumoResource;
use App\Models\Cidadao;
use App\Models\ViabilityRequest;

/**
 * Leitura agregada do painel do cidadão (Meu Painel). Route-free e sem estado:
 * recebe o usuário EFETIVO (representado quando "em nome de") e o usuário LOGADO,
 * e devolve um array pronto para a página Inertia.
 *
 * Escopo (LGPD): os processos são do efetivo. Em representação, o painel mostra
 * os processos do representado, não os do procurador.
 */
class PainelCidadaoService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Cidadao $efetivo, Cidadao $logado): array
    {
        $emRepresentacao = $efetivo->id !== $logado->id;

        return [
            'indicadores' => $this->indicadores($efetivo),
            'atencao' => $this->atencao($efetivo),
            'solicitacoesRecentes' => $this->solicitacoesRecentes($efetivo),
            'emRepresentacao' => $emRepresentacao,
        ];
    }

    /**
     * @return array{em_analise: int, processos: int, em_convite: int}
     */
    private function indicadores(Cidadao $efetivo): array
    {
        $porStatus = ViabilityRequest::query()
            ->visivelPara($efetivo->id, $efetivo->cpf)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'em_analise' => (int) ($porStatus[ViabilityRequestStatus::EmAnalise->value] ?? 0),
            'processos' => (int) $porStatus->sum(),
            'em_convite' => (int) ($porStatus[ViabilityRequestStatus::EmPendencia->value] ?? 0),
        ];
    }

    /**
     * Últimas N solicitações do efetivo (N = config técnica, fora do catálogo
     * HU-014), no shape comum do SolicitacaoResumoResource.
     *
     * @return list<array<string, mixed>>
     */
    private function solicitacoesRecentes(Cidadao $efetivo): array
    {
        $limit = (int) config('viabiliza.ui.painel.solicitacoes_recentes', 5);

        return SolicitacaoResumoResource::collection(
            ViabilityRequest::query()
                ->visivelPara($efetivo->id, $efetivo->cpf)
                ->with(['company', 'serviceType'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
        )->resolve();
    }

    /**
     * Convites que o efetivo pode responder agora (requerente ou solicitante
     * declarado, abertos e no prazo). Pedido novo não nasce no portal: não há
     * rascunho a retomar.
     *
     * @return array{pendencias: list<array<string, mixed>>}
     */
    private function atencao(Cidadao $efetivo): array
    {
        return ['pendencias' => app(ConvitesDoCidadao::class)->abertos($efetivo)];
    }
}
