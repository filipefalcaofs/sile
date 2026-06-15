<?php

namespace Tests\Feature\Auditoria;

use App\Http\Resources\DecisionExplanationResource;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Auditoria\DecisionExplanationService;
use App\Services\Auditoria\DecisionTraceBuilder;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Explicabilidade passo a passo das decisões (HU-099 RN-004/RN-005) como
 * PROJEÇÃO PURA: o DecisionExplanationService LÊ o decision_trace gravado na
 * 12-02 (+ rules_versions + fundamentacao) e devolve a visualização ordenada,
 * sem NUNCA chamar o motor (spy=0). Decisão legada (decision_trace null) degrada
 * honesta, marcando os passos não snapshotados como "não registrado nesta
 * decisão" — nunca inventa nem reexecuta. A explicação é exposta no detalhe da
 * decisão na retaguarda (ResultadoExpressoController::show e
 * ProcessoController::show), sob a permissão consultar-solicitacoes que já
 * existe (sem rota nem permissão nova).
 */
class DecisionExplanationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * RN-004: explain() projeta o trace por CNAE na ORDEM canônica (entrada →
     * risco → LOUOS 7→10→11→11A → consolidação → desfecho), refletindo
     * versao_regra/motivo exatamente como o motor gravou — sem recomputar. A
     * fundamentação consolidada, o mapa de versões e o desfecho gravado também
     * são projetados.
     */
    public function test_explain_projeta_passos_do_trace_na_ordem_com_versao_motivo_e_desfecho(): void
    {
        $decision = $this->decisionComTrace();

        $explicacao = app(DecisionExplanationService::class)->explain($decision);

        $this->assertFalse($explicacao['legado'], 'Decisão com trace não é legada.');
        $this->assertCount(2, $explicacao['por_cnae'], 'Um bloco por CNAE.');

        $this->assertSame('8888881', $explicacao['por_cnae'][0]['cnae']);
        $this->assertSame('8888882', $explicacao['por_cnae'][1]['cnae']);
        $this->assertTrue($explicacao['por_cnae'][0]['is_primary']);
        $this->assertSame('motor', $explicacao['por_cnae'][0]['origem']);

        // Ordem canônica dos passos do CNAE principal.
        $ids = $this->idsDosPassos($explicacao['por_cnae'][0]['passos']);
        $this->assertLessThan($this->posicao($ids, 'risco'), $this->posicao($ids, 'entrada'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro7'), $this->posicao($ids, 'risco'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro10'), $this->posicao($ids, 'louos.quadro7'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro11'), $this->posicao($ids, 'louos.quadro10'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro11a'), $this->posicao($ids, 'louos.quadro11'));
        $this->assertLessThan($this->posicao($ids, 'consolidacao'), $this->posicao($ids, 'louos.quadro11a'));
        $this->assertLessThan($this->posicao($ids, 'desfecho'), $this->posicao($ids, 'consolidacao'));

        // Passos LOUOS refletem versao_regra/motivo do trace, jamais inventados.
        $quadro10 = $this->passo($explicacao['por_cnae'][0]['passos'], 'louos.quadro10');
        $this->assertTrue($quadro10['registrado']);
        $this->assertSame('lei-9148-2016-quadro10', $quadro10['versao_regra']);

        $quadro11 = $this->passo($explicacao['por_cnae'][0]['passos'], 'louos.quadro11');
        $this->assertSame('sem condicionante de uso aplicável', $quadro11['motivo']);
        $this->assertSame('lei-9148-2016-quadro11', $quadro11['versao_regra']);

        // Fundamentação consolidada e versões das regras GRAVADAS são projetadas.
        $this->assertNotEmpty($explicacao['fundamentacao']);
        $this->assertArrayHasKey('louos', $explicacao['rules_versions']);

        // Desfecho projeta o que foi decidido (não recomputa).
        $this->assertSame('deferida', $explicacao['desfecho']['outcome']);
        $this->assertSame('permitido', $explicacao['desfecho']['consolidated_result']);
    }

    /**
     * RN-005 (PROJEÇÃO PURA): explain() NUNCA chama o motor. Com um SPY do
     * SolicitacaoViabilityResolver registrado no container (que conta e lança se
     * acionado), a projeção roda e o motor permanece com ZERO chamadas — a fonte
     * é o registro, jamais a reexecução.
     */
    public function test_explain_e_projecao_pura_nao_chama_o_motor(): void
    {
        $spy = $this->spyResolver();
        $this->app->instance(SolicitacaoViabilityResolver::class, $spy);

        $decision = $this->decisionComTrace();

        $explicacao = app(DecisionExplanationService::class)->explain($decision);

        $this->assertSame(0, $spy->calls, 'explain() não pode reexecutar o motor (RN-005).');
        $this->assertNotEmpty($explicacao['por_cnae'], 'A projeção ainda produz a explicação a partir do registro.');
    }

    /**
     * Decisão LEGADA (decision_trace null): explain() degrada honesto — monta a
     * explicação compacta a partir de per_cnae/fundamentacao/rules_versions e
     * MARCA cada passo não snapshotado como "não registrado nesta decisão", sem
     * inventar valores nem lançar.
     */
    public function test_explain_decisao_legada_marca_passos_nao_registrados_sem_inventar(): void
    {
        $decision = ViabilityDecision::factory()->create();
        $this->assertNull($decision->fresh()->decision_trace, 'A decisão legada não tem trace.');

        $explicacao = app(DecisionExplanationService::class)->explain($decision);

        $this->assertTrue($explicacao['legado']);
        $this->assertNotEmpty($explicacao['por_cnae'], 'O legado ainda lista o que existe em per_cnae.');

        $passos = $explicacao['por_cnae'][0]['passos'];

        // A entrada do CNAE é conhecida (per_cnae) — registrada.
        $this->assertTrue($this->passo($passos, 'entrada')['registrado']);

        // Os passos do motor não foram snapshotados: marcados honestamente.
        $risco = $this->passo($passos, 'risco');
        $this->assertFalse($risco['registrado']);
        $this->assertSame('não registrado nesta decisão', $risco['motivo']);
        $this->assertNull($risco['versao_regra'], 'Nada inventado no passo não registrado.');

        $quadro10 = $this->passo($passos, 'louos.quadro10');
        $this->assertFalse($quadro10['registrado']);
        $this->assertNull($quadro10['versao_regra']);
    }

    /**
     * O DecisionExplanationResource resolve o payload da projeção no shape de
     * apresentação consumido pela UI (12-10): por_cnae[].passos, fundamentacao,
     * rules_versions, legado e desfecho.
     */
    public function test_resource_resolve_expoe_por_cnae_passos_e_flag_legado(): void
    {
        $decision = $this->decisionComTrace();
        $payload = app(DecisionExplanationService::class)->explain($decision);

        $resolved = (new DecisionExplanationResource($payload))->resolve();

        $this->assertArrayHasKey('por_cnae', $resolved);
        $this->assertArrayHasKey('passos', $resolved['por_cnae'][0]);
        $this->assertArrayHasKey('fundamentacao', $resolved);
        $this->assertArrayHasKey('rules_versions', $resolved);
        $this->assertArrayHasKey('desfecho', $resolved);
        $this->assertFalse($resolved['legado']);
    }

    /**
     * Decisão expressa de 2 CNAEs com decision_trace REAL montado pelo
     * DecisionTraceBuilder (a mesma fonte da 12-02) — garante que a projeção
     * consome o shape de verdade, não um literal divergente.
     */
    private function decisionComTrace(): ViabilityDecision
    {
        $builder = new DecisionTraceBuilder;

        $trace = [
            $builder->cnaeExpresso(
                $this->consultaArrayStub('8888881', '8888-8/81'),
                ['cnae' => '8888881', 'cnae_formatado' => '8888-8/81', 'is_primary' => true, 'ponto' => ['lat' => -12.9714, 'lng' => -38.5014]],
            ),
            $builder->cnaeExpresso(
                $this->consultaArrayStub('8888882', '8888-8/82'),
                ['cnae' => '8888882', 'cnae_formatado' => '8888-8/82', 'is_primary' => false, 'ponto' => ['lat' => -12.9714, 'lng' => -38.5014]],
            ),
        ];

        return ViabilityDecision::factory()->create(['decision_trace' => $trace]);
    }

    /**
     * consulta_array (ConsultaViabilidadeResult::toArray) permitido na zona:
     * Quadro 7/10 identificados, 11/11A não encontrados (com motivo), risco
     * municipal baixo (expresso) — o shape que o builder consome.
     *
     * @return array<string, mixed>
     */
    private function consultaArrayStub(string $cnae, string $formatado): array
    {
        return [
            'entrada' => ['tipo' => 'ponto_conhecido', 'cnae' => $cnae, 'cnae_formatado' => $formatado, 'area' => 120.0],
            'enquadramento' => [
                'quadro7' => ['status' => 'identificado', 'grupo' => 'nR1', 'subgrupo' => 'nR1-01', 'motivo' => null, 'versao_regra' => 'lei-9148-2016-quadro7'],
                'quadro10' => ['status' => 'identificado', 'permissao' => 'permitido', 'condicionante_ref' => null, 'motivo' => null, 'versao_regra' => 'lei-9148-2016-quadro10'],
                'quadro11' => ['status' => 'nao_encontrado', 'condicoes' => [], 'motivo' => 'sem condicionante de uso aplicável', 'versao_regra' => 'lei-9148-2016-quadro11'],
                'quadro11a' => ['status' => 'nao_encontrado', 'condicoes' => [], 'motivo' => 'sem porte especial aplicável', 'versao_regra' => 'lei-9148-2016-quadro11a'],
            ],
            'risco' => [
                'municipal' => ['status' => 'classificado', 'nivel' => 'baixo_a', 'nivel_label' => 'Baixo A', 'versao_regras' => 'decreto-32636-2020'],
                'sanitario' => ['status' => 'classificado', 'nivel_final' => null, 'versao_regras' => null],
                'encaminhamento' => ['fluxo' => 'expresso', 'dimensao_decisiva' => 'municipal', 'motivo' => 'risco municipal baixo — elegível ao fluxo expresso', 'gatilhos_acionados' => []],
                'versoes' => ['municipal' => 'decreto-32636-2020', 'sanitario' => null],
            ],
            'veredito_locacional' => ['resultado' => 'permitido', 'label' => 'Permitido', 'motivo' => 'uso permitido na zona'],
            'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 10', 'Decreto nº 32.636/2020 — classificação de risco'],
        ];
    }

    /**
     * Resolver SPY: conta as chamadas e lança se acionado — explain() não pode
     * tocá-lo (RN-005). Sem PostGIS, jamais reexecuta motor.
     */
    private function spyResolver(): SolicitacaoViabilityResolver
    {
        return new class extends SolicitacaoViabilityResolver
        {
            public int $calls = 0;

            public function __construct() {}

            public function resolve(ViabilityRequest $request): ResolvedViability
            {
                $this->calls++;

                throw new \RuntimeException('RN-005 violado: explain() chamou o motor.');
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
        $this->assertNotFalse($pos, "Passo ausente na explicação: {$passo}");

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

        $this->fail("Passo ausente na explicação: {$id}");
    }
}
