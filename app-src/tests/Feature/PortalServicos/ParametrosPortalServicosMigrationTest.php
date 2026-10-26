<?php

namespace Tests\Feature\PortalServicos;

use App\Enums\ParameterGovernance;
use App\Models\Parameter;
use App\Services\PortalServicos\PortalServicos;
use App\Support\Settings;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ParametrosPortalServicosMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CHAVES = [
        'features.login_portal_servicos',
        'integrations.portal_servicos.bff_base_url',
        'integrations.portal_servicos.login_url',
        'integrations.portal_servicos.carta_url',
        'integrations.portal_servicos.intervalo_checagem_sessao',
    ];

    public function test_migration_cria_os_parametros_sem_seeder_com_a_entrada_desligada(): void
    {
        foreach (self::CHAVES as $chave) {
            $parametro = Parameter::query()->where('key', $chave)->first();

            $this->assertNotNull($parametro, $chave);
            $this->assertSame(ParameterGovernance::Operational, $parametro->governance);
        }

        $this->assertFalse(PortalServicos::entradaDisponivel());
        $this->assertSame(300, Settings::get('integrations.portal_servicos.intervalo_checagem_sessao'));
    }

    public function test_migration_preserva_valor_existente(): void
    {
        Parameter::query()->where('key', 'features.login_portal_servicos')->update(['value' => '1']);
        Cache::flush();

        $migration = require database_path('migrations/2026_10_26_120000_cria_parametros_portal_servicos.php');
        $migration->up();

        $this->assertTrue(Settings::enabled('login_portal_servicos'));
        $this->assertSame(1, Parameter::query()->where('key', 'features.login_portal_servicos')->count());
    }

    public function test_seeder_depois_da_migration_nao_duplica(): void
    {
        $this->seed(ParameterSeeder::class);

        foreach (self::CHAVES as $chave) {
            $this->assertSame(1, Parameter::query()->where('key', $chave)->count(), $chave);
        }
    }
}
