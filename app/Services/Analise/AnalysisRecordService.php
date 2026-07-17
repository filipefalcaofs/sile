<?php

namespace App\Services\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisDivergence;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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

    /** Campo da divergência por CNAE (status sugerido pelo motor × escolhido). */
    private const CAMPO_STATUS = 'status';

    public function __construct(private readonly AuditService $audit) {}

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

        if (array_key_exists('is_virtual_office_hq', $data)) {
            $record->is_virtual_office_hq = $data['is_virtual_office_hq'];
        }

        $record->save();

        return $record;
    }

    /**
     * Finaliza a revisão (HU-135 RN-003): torna-a IMUTÁVEL (status finalizada +
     * finalized_at) e materializa as divergências analista×motor (HU-140 RN-002)
     * numa transação com a auditoria síncrona (RN-002). Finalizar uma revisão já
     * finalizada lança AnalysisRecordImutavelException (422 no caller). NÃO decide
     * o processo — a decisão é 10-10, a partir da ficha finalizada.
     */
    public function finalizar(AnalysisRecord $record, ?User $ator = null): AnalysisRecord
    {
        if ($record->isFinalizada()) {
            throw AnalysisRecordImutavelException::finalizada($record);
        }

        return DB::transaction(function () use ($record, $ator): AnalysisRecord {
            $divergencias = $this->registrarDivergencias($record);

            $record->status = AnalysisRecordStatus::Finalizada;
            $record->finalized_at = now();

            if ($ator !== null) {
                $record->analyst_user_id = $ator->id;
            }

            $record->save();

            $this->audit->log(
                logName: 'analise',
                event: 'ficha-finalizar',
                description: "Finalização da ficha de análise do processo #{$record->viability_request_id} (revisão {$record->revision})",
                properties: [
                    'viability_request_id' => $record->viability_request_id,
                    'revision' => $record->revision,
                    'divergencias' => $divergencias,
                ],
                subject: $record,
                result: 'sucesso',
                rulesVersion: $this->rulesVersionRepresentativa($record->engine_rules_versions),
            );

            return $record;
        });
    }

    /**
     * Cria a próxima revisão (revision+1, rascunho) COPIANDO a última revisão como
     * base para reedição após a finalização — a finalizada permanece intacta
     * (append-only). "Recalcular" (reexecutar o motor) é uma variação explícita
     * que reporia o engine_snapshot numa nova revisão (reusando o PreAnaliseService)
     * — esta cria a revisão de EDIÇÃO; a finalizada nunca é alterada.
     */
    public function novaRevisao(ViabilityRequest $request, ?User $ator = null): AnalysisRecord
    {
        $ultima = $request->analysisRecords()->orderByDesc('revision')->first();

        if ($ultima === null) {
            // Defensivo: sem revisão anterior, materializa a revisão 1 vazia.
            return $this->current($request);
        }

        return DB::transaction(function () use ($request, $ultima, $ator): AnalysisRecord {
            $nova = AnalysisRecord::create([
                'viability_request_id' => $request->id,
                'revision' => $ultima->revision + 1,
                'status' => AnalysisRecordStatus::Rascunho,
                'analyst_user_id' => $ator?->id ?? $ultima->analyst_user_id,
                'engine_available' => $ultima->engine_available,
                'engine_snapshot' => $ultima->engine_snapshot,
                'engine_rules_versions' => $ultima->engine_rules_versions,
                'per_cnae' => $ultima->per_cnae,
                'conditions' => $ultima->conditions,
                'parking' => $ultima->parking,
                'parecer' => $ultima->parecer,
                'finalized_at' => null,
            ]);

            $this->audit->log(
                logName: 'analise',
                event: 'ficha-nova-revisao',
                description: "Nova revisão da ficha de análise do processo #{$request->id} (revisão {$nova->revision})",
                properties: [
                    'viability_request_id' => $request->id,
                    'revision_anterior' => $ultima->revision,
                    'revision' => $nova->revision,
                ],
                subject: $nova,
                result: 'sucesso',
            );

            return $nova;
        });
    }

    /**
     * Materializa as divergências (HU-140 RN-002): para cada CNAE onde o status
     * ESCOLHIDO pelo analista diverge do SUGERIDO pelo motor, grava uma
     * analysis_divergences (com a justificativa, quando informada). Concordâncias e
     * itens sem sugestão do motor (FA-01) NÃO geram divergência — anti-fachada.
     *
     * @return int Quantidade de divergências gravadas (para a auditoria).
     */
    private function registrarDivergencias(AnalysisRecord $record): int
    {
        $total = 0;

        foreach ($record->per_cnae ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sugerido = $item['status_sugerido'] ?? null;
            $escolhido = $item['status_escolhido'] ?? null;

            if ($sugerido === null || $escolhido === null || (string) $sugerido === (string) $escolhido) {
                continue;
            }

            AnalysisDivergence::create([
                'analysis_record_id' => $record->id,
                'cnae' => (string) ($item['cnae'] ?? ''),
                'field' => self::CAMPO_STATUS,
                'suggested_value' => (string) $sugerido,
                'final_value' => (string) $escolhido,
                'justification' => $item['justificativa'] ?? null,
            ]);

            $total++;
        }

        return $total;
    }

    /**
     * Versão de regra representativa para a coluna rules_version da auditoria: a
     * primeira versão real do engine_rules_versions da ficha. O mapa completo já
     * vive em engine_rules_versions (a explicabilidade detalhada é de 10-10/12).
     *
     * @param  array<string, mixed>|null  $rulesVersions
     */
    private function rulesVersionRepresentativa(?array $rulesVersions): ?string
    {
        foreach ($rulesVersions ?? [] as $grupo) {
            if (is_array($grupo)) {
                foreach ($grupo as $versao) {
                    if (is_string($versao) && $versao !== '') {
                        return $versao;
                    }
                }

                continue;
            }

            if (is_string($grupo) && $grupo !== '') {
                return $grupo;
            }
        }

        return null;
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
