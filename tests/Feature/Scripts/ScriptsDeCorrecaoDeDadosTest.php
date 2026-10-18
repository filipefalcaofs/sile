<?php

namespace Tests\Feature\Scripts;

use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Inspection;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Scripts de correção de dados rodados no pod: simulam por padrão e só gravam
 * com --aplicar, recusando os casos que não se desfazem por script.
 */
class ScriptsDeCorrecaoDeDadosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function reverter(string $numero, bool $aplicar): int
    {
        $app = app();

        ob_start();
        $codigo = include base_path('scripts/reverter-analise-concluida.php');
        ob_end_clean();

        return $codigo;
    }

    /**
     * @param  list<string>  $numeros
     */
    private function devolver(array $numeros, bool $aplicar, bool $removerFicha = false): int
    {
        $app = app();

        ob_start();
        $codigo = include base_path('scripts/devolver-vistoria-para-distribuir.php');
        ob_end_clean();

        return $codigo;
    }

    private function concluida(string $bap, ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(['external_reference' => $bap]);
        $processo->forceFill([
            'status' => $status,
            'analysis_status' => AnalysisStatus::AnaliseConcluida,
        ])->save();

        return $processo->fresh();
    }

    private function emVistoria(string $bap, ?User $vistoriador = null): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(['external_reference' => $bap]);
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::Vistoriar,
            'assigned_user_id' => $vistoriador?->id,
        ])->save();

        return $processo->fresh();
    }

    public function test_reverter_simula_sem_gravar(): void
    {
        $processo = $this->concluida('BAP2601857401');

        $this->assertSame(0, $this->reverter('BAP2601857401', false));

        $this->assertSame(AnalysisStatus::AnaliseConcluida, $processo->fresh()->analysis_status);
    }

    public function test_reverter_aplica_voltando_para_conclusao_e_audita(): void
    {
        $processo = $this->concluida('BAP2601857401');

        $this->assertSame(0, $this->reverter('BAP2601857401', true));

        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->fresh()->analysis_status);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'analise', 'event' => 'reverter-analise-concluida']);
        $this->assertTrue(
            $processo->analysisStatusTransitions()->where('to_status', AnalysisStatus::ParaConclusao->value)->exists(),
        );
    }

    public function test_reverter_recusa_processo_com_decisao_gravada(): void
    {
        $processo = $this->concluida('BAP2601857402', ViabilityRequestStatus::Deferida);
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id]);

        $this->assertSame(1, $this->reverter('BAP2601857402', true));

        $this->assertSame(AnalysisStatus::AnaliseConcluida, $processo->fresh()->analysis_status);
    }

    public function test_reverter_recusa_quando_o_status_nao_e_analise_concluida(): void
    {
        $processo = ViabilityRequest::factory()->create(['external_reference' => 'BAP2601857403']);
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise, 'analysis_status' => AnalysisStatus::EmAnalise])->save();

        $this->assertSame(1, $this->reverter('BAP2601857403', true));
    }

    public function test_reverter_recusa_numero_que_nao_existe(): void
    {
        $this->assertSame(1, $this->reverter('BAP0000000000', true));
    }

    public function test_devolver_simula_sem_gravar(): void
    {
        $lisa = User::factory()->create();
        $processo = $this->emVistoria('BAP2601872682', $lisa);

        $this->assertSame(0, $this->devolver(['BAP2601872682'], false));

        $this->assertSame($lisa->id, $processo->fresh()->assigned_user_id);
    }

    public function test_devolver_ficha_em_preenchimento_exige_o_aviso_explicito(): void
    {
        $lisa = User::factory()->create();
        $processo = $this->emVistoria('BAP2601872682', $lisa);
        Inspection::factory()->create(['viability_request_id' => $processo->id, 'vistoriador_user_id' => $lisa->id]);

        $this->assertSame(1, $this->devolver(['BAP2601872682'], true));

        $this->assertSame($lisa->id, $processo->fresh()->assigned_user_id);
        $this->assertSame(1, Inspection::query()->count());
    }

    public function test_devolver_com_remocao_da_ficha_deixa_o_processo_para_distribuir(): void
    {
        $lisa = User::factory()->create();
        $processo = $this->emVistoria('BAP2601872682', $lisa);
        Inspection::factory()->create(['viability_request_id' => $processo->id, 'vistoriador_user_id' => $lisa->id]);

        $this->assertSame(0, $this->devolver(['BAP2601872682'], true, true));

        $fresh = $processo->fresh();
        $this->assertNull($fresh->assigned_user_id);
        $this->assertSame(AnalysisStatus::Vistoriar, $fresh->analysis_status);
        $this->assertSame(0, Inspection::query()->count());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vistoria', 'event' => 'devolver-para-distribuir']);
    }

    public function test_devolver_recusa_ficha_concluida(): void
    {
        $lisa = User::factory()->create();
        $processo = $this->emVistoria('BAP2601872682', $lisa);
        Inspection::factory()->concluida()->create(['viability_request_id' => $processo->id, 'vistoriador_user_id' => $lisa->id]);

        $this->assertSame(1, $this->devolver(['BAP2601872682'], true, true));

        $this->assertSame(InspectionStatus::Concluida, Inspection::query()->sole()->status);
        $this->assertSame($lisa->id, $processo->fresh()->assigned_user_id);
    }

    public function test_devolver_dois_processos_trata_os_que_podem_e_sinaliza_falha_nos_outros(): void
    {
        $lisa = User::factory()->create();
        $comResponsavel = $this->emVistoria('BAP2601872682', $lisa);
        $jaSolto = $this->emVistoria('BAP2603854348');

        $codigo = $this->devolver(['BAP2601872682', 'BAP2603854348'], true);

        $this->assertNull($comResponsavel->fresh()->assigned_user_id);
        $this->assertNull($jaSolto->fresh()->assigned_user_id);
        $this->assertSame(0, $codigo, 'Processo já sem responsável e sem ficha continua válido (nada a soltar).');
    }
}
