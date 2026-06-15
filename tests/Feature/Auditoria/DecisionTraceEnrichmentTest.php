<?php

namespace Tests\Feature\Auditoria;

use App\Enums\DecisionOutcome;
use App\Enums\ResultadoViabilidade;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Risco\RiscoResult;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Services\Viabilidade\ConsultaViabilidadeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Enriquecimento ADITIVO do decision_trace (HU-099 RN-004/RN-005): os DOIS
 * pontos de escrita da decisão passam a persistir o snapshot passo a passo
 * (entrada → risco → LOUOS Quadro 7/10/11/11A → consolidação → desfecho, por
 * CNAE) a partir do que o motor JÁ produziu em memória — sem recomputar e sem
 * mudar o veredito. A explicabilidade (12-05) projeta exatamente este shape;
 * decisões legadas (sem trace) ficam null e degradam honestas.
 */
class DecisionTraceEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Caso EXPRESSO (Fase 9, SQLite sem PostGIS): com um resolver FAKE devolvendo
     * um ResolvedViability de consulta_array conhecido, decide()→emitir() grava o
     * decision_trace por CNAE na ORDEM, com os passos LOUOS (Quadro 7→10→11→11A)
     * carregando versao_regra/motivo refletindo o consulta_array.
     */
    public function test_decisao_expressa_grava_decision_trace_passo_a_passo_por_cnae_na_ordem(): void
    {
        Event::fake();

        $resolved = $this->resolvedExpressoStub();
        $fake = $this->fakeResolver($resolved);
        $this->app->instance(SolicitacaoViabilityResolver::class, $fake);

        $request = ViabilityRequest::factory()->protocoled()->create();

        $result = app(FluxoExpressoService::class)->decide($request);

        $decision = $request->fresh()->decision;
        $this->assertNotNull($decision, 'A decisão expressa deveria ter sido criada.');

        // RN-005: o trace é montado do snapshot já em memória — o resolver é
        // chamado UMA única vez pela decisão (sem recomputo para explicar).
        $this->assertSame(1, $fake->calls, 'O resolver não pode ser reexecutado para montar o trace (RN-005).');

        $trace = $decision->decision_trace;
        $this->assertIsArray($trace);
        $this->assertCount(2, $trace, 'Um item de trace por CNAE.');

        // Ordem dos CNAEs preservada (principal primeiro).
        $this->assertSame('8888881', $trace[0]['cnae']);
        $this->assertSame('8888882', $trace[1]['cnae']);
        $this->assertTrue($trace[0]['is_primary']);
        $this->assertSame('motor', $trace[0]['origem']);

        // Ordem dos passos do CNAE principal: entrada → risco → LOUOS
        // (7→10→11→11A) → consolidação → desfecho.
        $ids = $this->idsDosPassos($trace[0]['passos']);

        $this->assertLessThan($this->posicao($ids, 'risco'), $this->posicao($ids, 'entrada'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro7'), $this->posicao($ids, 'risco'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro10'), $this->posicao($ids, 'louos.quadro7'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro11'), $this->posicao($ids, 'louos.quadro10'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro11a'), $this->posicao($ids, 'louos.quadro11'));
        $this->assertLessThan($this->posicao($ids, 'consolidacao'), $this->posicao($ids, 'louos.quadro11a'));
        $this->assertLessThan($this->posicao($ids, 'desfecho'), $this->posicao($ids, 'consolidacao'));

        // Passos LOUOS refletem versao_regra/motivo do consulta_array (nunca inventados).
        $quadro10 = $this->passo($trace[0]['passos'], 'louos.quadro10');
        $this->assertSame('lei-9148-2016-quadro10', $quadro10['versao_regra']);
        $this->assertTrue($quadro10['registrado']);

        $quadro11 = $this->passo($trace[0]['passos'], 'louos.quadro11');
        $this->assertSame('sem condicionante de uso aplicável', $quadro11['motivo']);
        $this->assertSame('lei-9148-2016-quadro11', $quadro11['versao_regra']);

        // Passo de risco carrega o encaminhamento e a versão da dimensão decisiva.
        $risco = $this->passo($trace[0]['passos'], 'risco');
        $this->assertSame('expresso', $risco['resultado_parcial']['encaminhamento']['fluxo']);
        $this->assertSame('decreto-32636-2020', $risco['versao_regra']);

        // Consolidação reflete o veredito locacional propagado do motor.
        $consolidacao = $this->passo($trace[0]['passos'], 'consolidacao');
        $this->assertSame('permitido', $consolidacao['resultado_parcial']['resultado']);
    }

    /**
     * Caso ANÁLISE TÉCNICA com snapshot do motor (Fase 10, SQLite): a ficha
     * finalizada traz o engine_snapshot por CNAE; a decisão humana grava o trace
     * reusando os passos do motor (Quadro 7→10→11→11A) e ACRESCENTA a decisão do
     * analista (sugerido × escolhido) — origem 'analista', sem recomputo.
     */
    public function test_decisao_tecnica_grava_decision_trace_da_ficha_com_snapshot_do_motor(): void
    {
        Event::fake();

        $consulta = $this->consultaResult('4712100', '4712-1/00')->toArray();
        $ficha = $this->fichaFinalizada(
            perCnae: [[
                'cnae' => '4712100',
                'cnae_formatado' => '4712-1/00',
                'is_primary' => true,
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'deferida',
                'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 10'],
            ]],
            override: [
                'engine_available' => true,
                'engine_snapshot' => [
                    'ponto' => ['lat' => -12.9714, 'lng' => -38.5014],
                    'area_m2' => 120.0,
                    'por_cnae' => [[
                        'cnae' => '4712100',
                        'cnae_formatado' => '4712-1/00',
                        'is_primary' => true,
                        'tendencia' => ResultadoViabilidade::Permitido->value,
                        'tendencia_label' => ResultadoViabilidade::Permitido->label(),
                        'consulta' => $consulta,
                    ]],
                ],
            ],
        );

        $result = app(AnaliseTecnicaDecisionService::class)->decide($ficha, User::factory()->create());

        $this->assertSame(DecisionOutcome::Deferida, $result->outcome);

        $decision = $ficha->viabilityRequest->fresh()->decision;
        $trace = $decision->decision_trace;
        $this->assertIsArray($trace);
        $this->assertCount(1, $trace);
        $this->assertSame('analista', $trace[0]['origem']);
        $this->assertSame('4712100', $trace[0]['cnae']);

        // Reusa os passos do motor do snapshot (mesma ordenação do Task 2).
        $quadro10 = $this->passo($trace[0]['passos'], 'louos.quadro10');
        $this->assertTrue($quadro10['registrado']);
        $this->assertSame('lei-9148-2016-quadro10', $quadro10['versao_regra']);

        // E acrescenta a decisão do analista (sugerido × escolhido).
        $humana = $this->passo($trace[0]['passos'], 'decisao_humana');
        $this->assertSame('deferida', $humana['entrada']['status_sugerido']);
        $this->assertSame('deferida', $humana['resultado_parcial']['status_escolhido']);
    }

    /**
     * Caso ANÁLISE TÉCNICA PENDENTE sem motor (FA-01): a ficha nasceu sem
     * snapshot (engine_available=false) e o analista decide o caso pendente; o
     * trace registra a entrada e a decisão humana e marca os passos do motor como
     * "não registrado" — honesto, jamais inventado nem recomputado.
     */
    public function test_decisao_tecnica_pendente_sem_motor_marca_passos_nao_registrados(): void
    {
        Event::fake();

        $ficha = $this->fichaFinalizada(
            perCnae: [[
                'cnae' => '4712100',
                'cnae_formatado' => '4712-1/00',
                'is_primary' => true,
                'status_sugerido' => 'analise',
                'status_escolhido' => 'deferida',
                'fundamentacao' => ['Decisão técnica do analista — uso compatível com a vizinhança.'],
            ]],
            override: [
                'engine_available' => false,
                'engine_snapshot' => null,
                'engine_rules_versions' => null,
            ],
        );

        $result = app(AnaliseTecnicaDecisionService::class)->decide($ficha, User::factory()->create());

        $this->assertSame(DecisionOutcome::Deferida, $result->outcome);

        $trace = $ficha->viabilityRequest->fresh()->decision->decision_trace;
        $this->assertIsArray($trace);
        $this->assertCount(1, $trace);
        $this->assertSame('analista', $trace[0]['origem']);

        // Entrada e decisão humana são registradas; o motor não.
        $this->assertTrue($this->passo($trace[0]['passos'], 'entrada')['registrado']);
        $this->assertTrue($this->passo($trace[0]['passos'], 'decisao_humana')['registrado']);

        $risco = $this->passo($trace[0]['passos'], 'risco');
        $this->assertFalse($risco['registrado']);
        $this->assertSame('não registrado nesta decisão', $risco['motivo']);

        $quadro10 = $this->passo($trace[0]['passos'], 'louos.quadro10');
        $this->assertFalse($quadro10['registrado']);
    }

    /**
     * Decisão LEGADA (anterior a esta fase): sem decision_trace gravado, a coluna
     * fica null e a decisão segue válida — a degradação honesta ("não registrado
     * nesta decisão") é exercida na projeção (12-05), nunca inventando.
     */
    public function test_decisao_legada_permanece_valida_com_decision_trace_null(): void
    {
        $decision = ViabilityDecision::factory()->create();

        $this->assertNull($decision->fresh()->decision_trace);
        $this->assertDatabaseHas('viability_decisions', [
            'id' => $decision->id,
            'decision_trace' => null,
        ]);
    }

    /**
     * Ficha FINALIZADA (revisão 1) de um processo em análise, com o per_cnae
     * escolhido pelo analista — espelha o helper da AnaliseTecnicaDecisionTest.
     *
     * @param  list<array<string, mixed>>  $perCnae
     * @param  array<string, mixed>  $override
     */
    private function fichaFinalizada(array $perCnae, array $override = []): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        return AnalysisRecord::factory()->finalizada()->create(array_merge([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'per_cnae' => $perCnae,
        ], $override));
    }

    /**
     * ResolvedViability de 2 CNAEs, ambos no fluxo expresso e permitidos, com um
     * ConsultaViabilidadeResult REAL por CNAE (toArray = consulta_array do trace).
     */
    private function resolvedExpressoStub(): ResolvedViability
    {
        $primeiro = $this->consultaResult('8888881', '8888-8/81');
        $segundo = $this->consultaResult('8888882', '8888-8/82');

        $porCnae = [
            $this->itemPorCnae('8888881', '8888-8/81', true, $primeiro),
            $this->itemPorCnae('8888882', '8888-8/82', false, $segundo),
        ];

        return new ResolvedViability(
            por_cnae: $porCnae,
            consolidado: ResultadoViabilidade::Permitido->value,
            rules_versions: $primeiro->versoes(),
            ponto: ['lat' => -12.9714, 'lng' => -38.5014],
            area_m2: 120.0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPorCnae(string $cnae, string $formatado, bool $primary, ConsultaViabilidadeResult $consulta): array
    {
        return [
            'cnae' => $cnae,
            'cnae_formatado' => $formatado,
            'is_primary' => $primary,
            'tendencia' => ResultadoViabilidade::Permitido->value,
            'tendencia_label' => ResultadoViabilidade::Permitido->label(),
            'fluxo' => 'expresso',
            'consulta' => $consulta,
            'consulta_array' => $consulta->toArray(),
        ];
    }

    /**
     * ConsultaViabilidadeResult permitido na zona — Quadro 7/10 identificados,
     * 11/11A não encontrados (com motivo), risco municipal baixo (expresso).
     */
    private function consultaResult(string $cnae, string $formatado): ConsultaViabilidadeResult
    {
        $enquadramento = new EnquadramentoResult(
            quadro7: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'grupo' => 'nR1',
                'subgrupo' => 'nR1-01',
                'motivo' => null,
                'versao_regra' => 'lei-9148-2016-quadro7',
            ],
            quadro10: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'permissao' => 'permitido',
                'condicionante_ref' => null,
                'motivo' => null,
                'versao_regra' => 'lei-9148-2016-quadro10',
            ],
            quadro11: [
                'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                'condicoes' => [],
                'motivo' => 'sem condicionante de uso aplicável',
                'versao_regra' => 'lei-9148-2016-quadro11',
            ],
            quadro11a: [
                'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                'condicoes' => [],
                'motivo' => 'sem porte especial aplicável',
                'versao_regra' => 'lei-9148-2016-quadro11a',
            ],
            consolidado: [
                'resultado' => ResultadoViabilidade::Permitido->value,
                'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 10'],
                'condicionantes' => [],
                'motivo' => 'uso permitido na zona',
            ],
            versoes: [
                'quadro7' => 'lei-9148-2016-quadro7',
                'quadro10' => 'lei-9148-2016-quadro10',
                'quadro11' => 'lei-9148-2016-quadro11',
                'quadro11a' => 'lei-9148-2016-quadro11a',
            ],
        );

        $risco = new RiscoResult(
            municipal: [
                'status' => RiscoResult::STATUS_CLASSIFICADO,
                'nivel' => 'baixo_a',
                'nivel_label' => 'Baixo A',
                'condicionantes' => [],
                'versao_regras' => 'decreto-32636-2020',
            ],
            sanitario: [
                'status' => RiscoResult::STATUS_CLASSIFICADO,
                'nivel_original' => null,
                'nivel_final' => null,
                'reclassificado' => false,
                'condicionantes_perguntas' => [],
                'versao_regras' => null,
            ],
            encaminhamento: [
                'fluxo' => 'expresso',
                'dimensao_decisiva' => 'municipal',
                'motivo' => 'risco municipal baixo — elegível ao fluxo expresso',
                'gatilhos_acionados' => [],
            ],
            fundamentacao: ['Decreto nº 32.636/2020 — classificação de risco'],
            versoes: [
                'municipal' => 'decreto-32636-2020',
                'sanitario' => null,
            ],
        );

        return new ConsultaViabilidadeResult(
            entrada: [
                'tipo' => 'ponto_conhecido',
                'cnae' => $cnae,
                'cnae_formatado' => $formatado,
                'area' => 120.0,
            ],
            geocode: null,
            territory: null,
            enquadramento: $enquadramento,
            risco: $risco,
            avisos: [],
        );
    }

    /**
     * Resolver FAKE: devolve o stub sem reexecutar os motores (sem PostGIS) e
     * conta as chamadas para provar o não-recomputo (RN-005).
     */
    private function fakeResolver(ResolvedViability $stub): SolicitacaoViabilityResolver
    {
        return new class($stub) extends SolicitacaoViabilityResolver
        {
            public int $calls = 0;

            public function __construct(private ResolvedViability $stub) {}

            public function resolve(ViabilityRequest $request): ResolvedViability
            {
                $this->calls++;

                return $this->stub;
            }
        };
    }

    /**
     * @param  list<array<string, mixed>>  $passos
     * @return list<string>
     */
    private function idsDosPassos(array $passos): array
    {
        return array_map(static fn (array $passo): string => (string) $passo['passo'], $passos);
    }

    /**
     * @param  list<string>  $ids
     */
    private function posicao(array $ids, string $passo): int
    {
        $pos = array_search($passo, $ids, true);
        $this->assertNotFalse($pos, "Passo ausente no trace: {$passo}");

        return (int) $pos;
    }

    /**
     * @param  list<array<string, mixed>>  $passos
     * @return array<string, mixed>
     */
    private function passo(array $passos, string $id): array
    {
        foreach ($passos as $passo) {
            if (($passo['passo'] ?? null) === $id) {
                return $passo;
            }
        }

        $this->fail("Passo ausente no trace: {$id}");
    }
}
