<?php

namespace App\Services\Expresso;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Services\Territorial\TerritorialRuleService;

/**
 * Varredura somente leitura das decisões expressas já emitidas contra os dois
 * desvios do motor corrigidos na 0.64.0: o Quadro 11-A com "Objeto de análise
 * particularizada pela CNLU" tratado como condição, e a observação (a)/(b) do
 * Quadro 10 sem a checagem da allow-list (RT-05). Lê o decision_trace gravado
 * na decisão, sem recomputar o motor, e conta as citações legais antigas da
 * fundamentação. Nunca grava.
 */
class AuditoriaDesviosExpressoService
{
    public const string MOTIVO_CNLU = 'Quadro 11-A encaminha à CNLU';

    public const string SITUACAO_FORA = 'fora da allow-list (provável erro)';

    public const string SITUACAO_SEM_DADO = 'sem CODLOG nem bairro (conferir à mão)';

    /**
     * Trechos citados em parte de um item da fundamentação.
     *
     * @var list<string>
     */
    private const array TRECHOS_ANTIGOS = [
        'com redação dada pela Lei Municipal nº 9.879/2025',
        '§3º do art. 162 do PDDU',
    ];

    /**
     * Itens antigos da fundamentação, comparados inteiros (a nova redação os
     * contém como parte de um texto maior).
     *
     * @var list<string>
     */
    private const array ITENS_ANTIGOS = [
        'Lei nº 9.148/2016 — Quadro 10',
        'Lei nº 9.148/2016 — Quadro 11A',
    ];

    private const string CITACAO_DECRETO_RISCO = 'Decreto Municipal nº 41.758/2026 sem o Decreto 32.636/2020';

    public function __construct(private TerritorialRuleService $territorialRules) {}

    /**
     * @return array{
     *     suspeitas: list<array{protocolo: ?string, decidido_em: ?string, cnae: ?string, motivo: string, detalhe: string, situacao: string}>,
     *     sem_rastro: int,
     *     avaliadas: int,
     *     citacoes: array<string, int>
     * }
     */
    public function auditar(): array
    {
        $suspeitas = [];
        $semRastro = 0;
        $avaliadas = 0;
        $citacoes = array_fill_keys([...self::TRECHOS_ANTIGOS, ...self::ITENS_ANTIGOS, self::CITACAO_DECRETO_RISCO], 0);

        ViabilityDecision::query()
            ->where('flow', 'expresso')
            ->with('viabilityRequest')
            ->chunkById(200, function ($decisoes) use (&$suspeitas, &$semRastro, &$avaliadas, &$citacoes): void {
                foreach ($decisoes as $decisao) {
                    foreach ($this->citacoesAntigas($decisao) as $rotulo) {
                        $citacoes[$rotulo]++;
                    }

                    if ($decisao->outcome !== DecisionOutcome::Deferida) {
                        continue;
                    }

                    $trace = $decisao->decision_trace;

                    if (! is_array($trace) || $trace === []) {
                        $semRastro++;

                        continue;
                    }

                    $avaliadas++;

                    foreach ($trace as $cnae) {
                        if (is_array($cnae)) {
                            array_push($suspeitas, ...$this->suspeitasDoCnae($decisao, $cnae));
                        }
                    }
                }
            });

        return [
            'suspeitas' => $suspeitas,
            'sem_rastro' => $semRastro,
            'avaliadas' => $avaliadas,
            'citacoes' => $citacoes,
        ];
    }

    /**
     * @param  array<string, mixed>  $cnae
     * @return list<array{protocolo: ?string, decidido_em: ?string, cnae: ?string, motivo: string, detalhe: string, situacao: string}>
     */
    private function suspeitasDoCnae(ViabilityDecision $decisao, array $cnae): array
    {
        $suspeitas = [];
        $quadro10 = $this->resultadoDoPasso($cnae, 'louos.quadro10');
        $quadro11a = $this->resultadoDoPasso($cnae, 'louos.quadro11a');

        $condicoes = is_array($quadro11a['condicoes'] ?? null) ? $quadro11a['condicoes'] : [];

        foreach ($condicoes as $condicao) {
            if (is_string($condicao) && str_contains(mb_strtolower($condicao), 'particularizada pela cnlu')) {
                $suspeitas[] = $this->linha($decisao, $cnae, self::MOTIVO_CNLU, 'via '.($quadro11a['classe_via'] ?? '?'), 'provável erro');

                break;
            }
        }

        $observacao = $this->observacao($quadro10['condicionante_ref'] ?? null);

        if ($observacao !== null) {
            $solicitacao = $decisao->viabilityRequest;
            $permite = $this->territorialRules->observacaoQuadro10Permite(
                $observacao === 'a' ? 'RT-05-A' : 'RT-05-B',
                $solicitacao?->cod_log,
                $solicitacao?->bairro_oficial,
            );

            if ($permite !== true) {
                $suspeitas[] = $this->linha(
                    $decisao,
                    $cnae,
                    "Quadro 10, observação ({$observacao})",
                    'CODLOG '.($solicitacao?->cod_log ?? '—').', bairro '.($solicitacao?->bairro_oficial ?? '—'),
                    $permite === false ? self::SITUACAO_FORA : self::SITUACAO_SEM_DADO,
                );
            }
        }

        return $suspeitas;
    }

    /**
     * @param  array<string, mixed>  $cnae
     * @return array<string, mixed>
     */
    private function resultadoDoPasso(array $cnae, string $passo): array
    {
        foreach (is_array($cnae['passos'] ?? null) ? $cnae['passos'] : [] as $item) {
            if (is_array($item) && ($item['passo'] ?? null) === $passo) {
                return is_array($item['resultado_parcial'] ?? null) ? $item['resultado_parcial'] : [];
            }
        }

        return [];
    }

    private function observacao(mixed $condicionanteRef): ?string
    {
        if (! is_string($condicionanteRef)) {
            return null;
        }

        return preg_match('/observa[cç][aã]o\s*\(\s*([ab])\s*\)/iu', $condicionanteRef, $match) === 1
            ? mb_strtolower($match[1])
            : null;
    }

    /**
     * @param  array<string, mixed>  $cnae
     * @return array{protocolo: ?string, decidido_em: ?string, cnae: ?string, motivo: string, detalhe: string, situacao: string}
     */
    private function linha(ViabilityDecision $decisao, array $cnae, string $motivo, string $detalhe, string $situacao): array
    {
        return [
            'protocolo' => $decisao->viabilityRequest?->protocol_number,
            'decidido_em' => $decisao->decided_at?->format('Y-m-d'),
            'cnae' => $cnae['cnae_formatado'] ?? $cnae['cnae'] ?? null,
            'motivo' => $motivo,
            'detalhe' => $detalhe,
            'situacao' => $situacao,
        ];
    }

    /**
     * @return list<string>
     */
    private function citacoesAntigas(ViabilityDecision $decisao): array
    {
        $itens = array_values(array_filter(
            is_array($decisao->fundamentacao) ? $decisao->fundamentacao : [],
            'is_string',
        ));
        $encontradas = [];

        foreach (self::TRECHOS_ANTIGOS as $trecho) {
            foreach ($itens as $item) {
                if (str_contains($item, $trecho)) {
                    $encontradas[] = $trecho;

                    break;
                }
            }
        }

        foreach (self::ITENS_ANTIGOS as $antigo) {
            if (in_array($antigo, array_map('trim', $itens), true)) {
                $encontradas[] = $antigo;
            }
        }

        foreach ($itens as $item) {
            if (str_contains($item, '41.758/2026') && ! str_contains($item, '32.636')) {
                $encontradas[] = self::CITACAO_DECRETO_RISCO;

                break;
            }
        }

        return $encontradas;
    }
}
