<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tramitação "Conclusão de Análise": o analista escolhe Deferido, Indeferido,
 * Desativado, Cassado ou Revogado. Deferido e indeferido nascem da ficha
 * finalizada. Os outros três encerram sem TVL, inclusive a partir de um
 * processo já deferido.
 */
class ConclusaoAnaliseTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function processoComFicha(array $perCnae): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'per_cnae' => $perCnae,
        ]);

        return $request->fresh();
    }

    public function test_deferido_conclui_quando_a_ficha_esta_toda_deferida(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_escolhido' => 'deferida'],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => 'deferido',
                'parecer' => 'Atividade compatível com a zona.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->status);
        $this->assertSame(AnalysisStatus::AnaliseConcluida, $processo->analysis_status);
        $this->assertSame(DecisionOutcome::Deferida, $processo->decision->outcome);
        $this->assertNotNull($processo->decision->tvl_product_number);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_deferido_recusa_quando_ha_atividade_indeferida(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_escolhido' => 'deferida'],
            ['cnae' => '5611201', 'status_escolhido' => 'indeferida'],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => 'deferido',
            ])
            ->assertSessionHasErrors('conclusao');

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_indeferido_explicito_sem_cnae_indeferida_e_bloqueado(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_escolhido' => 'deferida'],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => 'indeferido',
                'parecer' => 'Parecer qualquer.',
            ])
            ->assertSessionHasErrors('conclusao');

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_cassado_revogado_e_desativado_encerram_sem_tvl(): void
    {
        foreach (['cassado', 'revogado', 'desativado'] as $indice => $resultado) {
            $processo = ViabilityRequest::factory()->protocoled()->create([
                'protocol_number' => 'VIA-2026-'.str_pad((string) ($indice + 1), 6, '0', STR_PAD_LEFT),
            ]);
            $processo->forceFill([
                'status' => ViabilityRequestStatus::EmAnalise,
                'analysis_status' => AnalysisStatus::EmAnalise,
            ])->save();

            $this->actingAs($this->analista(), 'gestao')
                ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                    'resultado' => $resultado,
                    'parecer' => 'Encerramento registrado na conclusão.',
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $processo->refresh();
            $this->assertSame($resultado, $processo->status->value);
            $this->assertSame(AnalysisStatus::AnaliseConcluida, $processo->analysis_status);
            $this->assertSame($resultado, $processo->decision->outcome->value);
            $this->assertNull($processo->decision->tvl_product_number);
        }

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'conclusao-analise',
            'result' => 'cassado',
        ]);
    }

    public function test_processo_deferido_pode_ser_cassado_revogado_ou_desativado(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create();
        $processo->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $processo->id,
            'tvl_product_number' => 'TVL-2026-880001',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => 'cassado',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(ViabilityRequestStatus::Cassado, $processo->status);
        $this->assertSame(1, ViabilityDecision::query()->where('viability_request_id', $processo->id)->count());
        $this->assertSame('TVL-2026-880001', $processo->decision->tvl_product_number);
    }

    public function test_processo_deferido_nao_pode_ser_indeferido_na_conclusao(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create();
        $processo->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => 'indeferido',
            ])
            ->assertSessionHasErrors('conclusao');

        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);
    }

    public function test_resultado_obrigatorio(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create();
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [])
            ->assertSessionHasErrors('resultado');
    }
}
