<?php

namespace Tests\Feature\Feriados;

use App\Models\Holiday;
use App\Models\User;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Feriados separados por tipo (nacional, estadual, municipal): a listagem entrega
 * o tipo, filtra por ele e o cadastro aceita só os três valores.
 */
class FeriadosPorTipoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_listagem_entrega_o_tipo_de_cada_feriado(): void
    {
        Holiday::factory()->create(['date' => '2027-03-29', 'type' => 'municipal']);

        $page = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/feriados')
            ->assertOk()
            ->viewData('page');

        $this->assertSame('municipal', $page['props']['holidays']['data'][0]['type']);
        $this->assertSame('', $page['props']['filters']['type']);
    }

    public function test_filtra_a_listagem_por_tipo(): void
    {
        Holiday::factory()->create(['date' => '2027-09-07', 'type' => 'nacional']);
        Holiday::factory()->create(['date' => '2027-07-02', 'type' => 'estadual']);
        Holiday::factory()->create(['date' => '2027-03-29', 'type' => 'municipal']);

        $page = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/feriados?type=estadual')
            ->assertOk()
            ->viewData('page');

        $this->assertCount(1, $page['props']['holidays']['data']);
        $this->assertSame('estadual', $page['props']['holidays']['data'][0]['type']);
        $this->assertSame('estadual', $page['props']['filters']['type']);
    }

    public function test_tipo_invalido_no_filtro_e_ignorado(): void
    {
        Holiday::factory()->count(2)->create();

        $page = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/feriados?type=qualquer')
            ->assertOk()
            ->viewData('page');

        $this->assertCount(2, $page['props']['holidays']['data']);
        $this->assertSame('', $page['props']['filters']['type']);
    }

    public function test_cadastro_grava_o_tipo(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/feriados', ['date' => '2027-06-24', 'name' => 'São João', 'type' => 'municipal'])
            ->assertSessionHasNoErrors();

        $this->assertSame('municipal', Holiday::query()->where('name', 'São João')->value('type'));
    }

    public function test_cadastro_rejeita_tipo_desconhecido(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/feriados', ['date' => '2027-06-24', 'name' => 'São João', 'type' => 'distrital'])
            ->assertSessionHasErrors('type');
    }

    public function test_edicao_altera_o_tipo(): void
    {
        $holiday = Holiday::factory()->create(['date' => '2027-07-02', 'type' => null]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/feriados/{$holiday->id}", ['date' => '2027-07-02', 'name' => $holiday->name, 'type' => 'estadual', 'active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('estadual', $holiday->refresh()->type);
    }

    public function test_seeder_marca_os_feriados_fixos_como_nacionais(): void
    {
        $this->seed(HolidaySeeder::class);

        $this->assertSame(0, Holiday::query()->whereNull('type')->count());
        $this->assertSame(Holiday::query()->count(), Holiday::query()->where('type', 'nacional')->count());
    }
}
