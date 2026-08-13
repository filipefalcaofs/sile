<?php

namespace Tests\Feature\Emails;

use App\Models\EmailLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailLogIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_administrador_consulta_log_de_emails_com_contadores(): void
    {
        EmailLog::factory()->count(2)->create();
        EmailLog::factory()->sent()->create();
        EmailLog::factory()->failed('SMTP indisponível')->create();

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/emails')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/emails/index')
                ->has('logs.data', 4)
                ->where('counts.na_fila', 2)
                ->where('counts.enviado', 1)
                ->where('counts.falhou', 1)
            );
    }

    public function test_filtro_por_status_restringe_a_listagem(): void
    {
        EmailLog::factory()->count(2)->create();
        EmailLog::factory()->failed()->create();

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/emails?status=falhou')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/emails/index')
                ->has('logs.data', 1)
                ->where('logs.data.0.status', 'falhou')
                ->where('filters.status', 'falhou')
            );
    }

    public function test_usuario_sem_permissao_nao_acessa_log_de_emails(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/emails')
            ->assertForbidden();
    }
}
