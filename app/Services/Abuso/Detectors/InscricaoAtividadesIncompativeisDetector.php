<?php

namespace App\Services\Abuso\Detectors;

use App\Enums\AbuseSeverity;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\Contracts\AbuseDetector;
use App\Services\Abuso\DetectionWindow;

/**
 * HU-149 (estrutural): a MESMA inscrição imobiliária com ATIVIDADES distintas
 * simultâneas na janela — sinal de uso incompatível/sobreposto do mesmo imóvel.
 * Determinístico sobre dado real, SEM IA: agrupa viability_requests não
 * canceladas por `property_registration` e emite um finding quando a inscrição
 * acumula `MINIMO_ATIVIDADES`+ CNAEs PRIMÁRIOS distintos no período. fingerprint
 * estável por (regra, inscrição) → idempotência da 12-03. Apenas ALERTA (RN-001).
 *
 * PROVISÓRIO (SEDUR refina): a matriz oficial de incompatibilidade entre CNAEs
 * (quais pares NÃO coexistem) é pendência da SEDUR. Enquanto isso, o proxy
 * honesto é a SIMULTANEIDADE de atividades distintas na mesma inscrição — que o
 * gestor revisa na malha fina; nunca decide nem pune.
 */
class InscricaoAtividadesIncompativeisDetector implements AbuseDetector
{
    /** Mínimo de CNAEs primários distintos na mesma inscrição para alertar (provisório — SEDUR calibra). */
    private const MINIMO_ATIVIDADES = 2;

    public function key(): string
    {
        return 'inscricao_atividades_incompativeis';
    }

    /**
     * @return iterable<AbuseFinding>
     */
    public function detect(DetectionWindow $window): iterable
    {
        $solicitacoes = ViabilityRequest::query()
            ->whereNotNull('property_registration')
            ->where('status', '!=', ViabilityRequestStatus::Cancelada)
            ->whereBetween('created_at', [$window->start, $window->end])
            ->with('primaryCnae')
            ->orderBy('id')
            ->get();

        /** @var array<string, array{ids: list<int>, cnaes: array<string, true>, requerentes: array<int, true>}> $grupos */
        $grupos = [];

        foreach ($solicitacoes as $solicitacao) {
            $inscricao = (string) $solicitacao->property_registration;
            $grupos[$inscricao]['ids'][] = (int) $solicitacao->id;

            $cnae = $solicitacao->primaryCnae->first()?->code;
            if ($cnae !== null) {
                $grupos[$inscricao]['cnaes'][(string) $cnae] = true;
            }

            if ($solicitacao->requester_user_id !== null) {
                $grupos[$inscricao]['requerentes'][(int) $solicitacao->requester_user_id] = true;
            }
        }

        foreach ($grupos as $inscricao => $grupo) {
            $cnaes = array_keys($grupo['cnaes'] ?? []);
            $cnaesDistintos = count($cnaes);

            if ($cnaesDistintos < self::MINIMO_ATIVIDADES) {
                continue;
            }

            $ids = $grupo['ids'];

            yield new AbuseFinding(
                ruleKey: $this->key(),
                severity: $cnaesDistintos > self::MINIMO_ATIVIDADES * 2 ? AbuseSeverity::Alta : AbuseSeverity::Media,
                fingerprint: hash('sha256', $this->key().'|'.$inscricao),
                evidence: [
                    'inscricao' => $inscricao,
                    'cnaes_primarios' => $cnaes,
                    'cnaes_distintos' => $cnaesDistintos,
                    'requerentes' => array_keys($grupo['requerentes'] ?? []),
                    'minimo' => self::MINIMO_ATIVIDADES,
                    'ids' => $ids,
                ],
                viabilityRequestId: $ids === [] ? null : max($ids),
                subject: null,
                windowStart: $window->start,
                windowEnd: $window->end,
            );
        }
    }
}
