<?php

namespace App\Services\Regin;

use App\Enums\DecisionOutcome;
use App\Models\ReginRecebimento;
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
    public function __construct(private ReginHttpClient $client, private ReginIntegrationSettings $settings) {}

    public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void
    {
        $this->client->enviarParecer($this->envelope($request, $decision));
    }

    /**
     * @return array<string, mixed>
     */
    public function envelope(ViabilityRequest $request, ViabilityDecision $decision): array
    {
        $cnpj = (string) Settings::get(
            'integrations.regin.cnpj_prefeitura',
            config('viabiliza.integrations.regin.cnpj_prefeitura'),
        );
        $protocolo = (string) $request->external_reference;

        // CNPJ/CPF da empresa do processo, como veio no envelope da JUCEB.
        $cnpjEmpresa = ReginRecebimento::query()->where('protocolo', $protocolo)->latest('id')->value('cnpj_empresa');

        return [
            'protocolo' => $protocolo,
            'servico' => 'WsProSol098',
            'cnpjDestino' => $cnpj,
            'cnpjOrigem' => $cnpj,
            'cnpjEmpresa' => is_string($cnpjEmpresa) && $cnpjEmpresa !== '' ? $cnpjEmpresa : $cnpj,
            'codFuncao' => 110,
            'dataGeracao' => now('America/Bahia')->format('Y-m-d\TH:i:s'),
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
                            'CODIGO_AREA' => $this->settings->codigoArea(),
                            'STATUS_ANALISE' => $decision->outcome === DecisionOutcome::Deferida ? 2 : 4,
                            'JUSTIFICATIVA_ANALISE' => $this->justificativaPara($request, $decision),
                            'DATA_ANALISE' => $decision->decided_at?->format('Ymd') ?? now()->format('Ymd'),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Texto do campo JUSTIFICATIVA_ANALISE (limite de 10.000 caracteres da PSCS).
     * Vale o texto gravado no processo pelo analista; sem ele, o motivo da decisão.
     * A fundamentação legal e as condicionantes não vão para a JUCEB (SEDUR,
     * 20/10/2026).
     */
    public function justificativaPara(ViabilityRequest $request, ViabilityDecision $decision): string
    {
        $gravada = trim((string) $request->regin_justificativa);

        if ($gravada !== '') {
            return mb_substr($gravada, 0, 10000);
        }

        $motivo = trim((string) $decision->reason);

        $origem = $decision->flow === 'analise_tecnica' ? 'após análise técnica' : 'pelo fluxo expresso';

        $texto = $motivo !== ''
            ? $motivo
            : "Viabilidade {$decision->outcome->value} {$origem}.";

        return mb_substr($texto, 0, 10000);
    }
}
