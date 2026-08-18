<?php

namespace Tests\Feature\Risco;

use App\Enums\TipoGatilho;
use App\Models\RiskTrigger;
use Database\Seeders\RiskTriggerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gatilhos CNAE (semi-expresso) como TABELA parametrizada (HU-049/HU-051): o
 * seeder semeia os 3 gatilhos conhecidos, todos ativos por padrão e ativáveis
 * por interface. Cada gatilho acionado derruba o encaminhamento para análise
 * técnica com motivo auditável — dado, nunca hardcode.
 */
class RiskTriggerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_os_tres_gatilhos_conhecidos(): void
    {
        $this->seed(RiskTriggerSeeder::class);

        $this->assertSame(3, RiskTrigger::query()->count());

        $this->assertEqualsCanonicalizing(
            ['enquadramento_ausente', 'zeis_especial', 'dados_do_processo'],
            RiskTrigger::query()->pluck('codigo')->map(fn (TipoGatilho $codigo) => $codigo->value)->all(),
        );

        // Todos nascem ativos (default da coluna) e são da categoria semi-expresso.
        $this->assertSame(3, RiskTrigger::query()->where('ativo', true)->count());
        $this->assertSame(3, RiskTrigger::query()->where('categoria', 'semi_expresso')->count());

        // Título e motivo auditável preenchidos em pt-BR (sem fachada).
        $zeis = RiskTrigger::query()->where('codigo', TipoGatilho::ZeisEspecial->value)->first();
        $this->assertNotNull($zeis);
        $this->assertNotEmpty($zeis->titulo);
        $this->assertNotEmpty($zeis->motivo);
        $this->assertInstanceOf(TipoGatilho::class, $zeis->codigo);
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(RiskTriggerSeeder::class);
        $this->seed(RiskTriggerSeeder::class);

        $this->assertSame(3, RiskTrigger::query()->count());
    }

    public function test_scope_ativos_filtra_apenas_os_gatilhos_ativos(): void
    {
        $this->seed(RiskTriggerSeeder::class);

        RiskTrigger::query()
            ->where('codigo', TipoGatilho::ZeisEspecial->value)
            ->update(['ativo' => false]);

        $this->assertSame(2, RiskTrigger::ativos()->count());
    }

    public function test_reseed_preserva_estado_ativo_administrado(): void
    {
        $this->seed(RiskTriggerSeeder::class);

        RiskTrigger::query()
            ->where('codigo', TipoGatilho::ZeisEspecial->value)
            ->update(['ativo' => false]);

        $this->seed(RiskTriggerSeeder::class);

        $zeis = RiskTrigger::query()->where('codigo', TipoGatilho::ZeisEspecial->value)->first();
        $this->assertFalse($zeis->ativo);
    }
}
