<?php

namespace Tests\Feature\Lgpd;

use App\Models\LegalTerm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * O aceite do termo LGPD é por ambiente: o servidor é levado ao termo do
 * console (/gestao/termo-lgpd) e o cidadão ao termo do portal — sem
 * cruzamento de URL entre os ambientes.
 */
class TermoLgpdPorAmbienteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        LegalTerm::factory()->published()->create();
    }

    public function test_servidor_sem_aceite_vai_para_o_termo_do_console(): void
    {
        $servidor = User::factory()->administrador()->create();

        $this->actingAs($servidor, 'gestao')
            ->get('/gestao')
            ->assertRedirect('/gestao/termo-lgpd');
    }

    public function test_cidadao_sem_aceite_vai_para_o_termo_do_portal(): void
    {
        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($cidadao, 'web')
            ->get('/portal/painel')
            ->assertRedirect('/portal/termo-lgpd');
    }

    public function test_servidor_aceita_o_termo_no_console(): void
    {
        $servidor = User::factory()->administrador()->create();

        $this->actingAs($servidor, 'gestao')
            ->post('/gestao/termo-lgpd', ['accepted' => true])
            ->assertRedirect('/gestao');

        $term = LegalTerm::current('lgpd');

        $this->assertTrue($servidor->fresh()->hasAcceptedTerm($term));
    }
}
