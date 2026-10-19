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
use App\Services\Analise\AnaliseTecnicaDecisionService;
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

    public function test_decidir_pela_ficha_leva_o_status_para_analise_concluida_com_o_resultado(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_escolhido' => 'indeferida'],
        ]);
        $processo->forceFill(['analysis_status' => AnalysisStatus::EmAnalise])->save();
        $ficha = AnalysisRecord::query()->where('viability_request_id', $processo->id)->sole();

        app(AnaliseTecnicaDecisionService::class)
            ->decide($ficha, $this->analista(), null, 'Atividade não permitida na zona.');

        $processo->refresh();
        $this->assertSame(AnalysisStatus::AnaliseConcluida, $processo->analysis_status);
        $this->assertSame('Análise concluída – Indeferido', $processo->rotuloStatusTramitacao());
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
            ->assertSessionHasErrors('divergencia');

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
            ->assertSessionHasErrors('divergencia');

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_divergencia_confirmada_deferido_com_atividade_indeferida_conclui(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_escolhido' => 'deferida'],
            ['cnae' => '5611201', 'status_escolhido' => 'indeferida'],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => 'deferido',
                'confirmar_divergencia' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);
    }

    public function test_divergencia_confirmada_indeferido_sem_atividade_indeferida_conclui(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_escolhido' => 'deferida'],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => 'indeferido',
                'parecer' => 'Indeferido por decisão do analista.',
                'confirmar_divergencia' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ViabilityRequestStatus::Indeferida, $processo->fresh()->status);
    }

    public function test_processo_finalizado_tambem_pede_confirmacao_da_divergencia(): void
    {
        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_escolhido' => 'deferida'],
            ['cnae' => '5611201', 'status_escolhido' => 'indeferida'],
        ]);
        $processo->forceFill(['status' => ViabilityRequestStatus::Indeferida])->save();
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id]);

        $rota = "/gestao/processos/{$processo->id}/conclusao-analise";

        $this->actingAs($this->analista(), 'gestao')
            ->post($rota, ['resultado' => 'deferido'])
            ->assertSessionHasErrors('divergencia');
        $this->assertSame(ViabilityRequestStatus::Indeferida, $processo->fresh()->status);

        $this->actingAs($this->analista(), 'gestao')
            ->post($rota, ['resultado' => 'deferido', 'confirmar_divergencia' => true])
            ->assertSessionHasNoErrors();
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);
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
            AnalysisRecord::factory()->create([
                'viability_request_id' => $processo->id,
                'revision' => 1,
                'analyst_user_id' => User::factory()->create()->id,
            ]);

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

    public function test_processo_finalizado_aceita_qualquer_outro_resultado(): void
    {
        $casos = [
            [ViabilityRequestStatus::Deferida, 'indeferido', ViabilityRequestStatus::Indeferida],
            [ViabilityRequestStatus::Indeferida, 'deferido', ViabilityRequestStatus::Deferida],
            [ViabilityRequestStatus::Indeferida, 'cassado', ViabilityRequestStatus::Cassado],
            [ViabilityRequestStatus::Revogado, 'indeferido', ViabilityRequestStatus::Indeferida],
            [ViabilityRequestStatus::Cassado, 'deferido', ViabilityRequestStatus::Deferida],
        ];

        foreach ($casos as $indice => [$origem, $resultado, $esperado]) {
            $processo = ViabilityRequest::factory()->protocoled()->create([
                'protocol_number' => sprintf('VIA-2026-9%05d', $indice),
            ]);
            $processo->forceFill(['status' => $origem])->save();
            ViabilityDecision::factory()->create([
                'viability_request_id' => $processo->id,
                'tvl_product_number' => sprintf('TVL-2026-9%05d', $indice),
            ]);

            $this->actingAs($this->analista(), 'gestao')
                ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                    'resultado' => $resultado,
                    'parecer' => 'Revisão da conclusão.',
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $processo->refresh();
            $this->assertSame($esperado, $processo->status);
            $this->assertSame($esperado->value, $processo->decision->outcome->value);
            $this->assertSame(1, ViabilityDecision::query()->where('viability_request_id', $processo->id)->count());
        }
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
