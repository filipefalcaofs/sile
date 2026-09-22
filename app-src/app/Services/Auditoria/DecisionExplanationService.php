<?php

namespace App\Services\Auditoria;

use App\Enums\ResultadoViabilidade;
use App\Models\ViabilityDecision;
use App\Services\Decisao\DecisionTextCatalog;

/**
 * Explicabilidade passo a passo das decisões (HU-099 RN-004/RN-005) como
 * PROJEÇÃO PURA: LÊ o decision_trace gravado na 12-02 (+ rules_versions +
 * fundamentacao) e devolve a visualização ordenada por CNAE (entrada → risco →
 * LOUOS enquadramento de uso / 10 / 11A → consolidação → desfecho), refletindo
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
    /**
     * Os títulos dos passos e os motivos dos passos não registrados (decisão
     * legada) vivem no catálogo decision_texts (explicacao.titulo.* /
     * explicacao.motivo.*) — administráveis sem deploy, com fallback aos
     * textos de fábrica byte-idênticos aos das constantes removidas.
     */
    public function __construct(private DecisionTextCatalog $textos) {}

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
            $this->passoNaoRegistrado('risco', $this->textos->get('explicacao.titulo.risco'), $this->textos->get('explicacao.motivo.risco_nao_registrado')),
            $this->passoNaoRegistrado('louos.enquadramento', $this->textos->get('explicacao.titulo.enquadramento'), $this->textos->get('explicacao.motivo.enquadramento_nao_registrado')),
            $this->passoNaoRegistrado('louos.quadro10', $this->textos->get('explicacao.titulo.quadro10'), $this->textos->get('explicacao.motivo.quadro10_nao_registrado')),
            $this->passoNaoRegistrado('louos.quadro11a', $this->textos->get('explicacao.titulo.quadro11a'), $this->textos->get('explicacao.motivo.quadro11a_nao_registrado')),
            $temVeredito
                ? $this->passoConsolidacaoLegado($item, $fundamentacao)
                : $this->passoNaoRegistrado('consolidacao', $this->textos->get('explicacao.titulo.consolidacao')),
            $temVeredito
                ? $this->passoDesfechoLegado($item)
                : $this->passoNaoRegistrado('desfecho', $this->textos->get('explicacao.titulo.desfecho')),
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
            'titulo' => $this->textos->get('explicacao.titulo.entrada'),
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
            'titulo' => $this->textos->get('explicacao.titulo.consolidacao'),
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
            'titulo' => $this->textos->get('explicacao.titulo.desfecho'),
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
            'motivo' => $motivo ?? $this->textos->get('explicacao.motivo.nao_registrado'),
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
        $citaQuadro10 = str_contains($texto, 'Quadro 10');
        $citaEnquadramento = str_contains($texto, 'enquadramento')
            || str_contains($texto, 'Quadro 7')
            || (str_contains($texto, '(LOUOS)') && ! $citaQuadro10);
        $tendencia = is_string($item['tendencia'] ?? null) ? $item['tendencia'] : '';

        $permitido = in_array($tendencia, [
            ResultadoViabilidade::Permitido->value,
            ResultadoViabilidade::PermitidoComCondicoes->value,
        ], true);

        if ($permitido && $citaEnquadramento && ! $citaQuadro10) {
            return $this->textos->get('explicacao.motivo.permitido_so_enquadramento');
        }

        if ($permitido && $citaQuadro10) {
            return $this->textos->get('explicacao.motivo.permitido_com_quadro10');
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
