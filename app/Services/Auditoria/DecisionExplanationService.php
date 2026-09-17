<?php

namespace App\Services\Auditoria;

use App\Enums\ResultadoViabilidade;
use App\Models\ViabilityDecision;

/**
 * Explicabilidade passo a passo das decisões (HU-099 RN-004/RN-005) como
 * PROJEÇÃO PURA: LÊ o decision_trace gravado na 12-02 (+ rules_versions +
 * fundamentacao) e devolve a visualização ordenada por CNAE (entrada → risco →
 * LOUOS Quadro 7/10/11/11A → consolidação → desfecho), refletindo
 * motivo/versao_regra exatamente como o motor gravou.
 *
 * REGRA ANTI-FACHADA (RN-005): NUNCA instancia nem chama o motor (resolver,
 * LOUOS, risco) — a fonte é o registro, jamais a reexecução. Por isso o serviço
 * não tem dependência alguma do motor: é só uma transformação de leitura.
 *
 * Decisão LEGADA (decision_trace null, anterior a esta fase): degrada HONESTO —
 * monta a explicação compacta a partir de per_cnae/fundamentacao/rules_versions
 * e MARCA cada passo do motor não snapshotado como "não registrado nesta
 * decisão", sem inventar valores. A projeção 12-05 nunca recomputa.
 */
final class DecisionExplanationService
{
    private const TITULO_ENTRADA = 'Entrada';

    private const TITULO_RISCO = 'Classificação de risco';

    private const TITULO_QUADRO7 = 'LOUOS — Quadro 7 (classificação do uso)';

    private const TITULO_QUADRO10 = 'LOUOS — Quadro 10 (permissão na zona)';

    private const TITULO_QUADRO11A = 'LOUOS — Quadro 11-A (condições pela via)';

    private const TITULO_CONSOLIDACAO = 'Consolidação do veredito locacional';

    private const TITULO_DESFECHO = 'Desfecho';

    private const MOTIVO_NAO_REGISTRADO = 'não registrado nesta decisão';

    private const MOTIVO_RISCO_NAO_REGISTRADO = 'O Decreto nº 32.636/2020 classifica o risco do CNAE e define se o processo vai ao fluxo expresso ou à análise técnica. O nível e o encaminhamento desta decisão não foram gravados.';

    private const MOTIVO_QUADRO7_NAO_REGISTRADO = 'O Quadro 7 classifica o uso (CNAE × área → grupo). O grupo e a faixa desta decisão não foram gravados.';

    private const MOTIVO_QUADRO10_NAO_REGISTRADO = 'O Quadro 10 permite ou proíbe o grupo na zona. A permissão e a zona desta decisão não foram gravadas.';

    private const MOTIVO_QUADRO11A_NAO_REGISTRADO = 'O Quadro 11-A condiciona a instalação pela via (classe viária × grupo). Não permite nem proíbe o uso. As condições desta decisão não foram gravadas.';

    private const MOTIVO_PERMITIDO_SO_QUADRO7 = 'O registro cita o Quadro 7 da LOUOS como fundamento do veredito permitido. O Quadro 7 só classifica o uso (grupo por CNAE e área). Quem permite ou proíbe na zona é o Quadro 10. Grupo, faixa de área e zona não foram gravados nesta decisão.';

    private const MOTIVO_PERMITIDO_COM_QUADRO10 = 'O registro cita o Quadro 10 da LOUOS (permissão do grupo na zona). Os detalhes (grupo, faixa e zona) não foram gravados nesta decisão.';

    /**
     * Projeta a explicação passo a passo da decisão a partir do que foi GRAVADO.
     * Com decision_trace: projeta o snapshot por CNAE na ordem registrada. Sem
     * trace (legado): explicação compacta honesta, marcando os passos do motor
     * como não registrados.
     *
     * @return array{
     *     legado: bool,
     *     desfecho: array<string, mixed>,
     *     por_cnae: list<array<string, mixed>>,
     *     fundamentacao: list<mixed>,
     *     rules_versions: array<string, mixed>,
     * }
     */
    public function explain(ViabilityDecision $decision): array
    {
        $trace = $decision->decision_trace;
        $legado = ! is_array($trace) || $trace === [];

        return [
            'legado' => $legado,
            'desfecho' => $this->desfecho($decision),
            'por_cnae' => $legado
                ? $this->porCnaeLegado($decision)
                : $this->porCnaeProjetado($trace),
            'fundamentacao' => array_values((array) ($decision->fundamentacao ?? [])),
            'rules_versions' => (array) ($decision->rules_versions ?? []),
        ];
    }

    /**
     * Desfecho consolidado da decisão (projetado do registro, nunca recomputado).
     *
     * @return array<string, mixed>
     */
    private function desfecho(ViabilityDecision $decision): array
    {
        $consolidado = $decision->consolidated_result;

        return [
            'outcome' => $decision->outcome->value,
            'outcome_label' => $decision->outcome->label(),
            'consolidated_result' => $consolidado,
            'consolidated_result_label' => $this->consolidatedResultLabel($consolidado),
        ];
    }

    /**
     * Projeção pura do trace: preserva a ordem registrada (o builder já gravou na
     * ordem canônica) e normaliza o shape de cada passo para a apresentação.
     *
     * @param  list<mixed>  $trace
     * @return list<array<string, mixed>>
     */
    private function porCnaeProjetado(array $trace): array
    {
        return array_values(array_map(
            fn (mixed $item): array => $this->cnaeProjetado(is_array($item) ? $item : []),
            $trace,
        ));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function cnaeProjetado(array $item): array
    {
        $passos = is_array($item['passos'] ?? null) ? $item['passos'] : [];
        $passos = array_values(array_filter(
            $passos,
            fn (mixed $passo): bool => ! is_array($passo) || ($passo['passo'] ?? null) !== 'louos.quadro11',
        ));

        return [
            'cnae' => $item['cnae'] ?? null,
            'cnae_formatado' => $item['cnae_formatado'] ?? null,
            'is_primary' => (bool) ($item['is_primary'] ?? false),
            'origem' => $item['origem'] ?? null,
            'passos' => array_values(array_map(
                fn (mixed $passo): array => $this->passoProjetado(is_array($passo) ? $passo : []),
                $passos,
            )),
        ];
    }

    /**
     * Normaliza um passo do trace ao shape uniforme de apresentação. Não inventa:
     * só garante as chaves e copia o que o motor gravou.
     *
     * @param  array<string, mixed>  $passo
     * @return array<string, mixed>
     */
    private function passoProjetado(array $passo): array
    {
        $projetado = [
            'passo' => $passo['passo'] ?? null,
            'titulo' => $passo['titulo'] ?? null,
            'registrado' => (bool) ($passo['registrado'] ?? false),
            'entrada' => $passo['entrada'] ?? null,
            'resultado_parcial' => $passo['resultado_parcial'] ?? null,
            'motivo' => $passo['motivo'] ?? null,
            'versao_regra' => $passo['versao_regra'] ?? null,
        ];

        if (array_key_exists('fundamentacao', $passo)) {
            $projetado['fundamentacao'] = array_values((array) $passo['fundamentacao']);
        }

        return $projetado;
    }

    /**
     * Explicação compacta do legado a partir de per_cnae: a entrada e o veredito
     * por CNAE (quando existem no registro) são mostrados; os passos do motor
     * (risco e quadros LOUOS), sem snapshot, ficam marcados como não registrados.
     *
     * @return list<array<string, mixed>>
     */
    private function porCnaeLegado(ViabilityDecision $decision): array
    {
        $perCnae = array_values((array) ($decision->per_cnae ?? []));

        return array_map(
            fn (mixed $item): array => $this->cnaeLegado(is_array($item) ? $item : []),
            $perCnae,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function cnaeLegado(array $item): array
    {
        $temVeredito = array_key_exists('tendencia', $item) && $item['tendencia'] !== null;
        $fundamentacao = array_values((array) ($item['fundamentacao'] ?? []));

        $passos = [
            $this->passoEntradaLegado($item),
            $this->passoNaoRegistrado('risco', self::TITULO_RISCO, self::MOTIVO_RISCO_NAO_REGISTRADO),
            $this->passoNaoRegistrado('louos.quadro7', self::TITULO_QUADRO7, self::MOTIVO_QUADRO7_NAO_REGISTRADO),
            $this->passoNaoRegistrado('louos.quadro10', self::TITULO_QUADRO10, self::MOTIVO_QUADRO10_NAO_REGISTRADO),
            $this->passoNaoRegistrado('louos.quadro11a', self::TITULO_QUADRO11A, self::MOTIVO_QUADRO11A_NAO_REGISTRADO),
            $temVeredito
                ? $this->passoConsolidacaoLegado($item, $fundamentacao)
                : $this->passoNaoRegistrado('consolidacao', self::TITULO_CONSOLIDACAO),
            $temVeredito
                ? $this->passoDesfechoLegado($item)
                : $this->passoNaoRegistrado('desfecho', self::TITULO_DESFECHO),
        ];

        return [
            'cnae' => $item['cnae'] ?? null,
            'cnae_formatado' => $item['cnae_formatado'] ?? null,
            'is_primary' => (bool) ($item['is_primary'] ?? false),
            'origem' => null,
            'passos' => $passos,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function passoEntradaLegado(array $item): array
    {
        return [
            'passo' => 'entrada',
            'titulo' => self::TITULO_ENTRADA,
            'registrado' => true,
            'entrada' => [
                'cnae' => $item['cnae'] ?? null,
                'cnae_formatado' => $item['cnae_formatado'] ?? null,
                'fluxo' => $item['fluxo'] ?? null,
            ],
            'resultado_parcial' => null,
            'motivo' => null,
            'versao_regra' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<mixed>  $fundamentacao
     * @return array<string, mixed>
     */
    private function passoConsolidacaoLegado(array $item, array $fundamentacao): array
    {
        return [
            'passo' => 'consolidacao',
            'titulo' => self::TITULO_CONSOLIDACAO,
            'registrado' => true,
            'entrada' => null,
            'resultado_parcial' => [
                'resultado' => $item['tendencia'] ?? null,
                'label' => $item['tendencia_label'] ?? null,
            ],
            'motivo' => $this->motivoConsolidacaoLegado($item, $fundamentacao),
            'versao_regra' => null,
            'fundamentacao' => $fundamentacao,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function passoDesfechoLegado(array $item): array
    {
        return [
            'passo' => 'desfecho',
            'titulo' => self::TITULO_DESFECHO,
            'registrado' => true,
            'entrada' => null,
            'resultado_parcial' => [
                'tendencia' => $item['tendencia'] ?? null,
                'tendencia_label' => $item['tendencia_label'] ?? null,
                'fluxo' => $item['fluxo'] ?? null,
            ],
            'motivo' => $this->motivoDesfechoLegado($item),
            'versao_regra' => null,
        ];
    }

    /**
     * Passo cujo snapshot do motor não foi registrado nesta decisão — honesto,
     * jamais reexecutado nem inventado (espelha o DecisionTraceBuilder).
     *
     * @return array<string, mixed>
     */
    private function passoNaoRegistrado(string $passo, string $titulo, ?string $motivo = null): array
    {
        return [
            'passo' => $passo,
            'titulo' => $titulo,
            'registrado' => false,
            'entrada' => null,
            'resultado_parcial' => null,
            'motivo' => $motivo ?? self::MOTIVO_NAO_REGISTRADO,
            'versao_regra' => null,
        ];
    }

    /**
     * Explica o papel dos Quadros sem inventar grupo, faixa ou zona.
     *
     * @param  array<string, mixed>  $item
     * @param  list<mixed>  $fundamentacao
     */
    private function motivoConsolidacaoLegado(array $item, array $fundamentacao): ?string
    {
        $texto = implode(' ', array_map(strval(...), $fundamentacao));
        $citaQuadro7 = str_contains($texto, 'Quadro 7');
        $citaQuadro10 = str_contains($texto, 'Quadro 10');
        $tendencia = is_string($item['tendencia'] ?? null) ? $item['tendencia'] : '';

        $permitido = in_array($tendencia, [
            ResultadoViabilidade::Permitido->value,
            ResultadoViabilidade::PermitidoComCondicoes->value,
        ], true);

        if ($permitido && $citaQuadro7 && ! $citaQuadro10) {
            return self::MOTIVO_PERMITIDO_SO_QUADRO7;
        }

        if ($permitido && $citaQuadro10) {
            return self::MOTIVO_PERMITIDO_COM_QUADRO10;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function motivoDesfechoLegado(array $item): string
    {
        $label = is_string($item['tendencia_label'] ?? null)
            ? $item['tendencia_label']
            : (is_string($item['tendencia'] ?? null) ? $item['tendencia'] : 'o veredito gravado');
        $fluxo = is_string($item['fluxo'] ?? null) ? $item['fluxo'] : null;
        $fluxoTxt = $fluxo === 'expresso'
            ? ' no fluxo expresso'
            : ($fluxo === 'analise' ? ' em análise técnica' : '');

        return "O desfecho {$label}{$fluxoTxt} foi gravado. Os passos do motor que o fundamentam não foram snapshotados.";
    }

    /**
     * Rótulo do veredito consolidado (LOUOS RN-009); mantém o valor cru como
     * fallback caso surja um resultado fora do enum — nunca esconde o dado real.
     */
    private function consolidatedResultLabel(?string $resultado): ?string
    {
        if ($resultado === null) {
            return null;
        }

        return ResultadoViabilidade::tryFrom($resultado)?->label() ?? $resultado;
    }
}
