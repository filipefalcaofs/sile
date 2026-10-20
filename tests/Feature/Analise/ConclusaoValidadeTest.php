<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ConclusionOutcome;
use App\Enums\DecisionOutcome;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\ConclusaoAnaliseService;
use App\Services\Analise\ValidadeDeferimento;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tipo do deferimento (Deferido / Provisório / Pré-operacional) e validade
 * (6 meses, 1 ano, 2 anos) gravados na decisão ao concluir a análise.
 */
class ConclusaoValidadeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Event::fake([ResultadoEmitido::class]);
        Carbon::setTestNow('2026-10-20 10:00');
    }

    private function analista(): User
    {
        return User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
    }

    private function processoComFicha(): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'per_cnae' => [['cnae' => '4712100', 'status_escolhido' => 'deferida']],
        ]);

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>  $decisao
     */
    private function processoFinalizado(ViabilityRequestStatus $status, array $decisao = []): ViabilityRequest
    {
        $request = $this->processoComFicha();
        ViabilityDecision::factory()->create($decisao + [
            'viability_request_id' => $request->id,
            'outcome' => $status === ViabilityRequestStatus::Deferida ? DecisionOutcome::Deferida : DecisionOutcome::Indeferida,
        ]);
        $request->forceFill(['status' => $status])->save();

        return $request->fresh();
    }

    private function concluir(ViabilityRequest $request, ConclusionOutcome $resultado, ?ValidadeDeferimento $validade): ViabilityDecision
    {
        app(ConclusaoAnaliseService::class)->concluir($request, $this->analista(), $resultado, 'Parecer.', confirmarDivergencia: true, validade: $validade);

        return $request->fresh()->decision;
    }

    public function test_deferido_provisorio_grava_tipo_e_validade_de_seis_meses(): void
    {
        $decisao = $this->concluir($this->processoComFicha(), ConclusionOutcome::Deferido, ValidadeDeferimento::de('provisoria', 6));

        $this->assertSame(TvlKind::Provisoria, $decisao->tvl_kind);
        $this->assertSame('2027-04-20', $decisao->tvl_valid_until->toDateString());
    }

    public function test_deferido_pre_operacional_dois_anos(): void
    {
        $decisao = $this->concluir($this->processoComFicha(), ConclusionOutcome::Deferido, ValidadeDeferimento::de('pre_operacional', 24));

        $this->assertSame(TvlKind::PreOperacional, $decisao->tvl_kind);
        $this->assertSame('2028-10-20', $decisao->tvl_valid_until->toDateString());
    }

    public function test_deferido_definitivo_nao_tem_validade(): void
    {
        $decisao = $this->concluir($this->processoComFicha(), ConclusionOutcome::Deferido, ValidadeDeferimento::de('definitiva', 12));

        $this->assertSame(TvlKind::Definitiva, $decisao->tvl_kind);
        $this->assertNull($decisao->tvl_valid_until);
    }

    public function test_revisao_de_indeferido_para_deferido_grava_validade(): void
    {
        $processo = $this->processoFinalizado(ViabilityRequestStatus::Indeferida);

        $decisao = $this->concluir($processo, ConclusionOutcome::Deferido, ValidadeDeferimento::de('provisoria', 12));

        $this->assertSame(TvlKind::Provisoria, $decisao->tvl_kind);
        $this->assertSame('2027-10-20', $decisao->tvl_valid_until->toDateString());
    }

    public function test_revisao_de_deferido_para_indeferido_limpa_tipo_e_validade(): void
    {
        $processo = $this->processoFinalizado(ViabilityRequestStatus::Deferida, [
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_valid_until' => '2027-01-01',
        ]);

        $decisao = $this->concluir($processo, ConclusionOutcome::Indeferido, null);

        $this->assertNull($decisao->tvl_kind);
        $this->assertNull($decisao->tvl_valid_until);
    }

    public function test_processo_ja_deferido_atualiza_tipo_e_validade_sem_novo_tvl(): void
    {
        $processo = $this->processoFinalizado(ViabilityRequestStatus::Deferida, ['tvl_product_number' => 'TVL-1']);

        $decisao = $this->concluir($processo, ConclusionOutcome::Deferido, ValidadeDeferimento::de('pre_operacional', 24));

        $this->assertSame(TvlKind::PreOperacional, $decisao->tvl_kind);
        $this->assertSame('2028-10-20', $decisao->tvl_valid_until->toDateString());
        $this->assertSame('TVL-1', $decisao->tvl_product_number);
        $this->assertSame(1, ViabilityDecision::query()->count());
        $this->assertTrue(Activity::query()->where('log_name', 'analise')->where('event', 'validade-deferimento')->exists());
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function postarConclusao(ViabilityRequest $processo, array $dados): TestResponse
    {
        return $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", $dados + ['confirmar_divergencia' => '1']);
    }

    public function test_deferir_sem_tipo_e_recusado(): void
    {
        $this->postarConclusao($this->processoComFicha(), ['resultado' => 'deferido'])
            ->assertSessionHasErrors('tipo');
    }

    public function test_provisorio_sem_validade_e_recusado(): void
    {
        $this->postarConclusao($this->processoComFicha(), ['resultado' => 'deferido', 'tipo' => 'provisoria'])
            ->assertSessionHasErrors('validade_meses');
    }

    public function test_validade_fora_das_opcoes_e_recusada(): void
    {
        $this->postarConclusao($this->processoComFicha(), ['resultado' => 'deferido', 'tipo' => 'provisoria', 'validade_meses' => 18])
            ->assertSessionHasErrors('validade_meses');
    }

    public function test_endpoint_grava_pre_operacional_um_ano(): void
    {
        $processo = $this->processoComFicha();

        $this->postarConclusao($processo, ['resultado' => 'deferido', 'tipo' => 'pre_operacional', 'validade_meses' => 12])
            ->assertSessionHasNoErrors();

        $decisao = $processo->fresh()->decision;
        $this->assertSame(TvlKind::PreOperacional, $decisao->tvl_kind);
        $this->assertSame('2027-10-20', $decisao->tvl_valid_until->toDateString());
    }

    public function test_indeferir_ignora_tipo_enviado(): void
    {
        $processo = $this->processoComFicha();

        $this->postarConclusao($processo, ['resultado' => 'indeferido', 'tipo' => 'provisoria', 'validade_meses' => 6])
            ->assertSessionHasNoErrors();

        $this->assertNull($processo->fresh()->decision->tvl_kind);
    }

    public function test_renovacao_nao_exige_tipo_na_conclusao(): void
    {
        $origem = ViabilityRequest::factory()->create();
        $origem->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $origem->id,
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_valid_until' => '2026-12-31',
        ]);
        $renovacao = ViabilityRequest::factory()->protocoled()->create(['renews_viability_request_id' => $origem->id]);
        $renovacao->forceFill(['status' => ViabilityRequestStatus::EmAnalise, 'analysis_status' => AnalysisStatus::EmAnalise])->save();
        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $renovacao->id,
            'revision' => 1,
            'per_cnae' => [['cnae' => '4712100', 'status_escolhido' => 'indeferida']],
        ]);

        $this->postarConclusao($renovacao, ['resultado' => 'deferido'])
            ->assertSessionDoesntHaveErrors(['tipo', 'validade_meses']);
    }
}
