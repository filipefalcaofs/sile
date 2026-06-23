<?php

namespace Tests\Feature\Seeders;

use App\Support\DemoMode;
use Database\Seeders\DemonstracaoClienteSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
