<?php

namespace App\Services\Abuso\Detectors;

use App\Enums\AbuseSeverity;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\Contracts\AbuseDetector;
use App\Services\Abuso\DetectionWindow;

/**
 * HU-149 (estrutural): requerente cujas respostas de condicionante SEMPRE evitam
 * a análise — a "resposta certa" estatisticamente improvável de quem aprendeu a
 * burlar o expresso. Determinístico sobre dado real, SEM IA: lê o snapshot da
 * simulação (`simulation_snapshot.por_cnae[].consulta.risco.sanitario.
 * condicionantes_perguntas[]`), onde cada item traz a `resposta` do requerente e
 * `acionou` (se bateu o `resposta_gatilho` que reclassificaria o risco para
 * análise). Uma solicitação é EVASIVA quando respondeu condicionantes e NENHUMA
 * acionou; HONESTA quando ao menos uma acionou. Agrupa por requerente e emite um
 * finding quando ele tem `MINIMO_PROCESSOS`+ evasivas e ZERO honestas (sempre
 * evita). fingerprint estável por (regra, requerente). Apenas ALERTA (RN-001).
 *
 * PROVISÓRIO (SEDUR refina): o limiar (3 processos) e a definição de "evita a
 * análise" são proxies honestos até a SEDUR calibrar com casos reais. A captura
 * das respostas de condicionante na simulação ainda não está plugada no fluxo
 * (o resolver consulta pelo ponto, sem respostas) — enquanto isso o detector lê
 * o schema real e não gera falso positivo (resposta null não conta), nunca finge.
 */
class CondicionanteEvasaoDetector implements AbuseDetector
{
    /** Mínimo de processos evasivos para alertar (provisório — SEDUR calibra). */
    private const MINIMO_PROCESSOS = 3;

    private const PADRAO_EVASIVO = 'evasivo';

    private const PADRAO_HONESTO = 'honesto';

    public function key(): string
    {
        return 'condicionante_evasao';
    }

    /**
     * @return iterable<AbuseFinding>
     */
    public function detect(DetectionWindow $window): iterable
    {
        $solicitacoes = ViabilityRequest::query()
            ->whereNotNull('simulation_snapshot')
            ->whereNotNull('requester_user_id')
            ->whereBetween('created_at', [$window->start, $window->end])
            ->orderBy('id')
            ->get(['id', 'requester_user_id', 'simulation_snapshot']);

        /** @var array<int, array{evasivos: list<int>, honestos: int}> $grupos */
        $grupos = [];

        foreach ($solicitacoes as $solicitacao) {
            $padrao = $this->classificar($solicitacao->simulation_snapshot);

            if ($padrao === null) {
                continue;
            }

            $requerenteId = (int) $solicitacao->requester_user_id;

            if (! isset($grupos[$requerenteId])) {
                $grupos[$requerenteId] = ['evasivos' => [], 'honestos' => 0];
            }

            if ($padrao === self::PADRAO_EVASIVO) {
                $grupos[$requerenteId]['evasivos'][] = (int) $solicitacao->id;
            } else {
                $grupos[$requerenteId]['honestos']++;
            }
        }

        foreach ($grupos as $requerenteId => $grupo) {
            $evasivos = $grupo['evasivos'];
            $total = count($evasivos);

            // "Sempre evita": acima do mínimo E sem nenhuma resposta honesta.
            if ($total < self::MINIMO_PROCESSOS || $grupo['honestos'] > 0) {
                continue;
            }

            yield new AbuseFinding(
                ruleKey: $this->key(),
                severity: $total > self::MINIMO_PROCESSOS * 2 ? AbuseSeverity::Alta : AbuseSeverity::Media,
                fingerprint: hash('sha256', $this->key().'|'.$requerenteId),
                evidence: [
                    'requester_user_id' => $requerenteId,
                    'processos' => $total,
                    'minimo' => self::MINIMO_PROCESSOS,
                    'ids' => $evasivos,
                ],
                viabilityRequestId: $evasivos === [] ? null : max($evasivos),
                subject: User::query()->find($requerenteId),
                windowStart: $window->start,
                windowEnd: $window->end,
            );
        }
    }

    /**
     * Classifica a solicitação pela leitura das respostas de condicionante no
     * snapshot: 'evasivo' (respondeu e NENHUMA acionou), 'honesto' (ao menos uma
     * acionou) ou null (não respondeu condicionante — sem sinal de evasão).
     *
     * @param  array<string, mixed>|null  $snapshot
     */
    private function classificar(?array $snapshot): ?string
    {
        $respondeuAlguma = false;

        foreach ($snapshot['por_cnae'] ?? [] as $cnae) {
            foreach ($cnae['consulta']['risco']['sanitario']['condicionantes_perguntas'] ?? [] as $pergunta) {
                if (($pergunta['resposta'] ?? null) === null) {
                    continue;
                }

                $respondeuAlguma = true;

                if (($pergunta['acionou'] ?? false) === true) {
                    return self::PADRAO_HONESTO;
                }
            }
        }

        return $respondeuAlguma ? self::PADRAO_EVASIVO : null;
    }
}
