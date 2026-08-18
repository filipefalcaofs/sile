<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\PendenciaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendenciaPrazoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_abrir_usa_prazo_de_48_horas_uteis(): void
    {
        // Sexta 14h: 48h úteis (pulando fds) => terça 14h. Sem feriados na base de teste.
        Carbon::setTestNow(Carbon::parse('2026-07-10 14:00:00'));

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();
        $analista = User::factory()->create();

        $pendency = app(PendenciaService::class)->abrir($request->fresh(), $analista, 'Complementar documento X');

        $this->assertSame('2026-07-14 14:00:00', $pendency->due_at->format('Y-m-d H:i:s'));
    }
}
