<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\DecisionOutcome;
use App\Enums\ResultadoViabilidade;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Escritório virtual — deferimento da SEDE (RN-EV-02/03/04): ao deferir uma ficha
 * com "Sede de Escritório Virtual = Sim" e o CNAE gatilho 8211-3/00, a inscrição
 * é travada, o produto marca is_virtual_office_hq + condicionante EV, e a
 * categoria is_virtual_office é derivada. Sem a flag → nada disso.
 */
class DeferirSedeTest extends TestCase
{
    use RefreshDatabase;

    private function fichaSedeDeferivel(bool $flagSede): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => '111.222.333',
        ]);
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();
        $request->cnaes()->attach(Cnae::factory()->create(['code' => '8211-3/00']), ['is_primary' => true]);

        return AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'is_virtual_office_hq' => $flagSede,
            'per_cnae' => [
                ['cnae' => '8211300', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ],
        ]);
    }

    public function test_deferir_com_sede_sim_trava_inscricao_e_grava_flag_condicionante(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $ficha = $this->fichaSedeDeferivel(flagSede: true);

        $result = app(AnaliseTecnicaDecisionService::class)->decide($ficha, User::factory()->create());

        $this->assertSame(DecisionOutcome::Deferida, $result->outcome);
        $decision = $ficha->viabilityRequest->fresh()->decision;
        $this->assertTrue($decision->is_virtual_office_hq);
        $this->assertSame(ResultadoViabilidade::PermitidoComCondicoes->value, $decision->consolidated_result);
        $this->assertContains(
            config('sile.analise.escritorio_virtual.condicionante_sede'),
            $decision->fundamentacao,
        );
        $this->assertTrue(VirtualOfficeInscriptionLock::ativoPara('111.222.333'));
        $this->assertTrue($ficha->viabilityRequest->fresh()->is_virtual_office);
    }

    public function test_deferir_com_sede_nao_nao_trava(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $ficha = $this->fichaSedeDeferivel(flagSede: false);

        app(AnaliseTecnicaDecisionService::class)->decide($ficha, User::factory()->create());

        $decision = $ficha->viabilityRequest->fresh()->decision;
        $this->assertFalse((bool) $decision->is_virtual_office_hq);
        $this->assertFalse(VirtualOfficeInscriptionLock::ativoPara('111.222.333'));
        $this->assertFalse($ficha->viabilityRequest->fresh()->is_virtual_office);
    }
}
