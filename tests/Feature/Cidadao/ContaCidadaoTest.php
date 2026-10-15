<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContaCidadaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_troca_de_senha_com_a_senha_atual_correta(): void
    {
        $cidadao = Cidadao::factory()->create();

        $this->actingAs($cidadao, 'web')->put('/portal/conta/senha', [
            'current_password' => 'password',
            'password' => 'NovaSenha1234',
            'password_confirmation' => 'NovaSenha1234',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NovaSenha1234', $cidadao->fresh()->password));
    }

    public function test_troca_de_senha_com_a_senha_atual_errada_e_recusada(): void
    {
        $cidadao = Cidadao::factory()->create();

        $this->actingAs($cidadao, 'web')->put('/portal/conta/senha', [
            'current_password' => 'errada',
            'password' => 'NovaSenha1234',
            'password_confirmation' => 'NovaSenha1234',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $cidadao->fresh()->password));
    }

    public function test_perfil_aceita_email_de_servidor(): void
    {
        User::factory()->create(['email' => 'servidor@example.com']);
        $cidadao = Cidadao::factory()->create();

        $this->actingAs($cidadao, 'web')->patch('/portal/conta/perfil', [
            'name' => 'Maria',
            'email' => 'servidor@example.com',
            'phone' => '(71) 99999-1234',
        ])->assertSessionHasNoErrors();

        $this->assertSame('servidor@example.com', $cidadao->fresh()->email);
    }

    public function test_perfil_recusa_email_de_outro_cidadao(): void
    {
        Cidadao::factory()->create(['email' => 'outro@example.com']);
        $cidadao = Cidadao::factory()->create();

        $this->actingAs($cidadao, 'web')->patch('/portal/conta/perfil', [
            'name' => 'Maria',
            'email' => 'outro@example.com',
            'phone' => '(71) 99999-1234',
        ])->assertSessionHasErrors('email');
    }
}
