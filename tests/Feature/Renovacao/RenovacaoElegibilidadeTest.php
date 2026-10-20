<?php

namespace Tests\Feature\Renovacao;

use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Renovacao\RenovacaoElegibilidadeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RenovacaoElegibilidadeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bloqueia_quando_nao_ha_viabilidade_vinculada(): void
    {
        $origem = ViabilityRequest::factory()->create();

        $resultado = app(RenovacaoElegibilidadeService::class)->avaliar($origem);

        $this->assertFalse($resultado->elegivel);
        $this->assertSame('Não há viabilidade vinculada a este CNPJ.', $resultado->motivo);
    }

    public function test_bloqueia_viabilidade_definitiva(): void
    {
        $origem = $this->tvlDeferido(TvlKind::Definitiva);

        $resultado = app(RenovacaoElegibilidadeService::class)->avaliar($origem);

        $this->assertFalse($resultado->elegivel);
        $this->assertSame('Viabilidade definitiva não pode ser renovada.', $resultado->motivo);
    }

    public function test_bloqueia_quando_renovada_ha_menos_de_tres_meses(): void
    {
        $origem = $this->tvlDeferido(TvlKind::Provisoria);
        $renovacao = ViabilityRequest::factory()->create([
            'renews_viability_request_id' => $origem->id,
        ]);
        $renovacao->forceFill([
            'status' => ViabilityRequestStatus::Deferida,
            'protocoled_at' => now()->subMonth(),
        ])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $renovacao->id,
            'tvl_product_number' => null,
            'decided_at' => now()->subMonth(),
        ]);

        $resultado = app(RenovacaoElegibilidadeService::class)->avaliar($origem);

        $this->assertFalse($resultado->elegivel);
        $this->assertSame('Esta viabilidade foi renovada há menos de 3 meses.', $resultado->motivo);
    }

    public function test_bloqueia_quando_ja_ha_renovacao_em_analise(): void
    {
        $origem = $this->tvlDeferido(TvlKind::Provisoria);
        $renovacao = ViabilityRequest::factory()->create([
            'renews_viability_request_id' => $origem->id,
        ]);
        $renovacao->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $resultado = app(RenovacaoElegibilidadeService::class)->avaliar($origem);

        $this->assertFalse($resultado->elegivel);
        $this->assertSame(
            'Para cadastrar novo processo de renovação, aguarde a conclusão da análise ou solicite desistência do processo em análise.',
            $resultado->motivo,
        );
    }

    public function test_tvl_provisorio_sem_renovacao_aberta_e_elegivel(): void
    {
        $origem = $this->tvlDeferido(TvlKind::Provisoria);

        $resultado = app(RenovacaoElegibilidadeService::class)->avaliar($origem);

        $this->assertTrue($resultado->elegivel);
        $this->assertNull($resultado->motivo);
    }

    public function test_pre_operacional_pode_ser_renovado(): void
    {
        $origem = $this->tvlDeferido(TvlKind::PreOperacional);

        $resultado = app(RenovacaoElegibilidadeService::class)->avaliar($origem);

        $this->assertTrue($resultado->elegivel);
        $this->assertNull($resultado->motivo);
    }

    private function tvlDeferido(TvlKind $tipo): ViabilityRequest
    {
        $origem = ViabilityRequest::factory()->create();
        $origem->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $origem->id,
            'tvl_kind' => $tipo,
            'tvl_valid_until' => '2027-09-28',
        ]);

        return $origem->fresh();
    }
}
