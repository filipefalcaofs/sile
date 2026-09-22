<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Support\Facades\DB;

/**
 * Carga ativa de análise por analista (Central de Distribuição — Fase 2). Para o
 * Apoio decidir a distribuição, conta os processos EM ANÁLISE atribuídos a cada
 * analista vinculado ao(s) setor(es), com breakdown por grupo de etapa
 * (AnalysisStatus::grupo()). Analistas sem carga aparecem com zero — o Apoio
 * precisa vê-los para poder escolhê-los. NÃO sugere destinatário: só mede.
 */
class CargaAnalistaService
{
    /**
     * @param  iterable<int, int>  $sectorIds
     * @return list<array{analista_id: int, analista: string, total: int, por_grupo: array<string, int>}>
     */
    public function cargaDosSetores(iterable $sectorIds): array
    {
        $ids = collect($sectorIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        // Grupos possíveis (todos zerados por padrão) — ordem estável para a UI.
        $gruposBase = [];
        foreach (AnalysisStatus::cases() as $status) {
            $gruposBase[$status->grupo()] = 0;
        }

        // Analistas vinculados ao(s) setor(es), com permissão de análise.
        $analistas = User::query()
            ->permission('analisar-processos')
            ->whereHas('sectors', fn ($query) => $query->whereIn('sectors.id', $ids))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Contagem ativa por analista e status (uma query), restrita aos setores.
        $contagens = ViabilityRequest::query()
            ->whereIn('sector_id', $ids)
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->whereNotNull('assigned_user_id')
            ->groupBy('assigned_user_id', 'analysis_status')
            ->get([
                'assigned_user_id',
                'analysis_status',
                DB::raw('count(*) as total'),
            ]);

        return $analistas->map(function (User $analista) use ($contagens, $gruposBase): array {
            $porGrupo = $gruposBase;
            $total = 0;

            foreach ($contagens->where('assigned_user_id', $analista->id) as $linha) {
                $status = $linha->analysis_status instanceof AnalysisStatus
                    ? $linha->analysis_status
                    : ($linha->analysis_status !== null ? AnalysisStatus::from($linha->analysis_status) : null);

                $grupo = $status?->grupo() ?? 'Análise';
                $porGrupo[$grupo] = ($porGrupo[$grupo] ?? 0) + (int) $linha->total;
                $total += (int) $linha->total;
            }

            return [
                'analista_id' => $analista->id,
                'analista' => $analista->name,
                'total' => $total,
                'por_grupo' => $porGrupo,
            ];
        })->all();
    }
}
