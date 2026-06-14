<?php

namespace App\Services\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisRecord;
use App\Models\ViabilityRequest;
use Illuminate\Database\QueryException;

/**
 * Ficha de análise (HU-135) — o coração da análise humana. Orquestra a abertura
 * da revisão vigente, o autosave do rascunho (RN-008), a finalização imutável
 * (RN-003) com o registro das divergências analista×motor (HU-140 RN-002) e a
 * criação de uma nova revisão para reedição/recálculo após a finalização.
 *
 * A revisão é versionada e append-only: a finalizada NUNCA é alterada — recalcular
 * ou reeditar gera uma nova linha (revision+1). O dado bruto do motor vive no
 * engine_snapshot; o autosave só toca os campos do rascunho preservando a sugestão
 * do motor (status_sugerido), que é o insumo da divergência na finalização.
 */
class AnalysisRecordService
{
    /** Revisão inicial materializada pela pré-análise (10-08) ou defensivamente aqui. */
    private const REVISAO_INICIAL = 1;

    /**
     * Revisão vigente da ficha (a de maior revisão). Defensivamente cria a revisão
     * 1 vazia se não houver nenhuma — normalmente a pré-análise (10-08) já a criou.
     */
    public function current(ViabilityRequest $request): AnalysisRecord
    {
        $record = $request->analysisRecords()->orderByDesc('revision')->first();

        if ($record !== null) {
            return $record;
        }

        try {
            return $this->criarRevisaoInicialVazia($request);
        } catch (QueryException $e) {
            // Corrida: outra execução criou a revisão 1 concorrentemente (a unique
            // viability_request_id+revision barra a 2ª). Devolve a existente.
            return $request->analysisRecords()->orderByDesc('revision')->first() ?? throw $e;
        }
    }

    /**
     * Autosave do rascunho (RN-008): atualiza só os campos enviados (status
     * escolhido por CNAE, condicionantes, vagas e parecer). A revisão finalizada é
     * imutável (RN-003) — autosave nela lança AnalysisRecordImutavelException
     * (422 no caller). A sugestão do motor (status_sugerido) é preservada.
     *
     * @param  array<string, mixed>  $data
     */
    public function autosave(AnalysisRecord $record, array $data): AnalysisRecord
    {
        if ($record->isFinalizada()) {
            throw AnalysisRecordImutavelException::finalizada($record);
        }

        if (array_key_exists('per_cnae', $data) && is_array($data['per_cnae'])) {
            $record->per_cnae = $this->mergePerCnae($record->per_cnae, $data['per_cnae']);
        }

        if (array_key_exists('conditions', $data) && is_array($data['conditions'])) {
            $record->conditions = array_values($data['conditions']);
        }

        if (array_key_exists('parking', $data) && is_array($data['parking'])) {
            $record->parking = $data['parking'];
        }

        if (array_key_exists('parecer', $data)) {
            $record->parecer = $data['parecer'];
        }

        $record->save();

        return $record;
    }

    /**
     * Funde a escolha do analista (status_escolhido/justificativa/condicionantes
     * por CNAE) na lista vigente, casando por `cnae` e PRESERVANDO os campos do
     * motor (status_sugerido, tendência, fundamentação). CNAEs ausentes na ficha
     * (modo manual) entram como novos itens.
     *
     * @param  array<int, mixed>|null  $existing
     * @param  array<int, mixed>  $incoming
     * @return list<array<string, mixed>>
     */
    private function mergePerCnae(?array $existing, array $incoming): array
    {
        $base = [];
        foreach ($existing ?? [] as $item) {
            if (is_array($item) && isset($item['cnae'])) {
                $base[(string) $item['cnae']] = $item;
            }
        }

        foreach ($incoming as $item) {
            if (! is_array($item) || ! isset($item['cnae'])) {
                continue;
            }

            $cnae = (string) $item['cnae'];
            $atual = $base[$cnae] ?? ['cnae' => $cnae];

            foreach (['status_escolhido', 'justificativa', 'condicionantes'] as $campo) {
                if (array_key_exists($campo, $item)) {
                    $atual[$campo] = $item[$campo];
                }
            }

            $base[$cnae] = $atual;
        }

        return array_values($base);
    }

    private function criarRevisaoInicialVazia(ViabilityRequest $request): AnalysisRecord
    {
        return AnalysisRecord::create([
            'viability_request_id' => $request->id,
            'revision' => self::REVISAO_INICIAL,
            'status' => AnalysisRecordStatus::Rascunho,
            'analyst_user_id' => null,
            'engine_available' => false,
            'engine_snapshot' => null,
            'engine_rules_versions' => null,
            'per_cnae' => null,
            'conditions' => [],
            'parking' => [],
            'parecer' => null,
            'finalized_at' => null,
        ]);
    }
}
