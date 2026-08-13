<?php

namespace App\Services\Auditoria;

/**
 * Montador PURO e determinístico do decision_trace (HU-099 RN-004/RN-005): a
 * partir do consulta_array JÁ produzido pelos motores (ConsultaViabilidadeResult
 * ::toArray) e/ou da ficha de análise, assembla o snapshot passo a passo da
 * decisão por CNAE, na ORDEM canônica:
 *
 *   entrada → risco → LOUOS Quadro 7 → 10 → 11 → 11A → consolidação → desfecho
 *   (+ decisão do analista, no fluxo humano).
 *
 * NÃO consulta banco, NÃO chama motor, NÃO inventa dado: só REORGANIZA o que já
 * está em memória, refletindo motivo/versao_regra de cada quadro tal como o
 * motor os gravou. É a FONTE ÚNICA do shape que os DOIS pontos de escrita
 * (FluxoExpressoService e AnaliseTecnicaDecisionService) persistem e que a
 * explicabilidade (DecisionExplanationService, 12-05) projeta sem recomputar.
 *
 * Cada passo tem o shape estável:
 *   {passo, titulo, registrado(bool), entrada, resultado_parcial, motivo, versao_regra}
 * (consolidação e decisão humana acrescentam `fundamentacao`). `registrado=false`
 * marca o passo cujo snapshot do motor não existe (decisão humana sem motor) —
 * honesto, jamais inventado.
 */
final class DecisionTraceBuilder
{
    /**
     * Entrada de trace de UM CNAE da decisão AUTOMÁTICA (fluxo expresso): origem
     * 'motor', passos entrada→…→desfecho montados do consulta_array.
     *
     * @param  array<string, mixed>  $consultaArray  ConsultaViabilidadeResult::toArray do CNAE.
     * @param  array<string, mixed>  $meta  cnae, cnae_formatado, is_primary, ponto?.
     * @return array<string, mixed>
     */
    public function cnaeExpresso(array $consultaArray, array $meta): array
    {
        return [
            'cnae' => $meta['cnae'] ?? null,
            'cnae_formatado' => $meta['cnae_formatado'] ?? null,
            'is_primary' => (bool) ($meta['is_primary'] ?? false),
            'origem' => 'motor',
            'passos' => $this->passosMotor($consultaArray, $meta),
        ];
    }

    /**
     * Entrada de trace de UM CNAE da decisão HUMANA (análise técnica): origem
     * 'analista'. Quando há snapshot do motor por CNAE ($consultaArray), reusa os
     * mesmos passos do motor e acrescenta a decisão do analista; sem snapshot
     * (caso pendente decidido pelo humano), registra a entrada + a decisão e marca
     * os passos do motor como "não registrado" — honesto, nunca inventado.
     *
     * @param  array<string, mixed>  $fichaItem  Item per_cnae da ficha (status_sugerido × escolhido, fundamentacao).
     * @param  array<string, mixed>|null  $consultaArray  Snapshot do motor para o CNAE (engine_snapshot), ou null.
     * @param  array<string, mixed>  $meta  cnae, cnae_formatado, is_primary, ponto?.
     * @return array<string, mixed>
     */
    public function cnaeAnaliseTecnica(array $fichaItem, ?array $consultaArray, array $meta): array
    {
        if ($consultaArray !== null) {
            $passos = [
                ...$this->passosMotor($consultaArray, $meta),
                $this->passoDecisaoHumana($fichaItem),
            ];
        } else {
            $passos = [
                $this->passoEntradaManual($fichaItem, $meta),
                $this->passoNaoRegistrado('risco', 'Classificação de risco'),
                $this->passoNaoRegistrado('louos.quadro7', 'LOUOS — Quadro 7 (classificação do uso)'),
                $this->passoNaoRegistrado('louos.quadro10', 'LOUOS — Quadro 10 (permissão na zona)'),
                $this->passoNaoRegistrado('louos.quadro11', 'LOUOS — Quadro 11 (condicionantes de uso)'),
                $this->passoNaoRegistrado('louos.quadro11a', 'LOUOS — Quadro 11-A (porte especial)'),
                $this->passoNaoRegistrado('consolidacao', 'Consolidação do veredito locacional'),
                $this->passoDecisaoHumana($fichaItem),
            ];
        }

        return [
            'cnae' => $meta['cnae'] ?? ($fichaItem['cnae'] ?? null),
            'cnae_formatado' => $meta['cnae_formatado'] ?? ($fichaItem['cnae_formatado'] ?? null),
            'is_primary' => (bool) ($meta['is_primary'] ?? ($fichaItem['is_primary'] ?? false)),
            'origem' => 'analista',
            'passos' => $passos,
        ];
    }

    /**
     * Passos do motor na ordem canônica, montados do consulta_array (entrada →
     * risco → Quadro 7 → 10 → 11 → 11A → consolidação → desfecho).
     *
     * @param  array<string, mixed>  $consultaArray
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private function passosMotor(array $consultaArray, array $meta): array
    {
        $entrada = is_array($consultaArray['entrada'] ?? null) ? $consultaArray['entrada'] : [];
        $enquadramento = is_array($consultaArray['enquadramento'] ?? null) ? $consultaArray['enquadramento'] : [];
        $risco = is_array($consultaArray['risco'] ?? null) ? $consultaArray['risco'] : [];
        $cnae = $meta['cnae'] ?? ($entrada['cnae'] ?? null);

        return [
            $this->passoEntrada($entrada, $meta),
            $this->passoRisco($risco),
            $this->passoLouos('louos.quadro7', 'LOUOS — Quadro 7 (classificação do uso)', $this->quadro($enquadramento, 'quadro7'), ['cnae' => $cnae, 'area_m2' => $entrada['area'] ?? null]),
            $this->passoLouos('louos.quadro10', 'LOUOS — Quadro 10 (permissão na zona)', $this->quadro($enquadramento, 'quadro10'), ['cnae' => $cnae]),
            $this->passoLouos('louos.quadro11', 'LOUOS — Quadro 11 (condicionantes de uso)', $this->quadro($enquadramento, 'quadro11'), ['cnae' => $cnae]),
            $this->passoLouos('louos.quadro11a', 'LOUOS — Quadro 11-A (porte especial)', $this->quadro($enquadramento, 'quadro11a'), ['cnae' => $cnae]),
            $this->passoConsolidacao($consultaArray),
            $this->passoDesfecho($consultaArray),
        ];
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function passoEntrada(array $entrada, array $meta): array
    {
        return [
            'passo' => 'entrada',
            'titulo' => 'Entrada',
            'registrado' => true,
            'entrada' => [
                'cnae' => $meta['cnae'] ?? ($entrada['cnae'] ?? null),
                'cnae_formatado' => $meta['cnae_formatado'] ?? ($entrada['cnae_formatado'] ?? null),
                'area_m2' => $entrada['area'] ?? null,
                'ponto' => $meta['ponto'] ?? null,
            ],
            'resultado_parcial' => null,
            'motivo' => null,
            'versao_regra' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fichaItem
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function passoEntradaManual(array $fichaItem, array $meta): array
    {
        return [
            'passo' => 'entrada',
            'titulo' => 'Entrada',
            'registrado' => true,
            'entrada' => [
                'cnae' => $meta['cnae'] ?? ($fichaItem['cnae'] ?? null),
                'cnae_formatado' => $meta['cnae_formatado'] ?? ($fichaItem['cnae_formatado'] ?? null),
                'area_m2' => null,
                'ponto' => $meta['ponto'] ?? null,
            ],
            'resultado_parcial' => null,
            'motivo' => null,
            'versao_regra' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $risco
     * @return array<string, mixed>
     */
    private function passoRisco(array $risco): array
    {
        $encaminhamento = is_array($risco['encaminhamento'] ?? null) ? $risco['encaminhamento'] : [];
        $versoes = is_array($risco['versoes'] ?? null) ? $risco['versoes'] : [];
        $dimensao = $encaminhamento['dimensao_decisiva'] ?? null;

        return [
            'passo' => 'risco',
            'titulo' => 'Classificação de risco',
            'registrado' => true,
            'entrada' => [
                'dimensao_decisiva' => is_string($dimensao) ? $dimensao : null,
            ],
            'resultado_parcial' => [
                'municipal' => $risco['municipal'] ?? null,
                'sanitario' => $risco['sanitario'] ?? null,
                'encaminhamento' => $encaminhamento,
            ],
            'motivo' => $encaminhamento['motivo'] ?? null,
            'versao_regra' => $this->versaoRisco($versoes, is_string($dimensao) ? $dimensao : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $quadro  {status, …, motivo, versao_regra} como o motor gravou.
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function passoLouos(string $passo, string $titulo, array $quadro, array $entrada): array
    {
        $resultadoParcial = $quadro;
        unset($resultadoParcial['motivo'], $resultadoParcial['versao_regra']);

        return [
            'passo' => $passo,
            'titulo' => $titulo,
            'registrado' => $quadro !== [],
            'entrada' => $entrada,
            'resultado_parcial' => $resultadoParcial === [] ? null : $resultadoParcial,
            'motivo' => $quadro['motivo'] ?? null,
            'versao_regra' => $quadro['versao_regra'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $consultaArray
     * @return array<string, mixed>
     */
    private function passoConsolidacao(array $consultaArray): array
    {
        $veredito = is_array($consultaArray['veredito_locacional'] ?? null) ? $consultaArray['veredito_locacional'] : [];
        $fundamentacao = is_array($consultaArray['fundamentacao'] ?? null) ? $consultaArray['fundamentacao'] : [];

        return [
            'passo' => 'consolidacao',
            'titulo' => 'Consolidação do veredito locacional',
            'registrado' => true,
            'entrada' => null,
            'resultado_parcial' => [
                'resultado' => $veredito['resultado'] ?? null,
                'label' => $veredito['label'] ?? null,
            ],
            'motivo' => $veredito['motivo'] ?? null,
            'versao_regra' => null,
            'fundamentacao' => array_values($fundamentacao),
        ];
    }

    /**
     * @param  array<string, mixed>  $consultaArray
     * @return array<string, mixed>
     */
    private function passoDesfecho(array $consultaArray): array
    {
        $veredito = is_array($consultaArray['veredito_locacional'] ?? null) ? $consultaArray['veredito_locacional'] : [];
        $encaminhamento = $consultaArray['risco']['encaminhamento'] ?? [];

        return [
            'passo' => 'desfecho',
            'titulo' => 'Desfecho',
            'registrado' => true,
            'entrada' => null,
            'resultado_parcial' => [
                'tendencia' => $veredito['resultado'] ?? null,
                'tendencia_label' => $veredito['label'] ?? null,
                'fluxo' => is_array($encaminhamento) ? ($encaminhamento['fluxo'] ?? null) : null,
            ],
            'motivo' => null,
            'versao_regra' => null,
        ];
    }

    /**
     * Decisão do analista (RN-004): sugerido × escolhido + fundamentação da ficha.
     *
     * @param  array<string, mixed>  $fichaItem
     * @return array<string, mixed>
     */
    private function passoDecisaoHumana(array $fichaItem): array
    {
        $fundamentacao = is_array($fichaItem['fundamentacao'] ?? null) ? $fichaItem['fundamentacao'] : [];

        return [
            'passo' => 'decisao_humana',
            'titulo' => 'Decisão do analista',
            'registrado' => true,
            'entrada' => [
                'status_sugerido' => $fichaItem['status_sugerido'] ?? null,
            ],
            'resultado_parcial' => [
                'status_escolhido' => $fichaItem['status_escolhido'] ?? null,
            ],
            'motivo' => null,
            'versao_regra' => null,
            'fundamentacao' => array_values($fundamentacao),
        ];
    }

    /**
     * Passo cujo snapshot do motor não foi registrado nesta decisão (humano sem
     * motor): honesto, jamais reexecutado nem inventado.
     *
     * @return array<string, mixed>
     */
    private function passoNaoRegistrado(string $passo, string $titulo): array
    {
        return [
            'passo' => $passo,
            'titulo' => $titulo,
            'registrado' => false,
            'entrada' => null,
            'resultado_parcial' => null,
            'motivo' => 'não registrado nesta decisão',
            'versao_regra' => null,
        ];
    }

    /**
     * Quadro do enquadramento como array (degrada para vazio se ausente).
     *
     * @param  array<string, mixed>  $enquadramento
     * @return array<string, mixed>
     */
    private function quadro(array $enquadramento, string $chave): array
    {
        return is_array($enquadramento[$chave] ?? null) ? $enquadramento[$chave] : [];
    }

    /**
     * Versão de regra do risco: a da dimensão decisiva; senão a primeira versão
     * real (municipal → sanitário). Null sem versão registrada.
     *
     * @param  array<string, mixed>  $versoes
     */
    private function versaoRisco(array $versoes, ?string $dimensao): ?string
    {
        if ($dimensao !== null) {
            $versao = $versoes[$dimensao] ?? null;

            if (is_string($versao) && $versao !== '') {
                return $versao;
            }
        }

        foreach (['municipal', 'sanitario'] as $chave) {
            $versao = $versoes[$chave] ?? null;

            if (is_string($versao) && $versao !== '') {
                return $versao;
            }
        }

        return null;
    }
}
