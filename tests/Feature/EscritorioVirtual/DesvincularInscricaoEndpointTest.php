<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gatilho manual de desvinculação da inscrição da sede (RN-EV-06): endpoint de
 * gestão gated por emitir-tvl. Delega ao DesvincularInscricaoService.
 */
class DesvincularInscricaoEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function sedeComLock(): ViabilityRequest
    {
        $sede = ViabilityRequest::factory()->create(['property_registration' => '123.456.789']);
        VirtualOfficeInscriptionLock::create([
            'property_registration' => '123.456.789',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        return $sede;
    }

    public function test_gestor_desvincula_inscricao_da_sede(): void
    {
        $user = User::factory()->analista()->create(); // possui emitir-tvl
        $sede = $this->sedeComLock();

        $this->actingAs($user, 'gestao')
            ->post("/gestao/processos/{$sede->id}/desvincular-inscricao", ['motivo' => 'Sede mudou de endereço.'])
            ->assertRedirect();

        $this->assertFalse(VirtualOfficeInscriptionLock::ativoPara('123.456.789'));
    }

    public function test_motivo_obrigatorio(): void
    {
        $user = User::factory()->analista()->create();
        $sede = $this->sedeComLock();

        $this->actingAs($user, 'gestao')
            ->post("/gestao/processos/{$sede->id}/desvincular-inscricao", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertTrue(VirtualOfficeInscriptionLock::ativoPara('123.456.789'));
    }

    public function test_sem_lock_ativo_avisa(): void
    {
        $user = User::factory()->analista()->create();
        $sede = ViabilityRequest::factory()->create(['property_registration' => '000']);

        $this->actingAs($user, 'gestao')
            ->post("/gestao/processos/{$sede->id}/desvincular-inscricao", ['motivo' => 'x'])
            ->assertSessionHas('error');
    }

    public function test_sem_permissao_403(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('acessar-gestao'); // sem emitir-tvl
        $sede = $this->sedeComLock();

        $this->actingAs($user, 'gestao')
            ->post("/gestao/processos/{$sede->id}/desvincular-inscricao", ['motivo' => 'x'])
            ->assertForbidden();
    }
}
