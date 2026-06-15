<?php

namespace App\Services\Lgpd;

use App\Models\Activity;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use App\Support\Settings;

/**
 * Agrega as TRÊS fontes REAIS do monitoramento LGPD (HU-102), sempre em
 * MÉTRICAS minimizadas (nunca PII crua): consentimentos da versão vigente do
 * termo, retenção (dias parametrizados + último pruning lido da trilha, com as
 * decisões FORA do pruning por compliance) e acessos a dado pessoal medidos
 * pela marca personal_data em activity_log.
 */
class LgpdMonitorService
{
    /**
     * Cobertura de consentimento da versão vigente do termo LGPD: total de
     * usuários, quantos aceitaram a versão em vigor e quantos estão pendentes
     * de (re)aceite. Sem termo publicado o gate desarma — degrada honesto.
     *
     * @return array{
     *     sem_termo_publicado: bool,
     *     versao_vigente: int|null,
     *     publicado_em: string|null,
     *     total_usuarios: int,
     *     aceitaram_vigente: int,
     *     pendentes_reaceite: int,
     *     percentual_aceite: float
     * }
     */
    public function consentimentos(): array
    {
        $total = User::query()->count();
        $term = LegalTerm::current('lgpd');

        if ($term === null) {
            return [
                'sem_termo_publicado' => true,
                'versao_vigente' => null,
                'publicado_em' => null,
                'total_usuarios' => $total,
                'aceitaram_vigente' => 0,
                'pendentes_reaceite' => 0,
                'percentual_aceite' => 0.0,
            ];
        }

        $aceitaram = LegalTermAcceptance::query()
            ->where('legal_term_id', $term->id)
            ->distinct()
            ->count('user_id');

        return [
            'sem_termo_publicado' => false,
            'versao_vigente' => (int) $term->version,
            'publicado_em' => $term->published_at?->toIso8601String(),
            'total_usuarios' => $total,
            'aceitaram_vigente' => $aceitaram,
            'pendentes_reaceite' => max(0, $total - $aceitaram),
            'percentual_aceite' => $total > 0 ? round($aceitaram / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Retenção honesta: a janela parametrizada de access_logs (HU-014) e a data
     * do ÚLTIMO pruning REAL registrado na trilha (o AuditModelsPruned grava em
     * log_name='retencao', event='pruning-access-logs'). As DECISÕES
     * (viability_decisions) ficam FORA do pruning — exigência de compliance.
     *
     * @return array{
     *     access_logs_dias: int,
     *     ultimo_pruning_em: string|null,
     *     ultimo_pruning_removidos: int|null,
     *     decisoes_fora_do_pruning: bool
     * }
     */
    public function retencao(): array
    {
        $ultimoPruning = Activity::query()
            ->where('log_name', 'retencao')
            ->where('event', 'pruning-access-logs')
            ->latest('created_at')
            ->first();

        $removidos = $ultimoPruning?->getProperty('removidos');

        return [
            'access_logs_dias' => (int) Settings::get('retencao.access_logs.dias', 365),
            'ultimo_pruning_em' => $ultimoPruning?->created_at?->toIso8601String(),
            'ultimo_pruning_removidos' => $removidos === null ? null : (int) $removidos,
            'decisoes_fora_do_pruning' => true,
        ];
    }

    /**
     * Acessos a dado pessoal na janela parametrizada (reusa
     * ui.dashboard.acessos_janela_dias — HU-014): total e série por ação,
     * contando SÓ os registros marcados personal_data=true pelos call sites
     * reais de leitura sensível. MÉTRICA — não despeja PII.
     *
     * @return array{
     *     janela_dias: int,
     *     desde: string,
     *     total: int,
     *     por_evento: list<array{log_name: string|null, event: string|null, total: int}>
     * }
     */
    public function acessosDadoPessoal(): array
    {
        $janela = (int) Settings::get('ui.dashboard.acessos_janela_dias', 7);
        $desde = now()->subDays($janela);

        $base = Activity::query()
            ->where('personal_data', true)
            ->where('created_at', '>=', $desde);

        $porEvento = (clone $base)
            ->selectRaw('log_name, event, COUNT(*) as total')
            ->groupBy('log_name', 'event')
            ->orderByDesc('total')
            ->get()
            ->map(fn (Activity $linha): array => [
                'log_name' => $linha->log_name,
                'event' => $linha->event,
                'total' => (int) $linha->total,
            ])
            ->all();

        return [
            'janela_dias' => $janela,
            'desde' => $desde->toIso8601String(),
            'total' => (clone $base)->count(),
            'por_evento' => $porEvento,
        ];
    }
}
