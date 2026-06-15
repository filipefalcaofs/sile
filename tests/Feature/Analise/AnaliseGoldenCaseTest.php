<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden cases da decisão técnica HUMANA (fechamento da Fase 10): casos-âncora
 * entrada→esperado declarados como fixtures JSON e executados pelo SERVIÇO REAL
 * (AnaliseTecnicaDecisionService::decide) sobre a ficha FINALIZADA do analista.
 * Via #[DataProvider], espelhando o padrão golden das Fases 5/6/7/8/9.
 *
 * Trava a regressão de domínio da decisão humana (RN-004): todas as CNAEs
 * deferidas → DEFERE (+TVL); qualquer indeferida → INDEFERE (sem TVL); e o caso
 * que exige o humano — sem zona oficial (engine_available=false), o analista
 * DEFERE com a fundamentação própria da ficha. Se a regra de consolidação, o TVL
 * ou a fundamentação mudarem, o caso âncora falha com o nome do golden.
 */
class AnaliseGoldenCaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Carrega cada fixture entrada→esperado de
     * tests/Fixtures/golden/analise/*.json (o provider roda antes do boot).
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function goldenCases(): array
    {
        $casos = [];

        foreach (glob(dirname(__DIR__, 2).'/Fixtures/golden/analise/*.json') as $arquivo) {
            /** @var array<string, mixed> $caso */
            $caso = json_decode((string) file_get_contents($arquivo), true, 512, JSON_THROW_ON_ERROR);
            $casos[$caso['nome']] = [$caso];
        }

        return $casos;
    }

    /**
     * @param  array<string, mixed>  $caso
     */
    #[DataProvider('goldenCases')]
    public function test_golden_case(array $caso): void
    {
        Event::fake([ResultadoEmitido::class]);

        $analista = User::factory()->create();
        $ficha = $this->fichaFinalizada($caso);

        $result = app(AnaliseTecnicaDecisionService::class)->decide($ficha, $analista);

        $esperado = $caso['esperado'];
        $contexto = "Golden case '{$caso['nome']}'";

        $this->assertSame($esperado['outcome'], $result->outcome->value, "{$contexto}: desfecho divergente.");

        $decision = $ficha->viabilityRequest->fresh()->decision;
        $this->assertNotNull($decision, "{$contexto}: esperava uma decisão gravada.");
        $this->assertSame('analise_tecnica', $decision->flow, "{$contexto}: flow divergente.");
        $this->assertSame($analista->id, $decision->decided_by_user_id, "{$contexto}: decisor divergente.");
        $this->assertSame(
            $esperado['tem_tvl'],
            $decision->tvl_product_number !== null,
            "{$contexto}: presença de TVL divergente.",
        );

        $statusEsperado = $esperado['outcome'] === DecisionOutcome::Deferida->value
            ? ViabilityRequestStatus::Deferida
            : ViabilityRequestStatus::Indeferida;
        $this->assertSame($statusEsperado, $ficha->viabilityRequest->fresh()->status, "{$contexto}: status divergente.");

        if (isset($esperado['fundamentacao_contem'])) {
            $this->assertContains(
                $esperado['fundamentacao_contem'],
                $decision->fundamentacao,
                "{$contexto}: a fundamentação não contém a referência esperada.",
            );
        }

        Event::assertDispatched(ResultadoEmitido::class);
    }

    /**
     * Ficha FINALIZADA (revisão 1) de um processo em análise, com o per_cnae e o
     * engine_available declarados no caso.
     *
     * @param  array<string, mixed>  $caso
     */
    private function fichaFinalizada(array $caso): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        return AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Finalizada,
            'engine_available' => (bool) $caso['engine_available'],
            'engine_snapshot' => $caso['engine_available'] ? ['consolidado' => 'permitido'] : ['consolidado' => 'pendente'],
            'engine_rules_versions' => $caso['engine_available'] ? ['louos' => ['quadro10' => '2026.1']] : null,
            'per_cnae' => $caso['per_cnae'],
        ]);
    }
}
