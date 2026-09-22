<?php

namespace App\Services\Analise;

use App\Enums\TipoGatilho;
use App\Models\AnalysisRecord;
use App\Models\ExpressoQueda;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Viabilidade\ConsultaViabilidadeResult;

/**
 * Motivo de Análise que o sistema grava na ficha: queda do expresso + fatos
 * do motor (CNAE, tipo de imóvel, zona, Quadro 10). O analista não edita.
 */
class MotivoAnaliseComposer
{
    /**
     * @return list<string>
     */
    public function para(ViabilityRequest $request, ?ResolvedViability $resolved): array
    {
        $linhas = [];

        foreach ($request->expressoQuedas()->orderBy('id')->get() as $queda) {
            $linhas[] = $this->queda($queda);
        }

        if ($resolved !== null) {
            $linhas = [...$linhas, ...$this->contexto($request, $resolved)];
        }

        return AnalysisRecord::normalizarMotivos($linhas);
    }

    private function queda(ExpressoQueda $queda): string
    {
        $partes = [];

        if (is_string($queda->cnae) && $queda->cnae !== '') {
            $partes[] = 'CNAE '.$this->formatarCnae($queda->cnae);
        }

        $motivo = trim((string) $queda->motivo);

        if ($motivo !== '') {
            $partes[] = $motivo;
        }

        $gatilho = TipoGatilho::tryFrom((string) $queda->tipo_gatilho)?->label();

        if ($gatilho !== null) {
            $partes[] = 'gatilho '.$gatilho;
        }

        if (is_string($queda->dimensao) && $queda->dimensao !== '') {
            $partes[] = 'dimensão decisiva '.$queda->dimensao;
        }

        return implode(' — ', $partes);
    }

    /**
     * @return list<string>
     */
    private function contexto(ViabilityRequest $request, ResolvedViability $resolved): array
    {
        $linhas = [];

        if (is_string($request->tipo_imovel) && trim($request->tipo_imovel) !== '') {
            $linhas[] = 'Tipo de imóvel informado no REGIN: '.$request->tipo_imovel.'.';
        }

        if ($request->used_area_m2 !== null) {
            $linhas[] = 'Área utilizada declarada: '.$request->used_area_m2.' m².';
        }

        $item = collect($resolved->por_cnae)->firstWhere('is_primary', true)
            ?? collect($resolved->por_cnae)->first();

        if (! is_array($item)) {
            return $linhas;
        }

        $consulta = $item['consulta'] ?? null;

        if (! $consulta instanceof ConsultaViabilidadeResult) {
            return $linhas;
        }

        $zona = $consulta->territory?->zona ?? [];
        $nomeZona = is_string($zona['nome'] ?? null) ? $zona['nome'] : null;
        $statusZona = is_string($zona['status'] ?? null) ? $zona['status'] : null;

        if ($statusZona === 'identificado' && $nomeZona !== null) {
            $linhas[] = 'Zona urbanística identificada: '.$nomeZona.'.';
        } elseif (is_string($zona['motivo'] ?? null) && $zona['motivo'] !== '') {
            $linhas[] = 'Zona urbanística: '.$zona['motivo'].'.';
        }

        $quadro10 = $consulta->enquadramento->quadro10;
        $permissao = is_string($quadro10['permissao'] ?? null) ? $quadro10['permissao'] : null;

        if (($quadro10['status'] ?? null) === 'identificado' && $permissao !== null) {
            $linhas[] = 'Quadro 10 da LOUOS: permissão '.$permissao
                .($nomeZona !== null ? ' na zona '.$nomeZona : '').'.';
        } elseif (is_string($quadro10['motivo'] ?? null) && $quadro10['motivo'] !== '') {
            $linhas[] = 'Quadro 10 da LOUOS: '.$quadro10['motivo'].'.';
        }

        $tendencia = is_string($item['tendencia_label'] ?? null)
            ? $item['tendencia_label']
            : (is_string($item['tendencia'] ?? null) ? $item['tendencia'] : null);

        if ($tendencia !== null && $tendencia !== '') {
            $linhas[] = 'Tendência locacional do motor: '.$tendencia.'.';
        }

        return $linhas;
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
