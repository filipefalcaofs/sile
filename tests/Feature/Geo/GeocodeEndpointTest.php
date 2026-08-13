<?php

namespace Tests\Feature\Geo;

use App\Models\Activity;
use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HU-029 — endpoint da gestão de geocodificação de endereço (CA-01..CA-04):
 * auditado em toda saída, protegido por permissão consultar-territorio e
 * throttle, com degradação comunicada quando o toggle está desligado.
 *
 * Prova o PADRÃO CROSS-GUARD: a permissão vive no guard web (User::$guard_name
 * fixo), mas o usuário autenticado está no guard gestao; o FormRequest
 * authorize() retorna true e o gate é o middleware permission: da rota — sem
 * 403 falso (insumo para os FormRequests de território do 04-06).
 */
class GeocodeEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Backoff zero mantém os testes de indisponibilidade rápidos.
        config(['sile.integrations.geocoding.backoff_ms' => 0]);
    }

    /**
     * Resposta REAL do Nominatim para um endereço de Salvador.
     *
     * @return array<int, array<string, mixed>>
     */
    private function nominatimFixture(): array
    {
        return [[
            'lat' => '-12.9730401',
            'lon' => '-38.5122621',
            'display_name' => 'Praça da Sé, Centro Histórico, Salvador, Bahia, Brasil',
            'importance' => 0.62,
            'address' => [
                'road' => 'Praça da Sé',
                'city' => 'Salvador',
                'state' => 'Bahia',
                'country_code' => 'br',
            ],
        ]];
    }

    public function test_visitante_nao_geocodifica(): void
    {
        $this->postJson('/gestao/territorio/geocodificar', ['address' => 'Praça da Sé, Salvador'])
            ->assertStatus(401);
    }

    public function test_sem_permissao_recebe_403_e_e_auditado(): void
    {
        // Usuário acessa a gestão (acessar-gestao) mas NÃO tem consultar-territorio:
        // o gate específico de território barra e audita (CA-04).
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        Http::fake();

        $this->actingAs($semPermissao, 'gestao')
            ->postJson('/gestao/territorio/geocodificar', ['address' => 'Praça da Sé, Salvador'])
            ->assertForbidden();

        Http::assertNothingSent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_analista_no_guard_gestao_geocodifica_com_sucesso_e_e_auditado(): void
    {
        // CROSS-GUARD (CA-01/CA-02): analista autenticado no guard gestao, com a
        // permissão consultar-territorio (que vive no guard web), recebe 200 —
        // NUNCA 403 falso. Prova que authorize()=true + middleware permission:
        // resolvem a permissão guard-agnóstica.
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        Http::fake(['*/search*' => Http::response($this->nominatimFixture(), 200)]);

        $response = $this->actingAs($analista, 'gestao')
            ->postJson('/gestao/territorio/geocodificar', ['address' => 'Praça da Sé, Salvador'])
            ->assertOk()
            ->assertJsonStructure(['latitude', 'longitude', 'display_name', 'confidence', 'address']);

        $this->assertEqualsWithDelta(-12.97, $response->json('latitude'), 0.01);
        $this->assertEqualsWithDelta(-38.51, $response->json('longitude'), 0.01);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'geocodificacao',
            'result' => 'sucesso',
        ]);

        $activity = Activity::query()->where('event', 'geocodificacao')->latest('id')->first();
        $this->assertSame('Praça da Sé, Salvador', $activity->properties['endereco']);
        $this->assertSame('nominatim.openstreetmap.org', $activity->properties['provider']);
    }

    public function test_endereco_invalido_e_rejeitado_sem_geocodificar(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        Http::fake();

        $this->actingAs($analista, 'gestao')
            ->postJson('/gestao/territorio/geocodificar', ['address' => 'ab'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('address');

        Http::assertNothingSent();
    }

    public function test_endereco_nao_localizado_responde_404_e_e_auditado(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        Http::fake(['*/search*' => Http::response([], 200)]);

        $this->actingAs($analista, 'gestao')
            ->postJson('/gestao/territorio/geocodificar', ['address' => 'endereço inexistente xyz'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Endereço não localizado. Ajuste o texto ou posicione o ponto no mapa.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'geocodificacao',
            'result' => 'falha',
        ]);
    }

    public function test_indisponibilidade_responde_503_e_e_auditada(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        Http::fake(['*/search*' => Http::failedConnection()]);

        $this->actingAs($analista, 'gestao')
            ->postJson('/gestao/territorio/geocodificar', ['address' => 'Praça da Sé, Salvador'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Serviço de geocodificação indisponível. Posicione o ponto manualmente no mapa.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'geocodificacao',
            'result' => 'falha',
        ]);
    }

    public function test_toggle_desligado_bloqueia_sem_request(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'features.geocoding')->first()->update(['value' => '0']);

        Http::fake();

        $this->actingAs($analista, 'gestao')
            ->postJson('/gestao/territorio/geocodificar', ['address' => 'Praça da Sé, Salvador'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A geocodificação está desativada. Informe a localização manualmente no mapa.');

        Http::assertNothingSent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'geocodificacao',
            'result' => 'bloqueado',
        ]);
    }

    public function test_excedente_recebe_429(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'seguranca.throttle.geocoding.por_minuto')->first()->update(['value' => '2']);

        Http::fake(['*/search*' => Http::response($this->nominatimFixture(), 200)]);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($analista, 'gestao')
                ->postJson('/gestao/territorio/geocodificar', ['address' => "Endereço {$i}, Salvador"])
                ->assertOk();
        }

        // Limite 2 por minuto: a 3a requisição na janela é bloqueada.
        $this->actingAs($analista, 'gestao')
            ->postJson('/gestao/territorio/geocodificar', ['address' => 'Endereço 3, Salvador'])
            ->assertStatus(429);
    }
}
