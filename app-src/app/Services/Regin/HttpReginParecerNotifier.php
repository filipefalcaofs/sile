<?php

namespace App\Services\Regin;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Support\Settings;

/**
 * Provider REAL do parecer ao REGIN/JUCEB: monta o envelope Resposta
 * (codFuncao 110) a partir da decisão imutável e envia pelo ReginHttpClient.
 * Sucesso é RECEBIDO_SUCESSO; qualquer falha propaga ReginUnavailableException
 * e o listener audita a pendência — nunca sucesso fictício.
 */
class HttpReginParecerNotifier implements ReginParecerNotifier
{
    public function __construct(private ReginHttpClient $client) {}

    public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void
    {
        $this->client->enviarParecer($this->envelope($request, $decision));
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(ViabilityRequest $request, ViabilityDecision $decision): array
    {
        $cnpj = (string) Settings::get(
            'integrations.regin.cnpj_prefeitura',
            config('sile.integrations.regin.cnpj_prefeitura'),
        );
        $protocolo = (string) $request->external_reference;

        return [
            'protocolo' => $protocolo,
            'servico' => 'WsProSol098',
            'cnpjDestino' => $cnpj,
            'cnpjOrigem' => $cnpj,
            'cnpjEmpresa' => $cnpj,
            'codFuncao' => 110,
            'dataGeracao' => now()->toIso8601String(),
            'dadosProcesso' => [
                'PROTOCOLO' => $protocolo,
                'CNPJ_INSTITUICAO' => $cnpj,
                'DATA_GERACAO' => now()->format('Ymd'),
                'FINALIZA_PROCESSO' => 1,
                'PROCESSO_INTERESSE_INSTITUICAO' => 1,
                'GERA_DOCUMENTO_PROCESSO' => 0,
                'GERA_DOCUMENTOS_AREAS' => 0,
                'ANALISES' => [
                    'AREA' => [
                        [
                            'STATUS_ANALISE' => $decision->outcome === DecisionOutcome::Deferida ? 2 : 4,
                            'JUSTIFICATIVA_ANALISE' => $this->justificativa($decision),
                            'DATA_ANALISE' => $decision->decided_at?->format('Ymd') ?? now()->format('Ymd'),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function justificativa(ViabilityDecision $decision): string
    {
        $fundacao = $decision->fundamentacao;

        if (is_string($fundacao) && $fundacao !== '') {
            return $fundacao;
        }

        if (is_array($fundacao)) {
            $texto = $fundacao['texto'] ?? null;

            if (is_string($texto) && $texto !== '') {
                return $texto;
            }

            $linhas = array_values(array_filter(
                $fundacao,
                fn (mixed $linha): bool => is_string($linha) && $linha !== '',
            ));

            if ($linhas !== []) {
                return implode(' ', $linhas);
            }
        }

        return "Viabilidade {$decision->outcome->value} pelo motor de regras.";
    }
}
