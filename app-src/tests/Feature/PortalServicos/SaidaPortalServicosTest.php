<?php

namespace Tests\Feature\PortalServicos;

use App\Models\Cidadao;
use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SaidaPortalServicosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function ligarEntrada(string $valor): void
    {
        Parameter::query()->where('key', 'features.login_portal_servicos')->update(['value' => $valor]);
        Cache::flush();
    }

    public function test_sair_com_a_chave_ligada_vai_a_carta_de_servicos(): void
    {
        $this->ligarEntrada('1');
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'web')->post('/portal/logout')
            ->assertRedirect('https://servicos.sedur.salvador.ba.gov.br/carta-servicos/17');

        $this->assertGuest('web');
    }

    public function test_sair_com_a_chave_desligada_segue_como_hoje(): void
    {
        $this->ligarEntrada('0');
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'web')->post('/portal/logout')->assertRedirect('/');

        $this->assertGuest('web');
    }

    public function test_sair_do_console_nao_muda(): void
    {
        $this->ligarEntrada('1');
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $location = (string) $this->actingAs($servidor, 'gestao')->post('/gestao/logout')->headers->get('Location');

        $this->assertStringNotContainsString('carta-servicos', $location);
    }
}
