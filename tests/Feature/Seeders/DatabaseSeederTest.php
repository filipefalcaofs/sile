<?php

namespace Tests\Feature\Seeders;

use App\Models\LegalTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_completo_prepara_ambiente_de_desenvolvimento(): void
    {
        $this->seed();

        $this->assertSame(4, Role::query()->count());
        $this->assertNotNull(LegalTerm::current('lgpd'));

        $admin = User::query()->where('email', 'admin@sile.dev')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('administrador'));
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_seed_e_idempotente(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::query()->where('email', 'admin@sile.dev')->count());
        $this->assertSame(4, Role::query()->count());
    }

    public function test_admin_dev_acessa_gestao_apos_aceitar_termo(): void
    {
        $this->seed();

        $this->post('/login', [
            'email' => 'admin@sile.dev',
            'password' => 'password',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticated();

        $this->get('/gestao')->assertRedirect(route('portal.termo-lgpd.show'));

        $this->post('/portal/termo-lgpd', ['accepted' => true]);

        $this->get('/gestao')->assertOk();
    }
}
