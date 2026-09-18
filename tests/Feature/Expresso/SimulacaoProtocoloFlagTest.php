<?php

namespace Tests\Feature\Expresso;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Garante que o bypass de homologação do motor de risco (simulação REGIN) só
 * opera com a flag features.simulacao_protocolo LIGADA. Com a flag off (default),
 * processo nascido do simulador com veredito pendente segue o fluxo normal
 * (pendente → análise técnica, sem TVL).
 */
class SimulacaoProtocoloFlagTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            RiskTriggerSeeder::class,
            PropertyTypeSeeder::class,
        ]);

        $this->actingAs(
            User::factory()->administrador()->withAcceptedLgpdTerm()->create(),
            'gestao',
        );
    }

    public function test_flag_desligada_por_padrao_simulacao_pendente_vai_para_analise_sem_tvl(): void
    {
        // Sem config() da flag — o default commitado é off.
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('33072');

        $this->assertNotNull($relatorio['processo_id']);
        $this->assertSame(ViabilityRequestStatus::EmAnalise->value, $relatorio['status']);
        $this->assertNull($relatorio['tvl']);

        $processo = ViabilityRequest::query()->find($relatorio['processo_id']);

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestOrigin::Regin, $processo->origin);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);
        $this->assertNull($processo->decision);
    }

    public function test_flag_ligada_preserva_o_caminho_de_homologacao_com_tvl(): void
    {
        config(['sile.features.simulacao_protocolo' => true]);

        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('33072');

        $this->assertNotNull($relatorio['processo_id']);
        $this->assertMatchesRegularExpression('/^VIA-\d{4}-\d{6}$/', (string) $relatorio['protocol_number']);
        $this->assertSame(ViabilityRequestStatus::Deferida->value, $relatorio['status']);
        $this->assertNotEmpty($relatorio['tvl']);

        $processo = ViabilityRequest::query()->find($relatorio['processo_id']);

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestOrigin::Regin, $processo->origin);
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->status);
        $this->assertSame($relatorio['tvl'], $processo->decision?->tvl_product_number);
    }
}
