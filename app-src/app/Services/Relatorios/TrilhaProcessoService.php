<?php

namespace App\Services\Relatorios;

use App\Models\User;
use App\Models\ViabilityRequest;
use Spatie\Activitylog\Models\Activity;

/**
 * Trilha de auditoria por processo (prestação de contas): consolida, por número
 * de protocolo, as fontes REAIS de histórico — transições do eixo canônico
 * (viability_request_transitions), do eixo operacional da análise
 * (analysis_status_transitions), a trilha de auditoria (activity_log do
 * subject) e a decisão (viability_decisions) — ordenadas por data. Protocolo
 * inexistente → null (o chamador mostra o estado honesto; nunca uma trilha
 * inventada). Os atores são resolvidos em batch (sem N+1).
 */
class TrilhaProcessoService
{
    /** @var list<string> */
    private const array EVENTOS_DE_ATRIBUICAO = ['distribuir', 'assumir', 'redistribuir'];

    /**
     * @return array{processo: array<string, mixed>, eventos: list<array<string, mixed>>}|null
     */
    public function trilha(string $protocolo): ?array
    {
        $processo = ViabilityRequest::query()
            ->with(['company', 'decision.decidedBy'])
            ->where('protocol_number', trim($protocolo))
            ->first();

        if ($processo === null) {
            return null;
        }

        $transicoes = $processo->transitions()->oldest()->get();
        $transicoesAnalise = $processo->analysisStatusTransitions()->oldest()->get();
        $atividades = Activity::query()
            ->where('subject_type', $processo->getMorphClass())
            ->where('subject_id', $processo->id)
            ->oldest()
            ->get();

        $usuarios = $this->resolverUsuarios($transicoes, $transicoesAnalise, $atividades);

        $eventos = [];

        foreach ($transicoes as $t) {
            $eventos[] = [
                'data' => $t->created_at?->toIso8601String(),
                'eixo' => 'status',
                'eixo_label' => 'Situação do processo',
                'descricao' => $this->descreverTransicao($t->from_status, $t->to_status, $t->reason),
                'usuario' => $t->actor_user_id !== null ? ($usuarios[$t->actor_user_id] ?? null) : null,
            ];
        }

        foreach ($transicoesAnalise as $t) {
            $eventos[] = [
                'data' => $t->created_at?->toIso8601String(),
                'eixo' => 'analise',
                'eixo_label' => 'Análise técnica',
                'descricao' => $this->descreverTransicao(
                    $t->from_status?->label(),
                    $t->to_status?->label(),
                    $t->reason,
                ),
                'usuario' => $t->actor_user_id !== null ? ($usuarios[$t->actor_user_id] ?? null) : null,
            ];
        }

        foreach ($atividades as $a) {
            $eventos[] = [
                'data' => $a->created_at?->toIso8601String(),
                'eixo' => 'auditoria',
                'eixo_label' => 'Auditoria',
                'descricao' => $this->descreverAtividade($a, $usuarios),
                'usuario' => $a->causer_id !== null ? ($usuarios[$a->causer_id] ?? null) : null,
            ];
        }

        if ($processo->decision !== null) {
            $eventos[] = [
                'data' => $processo->decision->decided_at?->toIso8601String(),
                'eixo' => 'decisao',
                'eixo_label' => 'Decisão',
                'descricao' => 'Decisão registrada: '.$processo->decision->outcome->label(),
                'usuario' => $processo->decision->decidedBy?->name ?? 'Sistema (fluxo expresso)',
            ];
        }

        usort($eventos, fn (array $a, array $b): int => strcmp((string) $a['data'], (string) $b['data']));

        return [
            'processo' => [
                'id' => $processo->id,
                'protocolo' => $processo->protocol_number,
                'status' => $processo->status->value,
                'status_label' => $processo->status->label(),
                'empresa' => $processo->company?->legal_name,
                'protocolado_em' => $processo->protocoled_at?->toIso8601String(),
            ],
            'eventos' => $eventos,
        ];
    }

    /**
     * Nomes dos atores em batch (transições dos dois eixos + causers da
     * auditoria) — uma query só, sem N+1.
     *
     * @return array<int, string>
     */
    private function resolverUsuarios(iterable $transicoes, iterable $transicoesAnalise, iterable $atividades): array
    {
        $ids = [];

        foreach ($transicoes as $t) {
            if ($t->actor_user_id !== null) {
                $ids[] = $t->actor_user_id;
            }
        }

        foreach ($transicoesAnalise as $t) {
            if ($t->actor_user_id !== null) {
                $ids[] = $t->actor_user_id;
            }
        }

        foreach ($atividades as $a) {
            if ($a->causer_id !== null) {
                $ids[] = $a->causer_id;
            }

            if (in_array($a->event, self::EVENTOS_DE_ATRIBUICAO, true) && isset($a->properties['assigned_user_id'])) {
                $ids[] = (int) $a->properties['assigned_user_id'];
            }
        }

        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', array_unique($ids))->pluck('name', 'id')->all();
    }

    /**
     * Registros de atribuição antigos gravaram só o id da analista; o nome entra
     * na descrição quando ela ainda não o traz.
     *
     * @param  array<int, string>  $usuarios
     */
    private function descreverAtividade(Activity $atividade, array $usuarios): string
    {
        $nome = isset($atividade->properties['assigned_user_id'])
            ? ($usuarios[(int) $atividade->properties['assigned_user_id']] ?? null)
            : null;

        if (! in_array($atividade->event, self::EVENTOS_DE_ATRIBUICAO, true) || $nome === null || str_contains($atividade->description, $nome)) {
            return $atividade->description;
        }

        return "{$atividade->description} Analista: {$nome}.";
    }

    /**
     * Descrição legível da transição: "De X para Y" + motivo quando registrado.
     * Os status podem vir como enum ou string conforme a fonte.
     */
    private function descreverTransicao(mixed $de, mixed $para, ?string $motivo): string
    {
        $deLabel = is_object($de) && method_exists($de, 'label') ? $de->label() : ($de ?? '—');
        $paraLabel = is_object($para) && method_exists($para, 'label') ? $para->label() : ($para ?? '—');

        $descricao = "De {$deLabel} para {$paraLabel}";

        if ($motivo !== null && trim($motivo) !== '') {
            $descricao .= " — {$motivo}";
        }

        return $descricao;
    }
}
