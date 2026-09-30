<?php

namespace App\Services\Analise;

use App\Enums\TipoGatilho;
use App\Models\AnalysisRecord;
use App\Models\ExpressoQueda;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ResolvedViability;

/**
 * Motivo de Análise que o sistema grava na ficha: só a frase operacional da
 * queda (loteamento, CNAE sem enquadramento, área). O analista não edita.
 * Diagnóstico técnico (camada, gatilho, dimensão, tipo de imóvel, Quadro 10)
 * fica nos blocos próprios da ficha.
 */
class MotivoAnaliseComposer
{
    /**
     * @return list<string>
     */
    public function para(ViabilityRequest $request, ?ResolvedViability $resolved): array
    {
        $linhas = [];
        $incluirArea = false;

        foreach ($request->expressoQuedas()->orderBy('id')->get() as $queda) {
            $linha = $this->queda($queda);

            if ($linha !== null) {
                $linhas[] = $linha;
            }

            if ($this->semEnquadramento($queda)) {
                $incluirArea = true;
            }
        }

        if ($incluirArea && $request->used_area_m2 !== null) {
            $linhas[] = $this->formatarArea($request->used_area_m2);
        }

        return AnalysisRecord::normalizarMotivos($linhas);
    }

    private function queda(ExpressoQueda $queda): ?string
    {
        $motivo = trim((string) $queda->motivo);
        $territorial = $this->fraseTerritorial($motivo);

        if ($territorial !== null) {
            return $territorial;
        }

        if ($this->semEnquadramento($queda)) {
            if (! is_string($queda->cnae) || $queda->cnae === '') {
                return null;
            }

            return 'CNAE '.$this->formatarCnae($queda->cnae).' — não enquadrado — para enquadramento do analista';
        }

        if ($motivo === '') {
            return null;
        }

        if (is_string($queda->cnae) && $queda->cnae !== '') {
            return 'CNAE '.$this->formatarCnae($queda->cnae).' — '.$motivo;
        }

        return $motivo;
    }

    private function semEnquadramento(ExpressoQueda $queda): bool
    {
        $gatilho = $queda->tipo_gatilho instanceof TipoGatilho
            ? $queda->tipo_gatilho
            : TipoGatilho::tryFrom((string) $queda->tipo_gatilho);

        if ($gatilho === TipoGatilho::EnquadramentoAusente) {
            return true;
        }

        $motivo = mb_strtolower(trim((string) $queda->motivo));

        return str_contains($motivo, 'sem enquadramento')
            || str_contains($motivo, 'não enquadrad')
            || str_contains($motivo, 'nao enquadrad')
            || str_contains($motivo, 'não parametrizada');
    }

    private function fraseTerritorial(string $motivo): ?string
    {
        $frases = [
            'Loteamento Itaigara' => 'Solicitação cadastrada no Loteamento Itaigara',
            'Loteamento Vela Branca' => 'Solicitação cadastrada no Loteamento Vela Branca',
            'Zona de Exploração Mineral' => 'Solicitação cadastrada na Zona de Exploração Mineral',
            'Zona de Proteção Ambiental' => 'Solicitação cadastrada na Zona de Proteção Ambiental',
            'Trechos 5 e 6' => 'Solicitação cadastrada nos Trechos 5 e 6 da AMB – Área de Borda Marítima, em conformidade com as disposições estabelecidas pelo Decreto Municipal nº 31.168/2019',
            'Zona de Uso Especial' => 'Solicitação cadastrada em Zona de Uso Especial',
        ];

        foreach ($frases as $marca => $frase) {
            if (str_contains($motivo, $marca)) {
                return $frase;
            }
        }

        return null;
    }

    private function formatarArea(mixed $area): string
    {
        $texto = rtrim(rtrim(number_format((float) $area, 2, '.', ''), '0'), '.');

        return 'Área utilizada declarada: '.$texto.' m².';
    }

    private function formatarCnae(string $cnae): string
    {
        $digitos = (string) preg_replace('/\D/', '', $cnae);

        if (strlen($digitos) !== 7) {
            return $cnae;
        }

        return substr($digitos, 0, 4).'-'.substr($digitos, 4, 1).'/'.substr($digitos, 5, 2);
    }
}
