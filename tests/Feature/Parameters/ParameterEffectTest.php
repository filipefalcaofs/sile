<?php

namespace Tests\Feature\Parameters;

use App\Models\Procuration;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParameterEffectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function cidadao(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    public function test_alteracao_de_parametro_tem_efeito_imediato_sem_deploy(): void
    {
        $admin = $this->admin();

        // Altera um parâmetro de negócio do catálogo pela tela de gestão.
        $this->actingAs($admin, 'gestao')
            ->put(route('gestao.parametros.update', 'security.login.max_attempts'), ['value' => '3'])
            ->assertRedirect();

        // Efeito imediato, sem novo deploy: o Settings reflete o novo valor na
        // hora (o cache do parâmetro é invalidado na gravação).
        $this->assertSame(3, (int) Settings::get('security.login.max_attempts'));
    }

    public function test_toggle_desligado_bloqueia_novo_vinculo_com_aviso(): void
    {
        $cidadao = $this->cidadao();
        $attorney = $this->cidadao();

        $this->actingAs($this->admin(), 'gestao')
            ->put(route('gestao.parametros.update', 'features.procuracoes'), ['value' => '0'])
            ->assertRedirect();

        $this->actingAs($cidadao, 'web')
            ->post('/portal/procuracoes', [
                'attorney_email' => $attorney->email,
                'expires_at' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertStringContainsString('desativad', session('status'));
        $this->assertDatabaseCount('procurations', 0);
    }

    public function test_toggle_desligado_preserva_revogacao(): void
    {
        $grantor = $this->cidadao();
        $procuration = Procuration::factory()->create(['grantor_user_id' => $grantor->id]);

        $this->actingAs($this->admin(), 'gestao')
            ->put(route('gestao.parametros.update', 'features.procuracoes'), ['value' => '0'])
            ->assertRedirect();

        $this->actingAs($grantor, 'web')
            ->delete("/portal/procuracoes/{$procuration->id}")
            ->assertRedirect();

        $this->assertNotNull($procuration->fresh()->revoked_at);
    }

    public function test_toggle_desligado_comunica_na_tela(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put(route('gestao.parametros.update', 'features.procuracoes'), ['value' => '0'])
            ->assertRedirect();

        $this->actingAs($this->cidadao(), 'web')
            ->get('/portal/procuracoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/procuracoes/index')
                ->where('procuracoesEnabled', false));
    }

    public function test_toggle_ligado_mantem_fluxo_normal(): void
    {
        $cidadao = $this->cidadao();
        $attorney = $this->cidadao();

        $this->actingAs($cidadao)
            ->post('/portal/procuracoes', [
                'attorney_email' => $attorney->email,
                'expires_at' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('procurations', 1);

        $this->actingAs($cidadao)
            ->get('/portal/procuracoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('procuracoesEnabled', true));
    }
}
