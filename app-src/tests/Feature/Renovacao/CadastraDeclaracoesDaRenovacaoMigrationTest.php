<?php

namespace Tests\Feature\Renovacao;

use App\Models\ServiceDeclaration;
use App\Models\ViabilityServiceType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CadastraDeclaracoesDaRenovacaoMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function executarMigracao(): void
    {
        $migracao = require base_path('database/migrations/2026_10_29_190000_cadastra_declaracoes_da_renovacao.php');
        $migracao->up();
    }

    public function test_cadastra_as_declaracoes_quando_o_tipo_nao_tem_nenhuma(): void
    {
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);

        $this->executarMigracao();

        $vigentes = ServiceDeclaration::vigentesPara($tipo->id);
        $this->assertCount(2, $vigentes);
        $this->assertTrue($vigentes->every(fn (ServiceDeclaration $declaracao): bool => $declaracao->required));
    }

    public function test_nao_recria_versoes_expiradas_pela_gestao(): void
    {
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);
        ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'version' => 3,
            'valid_from' => now()->subDay(),
            'valid_to' => null,
        ]);

        $this->executarMigracao();

        $this->assertSame(1, ServiceDeclaration::query()->where('service_type_id', $tipo->id)->count());
    }

    public function test_rodar_duas_vezes_nao_duplica(): void
    {
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);

        $this->executarMigracao();
        $this->executarMigracao();

        $this->assertSame(2, ServiceDeclaration::query()->where('service_type_id', $tipo->id)->count());
    }

    public function test_sem_o_tipo_nao_faz_nada(): void
    {
        $this->executarMigracao();

        $this->assertSame(0, ServiceDeclaration::query()->count());
    }
}
