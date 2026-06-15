<?php

namespace Tests\Feature\Auditoria;

use App\Enums\ResultadoViabilidade;
use App\Models\ViabilityRequest;
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
