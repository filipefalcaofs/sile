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
 * (deferida/indeferida/análise mapeado da tendência — SUGESTÃO, nunca decisão),
 * `status_escolhido` igual à sugestão (o analista só altera se divergir),
 * condicionantes, vagas e parecer-rascunho com a fundamentação dos Quadros
 * da LOUOS. Mesmo quando o processo NÃO é expresso, a ficha chega completa
 * para confirmar ou alterar — não para preencher do zero (HU-140 CA-01).
 *
 * Idempotente (RN-004): se a revisão 1 já existe, é no-op (retorna a existente) —
 * reabrir/reprocessar não reexecuta; recalcular é ação explícita (nova revisão,
 * 10-09). Degrada honesto (FA-01/CA-03): exceção do motor → revisão 1 em modo
 * manual com `engine_available=false` e ficha vazia. Veredito locacional
 * pendente (zona SEDUR) NÃO esvazia a ficha: traz o que o motor sabe (risco,
 * Quadro 7, avisos) com status `analise`, sem inventar deferimento. Toda
 * execução é auditada (RN-005).
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
        private readonly MotivoAnaliseComposer $motivos,
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
            if (! $this->eRascunhoVazioDoMotor($existente)) {
                return $existente;
            }

            $existente->delete();
            $request->unsetRelation('analysisRecords');
            $request->unsetRelation('currentAnalysisRecord');
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
                'engine_snapshot' => [
                    ...$resolved->toSnapshot(),
                    'consolidado' => $resolved->consolidado,
                ],
                'engine_rules_versions' => $resolved->rules_versions,
                'per_cnae' => $this->perCnae($resolved),
                'conditions' => $this->conditions($resolved),
                'parking' => $this->parking($resolved),
                'parecer' => $this->parecerRascunho($request, $resolved),
                'analysis_reasons' => $this->motivosDaQueda($request, $resolved),
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
                'analysis_reasons' => $this->motivosDaQueda($request, null),
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
        return array_map(function (array $item): array {
            $status = $this->statusSugerido((string) $item['tendencia']);
            $consulta = $item['consulta'];
            $quadro7 = $consulta->enquadramento->quadro7;
            $grupo = is_string($quadro7['grupo'] ?? null) && $quadro7['grupo'] !== ''
                ? $quadro7['grupo']
                : null;

            return [
                'cnae' => $item['cnae'],
                'cnae_formatado' => $item['cnae_formatado'],
                'is_primary' => $item['is_primary'],
                'tendencia' => $item['tendencia'],
                'tendencia_label' => $item['tendencia_label'],
                'status_sugerido' => $status,
                'status_escolhido' => $status,
                'fluxo' => $item['fluxo'],
                'grupo_uso' => $grupo,
                'gatilhos' => $this->rotulosGatilhos($consulta->risco->encaminhamento['gatilhos_acionados'] ?? []),
                'condicionantes' => $this->textosCondicionantes($consulta->enquadramento->consolidado['condicionantes'] ?? []),
                'fundamentacao' => $consulta->fundamentacao(),
                'justificativa' => null,
                // Paridade com o legado (spec 2026-07-24): código LOUOS/TLL
                // estruturado não é entregue pela SEDUR ainda (bloqueio externo
                // real) — contrato explícito null, nunca um valor inventado.
                'codigo_louos' => null,
                'codigo_tll' => null,
            ];
        }, $resolved->por_cnae);
    }

    /**
     * Condicionantes do motor já marcadas na ficha (HU-140 CA-01).
     *
     * @return list<string>
     */
    private function conditions(ResolvedViability $resolved): array
    {
        $textos = [];

        foreach ($this->perCnae($resolved) as $item) {
            foreach ($item['condicionantes'] as $texto) {
                $textos[] = $texto;
            }
        }

        return array_values(array_unique($textos));
    }

    /**
     * Vagas calculadas pelo motor (HU-042) — o analista confirma ou altera.
     *
     * @return array{vagas_requeridas: int|null, vagas_exigidas: int|null, vistoria: bool}|array{}
     */
    private function parking(ResolvedViability $resolved): array
    {
        foreach ($resolved->por_cnae as $item) {
            foreach ($item['consulta']->enquadramento->consolidado['condicionantes'] ?? [] as $condicionante) {
                if (! is_array($condicionante) || ($condicionante['tipo'] ?? null) !== 'vagas') {
                    continue;
                }

                $exigidas = $this->somarQuantidades(is_array($condicionante['exigido'] ?? null) ? $condicionante['exigido'] : []);
                $requeridas = $this->somarQuantidades(is_array($condicionante['declarado'] ?? null) ? $condicionante['declarado'] : []);

                return [
                    'vagas_requeridas' => $requeridas > 0 ? $requeridas : null,
                    'vagas_exigidas' => $exigidas > 0 ? $exigidas : null,
                    'vistoria' => ($condicionante['conforme'] ?? null) === false,
                ];
            }
        }

        return [];
    }

    /**
     * Parecer-rascunho determinístico: só o que o motor já fundamentou
     * (Quadros LOUOS + risco). Nunca inventa zona nem desfecho.
     */
    private function parecerRascunho(ViabilityRequest $request, ResolvedViability $resolved): string
    {
        $linhas = [
            'Veredito locacional consolidado: '.ResultadoViabilidade::from($resolved->consolidado)->label().'.',
        ];

        foreach ($resolved->por_cnae as $item) {
            $consulta = $item['consulta'];
            $quadro7 = $consulta->enquadramento->quadro7;
            $quadro10 = $consulta->enquadramento->quadro10;
            $rotulo = ($item['is_primary'] ?? false) ? 'principal' : 'secundária';
            $codigo = $item['cnae_formatado'] ?? $item['cnae'];
            $tendencia = $item['tendencia_label'] ?? $item['tendencia'];

            $linhas[] = '';
            $linhas[] = "Atividade {$codigo} ({$rotulo}): {$tendencia}.";

            if (is_string($quadro7['grupo'] ?? null) && $quadro7['grupo'] !== '') {
                $subgrupo = is_string($quadro7['subgrupo'] ?? null) && $quadro7['subgrupo'] !== ''
                    ? ' / '.$quadro7['subgrupo']
                    : '';
                $linhas[] = 'Quadro 7 da LOUOS: grupo '.$quadro7['grupo'].$subgrupo.'.';
            }

            if (($quadro10['status'] ?? null) === 'identificado') {
                $permissao = is_string($quadro10['permissao'] ?? null) ? $quadro10['permissao'] : '';
                $linhas[] = 'Quadro 10 da LOUOS: permissão '.$permissao.' na zona identificada.';
            } elseif (is_string($quadro10['motivo'] ?? null) && $quadro10['motivo'] !== '') {
                $linhas[] = 'Quadro 10 da LOUOS: '.$quadro10['motivo'].'.';
            }

            $motivo = $consulta->enquadramento->consolidado['motivo'] ?? null;

            if (is_string($motivo) && $motivo !== '') {
                $linhas[] = $motivo.'.';
            }
        }

        $referencias = [];

        foreach ($resolved->por_cnae as $item) {
            $referencias = [...$referencias, ...$item['consulta']->fundamentacao()];
        }

        $referencias = array_values(array_unique($referencias));

        if ($referencias !== []) {
            $linhas[] = '';
            $linhas[] = 'Fundamentação legal:';

            foreach ($referencias as $referencia) {
                $linhas[] = '- '.$referencia;
            }
        }

        $quedas = $this->motivosDaQueda($request, $resolved) ?? [];

        if ($quedas !== []) {
            $linhas[] = '';
            $linhas[] = 'Motivo do encaminhamento à análise:';

            foreach ($quedas as $queda) {
                $linhas[] = '- '.$queda;
            }
        }

        return implode("\n", $linhas);
    }

    /**
     * @param  list<mixed>  $condicionantes
     * @return list<string>
     */
    private function textosCondicionantes(array $condicionantes): array
    {
        $textos = [];

        foreach ($condicionantes as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['tipo'] ?? null) === 'vagas' && ($item['exigido'] ?? null) === null) {
                continue;
            }

            $motivo = trim((string) ($item['motivo'] ?? ''));

            if ($motivo === '') {
                continue;
            }

            $textos[] = $motivo;
        }

        return array_values(array_unique($textos));
    }

    /**
     * @param  list<mixed>  $gatilhos
     * @return list<string>
     */
    private function rotulosGatilhos(array $gatilhos): array
    {
        $rotulos = [];

        foreach ($gatilhos as $gatilho) {
            if (! is_array($gatilho)) {
                continue;
            }

            $rotulo = trim((string) ($gatilho['motivo'] ?? $gatilho['codigo'] ?? ''));

            if ($rotulo === '') {
                continue;
            }

            $rotulos[] = $rotulo;
        }

        return $rotulos;
    }

    /**
     * @param  array<string, mixed>  $quantidades
     */
    private function somarQuantidades(array $quantidades): int
    {
        $total = 0;

        foreach ($quantidades as $quantidade) {
            if (is_numeric($quantidade)) {
                $total += (int) $quantidade;
            }
        }

        return $total;
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
     * Revisão 1 vazia de uma pré-análise degradada: não é uma análise humana
     * iniciada — pode ser refeita para entregar o rascunho do motor. Rascunho
     * já preenchido (mesmo que o analista ainda não tenha tocado) permanece
     * idempotente (RN-004).
     */
    private function eRascunhoVazioDoMotor(AnalysisRecord $record): bool
    {
        $parecer = trim((string) ($record->parecer ?? ''));

        return $record->revision === self::REVISAO_INICIAL
            && $record->status === AnalysisRecordStatus::Rascunho
            && $record->engine_available === false
            && ($record->per_cnae === null || $record->per_cnae === [])
            && $parecer === '';
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
     * veredito (Quadro 10 → 7 → 11A → risco → território) — mesma ordem do
     * FluxoExpressoService. O mapa completo vai em engine_rules_versions.
     *
     * @param  array<string, array<string, ?string>>  $rulesVersions
     */
    private function rulesVersionRepresentativa(array $rulesVersions): ?string
    {
        $ordem = [
            ['louos', 'quadro10'],
            ['louos', 'quadro7'],
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

    /**
     * Motivo da queda do expresso — o sistema preenche a ficha; o analista não
     * edita. Sem queda gravada, a lista fica vazia (null) até o Resource
     * resolver no fallback de leitura.
     *
     * @return list<string>|null
     */
    private function motivosDaQueda(ViabilityRequest $request, ?ResolvedViability $resolved): ?array
    {
        $motivos = $this->motivos->para($request, $resolved);

        return $motivos === [] ? null : $motivos;
    }
}
