<?php

namespace Tests\Feature\Analise;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Permissão analisar-malha-fina (Caixa de Malha Fina): separada de
 * encaminhar-malha-fina — quem encaminha não opera necessariamente a caixa.
 * Atribuída no seed apenas a gestor e administrador; os responsáveis finais
 * são definidos pelo admin nas telas de perfis/usuários.
 */
class AnalisarMalhaFinaPermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_permissao_existe_no_catalogo_seedado(): void
    {
        $this->assertDatabaseHas('permissions', [
            'name' => 'analisar-malha-fina',
            'guard_name' => 'web',
        ]);
    }

    public function test_gestor_e_administrador_recebem_analisar_malha_fina(): void
    {
        $gestor = User::factory()->gestor()->create();
        $administrador = User::factory()->administrador()->create();

        $this->assertTrue($gestor->can('analisar-malha-fina'));
        $this->assertTrue($administrador->can('analisar-malha-fina'));
    }

    public function test_analista_e_apoio_nao_recebem_analisar_malha_fina(): void
    {
        $analista = User::factory()->analista()->create();
        $apoio = User::factory()->apoio()->create();

        $this->assertFalse($analista->can('analisar-malha-fina'));
        $this->assertFalse($apoio->can('analisar-malha-fina'));
        // Quem encaminha (analista tem encaminhar-malha-fina) não opera a caixa.
        $this->assertTrue($analista->can('encaminhar-malha-fina'));
    }
}
