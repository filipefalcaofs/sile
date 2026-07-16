<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\PendenciaInvalidaException;
use App\Services\Analise\PendenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendenciaCancelarTest extends TestCase
{
    use RefreshDatabase;

    private function emPendencia(): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmPendencia])->save();

        return $request->refresh();
    }

    public function test_cancelar_registra_parecer_e_reabre_analise(): void
    {
        $request = $this->emPendencia();
        $pendency = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create([
            'status' => AnalysisPendencyStatus::Aberta,
        ]);
        $analista = User::factory()->create();

        app(PendenciaService::class)->cancelar($pendency, $analista, 'Convite desnecessário — dado já consta.');

        $pendency->refresh();
        $this->assertSame(AnalysisPendencyStatus::Cancelada, $pendency->status);
        $this->assertSame('Convite desnecessário — dado já consta.', $pendency->parecer);
        $this->assertSame($analista->id, $pendency->cancelled_by_user_id);
        $this->assertNotNull($pendency->cancelled_at);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
    }

    public function test_nao_cancela_convite_ja_respondido(): void
    {
        $request = $this->emPendencia();
        $pendency = AnalysisPendency::factory()->for($request, 'viabilityRequest')->create([
            'status' => AnalysisPendencyStatus::Respondida,
        ]);

        $this->expectException(PendenciaInvalidaException::class);
        app(PendenciaService::class)->cancelar($pendency, User::factory()->create(), 'motivo');
    }
}
