<?php

namespace App\Services\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ResultadoViabilidade;
use App\Models\AnalysisRecord;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Support\Audit\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pré-análise pelo motor (HU-140) — a maior alavanca de produtividade do projeto:
 * ao encaminhar um processo à análise humana, REEXECUTA o
 * SolicitacaoViabilityResolver FRESCO (o MESMO motor real da Fase 9 — sem lógica
 * de decisão paralela, RN-001) e cria a `analysis_records` revisão 1 (rascunho)
 * pré-preenchida: `engine_snapshot` INTEGRAL (a zona fica aninhada em
 * `por_cnae[i].consulta.territorio.zona`, conforme o PrecedentService lê),
 * `engine_rules_versions` e `per_cnae` com o status sugerido por CNAE
 * (deferida/indeferida/análise mapeado da tendência — SUGESTÃO, nunca decisão).
 *
 * Idempotente (RN-004): se a revisão 1 já existe, é no-op (retorna a existente) —
 * reabrir/reprocessar não reexecuta; recalcular é ação explícita (nova revisão,
 * 10-09). Degrada honesto (FA-01/CA-03): exceção do motor OU veredito consolidado
 * pendente (zona urbanística pendente SEDUR) → revisão 1 em modo manual com
 * `engine_available=false` e ficha vazia, NUNCA falha silenciosa nem sugestão
 * inventada. Toda execução é auditada (RN-005).
 */
class PreAnaliseService
{
    /**
     * A pré-análise materializa SEMPRE a primeira revisão da ficha; recalcular
     * (10-09) cria revisões subsequentes.
     */
    private const REVISAO_INICIAL = 1;

    /**
     * Status sugerido quando o motor não consegue propor um desfecho (veredito
     * pendente por CNAE) — encaminha à análise humana, jamais decide.
     */
    private const SUGESTAO_ANALISE = 'analise';

    public function __construct(
        private readonly SolicitacaoViabilityResolver $resolver,
        private readonly AuditService $audit,
    ) {}

    /**
     * Cria (ou retorna) a revisão 1 pré-preenchida do processo encaminhado à
     * análise. Idempotente por revisão (unique viability_request_id+revision):
     * se a revisão 1 já existe, devolve a existente sem reexecutar o motor.
     */
    public function preAnalisar(ViabilityRequest $request): ?AnalysisRecord
    {
        $existente = $this->revisaoInicial($request);

        if ($existente !== null) {
            return $existente;
        }

        try {
            return $this->criarRevisaoInicial($request);
        } catch (QueryException $e) {
            // Corrida rara: outra execução criou a revisão 1 concorrentemente
            // (a unique barra a 2ª). NO-OP idempotente — devolve a real gravada.
            return $this->revisaoInicial($request) ?? throw $e;
        }
    }

    /**
     * Tenta resolver pelo motor real e materializa a ficha conforme o resultado:
     * sucesso (sugestão pré-preenchida) ou degradação honesta (FA-01).
     */
    private function criarRevisaoInicial(ViabilityRequest $request): AnalysisRecord
    {
        try {
            $resolved = $this->resolver->resolve($request);
        } catch (Throwable $e) {
            // FA-01: motor indisponível/regra ausente → modo manual auditado.
            return $this->criarDegradada($request, 'motor indisponível: '.$e->getMessage());
        }

        // FA-01 / anti-fachada: sem dado confiável (veredito consolidado pendente
        // — zona urbanística pendente SEDUR) o motor NÃO sugere desfecho.
        if ($resolved->consolidado === ResultadoViabilidade::Pendente->value) {
            return $this->criarDegradada(
                $request,
                'veredito locacional pendente — zona urbanística pendente SEDUR',
            );
        }

        return $this->criarPreenchida($request, $resolved);
    }

    /**
     * Revisão 1 pré-preenchida pelo motor (sucesso): snapshot integral, versões
     * das regras e per_cnae com a sugestão por CNAE. Tudo numa transação com a
     * auditoria (RN-005).
     */
    private function criarPreenchida(ViabilityRequest $request, ResolvedViability $resolved): AnalysisRecord
    {
        return DB::transaction(function () use ($request, $resolved): AnalysisRecord {
            $record = AnalysisRecord::create([
                'viability_request_id' => $request->id,
                'revision' => self::REVISAO_INICIAL,
                'status' => AnalysisRecordStatus::Rascunho,
                'analyst_user_id' => null,
                'engine_available' => true,
                'engine_snapshot' => $resolved->toSnapshot(),
                'engine_rules_versions' => $resolved->rules_versions,
                'per_cnae' => $this->perCnae($resolved),
                'conditions' => [],
                'parking' => [],
                'parecer' => null,
                'finalized_at' => null,
            ]);

            $this->audit->log(
                logName: 'analise',
                event: 'pre-analise',
                description: "Pré-análise do processo #{$request->id} pré-preenchida pelo motor",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'revision' => self::REVISAO_INICIAL,
                    'engine_available' => true,
                    'consolidado' => $resolved->consolidado,
                    'sugestao_por_cnae' => $this->sugestaoPorCnae($resolved),
                ],
                subject: $record,
                result: 'sucesso',
                rulesVersion: $this->rulesVersionRepresentativa($resolved->rules_versions),
            );

            return $record;
        });
    }

    /**
     * Revisão 1 em modo manual (FA-01): ficha vazia + engine_available=false; o
     * analista preenche manualmente. O evento é auditado como degradado — NUNCA
     * falha silenciosa nem sugestão inventada.
     */
    private function criarDegradada(ViabilityRequest $request, string $motivo): AnalysisRecord
    {
        return DB::transaction(function () use ($request, $motivo): AnalysisRecord {
            $record = AnalysisRecord::create([
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

            $this->audit->log(
                logName: 'analise',
                event: 'pre-analise',
                description: "Pré-análise do processo #{$request->id} em modo manual: {$motivo}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'revision' => self::REVISAO_INICIAL,
                    'engine_available' => false,
                    'motivo' => $motivo,
                ],
                subject: $record,
                result: 'degradado',
            );

            return $record;
        });
    }

    /**
     * per_cnae da revisão 1: espelha a ficha SAPS (status sugerido × tendência,
     * encaminhamento de risco e fundamentação do motor) — insumo da revisão
     * humana (10-09) e da ficha SAPS (10-17).
     *
     * @return list<array<string, mixed>>
     */
    private function perCnae(ResolvedViability $resolved): array
    {
        return array_map(fn (array $item): array => [
            'cnae' => $item['cnae'],
            'cnae_formatado' => $item['cnae_formatado'],
            'is_primary' => $item['is_primary'],
            'tendencia' => $item['tendencia'],
            'tendencia_label' => $item['tendencia_label'],
            'status_sugerido' => $this->statusSugerido((string) $item['tendencia']),
            'fluxo' => $item['fluxo'],
            'fundamentacao' => $item['consulta']->fundamentacao(),
        ], $resolved->por_cnae);
    }

    /**
     * Resumo da sugestão por CNAE para a auditoria (RN-005): código, tendência e
     * status sugerido — explicabilidade compacta da pré-análise.
     *
     * @return list<array<string, mixed>>
     */
    private function sugestaoPorCnae(ResolvedViability $resolved): array
    {
        return array_map(fn (array $item): array => [
            'cnae' => $item['cnae'],
            'tendencia' => $item['tendencia'],
            'status_sugerido' => $this->statusSugerido((string) $item['tendencia']),
        ], $resolved->por_cnae);
    }

    /**
     * Mapeia a tendência locacional do motor para o status SUGERIDO ao analista
     * com a MESMA semântica da decisão (RN-001 — sugestão, não decisão):
     * permitido(_com_condicoes) → deferida; não permitido → indeferida; pendente
     * → análise (o humano decide o caso sem zona oficial).
     */
    private function statusSugerido(string $tendencia): string
    {
        return match ($tendencia) {
            ResultadoViabilidade::Permitido->value,
            ResultadoViabilidade::PermitidoComCondicoes->value => DecisionOutcome::Deferida->value,
            ResultadoViabilidade::NaoPermitido->value => DecisionOutcome::Indeferida->value,
            default => self::SUGESTAO_ANALISE,
        };
    }

    /**
     * Revisão 1 já materializada do processo (idempotência por revisão).
     */
    private function revisaoInicial(ViabilityRequest $request): ?AnalysisRecord
    {
        return $request->analysisRecords()
            ->where('revision', self::REVISAO_INICIAL)
            ->first();
    }

    /**
     * Versão de regra representativa (RN-005) para a coluna rules_version da
     * auditoria: a primeira versão real aplicada, na ordem em que governa o
     * veredito (Quadro 10 → 7 → 11/11A → risco → território) — mesma ordem do
     * FluxoExpressoService. O mapa completo vai em engine_rules_versions.
     *
     * @param  array<string, array<string, ?string>>  $rulesVersions
     */
    private function rulesVersionRepresentativa(array $rulesVersions): ?string
    {
        $ordem = [
            ['louos', 'quadro10'],
            ['louos', 'quadro7'],
            ['louos', 'quadro11'],
            ['louos', 'quadro11a'],
            ['risco', 'municipal'],
            ['risco', 'sanitario'],
            ['territorio', 'zona'],
            ['territorio', 'bairro'],
        ];

        foreach ($ordem as [$grupo, $chave]) {
            $versao = $rulesVersions[$grupo][$chave] ?? null;

            if (is_string($versao) && $versao !== '') {
                return $versao;
            }
        }

        return null;
    }
}
