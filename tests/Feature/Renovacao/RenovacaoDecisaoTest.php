<?php

namespace Tests\Feature\Renovacao;

use App\Enums\DecisionOutcome;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RenovacaoDecisaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_deferir_renovacao_exige_tipo_e_nova_validade(): void
    {
        $processo = $this->renovacaoEmAnalise();

        $this->expectException(DomainException::class);

        app(AnaliseTecnicaDecisionService::class)->decide(
            $processo->currentAnalysisRecord,
            $this->analista(),
        );
    }

    public function test_deferir_renovacao_mantem_o_numero_e_grava_validade(): void
    {
        $processo = $this->renovacaoEmAnalise();
        $numeroOrigem = $processo->renews->decision->tvl_product_number;

        $resultado = app(AnaliseTecnicaDecisionService::class)->decide(
            $processo->currentAnalysisRecord,
            $this->analista(),
            DecisionOutcome::Deferida,
            null,
            TvlKind::Definitiva,
            '2028-01-15',
            true,
        );

        $this->assertSame(DecisionOutcome::Deferida, $resultado->outcome);
        $this->assertNull($resultado->decision->tvl_product_number);
        $this->assertSame($numeroOrigem, $processo->renews->fresh()->decision->tvl_product_number);
        $this->assertSame(TvlKind::Definitiva, $resultado->decision->tvl_kind);
        $this->assertSame('2028-01-15', $resultado->decision->tvl_valid_until->toDateString());
        $this->assertDatabaseMissing('activity_log', ['event' => 'sefaz-viabilidade']);
    }

    public function test_endpoint_grava_tipo_e_validade_da_renovacao(): void
    {
        $processo = $this->renovacaoEmAnalise();

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/decidir", [
                'tvl_kind' => 'provisoria',
                'tvl_valid_until' => '2028-06-01',
                'condicionantes_atendidos' => '1',
            ])
            ->assertRedirect();

        $decisao = $processo->fresh()->decision;

        $this->assertSame(TvlKind::Provisoria, $decisao->tvl_kind);
        $this->assertSame('2028-06-01', $decisao->tvl_valid_until->toDateString());
    }

    public function test_condicionante_nao_atendido_indefere(): void
    {
        $processo = $this->renovacaoEmAnalise();

        $resultado = app(AnaliseTecnicaDecisionService::class)->decide(
            $processo->currentAnalysisRecord,
            $this->analista(),
            DecisionOutcome::Deferida,
            'Condicionante do TVL provisório não atendido.',
            null,
            null,
            false,
        );

        $this->assertSame(DecisionOutcome::Indeferida, $resultado->outcome);
        $this->assertNull($resultado->decision->tvl_product_number);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function renovacaoEmAnalise(): ViabilityRequest
    {
        $origem = ViabilityRequest::factory()->create();
        $origem->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $origem->id,
            'tvl_product_number' => 'TVL-ORIGEM-'.$origem->id,
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_valid_until' => '2026-12-31',
        ]);

        $renovacao = ViabilityRequest::factory()->protocoled()->create([
            'renews_viability_request_id' => $origem->id,
        ]);
        $renovacao->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $renovacao->id,
            'revision' => 1,
            'per_cnae' => [
                ['cnae' => '4712100', 'status_escolhido' => 'deferida'],
            ],
        ]);

        return $renovacao->fresh();
    }
}
