<?php

namespace App\Services\Painel;

use App\Enums\ViabilityRequestStatus;
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
            'atencao' => ['pendencias' => [], 'rascunhos' => []],
            'solicitacoesRecentes' => [],
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
}
