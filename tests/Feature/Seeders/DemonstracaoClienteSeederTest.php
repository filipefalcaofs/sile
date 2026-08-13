<?php

namespace Tests\Feature\Seeders;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\DemoMode;
use Database\Seeders\DemonstracaoClienteSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemonstracaoClienteSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_ignora_quando_demo_data_desligado(): void
    {
        config(['sile.demo_data' => false]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DemonstracaoClienteSeeder::class);

        $this->assertDatabaseMissing('users', [
            'email' => DemonstracaoClienteSeeder::CLIENTE_GESTAO_EMAIL,
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'cidadao@sile.dev']);
    }

    public function test_cria_usuarios_e_perfis_quando_demo_data_ligado(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => DemonstracaoClienteSeeder::CLIENTE_GESTAO_EMAIL,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => DemonstracaoClienteSeeder::CLIENTE_PORTAL_EMAIL,
        ]);
        $this->assertTrue(Role::where('name', 'validacao-fase-completa')->exists());
        $this->assertTrue(DemoMode::enabled());
    }

    public function test_cria_massa_de_demonstracao_em_diversas_situacoes(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        $cidadao = User::query()->where('email', 'cidadao@sile.dev')->first();
        $this->assertNotNull($cidadao, 'Esperava o cidadão de demonstração (cidadao@sile.dev).');

        $statuses = ViabilityRequest::query()
            ->where('requester_user_id', $cidadao->id)
            ->pluck('status');

        $this->assertTrue($statuses->contains(ViabilityRequestStatus::Rascunho));
        $this->assertTrue($statuses->contains(ViabilityRequestStatus::Protocolada));
        $this->assertTrue($statuses->contains(ViabilityRequestStatus::Cancelada));

        $this->assertTrue(
            ViabilityRequest::query()
                ->where('requester_user_id', $cidadao->id)
                ->where('origin', ViabilityRequestOrigin::Contingencia)
                ->exists(),
            'Esperava uma solicitação de contingência de demonstração.'
        );

        $this->assertDatabaseHas('users', ['email' => 'analista@sile.dev']);
        $this->assertDatabaseHas('users', ['email' => 'gestor@sile.dev']);
        $this->assertDatabaseHas('users', ['email' => 'admin@sile.dev']);
    }

    public function test_rotaciona_senhas_dos_usuarios_dev_para_a_senha_demo(): void
    {
        config(['sile.demo_data' => true]);

        $this->seed(DemonstracaoClienteSeeder::class);

        foreach (['admin@sile.dev', 'cidadao@sile.dev', 'analista@sile.dev', 'gestor@sile.dev'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue(
                Hash::check(DemonstracaoClienteSeeder::DEMO_PASSWORD, $user->password),
                "Esperava a senha demo rotacionada para {$email}."
            );
        }
    }
}
