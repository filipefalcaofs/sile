<?php

namespace Tests\Feature\Renovacao;

use App\Models\Parameter;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RenovacaoLegadaVinculoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_numero_e_snapshot_da_tvl_legada_persistem_e_voltam_como_array(): void
    {
        $snapshot = [
            'numero' => 12345,
            'validade' => '2026-12-31',
            'atividades' => [['cnae' => '4711302', 'principal' => true]],
        ];

        $processo = ViabilityRequest::factory()->create([
            'renews_legacy_tvl_number' => 12345,
            'legacy_tvl_snapshot' => $snapshot,
        ]);

        $recarregado = ViabilityRequest::query()->findOrFail($processo->id);

        $this->assertSame(12345, $recarregado->renews_legacy_tvl_number);
        $this->assertSame($snapshot, $recarregado->legacy_tvl_snapshot);
    }

    public function test_numero_legado_e_consultavel_e_ausente_fica_nulo(): void
    {
        $vinculado = ViabilityRequest::factory()->create(['renews_legacy_tvl_number' => 777]);
        $outro = ViabilityRequest::factory()->create();

        $ids = ViabilityRequest::query()->where('renews_legacy_tvl_number', 777)->pluck('id')->all();

        $this->assertSame([$vinculado->id], $ids);
        $this->assertNull($outro->fresh()->renews_legacy_tvl_number);
        $this->assertNull($outro->fresh()->legacy_tvl_snapshot);
    }

    public function test_parametros_da_renovacao_legada_tem_padrao_apos_o_seeder(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertSame(90, Settings::get('renovacao.legado.janela_a_vencer_dias'));
        $this->assertTrue(Settings::get('renovacao.legado.permite_vencida'));
    }

    public function test_migration_cria_os_parametros_sem_seeder_e_preserva_valor_existente(): void
    {
        $this->assertNotNull(Parameter::query()->where('key', 'renovacao.legado.janela_a_vencer_dias')->first());

        Parameter::query()->where('key', 'renovacao.legado.permite_vencida')->update(['value' => '0']);
        $migration = require database_path('migrations/2026_10_19_191349_cria_parametros_renovacao_legada.php');
        $migration->up();

        $this->assertFalse(Settings::get('renovacao.legado.permite_vencida'));
    }
}
