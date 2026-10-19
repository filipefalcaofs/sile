<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * O status de tramitação passa a "Em análise" sozinho quando o analista começa
 * a preencher a ficha, sem alteração manual.
 */
class FichaIniciaEmAnaliseTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function processoCom(AnalysisStatus $status): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        ]);
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => $status,
        ])->save();
        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        return $processo->fresh();
    }

    private function salvarFicha(ViabilityRequest $processo): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", ['parecer' => 'Começando a análise.'])
            ->assertOk();

        return $analista;
    }

    public function test_salvar_a_ficha_leva_analisar_para_em_analise(): void
    {
        $processo = $this->processoCom(AnalysisStatus::Analisar);

        $analista = $this->salvarFicha($processo);

        $this->assertSame(AnalysisStatus::EmAnalise, $processo->fresh()->analysis_status);
        $this->assertTrue(
            $processo->analysisStatusTransitions()
                ->where('to_status', AnalysisStatus::EmAnalise->value)
                ->where('actor_user_id', $analista->id)
                ->exists(),
        );
    }

    public function test_depois_da_vistoria_e_do_convite_a_ficha_tambem_retoma_a_analise(): void
    {
        foreach ([AnalysisStatus::Vistoriado, AnalysisStatus::ConviteRespondido, AnalysisStatus::ConviteCancelado] as $status) {
            $processo = $this->processoCom($status);

            $this->salvarFicha($processo);

            $this->assertSame(AnalysisStatus::EmAnalise, $processo->fresh()->analysis_status, $status->value);
        }
    }

    public function test_outros_estados_nao_mudam_ao_salvar_a_ficha(): void
    {
        foreach ([AnalysisStatus::ParaConclusao, AnalysisStatus::EmConvite, AnalysisStatus::Vistoriar, AnalysisStatus::EmAnalise] as $status) {
            $processo = $this->processoCom($status);

            $this->salvarFicha($processo);

            $this->assertSame($status, $processo->fresh()->analysis_status, $status->value);
        }
    }
}
