<?php

namespace Tests\Feature\Companies;

use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CnpjThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Payload REAL da BrasilAPI (Banco do Brasil) registrado na pesquisa.
     *
     * @return array<string, mixed>
     */
    private function brasilApiFixture(): array
    {
        return json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/cnpj/brasilapi-banco-do-brasil.json')),
            true,
        );
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    public function test_excedente_recebe_429(): void
    {
        Http::fake(['*/00000000000191' => Http::response($this->brasilApiFixture(), 200)]);

        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'seguranca.throttle.cnpj_lookup.por_minuto')->first()->update(['value' => '2']);

        $user = $this->portalUser();

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
                ->assertOk();
        }

        // Limite 2 por minuto: a 3a requisicao dentro da janela e bloqueada.
        $this->actingAs($user)
            ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
            ->assertStatus(429);
    }

    public function test_aumentar_o_parametro_eleva_o_limite(): void
    {
        Http::fake(['*/00000000000191' => Http::response($this->brasilApiFixture(), 200)]);

        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'seguranca.throttle.cnpj_lookup.por_minuto')->first()->update(['value' => '5']);

        $user = $this->portalUser();

        // Com limite 5, as mesmas 3 requisicoes que estourariam o limite 2
        // passam: o parametro altera o teto sem deploy.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)
                ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
                ->assertOk();
        }
    }
}
