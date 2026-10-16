<?php

namespace Tests\Feature\Sefaz;

use App\Models\Parameter;
use App\Models\User;
use App\Support\ApiIntegracaoCatalog;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EnvioSefazViabilidadeToggleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_interruptor_esta_no_catalogo_de_parametros(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertDatabaseHas('parameters', [
            'key' => 'features.envio_sefaz_viabilidade',
            'group' => 'features',
            'type' => 'boolean',
        ]);
    }

    public function test_interruptor_nasce_desligado(): void
    {
        $this->seed(ParameterSeeder::class);

        $parametro = Parameter::query()->where('key', 'features.envio_sefaz_viabilidade')->sole();

        $this->assertSame('0', $parametro->default_value);
        $this->assertFalse((bool) $parametro->typedValue());
    }

    public function test_interruptor_aparece_na_aba_sefaz(): void
    {
        $aba = ApiIntegracaoCatalog::find('sefaz');

        $this->assertNotNull($aba);
        $this->assertSame('SEFAZ', $aba['nome']);
        $this->assertContains('features.envio_sefaz_viabilidade', array_column($aba['campos'], 'key'));
    }

    public function test_administrador_liga_o_envio_pela_tela_de_apis(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);

        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/sefaz', ['campos' => ['envio_viabilidade' => '1']])
            ->assertRedirect(route('gestao.config-apis.edit', ['aba' => 'sefaz']));

        $this->assertTrue((bool) Parameter::query()->where('key', 'features.envio_sefaz_viabilidade')->sole()->typedValue());
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }
}
