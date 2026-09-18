<?php

namespace Tests\Feature\Analise;

use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AiSuggestion;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A ficha em análise precisa mostrar, no topo, se o especialista sugere deferir
 * ou indeferir. A escolha do analista continua sendo a decisão; a indicação
 * nunca finaliza o processo.
 */
class IndicacaoEspecialistaFichaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function processoComFicha(array $perCnae): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => fake()->unique()->numerify('VIA-'.now()->year.'-######'),
            'protocoled_at' => now(),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => $perCnae,
        ]);

        return $processo;
    }

    public function test_ficha_indica_deferir_quando_o_motor_sugere_deferida(): void
    {
        $processo = $this->processoComFicha([
            [
                'cnae' => '4712100',
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'analise',
                'justificativa' => 'Enquadramento permitido no Quadro 10.',
            ],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('indicacaoEspecialista.desfecho', 'deferida')
                ->where('indicacaoEspecialista.desfecho_label', 'Deferir')
                ->where('indicacaoEspecialista.analista_decide', true)
                ->where('indicacaoEspecialista.fonte', 'motor'));
    }

    public function test_ficha_indica_indeferir_quando_o_motor_sugere_indeferida(): void
    {
        $processo = $this->processoComFicha([
            [
                'cnae' => '4781400',
                'status_sugerido' => 'indeferida',
                'status_escolhido' => 'analise',
                'justificativa' => 'Zona proíbe o grupo de uso.',
            ],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('indicacaoEspecialista.desfecho', 'indeferida')
                ->where('indicacaoEspecialista.desfecho_label', 'Indeferir'));
    }

    public function test_ficha_diz_sem_indicacao_quando_o_motor_nao_fecha_o_desfecho(): void
    {
        $processo = $this->processoComFicha([
            [
                'cnae' => '4781400',
                'status_sugerido' => 'analise',
                'status_escolhido' => 'analise',
                'tendencia_label' => 'Pendente de análise técnica',
                'justificativa' => 'Combinação zona × grupo de uso sem regra no Quadro 10 vigente.',
            ],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('indicacaoEspecialista.desfecho', null)
                ->where('indicacaoEspecialista.desfecho_label', 'Sem indicação de deferir ou indeferir')
                ->where('indicacaoEspecialista.analista_decide', true)
                ->where('indicacaoEspecialista.motivo', fn (mixed $motivo): bool => is_string($motivo)
                    && str_contains($motivo, 'Quadro 10')));
    }

    public function test_agente_especialista_confirma_a_indicacao_do_motor_sem_decidir(): void
    {
        $processo = $this->processoComFicha([
            [
                'cnae' => '4712100',
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'analise',
            ],
        ]);

        AiSuggestion::factory()->create([
            'viability_request_id' => $processo->id,
            'type' => AiSuggestionType::Parecer,
            'status' => AiSuggestionStatus::Sugerida,
            'output' => [
                'minuta' => 'Minuta de apoio.',
                'recomendacao' => 'deferida',
                'fonte' => 'pré-análise do motor',
            ],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('indicacaoEspecialista.desfecho', 'deferida')
                ->where('indicacaoEspecialista.fonte', 'motor_e_ia')
                ->where('indicacaoEspecialista.analista_decide', true));
    }
}
