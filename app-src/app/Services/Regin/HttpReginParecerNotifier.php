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
    /**
     * Trechos que marcam nota interna do motor (falha de camada, indisponibilidade,
     * encaminhamento à análise): vão para a trilha do processo, não para a JUCEB.
     *
     * @var list<string>
     */
    private const MARCAS_NOTA_INTERNA = [
        'geoserver',
        'indispon',
        'resposta incompleta',
        'não foi possível verificar',
        'segue para análise técnica',
    ];

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
     * Vale o texto gravado no processo pelo analista; sem ele, o motivo da decisão
     * e o fundamento legal registrado, com as condicionantes separadas.
     */
    public function justificativaPara(ViabilityRequest $request, ViabilityDecision $decision): string
    {
        $gravada = trim((string) $request->regin_justificativa);

        if ($gravada !== '') {
            return mb_substr($gravada, 0, 10000);
        }

        $motivo = trim((string) $decision->reason);
        $linhas = $this->fundamentacao($decision);
        $condicionantes = array_values(array_filter($linhas, fn (string $linha): bool => $this->ehCondicionante($linha)));
        $base = array_values(array_filter($linhas, fn (string $linha): bool => ! $this->ehCondicionante($linha)));

        $partes = [];

        if ($motivo !== '') {
            $partes[] = $motivo;
        }

        if ($base !== []) {
            $abertura = $motivo !== '' ? 'Fundamentação legal: ' : $this->abertura($decision);
            $partes[] = $abertura.implode('; ', $base);
        }

        if ($condicionantes !== []) {
            $partes[] = 'Condicionantes: '.implode('; ', $condicionantes);
        }

        $texto = $partes === []
            ? "Viabilidade {$decision->outcome->value} pelo motor de regras."
            : implode("\n\n", $partes);

        return mb_substr($texto, 0, 10000);
    }

    private function abertura(ViabilityDecision $decision): string
    {
        return match ($decision->outcome) {
            DecisionOutcome::Deferida => 'Deferida com fundamento em: ',
            DecisionOutcome::Indeferida => 'Indeferida com fundamento em: ',
            default => 'Fundamentação legal: ',
        };
    }

    private function ehCondicionante(string $linha): bool
    {
        return preg_match('/^\s*desde que\b/iu', $linha) === 1;
    }

    /**
     * Linhas de fundamento da decisão, sem as notas internas do motor.
     *
     * @return list<string>
     */
    private function fundamentacao(ViabilityDecision $decision): array
    {
        $fundacao = $decision->fundamentacao;

        if (is_string($fundacao) && $fundacao !== '') {
            return [$fundacao];
        }

        if (! is_array($fundacao)) {
            return [];
        }

        $texto = $fundacao['texto'] ?? null;

        if (is_string($texto) && $texto !== '') {
            return [$texto];
        }

        return array_values(array_filter(
            $fundacao,
            fn (mixed $linha): bool => is_string($linha) && $linha !== '' && ! $this->ehNotaInterna($linha),
        ));
    }

    private function ehNotaInterna(string $linha): bool
    {
        $texto = mb_strtolower($linha);

        foreach (self::MARCAS_NOTA_INTERNA as $marca) {
            if (str_contains($texto, $marca)) {
                return true;
            }
        }

        return false;
    }
}
