<?php

namespace Tests\Feature\Auditoria;

use App\Enums\ViabilityRequestStatus;
use App\Http\Resources\DecisionExplanationResource;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Auditoria\DecisionExplanationService;
use App\Services\Auditoria\DecisionTraceBuilder;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
    use LazilyRefreshDatabase;

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

        $this->assertSame('4712100', $explicacao['por_cnae'][0]['cnae']);
        $this->assertSame('0111301', $explicacao['por_cnae'][1]['cnae']);
        $this->assertTrue($explicacao['por_cnae'][0]['is_primary']);
        $this->assertSame('motor', $explicacao['por_cnae'][0]['origem']);

        // Ordem canônica dos passos do CNAE principal.
        $ids = $this->idsDosPassos($explicacao['por_cnae'][0]['passos']);
        $this->assertLessThan($this->posicao($ids, 'risco'), $this->posicao($ids, 'entrada'));
        $this->assertLessThan($this->posicao($ids, 'louos.enquadramento'), $this->posicao($ids, 'risco'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro10'), $this->posicao($ids, 'louos.enquadramento'));
        $this->assertLessThan($this->posicao($ids, 'louos.quadro11a'), $this->posicao($ids, 'louos.quadro10'));
        $this->assertLessThan($this->posicao($ids, 'consolidacao'), $this->posicao($ids, 'louos.quadro11a'));
        $this->assertNotContains('louos.quadro11', $ids);
        $this->assertLessThan($this->posicao($ids, 'desfecho'), $this->posicao($ids, 'consolidacao'));

        // Passos LOUOS refletem versao_regra/motivo do trace, jamais inventados.
        $quadro7 = $this->passo($explicacao['por_cnae'][0]['passos'], 'louos.enquadramento');
        $this->assertTrue($quadro7['registrado']);
        $this->assertStringContainsString('nR1', (string) $quadro7['motivo']);
        $this->assertStringNotContainsStringIgnoringCase('quadro 7', (string) $quadro7['motivo']);

        $quadro10 = $this->passo($explicacao['por_cnae'][0]['passos'], 'louos.quadro10');
        $this->assertTrue($quadro10['registrado']);
        $this->assertSame('lei-9148-2016-quadro10', $quadro10['versao_regra']);
        $this->assertStringContainsString('Quadro 10', (string) $quadro10['motivo']);

        $quadro11a = $this->passo($explicacao['por_cnae'][0]['passos'], 'louos.quadro11a');
        $this->assertSame('sem porte especial aplicável', $quadro11a['motivo']);
        $this->assertSame('lei-9148-2016-quadro11a', $quadro11a['versao_regra']);

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

        // Os passos do motor não foram snapshotados: marcados honestamente,
        // com o papel de cada quadro/risco — sem inventar grupo, zona ou nível.
        $risco = $this->passo($passos, 'risco');
        $this->assertFalse($risco['registrado']);
        $this->assertStringContainsString('risco', mb_strtolower((string) $risco['motivo']));
        $this->assertStringContainsString('Decreto', (string) $risco['motivo']);
        $this->assertNull($risco['versao_regra'], 'Nada inventado no passo não registrado.');

        $quadro10 = $this->passo($passos, 'louos.quadro10');
        $this->assertFalse($quadro10['registrado']);
        $this->assertNull($quadro10['versao_regra']);

        $consolidacao = $this->passo($passos, 'consolidacao');
        $this->assertTrue($consolidacao['registrado']);
        $this->assertSame('permitido', $consolidacao['resultado_parcial']['resultado']);
        $this->assertStringContainsString('enquadramento', mb_strtolower((string) $consolidacao['motivo']));
        $this->assertStringContainsString('Quadro 10', (string) $consolidacao['motivo']);
        $this->assertStringContainsString('classifica', mb_strtolower((string) $consolidacao['motivo']));
        $this->assertStringNotContainsString('nR1', (string) $consolidacao['motivo'], 'Não inventa grupo no legado.');

        $quadro7 = $this->passo($passos, 'louos.enquadramento');
        $this->assertFalse($quadro7['registrado']);
        $this->assertStringContainsString('classifica', mb_strtolower((string) $quadro7['motivo']));
        $this->assertNull($quadro7['versao_regra']);

        $quadro11a = $this->passo($passos, 'louos.quadro11a');
        $this->assertFalse($quadro11a['registrado']);
        $this->assertStringContainsString('11-A', (string) $quadro11a['motivo']);
        $this->assertNotContains('louos.quadro11', $this->idsDosPassos($passos));

        $desfecho = $this->passo($passos, 'desfecho');
        $this->assertTrue($desfecho['registrado']);
        $this->assertStringContainsString('Permitido', (string) $desfecho['motivo']);
        $this->assertStringNotContainsString('nR1', (string) $desfecho['motivo']);
    }

    /**
     * Golden de byte-identidade (Fase 4, Task 4): os 14 textos da explicação
     * legada (7 títulos + 7 motivos) são assertados como strings EXATAS — a
     * rede que prova que a migração das constantes para o catálogo
     * decision_texts não mudou uma vírgula do que é emitido.
     */
    public function test_textos_da_explicacao_legada_sao_byte_identicos(): void
    {
        // Cenário A: veredito permitido cuja fundamentação cita só o Quadro 7.
        $decision = ViabilityDecision::factory()->create();

        $passos = app(DecisionExplanationService::class)->explain($decision)['por_cnae'][0]['passos'];

        $entrada = $this->passo($passos, 'entrada');
        $this->assertSame('Entrada', $entrada['titulo']);

        $risco = $this->passo($passos, 'risco');
        $this->assertSame('Classificação de risco', $risco['titulo']);
        $this->assertSame(
            'O Decreto nº 41.758/2026 classifica o risco do CNAE e define se o processo vai ao fluxo expresso ou à análise técnica. O nível e o encaminhamento desta decisão não foram gravados.',
            $risco['motivo'],
        );

        $quadro7 = $this->passo($passos, 'louos.enquadramento');
        $this->assertSame('LOUOS — Enquadramento de uso', $quadro7['titulo']);
        $this->assertSame(
            'O enquadramento classifica o uso (CNAE × perguntas × área → grupo). O grupo desta decisão não foi gravado.',
            $quadro7['motivo'],
        );

        $quadro10 = $this->passo($passos, 'louos.quadro10');
        $this->assertSame('LOUOS — Quadro 10 (permissão na zona)', $quadro10['titulo']);
        $this->assertSame(
            'O Quadro 10 permite ou proíbe o grupo na zona. A permissão e a zona desta decisão não foram gravadas.',
            $quadro10['motivo'],
        );

        $quadro11a = $this->passo($passos, 'louos.quadro11a');
        $this->assertSame('LOUOS — Quadro 11-A (condições pela via)', $quadro11a['titulo']);
        $this->assertSame(
            'O Quadro 11-A condiciona a instalação pela via (classe viária × grupo). Não permite nem proíbe o uso. As condições desta decisão não foram gravadas.',
            $quadro11a['motivo'],
        );

        $consolidacao = $this->passo($passos, 'consolidacao');
        $this->assertSame('Consolidação do veredito locacional', $consolidacao['titulo']);
        $this->assertSame(
            'O registro cita o enquadramento da LOUOS como fundamento do veredito permitido. O enquadramento só classifica o uso. Quem permite ou proíbe na zona é o Quadro 10. Grupo e zona não foram gravados nesta decisão.',
            $consolidacao['motivo'],
        );

        $desfecho = $this->passo($passos, 'desfecho');
        $this->assertSame('Desfecho', $desfecho['titulo']);

        // Cenário B: veredito permitido cuja fundamentação cita o Quadro 10.
        $decisionB = ViabilityDecision::factory()->create([
            'viability_request_id' => ViabilityRequest::factory()->protocoled()->create([
                'protocol_number' => 'VIA-'.now()->year.'-000002',
            ]),
            'tvl_product_number' => 'TVL-'.now()->year.'-000002',
            'per_cnae' => [[
                'cnae' => '4712100',
                'cnae_formatado' => '4712-1/00',
                'is_primary' => true,
                'tendencia' => 'permitido',
                'tendencia_label' => 'Permitido',
                'fluxo' => 'expresso',
                'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 10'],
            ]],
        ]);

        $passosB = app(DecisionExplanationService::class)->explain($decisionB)['por_cnae'][0]['passos'];

        $this->assertSame(
            'O registro cita o Quadro 10 da LOUOS (permissão do grupo na zona). Os detalhes (grupo, faixa e zona) não foram gravados nesta decisão.',
            $this->passo($passosB, 'consolidacao')['motivo'],
        );

        // Cenário C: CNAE sem veredito gravado — consolidação e desfecho
        // marcados com o motivo genérico de passo não registrado.
        $decisionC = ViabilityDecision::factory()->create([
            'viability_request_id' => ViabilityRequest::factory()->protocoled()->create([
                'protocol_number' => 'VIA-'.now()->year.'-000003',
            ]),
            'tvl_product_number' => 'TVL-'.now()->year.'-000003',
            'per_cnae' => [[
                'cnae' => '4712100',
                'cnae_formatado' => '4712-1/00',
                'is_primary' => true,
                'fluxo' => 'expresso',
            ]],
        ]);

        $passosC = app(DecisionExplanationService::class)->explain($decisionC)['por_cnae'][0]['passos'];

        $this->assertSame('não registrado nesta decisão', $this->passo($passosC, 'consolidacao')['motivo']);
        $this->assertSame('não registrado nesta decisão', $this->passo($passosC, 'desfecho')['motivo']);
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
     * Task 2 — o detalhe do resultado expresso passa a enviar a prop ADITIVA
     * 'explicacao' (projeção do trace), sob a permissão consultar-solicitacoes
     * que já existe (sem rota nem permissão nova), sem mexer nas demais props.
     */
    public function test_show_resultado_expresso_envia_prop_explicacao_projetada(): void
    {
        $request = $this->requestDeferida(comTrace: true);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/resultados-expresso/{$request->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/resultados-expresso/show')
                ->has('decisao')
                ->has('transmissao')
                ->where('explicacao.legado', false)
                ->has('explicacao.por_cnae', 2)
                ->has('explicacao.por_cnae.0.passos'));
    }

    /**
     * Decisão LEGADA no resultado expresso: a prop 'explicacao' está presente e
     * marcada como legada (degradação honesta) — nunca ausente nem inventada.
     */
    public function test_show_resultado_expresso_marca_explicacao_legada(): void
    {
        $request = $this->requestDeferida(comTrace: false);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/resultados-expresso/{$request->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('explicacao.legado', true)
                ->has('explicacao.por_cnae'));
    }

    /**
     * A explicação também aparece no detalhe do processo (análise técnica)
     * quando há decisão: prop ADITIVA 'explicacao', preservando o gate
     * consultar-solicitacoes, a auditoria e as demais props.
     */
    public function test_show_processo_envia_prop_explicacao_quando_ha_decisao(): void
    {
        $processo = $this->processoEmAnalise(comDecisao: true);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/show')
                ->has('processo')
                ->where('explicacao.legado', false)
                ->has('explicacao.por_cnae', 2));
    }

    /**
     * Processo SEM decisão (ainda em análise, sem desfecho): a prop 'explicacao'
     * é null — nunca uma explicação inventada (anti-fachada).
     */
    public function test_show_processo_sem_decisao_nao_envia_explicacao(): void
    {
        $processo = $this->processoEmAnalise(comDecisao: false);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/show')
                ->where('explicacao', null));
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Solicitação decidida (deferida) com decisão real; com ou sem decision_trace
     * (legado) conforme o caso de teste.
     */
    private function requestDeferida(bool $comTrace): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'status' => ViabilityRequestStatus::Deferida,
        ]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'decision_trace' => $comTrace ? $this->traceStub() : null,
        ]);

        return $request;
    }

    /**
     * Processo em análise; com decisão (trace) anexada ou sem decisão alguma.
     */
    private function processoEmAnalise(bool $comDecisao): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);

        if ($comDecisao) {
            ViabilityDecision::factory()->create([
                'viability_request_id' => $request->id,
                'decision_trace' => $this->traceStub(),
            ]);
        }

        return $request;
    }

    /**
     * Decisão (com seu request) cujo decision_trace REAL de 2 CNAEs foi montado
     * pelo DecisionTraceBuilder (a mesma fonte da 12-02) — garante que a projeção
     * consome o shape de verdade, não um literal divergente.
     */
    private function decisionComTrace(): ViabilityDecision
    {
        return ViabilityDecision::factory()->create(['decision_trace' => $this->traceStub()]);
    }

    /**
     * decision_trace REAL de 2 CNAEs (principal + complementar) montado pelo
     * DecisionTraceBuilder — reutilizado pelos testes de projeção e de exposição.
     *
     * @return list<array<string, mixed>>
     */
    private function traceStub(): array
    {
        $builder = new DecisionTraceBuilder;

        return [
            $builder->cnaeExpresso(
                $this->consultaArrayStub('4712100', '8888-8/81'),
                ['cnae' => '4712100', 'cnae_formatado' => '8888-8/81', 'is_primary' => true, 'ponto' => ['lat' => -12.9714, 'lng' => -38.5014]],
            ),
            $builder->cnaeExpresso(
                $this->consultaArrayStub('0111301', '8888-8/82'),
                ['cnae' => '0111301', 'cnae_formatado' => '8888-8/82', 'is_primary' => false, 'ponto' => ['lat' => -12.9714, 'lng' => -38.5014]],
            ),
        ];
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
                'enquadramento' => ['status' => 'identificado', 'grupo' => 'nR1', 'subgrupo' => 'nR1-01', 'motivo' => 'O CNAE 8888-8/81 com área 120 m² enquadra-se no grupo nR1 (nR1-01) da LOUOS (07.01.05).', 'versao_regra' => 'planilha-20-08-26'],
                'quadro10' => ['status' => 'identificado', 'permissao' => 'permitido', 'condicionante_ref' => null, 'motivo' => 'O grupo nR1 é permitido na zona ZR-1 segundo o Quadro 10 da LOUOS.', 'versao_regra' => 'lei-9148-2016-quadro10'],
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
