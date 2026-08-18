<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use App\Services\Analise\IndeferirPorPrazoConviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class IndeferirPorPrazoConviteTest extends TestCase
{
    use RefreshDatabase;

    private function emPendencia(): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmPendencia])->save();

        return $request->refresh();
    }

    public function test_indefere_processo_e_cria_decisao(): void
    {
        $request = $this->emPendencia();
        $pendency = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create([
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->subHour(),
        ]);

        $decision = app(IndeferirPorPrazoConviteService::class)->indeferir($request, $pendency);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->fresh()->status);
        $this->assertSame($request->id, $decision->viability_request_id);
        $this->assertNull($decision->decided_by_user_id);
        $this->assertSame('indeferida', $decision->outcome->value);
        $this->assertSame('convite_expirado', $decision->consolidated_result);
    }

    public function test_exige_status_em_pendencia(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();
        $pendency = AnalysisPendency::factory()->for($request->fresh(), 'viabilityRequest')->create();

        $this->expectException(InvalidArgumentException::class);
        app(IndeferirPorPrazoConviteService::class)->indeferir($request->fresh(), $pendency);
    }
}
