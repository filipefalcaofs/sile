<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A Conclusão de Análise só vale com, no mínimo, uma ficha de análise salva
 * pelo analista. Com a ficha ainda em rascunho, a conclusão a finaliza (e
 * bloqueia para edição) antes de decidir.
 */
class ConclusaoExigeFichaSalvaTest extends TestCase
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

    private function processoEmAnalise(): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function ficha(ViabilityRequest $processo, array $atributos = []): AnalysisRecord
    {
        return AnalysisRecord::factory()->create(array_merge([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'analyst_user_id' => null,
            'per_cnae' => [['cnae' => '4712100', 'status_escolhido' => 'deferida']],
        ], $atributos));
    }

    private function concluir(ViabilityRequest $processo, string $resultado): TestResponse
    {
        return $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => $resultado,
                'parecer' => 'Parecer da conclusão.',
            ]);
    }

    public function test_sem_ficha_nenhuma_a_conclusao_e_recusada(): void
    {
        $processo = $this->processoEmAnalise();

        $this->concluir($processo, 'desativado')->assertSessionHasErrors('conclusao');

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
    }

    public function test_ficha_so_pre_analisada_pelo_motor_nao_conta_como_salva(): void
    {
        $processo = $this->processoEmAnalise();
        $this->ficha($processo);

        $this->concluir($processo, 'deferido')
            ->assertSessionHasErrors('conclusao');

        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_ficha_salva_em_rascunho_e_finalizada_pela_conclusao(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $processo = $this->processoEmAnalise();
        $ficha = $this->ficha($processo, ['analyst_user_id' => User::factory()->create()->id]);

        $this->concluir($processo, 'deferido')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(AnalysisRecordStatus::Finalizada, $ficha->fresh()->status);
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);
    }

    public function test_processo_ja_deferido_pode_ser_cassado_sem_ficha_nesta_base(): void
    {
        $processo = $this->processoEmAnalise();
        $processo->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();

        $this->concluir($processo, 'cassado')->assertSessionHasNoErrors();
    }

    public function test_autosave_registra_o_analista_que_salvou_a_ficha(): void
    {
        $processo = $this->processoEmAnalise();
        $ficha = $this->ficha($processo);
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", ['parecer' => 'Em elaboração.'])
            ->assertOk();

        $this->assertSame($analista->id, $ficha->fresh()->analyst_user_id);
    }
}
